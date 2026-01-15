<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Buscar Dias de um Ponto de Medição
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('REGISTRO DE VAZÃO', ACESSO_LEITURA);

include_once '../conexao.php';

try {
    $cdPontoMedicao = isset($_GET['cd_ponto_medicao']) ? trim($_GET['cd_ponto_medicao']) : '';
    $dataInicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : '';
    $dataFim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';
    $descarte = isset($_GET['descarte']) ? trim($_GET['descarte']) : '';

    // CORREÇÃO: Validar valor de descarte (aceitar apenas '1' ou '2')
    if (!in_array($descarte, ['1', '2'], true)) {
        $descarte = '';
    }

    if (empty($cdPontoMedicao)) {
        throw new Exception('Ponto de medição não informado');
    }

    // WHERE base
    $whereBase = "WHERE RVP.CD_PONTO_MEDICAO = :cd_ponto_medicao";
    $paramsBase = [':cd_ponto_medicao' => $cdPontoMedicao];

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

    // Buscar estatísticas por dia deste ponto
    $sqlEstatisticas = "SELECT 
                CONVERT(DATE, RVP.DT_LEITURA) AS DATA_DIA,
                COUNT(*) AS TOTAL_GERAL,
                SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS TOTAL_NAO_DESCARTE,
                SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS TOTAL_DESCARTE,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_VAZAO_EFETIVA ELSE NULL END) AS MEDIA_VAZAO,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_PRESSAO ELSE NULL END) AS MEDIA_PRESSAO,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_RESERVATORIO ELSE NULL END) AS MEDIA_NIVEL,
                MAX(CASE WHEN $condicaoCalculo THEN RVP.VL_RESERVATORIO ELSE NULL END) AS MAX_NIVEL
            FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
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
                $whereCompleto
                GROUP BY CONVERT(DATE, RVP.DT_LEITURA)";

        $stmtFiltrado = $pdoSIMP->prepare($sqlFiltrado);
        $stmtFiltrado->execute($paramsCompleto);
        $filtradosRaw = $stmtFiltrado->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filtradosRaw as $f) {
            $contagemFiltradaPorDia[$f['DATA_DIA']] = (int) $f['TOTAL_FILTRADO'];
        }
    }

    // Buscar estatísticas por hora para gráfico
    $sqlEstatHora = "SELECT 
                CONVERT(DATE, RVP.DT_LEITURA) AS DATA_DIA,
                DATEPART(HOUR, RVP.DT_LEITURA) AS HORA,
                COUNT(*) AS TOTAL_HORA,
                SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS TOTAL_VALIDOS,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_VAZAO_EFETIVA ELSE NULL END) AS MEDIA_VAZAO_HORA,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_PRESSAO ELSE NULL END) AS MEDIA_PRESSAO_HORA,
                AVG(CASE WHEN $condicaoCalculo THEN RVP.VL_RESERVATORIO ELSE NULL END) AS MEDIA_NIVEL_HORA,
                MAX(CASE WHEN $condicaoCalculo THEN RVP.VL_RESERVATORIO ELSE NULL END) AS MAX_NIVEL_HORA
            FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
            $whereBase
            GROUP BY CONVERT(DATE, RVP.DT_LEITURA), DATEPART(HOUR, RVP.DT_LEITURA)
            ORDER BY DATA_DIA DESC, HORA";

    $stmtHora = $pdoSIMP->prepare($sqlEstatHora);
    $stmtHora->execute($paramsBase);
    $horasRaw = $stmtHora->fetchAll(PDO::FETCH_ASSOC);

    // Organizar estatísticas por hora por dia
    $estatPorHora = [];
    foreach ($horasRaw as $h) {
        $dataChave = $h['DATA_DIA'];
        if (!isset($estatPorHora[$dataChave])) {
            $estatPorHora[$dataChave] = [];
        }
        $estatPorHora[$dataChave][(int) $h['HORA']] = [
            'total' => (int) $h['TOTAL_HORA'],
            'validos' => (int) $h['TOTAL_VALIDOS'],
            'mediaVazao' => $h['MEDIA_VAZAO_HORA'] !== null ? round((float) $h['MEDIA_VAZAO_HORA'], 2) : null,
            'mediaPressao' => $h['MEDIA_PRESSAO_HORA'] !== null ? round((float) $h['MEDIA_PRESSAO_HORA'], 2) : null,
            'nivelValor' => $h['MEDIA_NIVEL_HORA'] !== null ? round((float) $h['MEDIA_NIVEL_HORA'], 2) : null
        ];
    }

    // Converter para array indexado por data
    $estatisticas = [];
    foreach ($estatisticasRaw as $est) {
        $dataChave = $est['DATA_DIA'];

        // Determinar total a exibir
        $totalExibir = (int) $est['TOTAL_GERAL'];
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
            'totalGeral' => (int) $est['TOTAL_GERAL'],
            'totalNaoDescarte' => (int) $est['TOTAL_NAO_DESCARTE'],
            'totalDescarte' => (int) $est['TOTAL_DESCARTE'],
            'mediaVazao' => $est['MEDIA_VAZAO'] !== null ? round((float) $est['MEDIA_VAZAO'], 2) : null,
            'mediaPressao' => $est['MEDIA_PRESSAO'] !== null ? round((float) $est['MEDIA_PRESSAO'], 2) : null,
            'mediaNivel' => $est['MEDIA_NIVEL'] !== null ? round((float) $est['MEDIA_NIVEL'], 2) : null,
            'maxNivel' => $est['MAX_NIVEL'] !== null ? round((float) $est['MAX_NIVEL'], 2) : null,
            'porHora' => isset($estatPorHora[$dataChave]) ? $estatPorHora[$dataChave] : []
        ];
    }

    // CORREÇÃO: Garantir que $estatisticas seja sempre um array válido
    if (!isset($estatisticas) || !is_array($estatisticas)) {
        $estatisticas = [];
    }

    echo json_encode([
        'success' => true,
        'diasPonto' => $estatisticas,
        'totalDias' => count($estatisticas),
        'cdPontoMedicao' => $cdPontoMedicao,
        'filtro_descarte' => $descarte
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}