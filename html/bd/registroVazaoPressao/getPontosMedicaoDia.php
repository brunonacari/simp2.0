<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Buscar Pontos de Medição de um Dia
 * 
 * @version 2.2 - Alterado para usar AVG em vez de SUM/1440
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('REGISTRO DE VAZÃO', ACESSO_LEITURA);

include_once '../conexao.php';

try {
    // Mapeamento de letras por tipo de medidor
    $letrasTipoMedidor = [
        1 => 'M', // Macromedidor
        2 => 'E', // Estação Pitométrica
        4 => 'P', // Medidor Pressão
        6 => 'R', // Nível Reservatório
        8 => 'H'  // Hidrômetro
    ];

    // Parâmetros de filtro
    $data = isset($_GET['data']) ? trim($_GET['data']) : '';
    $cdUnidade = isset($_GET['cd_unidade']) ? trim($_GET['cd_unidade']) : '';
    $cdLocalidade = isset($_GET['cd_localidade']) ? trim($_GET['cd_localidade']) : '';
    $cdPontoMedicao = isset($_GET['cd_ponto_medicao']) ? trim($_GET['cd_ponto_medicao']) : '';
    $tipoMedidor = isset($_GET['tipo_medidor']) ? trim($_GET['tipo_medidor']) : '';
    $tipoLeitura = isset($_GET['tipo_leitura']) ? trim($_GET['tipo_leitura']) : '';
    $descarte = isset($_GET['descarte']) ? trim($_GET['descarte']) : '';

    if (empty($data)) {
        throw new Exception('Data não informada');
    }

    // Construir WHERE base (sem filtro de descarte)
    $whereBase = "WHERE CAST(RVP.DT_LEITURA AS DATE) = :data";
    $paramsBase = [':data' => $data];

    // Clonar para versão com descarte
    $whereComDescarte = $whereBase;
    $paramsComDescarte = $paramsBase;

    // Filtro por Unidade
    if (!empty($cdUnidade)) {
        $whereBase .= " AND LOC.CD_UNIDADE = :cd_unidade";
        $paramsBase[':cd_unidade'] = $cdUnidade;
        $whereComDescarte .= " AND LOC.CD_UNIDADE = :cd_unidade";
        $paramsComDescarte[':cd_unidade'] = $cdUnidade;
    }

    // Filtro por Localidade
    if (!empty($cdLocalidade)) {
        $whereBase .= " AND PM.CD_LOCALIDADE = :cd_localidade";
        $paramsBase[':cd_localidade'] = $cdLocalidade;
        $whereComDescarte .= " AND PM.CD_LOCALIDADE = :cd_localidade";
        $paramsComDescarte[':cd_localidade'] = $cdLocalidade;
    }

    // Filtro por Ponto de Medição
    if (!empty($cdPontoMedicao)) {
        $whereBase .= " AND RVP.CD_PONTO_MEDICAO = :cd_ponto_medicao";
        $paramsBase[':cd_ponto_medicao'] = $cdPontoMedicao;
        $whereComDescarte .= " AND RVP.CD_PONTO_MEDICAO = :cd_ponto_medicao";
        $paramsComDescarte[':cd_ponto_medicao'] = $cdPontoMedicao;
    }

    // Filtro por Tipo de Medidor
    if (!empty($tipoMedidor)) {
        $whereBase .= " AND PM.ID_TIPO_MEDIDOR = :tipo_medidor";
        $paramsBase[':tipo_medidor'] = $tipoMedidor;
        $whereComDescarte .= " AND PM.ID_TIPO_MEDIDOR = :tipo_medidor";
        $paramsComDescarte[':tipo_medidor'] = $tipoMedidor;
    }

    // Filtro por Tipo de Leitura
    if (!empty($tipoLeitura)) {
        $whereBase .= " AND PM.ID_TIPO_LEITURA = :tipo_leitura";
        $paramsBase[':tipo_leitura'] = $tipoLeitura;
        $whereComDescarte .= " AND PM.ID_TIPO_LEITURA = :tipo_leitura";
        $paramsComDescarte[':tipo_leitura'] = $tipoLeitura;
    }

    // Condição para cálculos (médias)
    $condicaoCalculo = "RVP.ID_SITUACAO = 1";
    if ($descarte == '2') {
        $condicaoCalculo = "RVP.ID_SITUACAO = 2"; // Se filtrou por descartados
    } elseif ($descarte == '1') {
        $condicaoCalculo = "RVP.ID_SITUACAO = 1"; // Se filtrou por não descartados
    }
    // Se descarte vazio, usa não descartados por padrão para cálculos
    
    // Aplicar filtro de descarte para contagem
    if (!empty($descarte)) {
        $whereComDescarte .= " AND RVP.ID_SITUACAO = :descarte";
        $paramsComDescarte[':descarte'] = $descarte;
    }

    // Buscar estatísticas por ponto de medição
    // ALTERADO: Usando AVG em vez de SUM/1440 para média diária
    $sql = "SELECT 
                RVP.CD_PONTO_MEDICAO,
                PM.DS_NOME AS DS_PONTO_MEDICAO,
                PM.ID_TIPO_MEDIDOR,
                PM.ID_TIPO_LEITURA,
                LOC.CD_LOCALIDADE,
                LOC.CD_UNIDADE,
                COUNT(*) AS TOTAL_GERAL,
                SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS TOTAL_NAO_DESCARTE,
                SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS TOTAL_DESCARTE,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_VAZAO_EFETIVA ELSE NULL END) AS MEDIA_VAZAO,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_PRESSAO ELSE NULL END) AS MEDIA_PRESSAO,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_RESERVATORIO ELSE NULL END) AS MEDIA_NIVEL,
                MAX(CASE WHEN $condicaoCalculo THEN RVP.VL_RESERVATORIO ELSE NULL END) AS MAX_NIVEL
            FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
            LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
            LEFT JOIN SIMP.dbo.UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
            $whereBase
            GROUP BY RVP.CD_PONTO_MEDICAO, PM.DS_NOME, PM.ID_TIPO_MEDIDOR, PM.ID_TIPO_LEITURA, LOC.CD_LOCALIDADE, LOC.CD_UNIDADE
            ORDER BY PM.DS_NOME";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($paramsBase);
    $estatisticasRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Se há filtro de descarte, buscar contagem filtrada
    $contagemFiltrada = [];
    if (!empty($descarte)) {
        $sqlFiltrado = "SELECT 
                    RVP.CD_PONTO_MEDICAO,
                    COUNT(*) AS TOTAL_FILTRADO
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                LEFT JOIN SIMP.dbo.UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
                $whereComDescarte
                GROUP BY RVP.CD_PONTO_MEDICAO";
        
        $stmtFiltrado = $pdoSIMP->prepare($sqlFiltrado);
        $stmtFiltrado->execute($paramsComDescarte);
        $filtradosRaw = $stmtFiltrado->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($filtradosRaw as $f) {
            $contagemFiltrada[$f['CD_PONTO_MEDICAO']] = (int)$f['TOTAL_FILTRADO'];
        }
    }
    
    // Converter para array
    $pontos = [];
    $totalGeral = 0;
    
    foreach ($estatisticasRaw as $est) {
        $cdPonto = $est['CD_PONTO_MEDICAO'];
        $idTipoMedidor = $est['ID_TIPO_MEDIDOR'];
        
        // Gerar código formatado
        $letraTipo = $letrasTipoMedidor[$idTipoMedidor] ?? 'X';
        $codigoFormatado = $est['CD_LOCALIDADE'] . '-' . 
                          str_pad($cdPonto, 6, '0', STR_PAD_LEFT) . '-' . 
                          $letraTipo . '-' . 
                          $est['CD_UNIDADE'];
        
        // Determinar total a exibir
        $totalExibir = (int)$est['TOTAL_GERAL'];
        if (!empty($descarte) && isset($contagemFiltrada[$cdPonto])) {
            $totalExibir = $contagemFiltrada[$cdPonto];
        } elseif (!empty($descarte)) {
            $totalExibir = 0;
        }
        
        // Se filtrou e não há registros, pular
        if (!empty($descarte) && $totalExibir == 0) {
            continue;
        }
        
        $totalGeral += $totalExibir;
        
        $pontos[] = [
            'cdPontoMedicao' => $cdPonto,
            'dsPontoMedicao' => $est['DS_PONTO_MEDICAO'],
            'codigoFormatado' => $codigoFormatado,
            'idTipoMedidor' => $idTipoMedidor,
            'idTipoLeitura' => $est['ID_TIPO_LEITURA'],
            'cdLocalidade' => $est['CD_LOCALIDADE'],
            'cdUnidade' => $est['CD_UNIDADE'],
            'total' => $totalExibir,
            'totalGeral' => (int)$est['TOTAL_GERAL'],
            'totalNaoDescarte' => (int)$est['TOTAL_NAO_DESCARTE'],
            'totalDescarte' => (int)$est['TOTAL_DESCARTE'],
            'mediaVazao' => $est['MEDIA_VAZAO'] !== null ? round((float)$est['MEDIA_VAZAO'], 2) : null,
            'mediaPressao' => $est['MEDIA_PRESSAO'] !== null ? round((float)$est['MEDIA_PRESSAO'], 2) : null,
            'mediaNivel' => $est['MEDIA_NIVEL'] !== null ? round((float)$est['MEDIA_NIVEL'], 2) : null,
            'maxNivel' => $est['MAX_NIVEL'] !== null ? round((float)$est['MAX_NIVEL'], 2) : null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'pontos' => $pontos,
        'total' => $totalGeral,
        'totalPontos' => count($pontos),
        'data' => $data,
        'filtro_descarte' => $descarte,
        'formula_media' => 'AVG'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
