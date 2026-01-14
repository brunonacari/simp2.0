<?php
// bd/registroManutencao/listarRegistros.php
header('Content-Type: application/json');
include_once '../conexao.php';

try {
    // Parâmetros de filtro
    $cdUnidade = isset($_GET['cd_unidade']) ? (int)$_GET['cd_unidade'] : 0;
    $cdLocalidade = isset($_GET['cd_localidade']) ? (int)$_GET['cd_localidade'] : 0;
    $cdPontoMedicao = isset($_GET['cd_ponto_medicao']) ? (int)$_GET['cd_ponto_medicao'] : 0;
    $cdTecnico = isset($_GET['cd_tecnico']) ? (int)$_GET['cd_tecnico'] : 0;
    $situacoes = isset($_GET['situacoes']) && !empty($_GET['situacoes']) ? explode(',', $_GET['situacoes']) : [];
    $dataInicial = isset($_GET['data_inicial']) ? trim($_GET['data_inicial']) : '';
    $dataFinal = isset($_GET['data_final']) ? trim($_GET['data_final']) : '';
    $buscaGeral = isset($_GET['busca_geral']) ? trim($_GET['busca_geral']) : '';

    // Parâmetros de paginação
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $itensPorPagina = isset($_GET['itens_por_pagina']) ? min(100, max(1, (int)$_GET['itens_por_pagina'])) : 15;
    $offset = ($pagina - 1) * $itensPorPagina;

    // Parâmetros de ordenação
    $camposOrdenacao = [
        'CODIGO_PROGRAMACAO' => 'P.CD_CODIGO',
        'CD_OCORRENCIA' => 'R.CD_OCORRENCIA',
        'CODIGO_PONTO' => 'L.CD_LOCALIDADE',
        'DS_TECNICO' => 'COALESCE(UT.DS_NOME, T.DS_NOME)',
        'DT_REALIZADO' => 'R.DT_REALIZADO',
        'ID_SITUACAO' => 'R.ID_SITUACAO'
    ];
    
    $ordemCampo = isset($_GET['ordem_campo']) && isset($camposOrdenacao[$_GET['ordem_campo']]) 
        ? $camposOrdenacao[$_GET['ordem_campo']] 
        : 'R.DT_REALIZADO';
    $ordemDirecao = isset($_GET['ordem_direcao']) && strtoupper($_GET['ordem_direcao']) === 'ASC' ? 'ASC' : 'DESC';

    // Construir WHERE
    $where = "WHERE 1=1";
    $params = [];

    if ($cdUnidade > 0) {
        $where .= " AND L.CD_UNIDADE = :cd_unidade";
        $params[':cd_unidade'] = $cdUnidade;
    }

    if ($cdLocalidade > 0) {
        $where .= " AND PM.CD_LOCALIDADE = :cd_localidade";
        $params[':cd_localidade'] = $cdLocalidade;
    }

    if ($cdPontoMedicao > 0) {
        $where .= " AND P.CD_PONTO_MEDICAO = :cd_ponto_medicao";
        $params[':cd_ponto_medicao'] = $cdPontoMedicao;
    }

    if ($cdTecnico > 0) {
        $where .= " AND R.CD_TECNICO = :cd_tecnico";
        $params[':cd_tecnico'] = $cdTecnico;
    }

    if (!empty($situacoes)) {
        $situacoesInt = array_map('intval', $situacoes);
        $where .= " AND R.ID_SITUACAO IN (" . implode(',', $situacoesInt) . ")";
    }

    if (!empty($dataInicial)) {
        $where .= " AND R.DT_REALIZADO >= :data_inicial";
        $params[':data_inicial'] = $dataInicial . ' 00:00:00';
    }

    if (!empty($dataFinal)) {
        $where .= " AND R.DT_REALIZADO <= :data_final";
        $params[':data_final'] = $dataFinal . ' 23:59:59';
    }

    // Busca geral
    if (!empty($buscaGeral)) {
        $buscaTermo = '%' . $buscaGeral . '%';
        $where .= " AND (
            L.CD_LOCALIDADE LIKE :busca_localidade
            OR PM.DS_NOME LIKE :busca_ponto
            OR ISNULL(T.DS_NOME, '') LIKE :busca_tecnico
            OR ISNULL(R.DS_REALIZADO, '') LIKE :busca_realizado
            OR ISNULL(R.DS_OBSERVACAO, '') LIKE :busca_obs
            OR CAST(R.CD_OCORRENCIA AS VARCHAR(20)) LIKE :busca_ocorrencia
            OR CONCAT(RIGHT('000' + CAST(P.CD_CODIGO AS VARCHAR(10)), 3), '-', CAST(P.CD_ANO AS VARCHAR(10)), '/', CAST(P.ID_TIPO_PROGRAMACAO AS VARCHAR(10))) LIKE :busca_codigo_prog
        )";
        $params[':busca_localidade'] = $buscaTermo;
        $params[':busca_ponto'] = $buscaTermo;
        $params[':busca_tecnico'] = $buscaTermo;
        $params[':busca_realizado'] = $buscaTermo;
        $params[':busca_obs'] = $buscaTermo;
        $params[':busca_ocorrencia'] = $buscaTermo;
        $params[':busca_codigo_prog'] = $buscaTermo;
    }

    // Query de contagem
    $sqlCount = "SELECT COUNT(*) AS TOTAL
                 FROM SIMP.dbo.REGISTRO_MANUTENCAO R
                 INNER JOIN SIMP.dbo.PROGRAMACAO_MANUTENCAO P ON R.CD_PROGRAMACAO = P.CD_CHAVE
                 INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON P.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
                 INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                 LEFT JOIN SIMP.dbo.USUARIO UT ON R.CD_TECNICO = UT.CD_USUARIO
                 LEFT JOIN SIMP.dbo.TECNICO T ON R.CD_TECNICO = T.CD_TECNICO
                 $where";
    
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['TOTAL'];

    // Query principal
    $sql = "SELECT 
                R.CD_CHAVE,
                R.CD_PROGRAMACAO,
                R.CD_OCORRENCIA,
                R.ID_SITUACAO,
                R.CD_TECNICO,
                R.DT_REALIZADO,
                R.DS_REALIZADO,
                R.ID_CLASSIFICACAO_MANUTENCAO,
                R.ID_TIPO_CALIBRACAO,
                R.DS_OBSERVACAO,
                P.CD_CODIGO AS CD_CODIGO_PROG,
                P.CD_ANO AS CD_ANO_PROG,
                P.CD_PONTO_MEDICAO,
                P.ID_TIPO_PROGRAMACAO,
                PM.DS_NOME AS DS_PONTO_MEDICAO,
                PM.ID_TIPO_MEDIDOR,
                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                L.DS_NOME AS DS_LOCALIDADE,
                L.CD_UNIDADE,
                COALESCE(UT.DS_NOME, T.DS_NOME) AS DS_TECNICO
            FROM SIMP.dbo.REGISTRO_MANUTENCAO R
            INNER JOIN SIMP.dbo.PROGRAMACAO_MANUTENCAO P ON R.CD_PROGRAMACAO = P.CD_CHAVE
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON P.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            LEFT JOIN SIMP.dbo.USUARIO UT ON R.CD_TECNICO = UT.CD_USUARIO
            LEFT JOIN SIMP.dbo.TECNICO T ON R.CD_TECNICO = T.CD_TECNICO
            $where
            ORDER BY $ordemCampo $ordemDirecao
            OFFSET $offset ROWS FETCH NEXT $itensPorPagina ROWS ONLY";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $registros,
        'total' => (int)$total,
        'pagina' => $pagina,
        'itens_por_pagina' => $itensPorPagina,
        'total_paginas' => ceil($total / $itensPorPagina)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar registros: ' . $e->getMessage(),
        'data' => [],
        'total' => 0
    ]);
}