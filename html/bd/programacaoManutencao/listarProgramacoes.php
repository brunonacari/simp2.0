<?php
// bd/programacaoManutencao/listarProgramacoes.php
header('Content-Type: application/json');
include_once '../conexao.php';

try {
    // Parâmetros de paginação
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $itensPorPagina = isset($_GET['itens_por_pagina']) ? (int)$_GET['itens_por_pagina'] : 15;
    $offset = ($pagina - 1) * $itensPorPagina;

    // Parâmetros de ordenação
    $ordemCampo = isset($_GET['ordem_campo']) ? $_GET['ordem_campo'] : 'DT_PROGRAMACAO';
    $ordemDirecao = isset($_GET['ordem_direcao']) && strtoupper($_GET['ordem_direcao']) === 'ASC' ? 'ASC' : 'DESC';

    // Validação de campos de ordenação permitidos
    $camposPermitidos = ['CD_CODIGO', 'CD_LOCALIDADE_CODIGO', 'DS_PONTO_MEDICAO', 'DS_RESPONSAVEL', 'DT_PROGRAMACAO', 'ID_TIPO_PROGRAMACAO', 'ID_SITUACAO'];
    if (!in_array($ordemCampo, $camposPermitidos)) {
        $ordemCampo = 'DT_PROGRAMACAO';
    }

    // Ajustar campo de ordenação para usar alias correto
    $ordemCampoSQL = $ordemCampo;
    if ($ordemCampo === 'CD_LOCALIDADE_CODIGO') {
        $ordemCampoSQL = 'L.CD_LOCALIDADE';
    }

    // Filtros
    $filtros = [];
    $params = [];

    // Filtro por Unidade
    if (!empty($_GET['cd_unidade'])) {
        $filtros[] = "L.CD_UNIDADE = :cd_unidade";
        $params[':cd_unidade'] = (int)$_GET['cd_unidade'];
    }

    // Filtro por Localidade
    if (!empty($_GET['cd_localidade'])) {
        $filtros[] = "PM.CD_LOCALIDADE = :cd_localidade";
        $params[':cd_localidade'] = (int)$_GET['cd_localidade'];
    }

    // Filtro por Ponto de Medição
    if (!empty($_GET['cd_ponto_medicao'])) {
        $filtros[] = "P.CD_PONTO_MEDICAO = :cd_ponto_medicao";
        $params[':cd_ponto_medicao'] = (int)$_GET['cd_ponto_medicao'];
    }

    // Busca Geral - pesquisa em múltiplos campos
    if (!empty($_GET['busca_geral'])) {
        $termo = '%' . trim($_GET['busca_geral']) . '%';
        $filtros[] = "(
            L.CD_LOCALIDADE LIKE :busca_cod_local
            OR CAST(PM.CD_PONTO_MEDICAO AS VARCHAR(20)) LIKE :busca_cd_ponto
            OR PM.DS_NOME LIKE :busca_ponto_nome
            OR ISNULL(UR.DS_NOME, '') LIKE :busca_responsavel
            OR ISNULL(P.DS_SOLICITANTE, '') LIKE :busca_solicitante
            OR CONCAT(RIGHT('000' + CAST(P.CD_CODIGO AS VARCHAR(10)), 3), '-', CAST(P.CD_ANO AS VARCHAR(10)), '/', CAST(P.ID_TIPO_PROGRAMACAO AS VARCHAR(10))) LIKE :busca_codigo_ano
            OR ISNULL(P.DS_SOLICITACAO, '') LIKE :busca_descricao
        )";
        $params[':busca_cod_local'] = $termo;
        $params[':busca_cd_ponto'] = $termo;
        $params[':busca_ponto_nome'] = $termo;
        $params[':busca_responsavel'] = $termo;
        $params[':busca_solicitante'] = $termo;
        $params[':busca_codigo_ano'] = $termo;
        $params[':busca_descricao'] = $termo;
    }

    // Filtro por Tipos de Programação (múltiplos via checkbox)
    if (!empty($_GET['tipos'])) {
        $tipos = array_filter(explode(',', $_GET['tipos']));
        if (!empty($tipos)) {
            $tiposPlaceholders = [];
            foreach ($tipos as $index => $tipo) {
                $paramName = ':tipo_' . $index;
                $tiposPlaceholders[] = $paramName;
                $params[$paramName] = (int)$tipo;
            }
            $filtros[] = "P.ID_TIPO_PROGRAMACAO IN (" . implode(',', $tiposPlaceholders) . ")";
        }
    }

    // Filtro por Situações (múltiplas via checkbox)
    if (!empty($_GET['situacoes'])) {
        $situacoes = array_filter(explode(',', $_GET['situacoes']));
        if (!empty($situacoes)) {
            $situacoesPlaceholders = [];
            foreach ($situacoes as $index => $situacao) {
                $paramName = ':situacao_' . $index;
                $situacoesPlaceholders[] = $paramName;
                $params[$paramName] = (int)$situacao;
            }
            $filtros[] = "P.ID_SITUACAO IN (" . implode(',', $situacoesPlaceholders) . ")";
        }
    }

    // Filtro por Data Solicitação Inicial
    if (!empty($_GET['solicitacao_inicial'])) {
        $filtros[] = "P.DT_SOLICITACAO >= :solicitacao_inicial";
        $params[':solicitacao_inicial'] = $_GET['solicitacao_inicial'] . ' 00:00:00';
    }

    // Filtro por Data Solicitação Final
    if (!empty($_GET['solicitacao_final'])) {
        $filtros[] = "P.DT_SOLICITACAO <= :solicitacao_final";
        $params[':solicitacao_final'] = $_GET['solicitacao_final'] . ' 23:59:59';
    }

    // Filtro por Data Programação Inicial
    if (!empty($_GET['programacao_inicial'])) {
        $filtros[] = "P.DT_PROGRAMACAO >= :programacao_inicial";
        $params[':programacao_inicial'] = $_GET['programacao_inicial'] . ' 00:00:00';
    }

    // Filtro por Data Programação Final
    if (!empty($_GET['programacao_final'])) {
        $filtros[] = "P.DT_PROGRAMACAO <= :programacao_final";
        $params[':programacao_final'] = $_GET['programacao_final'] . ' 23:59:59';
    }

    // Monta cláusula WHERE
    $whereClause = count($filtros) > 0 ? 'WHERE ' . implode(' AND ', $filtros) : '';

    // Query de contagem
    $sqlCount = "SELECT COUNT(*) AS total
                 FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO P
                 INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON P.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
                 INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                 LEFT JOIN SIMP.dbo.USUARIO UR ON P.CD_USUARIO_RESPONSAVEL = UR.CD_USUARIO
                 $whereClause";

    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // Query de dados
    $sql = "SELECT 
                P.CD_CHAVE,
                P.CD_CODIGO,
                P.CD_ANO,
                P.CD_PONTO_MEDICAO,
                P.ID_TIPO_PROGRAMACAO,
                P.ID_SITUACAO,
                P.DT_PROGRAMACAO,
                P.DS_SOLICITANTE,
                P.DT_SOLICITACAO,
                P.DS_SOLICITACAO,
                PM.DS_NOME AS DS_PONTO_MEDICAO,
                PM.ID_TIPO_MEDIDOR,
                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                L.DS_NOME AS DS_LOCALIDADE,
                L.CD_UNIDADE,
                U.DS_NOME AS DS_UNIDADE,
                UR.DS_NOME AS DS_RESPONSAVEL
            FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO P
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON P.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            LEFT JOIN SIMP.dbo.USUARIO UR ON P.CD_USUARIO_RESPONSAVEL = UR.CD_USUARIO
            $whereClause
            ORDER BY $ordemCampoSQL $ordemDirecao
            OFFSET $offset ROWS FETCH NEXT $itensPorPagina ROWS ONLY";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $dados,
        'total' => (int)$total,
        'pagina' => $pagina,
        'itens_por_pagina' => $itensPorPagina,
        'total_paginas' => ceil($total / $itensPorPagina)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar programações: ' . $e->getMessage()
    ]);
}