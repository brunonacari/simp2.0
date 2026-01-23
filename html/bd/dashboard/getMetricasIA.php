<?php
/**
 * SIMP - Sistema Integrado de Macromedicao e Pitometria
 * Endpoint: Buscar Metricas IA
 * 
 * ATUALIZADO: Resumo e rankings consideram PERÍODO COMPLETO (não só última data)
 * 
 * @author Bruno
 * @version 1.2
 * @date 2026-01-23
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexao nao estabelecida');
    }

    // Parametros
    $acao = $_GET['acao'] ?? 'listar';
    $periodo = (int) ($_GET['periodo'] ?? 7);
    $unidade = $_GET['unidade'] ?? '';
    $tipo = $_GET['tipo'] ?? '';
    $status = $_GET['status'] ?? '';
    $cdPonto = $_GET['cdPonto'] ?? '';
    $dataRef = $_GET['dataRef'] ?? '';

    // ========================================
    // BUSCAR DATA MÁXIMA DISPONÍVEL (MAX da tabela)
    // ========================================
    $sqlMaxData = "SELECT MAX(DT_REFERENCIA) AS ULTIMA_DATA FROM SIMP.dbo.IA_METRICAS_DIARIAS";
    $stmtMax = $pdoSIMP->query($sqlMaxData);
    $ultimaData = $stmtMax->fetch(PDO::FETCH_ASSOC)['ULTIMA_DATA'];

    if (!$ultimaData) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'resumo' => [
                'total' => 0,
                'ok' => 0,
                'atencao' => 0,
                'critico' => 0,
                'coberturaMedia' => 0,
                'totalRegistrosTratados' => 0,
                'pontosComTratamento' => 0,
                'percentualTratado' => 0
            ],
            'evolucao' => [],
            'criticos' => [],
            'maisTratados' => [],
            'dataReferencia' => date('d/m/Y'),
            'message' => 'Nenhum dado encontrado na tabela'
        ]);
        exit;
    }

    // ========================================
    // DEFINIR DATA DE REFERÊNCIA (dataRef ou ultimaData)
    // ========================================
    if (!empty($dataRef) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRef)) {
        // Validar que dataRef não é maior que ultimaData
        $dataFim = ($dataRef <= $ultimaData) ? $dataRef : $ultimaData;
    } else {
        $dataFim = $ultimaData;
    }

    // Calcular data inicial baseada no período (a partir de dataFim, não ultimaData)
    $dataInicio = date('Y-m-d', strtotime("-{$periodo} days", strtotime($dataFim)));

    // ========================================
    // MONTAR CLAUSULA WHERE (período completo)
    // ========================================
    $where = ["IM.DT_REFERENCIA BETWEEN :dataInicio AND :dataFim"];
    $params = [
        ':dataInicio' => $dataInicio,
        ':dataFim' => $dataFim
    ];

    if (!empty($unidade)) {
        $where[] = "L.CD_UNIDADE = :unidade";
        $params[':unidade'] = $unidade;
    }

    if (!empty($tipo)) {
        $where[] = "IM.ID_TIPO_MEDIDOR = :tipo";
        $params[':tipo'] = $tipo;
    }

    if (!empty($status)) {
        $where[] = "IM.DS_STATUS = :status";
        $params[':status'] = $status;
    }

    if (!empty($cdPonto)) {
        $where[] = "IM.CD_PONTO_MEDICAO = :cdPonto";
        $params[':cdPonto'] = $cdPonto;
    }

    $whereClause = implode(' AND ', $where);

    // ========================================
    // ACAO: LISTAR (padrao)
    // ========================================
    if ($acao === 'listar') {

        // Parametros de paginacao
        $limite = (int) ($_GET['limite'] ?? 100);
        $pagina = (int) ($_GET['pagina'] ?? 1);
        $offset = ($pagina - 1) * $limite;

        // 1. BUSCAR DADOS DETALHADOS
        $sqlDados = "
            SELECT 
                IM.CD_PONTO_MEDICAO,
                IM.DT_REFERENCIA,
                IM.ID_TIPO_MEDIDOR,
                IM.QTD_REGISTROS,
                IM.PERC_COBERTURA,
                IM.QTD_HORAS_COM_DADOS,
                IM.HORAS_SEM_DADOS,
                IM.VL_MEDIA,
                IM.VL_MIN,
                IM.VL_MAX,
                IM.VL_DESVIO_PADRAO,
                IM.VL_MEDIA_HIST_4SEM,
                IM.VL_DESVIO_HIST_PERC,
                IM.VL_TENDENCIA_7D,
                IM.FL_COBERTURA_BAIXA,
                IM.FL_SENSOR_PROBLEMA,
                IM.FL_VALOR_ANOMALO,
                IM.FL_DESVIO_SIGNIFICATIVO,
                IM.DS_STATUS,
                IM.DS_RESUMO,
                IM.QTD_TRATADOS_MANUAL,
                PM.DS_NOME,
                L.CD_UNIDADE,
                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                U.DS_NOME AS DS_UNIDADE
            FROM SIMP.dbo.IA_METRICAS_DIARIAS IM
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON IM.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            LEFT JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            WHERE {$whereClause}
            ORDER BY 
                CASE IM.DS_STATUS 
                    WHEN 'CRITICO' THEN 1 
                    WHEN 'ATENCAO' THEN 2 
                    ELSE 3 
                END,
                IM.DT_REFERENCIA DESC,
                PM.DS_NOME
            OFFSET {$offset} ROWS FETCH NEXT {$limite} ROWS ONLY
        ";

        $stmtDados = $pdoSIMP->prepare($sqlDados);
        $stmtDados->execute($params);
        $dados = $stmtDados->fetchAll(PDO::FETCH_ASSOC);

        // ========================================
        // 2. RESUMO - ÚLTIMA DATA (status atual)
        // ========================================
        $sqlResumoStatus = "
            SELECT 
                COUNT(*) AS TOTAL,
                SUM(CASE WHEN DS_STATUS = 'OK' THEN 1 ELSE 0 END) AS QTD_OK,
                SUM(CASE WHEN DS_STATUS = 'ATENCAO' THEN 1 ELSE 0 END) AS QTD_ATENCAO,
                SUM(CASE WHEN DS_STATUS = 'CRITICO' THEN 1 ELSE 0 END) AS QTD_CRITICO,
                AVG(PERC_COBERTURA) AS COBERTURA_MEDIA
            FROM SIMP.dbo.IA_METRICAS_DIARIAS IM
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON IM.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            WHERE IM.DT_REFERENCIA = :dataFim
        ";

        $paramsResumoStatus = [':dataFim' => $dataFim];

        if (!empty($unidade)) {
            $sqlResumoStatus .= " AND L.CD_UNIDADE = :unidade";
            $paramsResumoStatus[':unidade'] = $unidade;
        }
        if (!empty($tipo)) {
            $sqlResumoStatus .= " AND IM.ID_TIPO_MEDIDOR = :tipo";
            $paramsResumoStatus[':tipo'] = $tipo;
        }

        $stmtResumoStatus = $pdoSIMP->prepare($sqlResumoStatus);
        $stmtResumoStatus->execute($paramsResumoStatus);
        $resumoStatus = $stmtResumoStatus->fetch(PDO::FETCH_ASSOC);

        // ========================================
        // 3. RESUMO TRATAMENTO - PERÍODO COMPLETO
        // ========================================
        $sqlResumoTratamento = "
            SELECT 
                SUM(ISNULL(QTD_TRATADOS_MANUAL, 0)) AS TOTAL_REGISTROS_TRATADOS,
                COUNT(DISTINCT CASE WHEN ISNULL(QTD_TRATADOS_MANUAL, 0) > 0 
                    THEN CAST(IM.CD_PONTO_MEDICAO AS VARCHAR) + '-' + CAST(IM.DT_REFERENCIA AS VARCHAR) 
                END) AS REGISTROS_COM_TRATAMENTO,
                COUNT(DISTINCT CASE WHEN ISNULL(QTD_TRATADOS_MANUAL, 0) > 0 
                    THEN IM.CD_PONTO_MEDICAO 
                END) AS PONTOS_COM_TRATAMENTO,
                SUM(QTD_REGISTROS) AS TOTAL_REGISTROS
            FROM SIMP.dbo.IA_METRICAS_DIARIAS IM
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON IM.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            WHERE IM.DT_REFERENCIA BETWEEN :dataInicio AND :dataFim
        ";

        $paramsResumoTratamento = [':dataInicio' => $dataInicio, ':dataFim' => $dataFim];

        if (!empty($unidade)) {
            $sqlResumoTratamento .= " AND L.CD_UNIDADE = :unidade";
            $paramsResumoTratamento[':unidade'] = $unidade;
        }
        if (!empty($tipo)) {
            $sqlResumoTratamento .= " AND IM.ID_TIPO_MEDIDOR = :tipo";
            $paramsResumoTratamento[':tipo'] = $tipo;
        }

        $stmtResumoTratamento = $pdoSIMP->prepare($sqlResumoTratamento);
        $stmtResumoTratamento->execute($paramsResumoTratamento);
        $resumoTratamento = $stmtResumoTratamento->fetch(PDO::FETCH_ASSOC);

        // Calcular percentual tratado
        $totalRegistros = (int) ($resumoTratamento['TOTAL_REGISTROS'] ?? 0);
        $totalTratados = (int) ($resumoTratamento['TOTAL_REGISTROS_TRATADOS'] ?? 0);
        $percentualTratado = $totalRegistros > 0
            ? round(($totalTratados / $totalRegistros) * 100, 2)
            : 0;

        // ========================================
        // 4. EVOLUÇÃO - PERÍODO COMPLETO
        // ========================================
        $sqlEvolucao = "
            SELECT 
                IM.DT_REFERENCIA,
                SUM(CASE WHEN IM.DS_STATUS = 'OK' THEN 1 ELSE 0 END) AS QTD_OK,
                SUM(CASE WHEN IM.DS_STATUS = 'ATENCAO' THEN 1 ELSE 0 END) AS QTD_ATENCAO,
                SUM(CASE WHEN IM.DS_STATUS = 'CRITICO' THEN 1 ELSE 0 END) AS QTD_CRITICO,
                COUNT(*) AS TOTAL,
                SUM(ISNULL(IM.QTD_TRATADOS_MANUAL, 0)) AS TOTAL_TRATADOS,
                SUM(CASE WHEN ISNULL(IM.QTD_TRATADOS_MANUAL, 0) > 0 THEN 1 ELSE 0 END) AS PONTOS_COM_TRATAMENTO
            FROM SIMP.dbo.IA_METRICAS_DIARIAS IM
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON IM.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            WHERE IM.DT_REFERENCIA BETWEEN :dataInicio AND :dataFim
        ";

        $paramsEvolucao = [':dataInicio' => $dataInicio, ':dataFim' => $dataFim];

        if (!empty($unidade)) {
            $sqlEvolucao .= " AND L.CD_UNIDADE = :unidade";
            $paramsEvolucao[':unidade'] = $unidade;
        }
        if (!empty($tipo)) {
            $sqlEvolucao .= " AND IM.ID_TIPO_MEDIDOR = :tipo";
            $paramsEvolucao[':tipo'] = $tipo;
        }

        $sqlEvolucao .= " GROUP BY IM.DT_REFERENCIA ORDER BY IM.DT_REFERENCIA";

        $stmtEvolucao = $pdoSIMP->prepare($sqlEvolucao);
        $stmtEvolucao->execute($paramsEvolucao);
        $evolucao = $stmtEvolucao->fetchAll(PDO::FETCH_ASSOC);

        // ========================================
        // 5. PONTOS CRÍTICOS - ÚLTIMA DATA
        // ========================================
        $sqlCriticos = "
            SELECT TOP 10
                IM.CD_PONTO_MEDICAO,
                IM.DT_REFERENCIA,
                IM.ID_TIPO_MEDIDOR,
                IM.PERC_COBERTURA,
                IM.VL_MEDIA,
                IM.DS_STATUS,
                IM.DS_RESUMO,
                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                IM.QTD_TRATADOS_MANUAL,
                PM.DS_NOME
            FROM SIMP.dbo.IA_METRICAS_DIARIAS IM
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON IM.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            WHERE IM.DT_REFERENCIA = :dataFim
            AND IM.DS_STATUS IN ('CRITICO', 'ATENCAO')
        ";

        $paramsCriticos = [':dataFim' => $dataFim];

        if (!empty($unidade)) {
            $sqlCriticos .= " AND L.CD_UNIDADE = :unidade";
            $paramsCriticos[':unidade'] = $unidade;
        }
        if (!empty($tipo)) {
            $sqlCriticos .= " AND IM.ID_TIPO_MEDIDOR = :tipo";
            $paramsCriticos[':tipo'] = $tipo;
        }

        $sqlCriticos .= "
            ORDER BY 
                CASE IM.DS_STATUS WHEN 'CRITICO' THEN 1 ELSE 2 END,
                IM.PERC_COBERTURA ASC
        ";

        $stmtCriticos = $pdoSIMP->prepare($sqlCriticos);
        $stmtCriticos->execute($paramsCriticos);
        $criticos = $stmtCriticos->fetchAll(PDO::FETCH_ASSOC);

        // ========================================
        // 6. MAIOR ESFORÇO OPERACIONAL - PERÍODO COMPLETO
        // (Agregado por ponto, soma de todos os dias)
        // ========================================
        $sqlMaisTratados = "
            SELECT TOP 10
                IM.CD_PONTO_MEDICAO,
                PM.ID_TIPO_MEDIDOR,
                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                PM.DS_NOME,
                SUM(ISNULL(IM.QTD_TRATADOS_MANUAL, 0)) AS QTD_TRATADOS_MANUAL,
                SUM(IM.QTD_REGISTROS) AS QTD_REGISTROS,
                CAST(SUM(ISNULL(IM.QTD_TRATADOS_MANUAL, 0)) * 100.0 / NULLIF(SUM(IM.QTD_REGISTROS), 0) AS DECIMAL(5,2)) AS PERC_TRATADO,
                COUNT(DISTINCT IM.DT_REFERENCIA) AS DIAS_COM_TRATAMENTO,
                MIN(IM.DT_REFERENCIA) AS PRIMEIRA_DATA,
                MAX(IM.DT_REFERENCIA) AS ULTIMA_DATA
            FROM SIMP.dbo.IA_METRICAS_DIARIAS IM
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON IM.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            WHERE IM.DT_REFERENCIA BETWEEN :dataInicio AND :dataFim
            AND ISNULL(IM.QTD_TRATADOS_MANUAL, 0) > 0
        ";

        $paramsMaisTratados = [':dataInicio' => $dataInicio, ':dataFim' => $dataFim];

        if (!empty($unidade)) {
            $sqlMaisTratados .= " AND L.CD_UNIDADE = :unidade";
            $paramsMaisTratados[':unidade'] = $unidade;
        }
        if (!empty($tipo)) {
            $sqlMaisTratados .= " AND PM.ID_TIPO_MEDIDOR = :tipo";
            $paramsMaisTratados[':tipo'] = $tipo;
        }

        $sqlMaisTratados .= "
            GROUP BY 
                IM.CD_PONTO_MEDICAO,
                PM.ID_TIPO_MEDIDOR,
                L.CD_LOCALIDADE,
                PM.DS_NOME
            ORDER BY SUM(ISNULL(IM.QTD_TRATADOS_MANUAL, 0)) DESC
        ";

        $stmtMaisTratados = $pdoSIMP->prepare($sqlMaisTratados);
        $stmtMaisTratados->execute($paramsMaisTratados);
        $maisTratados = $stmtMaisTratados->fetchAll(PDO::FETCH_ASSOC);

        // ========================================
        // RETORNAR RESPOSTA
        // ========================================
        echo json_encode([
            'success' => true,
            'data' => $dados,
            'resumo' => [
                // Status da última data
                'total' => (int) ($resumoStatus['TOTAL'] ?? 0),
                'ok' => (int) ($resumoStatus['QTD_OK'] ?? 0),
                'atencao' => (int) ($resumoStatus['QTD_ATENCAO'] ?? 0),
                'critico' => (int) ($resumoStatus['QTD_CRITICO'] ?? 0),
                'coberturaMedia' => (float) ($resumoStatus['COBERTURA_MEDIA'] ?? 0),
                // Tratamento do período completo
                'totalRegistrosTratados' => (int) ($resumoTratamento['TOTAL_REGISTROS_TRATADOS'] ?? 0),
                'pontosComTratamento' => (int) ($resumoTratamento['PONTOS_COM_TRATAMENTO'] ?? 0),
                'percentualTratado' => $percentualTratado
            ],
            'evolucao' => $evolucao,
            'criticos' => $criticos,
            'maisTratados' => $maisTratados,
            'dataReferencia' => date('d/m/Y', strtotime($ultimaData)),
            'periodo' => [
                'inicio' => $dataInicio,
                'fim' => $dataFim,
                'dias' => $periodo
            ]
        ], JSON_UNESCAPED_UNICODE);

    }
    // ========================================
    // ACAO: DETALHE (um ponto especifico)
    // ========================================
    elseif ($acao === 'detalhe') {

        if (empty($cdPonto)) {
            throw new Exception('Ponto nao informado');
        }

        $sqlDetalhe = "
            SELECT 
                IM.*,
                PM.DS_NOME,
                PM.DS_LOCALIZACAO,
                PM.VL_LIMITE_INFERIOR_VAZAO,
                PM.VL_LIMITE_SUPERIOR_VAZAO
            FROM SIMP.dbo.IA_METRICAS_DIARIAS IM
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON IM.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            WHERE IM.CD_PONTO_MEDICAO = :cdPonto
            ORDER BY IM.DT_REFERENCIA DESC
        ";

        $stmtDetalhe = $pdoSIMP->prepare($sqlDetalhe);
        $stmtDetalhe->execute([':cdPonto' => $cdPonto]);
        $detalhe = $stmtDetalhe->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $detalhe
        ], JSON_UNESCAPED_UNICODE);

    }
    // ========================================
    // ACAO DESCONHECIDA
    // ========================================
    else {
        throw new Exception('Acao nao reconhecida: ' . $acao);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}