<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Buscar Estatísticas por Ponto de Medição de um Dia
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

try {
    $data = isset($_GET['data']) ? trim($_GET['data']) : '';
    $cdUnidade = isset($_GET['cd_unidade']) ? trim($_GET['cd_unidade']) : '';
    $cdLocalidade = isset($_GET['cd_localidade']) ? trim($_GET['cd_localidade']) : '';
    $tipoMedidor = isset($_GET['tipo_medidor']) ? trim($_GET['tipo_medidor']) : '';
    $tipoLeitura = isset($_GET['tipo_leitura']) ? trim($_GET['tipo_leitura']) : '';
    $descarte = isset($_GET['descarte']) ? trim($_GET['descarte']) : '';

    if (empty($data)) {
        throw new Exception('Data não informada');
    }

    // Validar formato da data (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        throw new Exception('Formato de data inválido');
    }

    // WHERE base (sem filtro de descarte)
    $whereBase = "WHERE CONVERT(DATE, RVP.DT_LEITURA) = :data";
    $paramsBase = [':data' => $data];

    // Filtro por Localidade
    if (!empty($cdLocalidade)) {
        $whereBase .= " AND PM.CD_LOCALIDADE = :cd_localidade";
        $paramsBase[':cd_localidade'] = $cdLocalidade;
    }
    // Filtro por Unidade
    elseif (!empty($cdUnidade)) {
        $whereBase .= " AND LOC.CD_UNIDADE = :cd_unidade";
        $paramsBase[':cd_unidade'] = $cdUnidade;
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

    // WHERE com filtro de descarte (para contagens e médias que respeitam o filtro)
    $whereComDescarte = $whereBase;
    $paramsComDescarte = $paramsBase;
    
    // Condição para médias/máximos - respeita filtro do usuário
    $condicaoCalculo = "RVP.ID_SITUACAO = 1"; // Padrão: não descartados
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
    $pontosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Se há filtro de descarte, buscar contagem filtrada separadamente
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

    // Buscar estatísticas por hora para cada ponto de medição
    $sqlHora = "SELECT 
                RVP.CD_PONTO_MEDICAO,
                PM.ID_TIPO_MEDIDOR,
                DATEPART(HOUR, RVP.DT_LEITURA) AS HORA,
                COUNT(*) AS TOTAL_HORA,
                SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS TOTAL_VALIDOS,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_VAZAO_EFETIVA ELSE NULL END) AS MEDIA_VAZAO_HORA,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_PRESSAO ELSE NULL END) AS MEDIA_PRESSAO_HORA,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_RESERVATORIO ELSE NULL END) AS MEDIA_NIVEL_HORA,
                MAX(CASE WHEN $condicaoCalculo THEN RVP.VL_RESERVATORIO ELSE NULL END) AS MAX_NIVEL_HORA
            FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
            LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
            LEFT JOIN SIMP.dbo.UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
            $whereBase
            GROUP BY RVP.CD_PONTO_MEDICAO, PM.ID_TIPO_MEDIDOR, DATEPART(HOUR, RVP.DT_LEITURA)
            ORDER BY RVP.CD_PONTO_MEDICAO, HORA";
    
    $stmtHora = $pdoSIMP->prepare($sqlHora);
    $stmtHora->execute($paramsBase);
    $horasRaw = $stmtHora->fetchAll(PDO::FETCH_ASSOC);

    // Organizar estatísticas por hora por ponto
    $estatPorHora = [];
    foreach ($horasRaw as $h) {
        $cdPonto = $h['CD_PONTO_MEDICAO'];
        $idTipoMedidor = $h['ID_TIPO_MEDIDOR'];
        if (!isset($estatPorHora[$cdPonto])) {
            $estatPorHora[$cdPonto] = [];
        }
        
        // Determinar valor do nível (MAX para reservatório, AVG para outros)
        $nivelValor = null;
        if ($idTipoMedidor == 6) {
            $nivelValor = $h['MAX_NIVEL_HORA'] !== null ? round((float)$h['MAX_NIVEL_HORA'], 2) : null;
        } else {
            $nivelValor = $h['MEDIA_NIVEL_HORA'] !== null ? round((float)$h['MEDIA_NIVEL_HORA'], 2) : null;
        }
        
        $estatPorHora[$cdPonto][(int)$h['HORA']] = [
            'total' => (int)$h['TOTAL_HORA'],
            'validos' => (int)$h['TOTAL_VALIDOS'],
            'mediaVazao' => $h['MEDIA_VAZAO_HORA'] !== null ? round((float)$h['MEDIA_VAZAO_HORA'], 2) : null,
            'mediaPressao' => $h['MEDIA_PRESSAO_HORA'] !== null ? round((float)$h['MEDIA_PRESSAO_HORA'], 2) : null,
            'nivelValor' => $nivelValor
        ];
    }

    // Formatar resultado
    $pontos = [];
    
    // Mapeamento de tipo de medidor para letra
    $letrasTipoMedidor = [
        1 => 'M', // Macromedidor
        2 => 'E', // Estação Pitométrica
        4 => 'P', // Medidor Pressão
        6 => 'R', // Nível Reservatório
        8 => 'H'  // Hidrômetro
    ];
    
    foreach ($pontosRaw as $p) {
        $cdPonto = $p['CD_PONTO_MEDICAO'];
        $idTipoMedidor = $p['ID_TIPO_MEDIDOR'];
        $idTipoLeitura = $p['ID_TIPO_LEITURA'];
        
        // Gerar código formatado: LOCALIDADE-ID_PONTO-LETRA-CD_UNIDADE
        $letraTipo = $letrasTipoMedidor[$idTipoMedidor] ?? 'X';
        $codigoFormatado = $p['CD_LOCALIDADE'] . '-' . str_pad($cdPonto, 6, '0', STR_PAD_LEFT) . '-' . $letraTipo . '-' . $p['CD_UNIDADE'];
        
        // Determinar total a exibir (filtrado ou geral)
        $totalExibir = (int)$p['TOTAL_GERAL'];
        if (!empty($descarte) && isset($contagemFiltrada[$cdPonto])) {
            $totalExibir = $contagemFiltrada[$cdPonto];
        } elseif (!empty($descarte)) {
            $totalExibir = 0;
        }
        
        // Para Nível Reservatório (ID=6), usar MAX ao invés de média
        $nivelValor = null;
        $nivelTipo = 'media';
        if ($idTipoMedidor == 6) {
            $nivelValor = $p['MAX_NIVEL'] !== null ? round((float)$p['MAX_NIVEL'], 2) : null;
            $nivelTipo = 'max';
        } else {
            $nivelValor = $p['MEDIA_NIVEL'] !== null ? round((float)$p['MEDIA_NIVEL'], 2) : null;
        }
        
        $pontos[$cdPonto] = [
            'cdPontoMedicao' => $cdPonto,
            'codigoFormatado' => $codigoFormatado,
            'dsPontoMedicao' => $p['DS_PONTO_MEDICAO'],
            'idTipoMedidor' => $idTipoMedidor,
            'dsTipoMedidor' => isset($tiposMedidor[$idTipoMedidor]) ? $tiposMedidor[$idTipoMedidor] : '-',
            'idTipoLeitura' => $idTipoLeitura,
            'dsTipoLeitura' => isset($tiposLeitura[$idTipoLeitura]) ? $tiposLeitura[$idTipoLeitura] : '-',
            'total' => $totalExibir,
            'totalGeral' => (int)$p['TOTAL_GERAL'],
            'totalNaoDescarte' => (int)$p['TOTAL_NAO_DESCARTE'],
            'totalDescarte' => (int)$p['TOTAL_DESCARTE'],
            'mediaVazao' => $p['MEDIA_VAZAO'] !== null ? round((float)$p['MEDIA_VAZAO'], 2) : null,
            'mediaPressao' => $p['MEDIA_PRESSAO'] !== null ? round((float)$p['MEDIA_PRESSAO'], 2) : null,
            'nivelValor' => $nivelValor,
            'nivelTipo' => $nivelTipo,
            'porHora' => isset($estatPorHora[$cdPonto]) ? $estatPorHora[$cdPonto] : []
        ];
    }
    
    // Filtrar pontos sem registros quando há filtro de descarte
    if (!empty($descarte)) {
        $pontos = array_filter($pontos, function($p) {
            return $p['total'] > 0;
        });
    }
    
    echo json_encode([
        'success' => true, 
        'pontosMedicao' => $pontos,
        'totalPontos' => count($pontos),
        'data_consultada' => $data,
        'filtro_descarte' => $descarte
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}