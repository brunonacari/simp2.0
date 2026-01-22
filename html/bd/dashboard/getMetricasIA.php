<?php
/**
 * SIMP - Sistema Integrado de Macromedicao e Pitometria
 * Endpoint: Buscar Metricas IA
 * 
 * Retorna dados da tabela IA_METRICAS_DIARIAS
 * Data base: MAX(DT_REFERENCIA) - ultimos dias COM DADOS
 * 
 * @author Bruno
 * @version 1.0
 * @date 2026-01-22
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

    // ========================================
    // BUSCAR DATA DE REFERENCIA (MAX da tabela)
    // Nao usa GETDATE(), usa a ultima data com dados
    // ========================================
    $sqlMaxData = "SELECT MAX(DT_REFERENCIA) AS ULTIMA_DATA FROM SIMP.dbo.IA_METRICAS_DIARIAS";
    $stmtMax = $pdoSIMP->query($sqlMaxData);
    $ultimaData = $stmtMax->fetch(PDO::FETCH_ASSOC)['ULTIMA_DATA'];

    if (!$ultimaData) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'resumo' => ['total' => 0, 'ok' => 0, 'atencao' => 0, 'critico' => 0],
            'evolucao' => [],
            'criticos' => [],
            'dataReferencia' => date('d/m/Y'),
            'message' => 'Nenhum dado encontrado na tabela'
        ]);
        exit;
    }

    // Calcular data inicial baseada no periodo
    // Ex: periodo=7 -> ultimos 7 dias A PARTIR da ultima data com dados
    $dataFim = $ultimaData;
    $dataInicio = date('Y-m-d', strtotime("-{$periodo} days", strtotime($ultimaData)));

    // ========================================
    // MONTAR CLAUSULA WHERE
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
        $limite = (int) ($_GET['limite'] ?? 1);
        $pagina = (int) ($_GET['pagina'] ?? 1);
        $offset = ($pagina - 1) * $limite;

        // 1. BUSCAR DADOS DETALHADOS (COM PAGINACAO)
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

        // 2. BUSCAR RESUMO (apenas ultima data)
        $sqlResumo = "
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

        // Adicionar filtros ao resumo (exceto periodo)
        $paramsResumo = [':dataFim' => $dataFim];
        $whereResumo = [];

        if (!empty($unidade)) {
            $whereResumo[] = "L.CD_UNIDADE = :unidade";
            $paramsResumo[':unidade'] = $unidade;
        }
        if (!empty($tipo)) {
            $whereResumo[] = "IM.ID_TIPO_MEDIDOR = :tipo";
            $paramsResumo[':tipo'] = $tipo;
        }

        if (!empty($whereResumo)) {
            $sqlResumo .= " AND " . implode(' AND ', $whereResumo);
        }

        $stmtResumo = $pdoSIMP->prepare($sqlResumo);
        $stmtResumo->execute($paramsResumo);
        $resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC);

        // 3. BUSCAR EVOLUCAO (agrupado por dia)
        $sqlEvolucao = "
            SELECT 
                IM.DT_REFERENCIA,
                SUM(CASE WHEN IM.DS_STATUS = 'OK' THEN 1 ELSE 0 END) AS QTD_OK,
                SUM(CASE WHEN IM.DS_STATUS = 'ATENCAO' THEN 1 ELSE 0 END) AS QTD_ATENCAO,
                SUM(CASE WHEN IM.DS_STATUS = 'CRITICO' THEN 1 ELSE 0 END) AS QTD_CRITICO,
                COUNT(*) AS TOTAL
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

        // 4. BUSCAR PONTOS CRITICOS (ranking)
        $sqlCriticos = "
            SELECT TOP 10
                IM.CD_PONTO_MEDICAO,
                IM.DT_REFERENCIA,
                IM.PERC_COBERTURA,
                IM.VL_MEDIA,
                IM.DS_STATUS,
                IM.DS_RESUMO,
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

        // RETORNAR RESPOSTA
        echo json_encode([
            'success' => true,
            'data' => $dados,
            'resumo' => [
                'total' => (int) ($resumo['TOTAL'] ?? 0),
                'ok' => (int) ($resumo['QTD_OK'] ?? 0),
                'atencao' => (int) ($resumo['QTD_ATENCAO'] ?? 0),
                'critico' => (int) ($resumo['QTD_CRITICO'] ?? 0),
                'coberturaMedia' => (float) ($resumo['COBERTURA_MEDIA'] ?? 0)
            ],
            'evolucao' => $evolucao,
            'criticos' => $criticos,
            'dataReferencia' => date('d/m/Y', strtotime($ultimaData)),
            'periodo' => [
                'inicio' => $dataInicio,
                'fim' => $dataFim
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