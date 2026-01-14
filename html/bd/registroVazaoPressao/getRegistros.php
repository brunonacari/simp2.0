<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Buscar Estatísticas por Dia (Agregadores)
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('REGISTRO DE VAZÃO', ACESSO_LEITURA);

include_once '../conexao.php';

try {
    $cdUnidade = isset($_GET['cd_unidade']) ? trim($_GET['cd_unidade']) : '';
    $cdLocalidade = isset($_GET['cd_localidade']) ? trim($_GET['cd_localidade']) : '';
    $cdPontoMedicao = isset($_GET['cd_ponto_medicao']) ? trim($_GET['cd_ponto_medicao']) : '';
    $dataInicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : '';
    $dataFim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';
    $tipoMedidor = isset($_GET['tipo_medidor']) ? trim($_GET['tipo_medidor']) : '';
    $tipoLeitura = isset($_GET['tipo_leitura']) ? trim($_GET['tipo_leitura']) : '';
    $descarte = isset($_GET['descarte']) ? trim($_GET['descarte']) : '';

    // WHERE base (sem filtro de descarte)
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

    // Condição para cálculos (médias) - respeita filtro do usuário
    $condicaoCalculo = "RVP.ID_SITUACAO = 1"; // Padrão: não descartados
    if ($descarte == '2') {
        $condicaoCalculo = "RVP.ID_SITUACAO = 2"; // Se filtrou por descartados
    }

    // Contar total de registros (com filtro de descarte)
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                 LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                 LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                 LEFT JOIN SIMP.dbo.UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
                 $whereCompleto";
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($paramsCompleto);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // Buscar estatísticas por dia (com todos os campos necessários)
    $sqlEstatisticas = "SELECT 
                CONVERT(DATE, RVP.DT_LEITURA) AS DATA_DIA,
                COUNT(*) AS TOTAL_GERAL,
                COUNT(DISTINCT RVP.CD_PONTO_MEDICAO) AS QTD_PONTOS,
                SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS TOTAL_NAO_DESCARTE,
                SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS TOTAL_DESCARTE,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_VAZAO_EFETIVA ELSE NULL END) AS MEDIA_VAZAO,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_PRESSAO ELSE NULL END) AS MEDIA_PRESSAO,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_RESERVATORIO ELSE NULL END) AS MEDIA_NIVEL
            FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
            LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
            LEFT JOIN SIMP.dbo.UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
            $whereBase
            GROUP BY CONVERT(DATE, RVP.DT_LEITURA)
            ORDER BY DATA_DIA DESC";
    
    $stmtEstat = $pdoSIMP->prepare($sqlEstatisticas);
    $stmtEstat->execute($paramsBase);
    $estatisticasRaw = $stmtEstat->fetchAll(PDO::FETCH_ASSOC);

    // Se há filtro de descarte, buscar contagem filtrada por dia
    $contagemFiltradaPorDia = [];
    if (!empty($descarte)) {
        $sqlFiltrado = "SELECT 
                    CONVERT(DATE, RVP.DT_LEITURA) AS DATA_DIA,
                    COUNT(*) AS TOTAL_FILTRADO
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                LEFT JOIN SIMP.dbo.UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
                $whereCompleto
                GROUP BY CONVERT(DATE, RVP.DT_LEITURA)";
        
        $stmtFiltrado = $pdoSIMP->prepare($sqlFiltrado);
        $stmtFiltrado->execute($paramsCompleto);
        $filtradosRaw = $stmtFiltrado->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($filtradosRaw as $f) {
            $contagemFiltradaPorDia[$f['DATA_DIA']] = (int)$f['TOTAL_FILTRADO'];
        }
    }
    
    // Buscar estatísticas por hora (para gráfico)
    $sqlEstatHora = "SELECT 
                CONVERT(DATE, RVP.DT_LEITURA) AS DATA_DIA,
                DATEPART(HOUR, RVP.DT_LEITURA) AS HORA,
                COUNT(*) AS TOTAL_HORA,
                SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS TOTAL_VALIDOS,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_VAZAO_EFETIVA ELSE NULL END) AS MEDIA_VAZAO_HORA
            FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
            LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
            LEFT JOIN SIMP.dbo.UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
            $whereBase
            GROUP BY CONVERT(DATE, RVP.DT_LEITURA), DATEPART(HOUR, RVP.DT_LEITURA)
            ORDER BY DATA_DIA DESC, HORA ASC";
    
    $stmtHora = $pdoSIMP->prepare($sqlEstatHora);
    $stmtHora->execute($paramsBase);
    $estatHoraRaw = $stmtHora->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar estatísticas por hora por dia
    $estatPorHora = [];
    foreach ($estatHoraRaw as $h) {
        $dataChave = $h['DATA_DIA'];
        if (!isset($estatPorHora[$dataChave])) {
            $estatPorHora[$dataChave] = [];
        }
        $estatPorHora[$dataChave][(int)$h['HORA']] = [
            'total' => (int)$h['TOTAL_HORA'],
            'validos' => (int)$h['TOTAL_VALIDOS'],
            'mediaVazao' => $h['MEDIA_VAZAO_HORA'] !== null ? round((float)$h['MEDIA_VAZAO_HORA'], 2) : null
        ];
    }
    
    // Converter para array indexado por data
    $estatisticas = [];
    foreach ($estatisticasRaw as $est) {
        $dataChave = $est['DATA_DIA'];
        
        // Determinar total a exibir (filtrado ou geral)
        $totalExibir = (int)$est['TOTAL_GERAL'];
        if (!empty($descarte) && isset($contagemFiltradaPorDia[$dataChave])) {
            $totalExibir = $contagemFiltradaPorDia[$dataChave];
        } elseif (!empty($descarte)) {
            $totalExibir = 0;
        }
        
        // Se filtrou e não há registros, pular este dia
        if (!empty($descarte) && $totalExibir == 0) {
            continue;
        }
        
        $estatisticas[$dataChave] = [
            'total' => $totalExibir,
            'totalGeral' => (int)$est['TOTAL_GERAL'],
            'qtdPontos' => (int)$est['QTD_PONTOS'],
            'totalNaoDescarte' => (int)$est['TOTAL_NAO_DESCARTE'],
            'totalDescarte' => (int)$est['TOTAL_DESCARTE'],
            'mediaVazao' => $est['MEDIA_VAZAO'] !== null ? round((float)$est['MEDIA_VAZAO'], 2) : null,
            'mediaPressao' => $est['MEDIA_PRESSAO'] !== null ? round((float)$est['MEDIA_PRESSAO'], 2) : null,
            'mediaNivel' => $est['MEDIA_NIVEL'] !== null ? round((float)$est['MEDIA_NIVEL'], 2) : null,
            'porHora' => isset($estatPorHora[$dataChave]) ? $estatPorHora[$dataChave] : []
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'estatisticasDia' => $estatisticas,
        'total' => (int)$total,
        'totalDias' => count($estatisticas),
        'filtro_descarte' => $descarte
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}