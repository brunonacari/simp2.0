<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Buscar Estatísticas por Ponto de Medição (Agregadores)
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('REGISTRO DE VAZÃO', ACESSO_LEITURA);

include_once '../conexao.php';

// Mapeamentos de tipos
$tiposMedidor = [
    1 => 'M - Macromedidor',
    2 => 'E - Estação Pitométrica',
    4 => 'P - Medidor Pressão',
    8 => 'H - Hidrômetro',
    6 => 'R - Nível Reservatório'
];

$tiposLeitura = [
    2 => 'Manual',
    4 => 'Planilha',
    8 => 'Integração CCO',
    6 => 'Integração CesanLims'
];

// Mapeamento de tipo de medidor para letra
$letrasTipoMedidor = [
    1 => 'M',
    2 => 'E',
    4 => 'P',
    6 => 'R',
    8 => 'H'
];

try {
    $cdUnidade = isset($_GET['cd_unidade']) ? trim($_GET['cd_unidade']) : '';
    $cdLocalidade = isset($_GET['cd_localidade']) ? trim($_GET['cd_localidade']) : '';
    $cdPontoMedicao = isset($_GET['cd_ponto_medicao']) ? trim($_GET['cd_ponto_medicao']) : '';
    $dataInicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : '';
    $dataFim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';
    $tipoMedidor = isset($_GET['tipo_medidor']) ? trim($_GET['tipo_medidor']) : '';
    $tipoLeitura = isset($_GET['tipo_leitura']) ? trim($_GET['tipo_leitura']) : '';
    $descarte = isset($_GET['descarte']) ? trim($_GET['descarte']) : '';

    // WHERE base
    $whereBase = "WHERE 1=1";
    $paramsBase = [];

    // Filtro por Ponto de Medição específico
    if (!empty($cdPontoMedicao)) {
        $whereBase .= " AND RVP.CD_PONTO_MEDICAO = :cd_ponto_medicao";
        $paramsBase[':cd_ponto_medicao'] = $cdPontoMedicao;
    } 
    // Filtro por Localidade
    elseif (!empty($cdLocalidade)) {
        $whereBase .= " AND PM.CD_LOCALIDADE = :cd_localidade";
        $paramsBase[':cd_localidade'] = $cdLocalidade;
    }
    // Filtro por Unidade
    elseif (!empty($cdUnidade)) {
        $whereBase .= " AND LOC.CD_UNIDADE = :cd_unidade";
        $paramsBase[':cd_unidade'] = $cdUnidade;
    }

    // Filtro por Data Início
    if (!empty($dataInicio)) {
        $whereBase .= " AND RVP.DT_LEITURA >= :data_inicio";
        $paramsBase[':data_inicio'] = $dataInicio . ' 00:00:00';
    }

    // Filtro por Data Fim
    if (!empty($dataFim)) {
        $whereBase .= " AND RVP.DT_LEITURA <= :data_fim";
        $paramsBase[':data_fim'] = $dataFim . ' 23:59:59';
    }

    // Filtro por Tipo de Medidor
    if (!empty($tipoMedidor)) {
        $whereBase .= " AND PM.ID_TIPO_MEDIDOR = :tipo_medidor";
        $paramsBase[':tipo_medidor'] = $tipoMedidor;
    }

    // Filtro por Tipo de Leitura
    if (!empty($tipoLeitura)) {
        $whereBase .= " AND PM.ID_TIPO_LEITURA = :tipo_leitura";
        $paramsBase[':tipo_leitura'] = $tipoLeitura;
    }

    // WHERE com filtro de descarte
    $whereCompleto = $whereBase;
    $paramsCompleto = $paramsBase;
    
    if (!empty($descarte)) {
        $whereCompleto .= " AND RVP.ID_SITUACAO = :descarte";
        $paramsCompleto[':descarte'] = $descarte;
    }

    // Condição para cálculos (médias)
    $condicaoCalculo = "RVP.ID_SITUACAO = 1";
    if ($descarte == '2') {
        $condicaoCalculo = "RVP.ID_SITUACAO = 2";
    }

    // Contar total de registros
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                 LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                 LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                 LEFT JOIN SIMP.dbo.UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
                 $whereCompleto";
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($paramsCompleto);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // Buscar estatísticas por ponto de medição
    $sqlEstatisticas = "SELECT 
                RVP.CD_PONTO_MEDICAO,
                PM.DS_NOME AS DS_PONTO_MEDICAO,
                PM.ID_TIPO_MEDIDOR,
                PM.ID_TIPO_LEITURA,
                LOC.CD_LOCALIDADE,
                LOC.CD_UNIDADE,
                COUNT(*) AS TOTAL_GERAL,
                COUNT(DISTINCT CONVERT(DATE, RVP.DT_LEITURA)) AS QTD_DIAS,
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
    
    $stmtEstat = $pdoSIMP->prepare($sqlEstatisticas);
    $stmtEstat->execute($paramsBase);
    $estatisticasRaw = $stmtEstat->fetchAll(PDO::FETCH_ASSOC);

    // Se há filtro de descarte, buscar contagem filtrada por ponto
    $contagemFiltradaPorPonto = [];
    if (!empty($descarte)) {
        $sqlFiltrado = "SELECT 
                    RVP.CD_PONTO_MEDICAO,
                    COUNT(*) AS TOTAL_FILTRADO
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                LEFT JOIN SIMP.dbo.UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
                $whereCompleto
                GROUP BY RVP.CD_PONTO_MEDICAO";
        
        $stmtFiltrado = $pdoSIMP->prepare($sqlFiltrado);
        $stmtFiltrado->execute($paramsCompleto);
        $filtradosRaw = $stmtFiltrado->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($filtradosRaw as $f) {
            $contagemFiltradaPorPonto[$f['CD_PONTO_MEDICAO']] = (int)$f['TOTAL_FILTRADO'];
        }
    }
    
    // Converter para array indexado por ponto
    $estatisticas = [];
    foreach ($estatisticasRaw as $est) {
        $cdPonto = $est['CD_PONTO_MEDICAO'];
        $idTipoMedidor = $est['ID_TIPO_MEDIDOR'];
        
        // Gerar código formatado
        $letraTipo = $letrasTipoMedidor[$idTipoMedidor] ?? 'X';
        $codigoFormatado = $est['CD_LOCALIDADE'] . '-' . str_pad($cdPonto, 6, '0', STR_PAD_LEFT) . '-' . $letraTipo . '-' . $est['CD_UNIDADE'];
        
        // Determinar total a exibir
        $totalExibir = (int)$est['TOTAL_GERAL'];
        if (!empty($descarte) && isset($contagemFiltradaPorPonto[$cdPonto])) {
            $totalExibir = $contagemFiltradaPorPonto[$cdPonto];
        } elseif (!empty($descarte)) {
            $totalExibir = 0;
        }
        
        // Se filtrou e não há registros, pular este ponto
        if (!empty($descarte) && $totalExibir == 0) {
            continue;
        }
        
        // Para Nível Reservatório (ID=6), usar MAX ao invés de média
        $nivelValor = null;
        $nivelTipo = 'media';
        if ($idTipoMedidor == 6) {
            $nivelValor = $est['MAX_NIVEL'] !== null ? round((float)$est['MAX_NIVEL'], 2) : null;
            $nivelTipo = 'max';
        } else {
            $nivelValor = $est['MEDIA_NIVEL'] !== null ? round((float)$est['MEDIA_NIVEL'], 2) : null;
        }
        
        $estatisticas[$cdPonto] = [
            'cdPontoMedicao' => $cdPonto,
            'codigoFormatado' => $codigoFormatado,
            'dsPontoMedicao' => $est['DS_PONTO_MEDICAO'],
            'idTipoMedidor' => $idTipoMedidor,
            'dsTipoMedidor' => $tiposMedidor[$idTipoMedidor] ?? '-',
            'idTipoLeitura' => $est['ID_TIPO_LEITURA'],
            'dsTipoLeitura' => $tiposLeitura[$est['ID_TIPO_LEITURA']] ?? '-',
            'total' => $totalExibir,
            'totalGeral' => (int)$est['TOTAL_GERAL'],
            'qtdDias' => (int)$est['QTD_DIAS'],
            'totalNaoDescarte' => (int)$est['TOTAL_NAO_DESCARTE'],
            'totalDescarte' => (int)$est['TOTAL_DESCARTE'],
            'mediaVazao' => $est['MEDIA_VAZAO'] !== null ? round((float)$est['MEDIA_VAZAO'], 2) : null,
            'mediaPressao' => $est['MEDIA_PRESSAO'] !== null ? round((float)$est['MEDIA_PRESSAO'], 2) : null,
            'nivelValor' => $nivelValor,
            'nivelTipo' => $nivelTipo
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'estatisticasPonto' => $estatisticas,
        'total' => (int)$total,
        'totalPontos' => count($estatisticas),
        'filtro_descarte' => $descarte
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}