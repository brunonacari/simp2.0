<?php
// bd/registroManutencao/getProgramacoes.php
header('Content-Type: application/json');
include_once '../conexao.php';

try {
    $cdUnidade = isset($_GET['cd_unidade']) ? (int)$_GET['cd_unidade'] : 0;
    $cdLocalidade = isset($_GET['cd_localidade']) ? (int)$_GET['cd_localidade'] : 0;
    $cdPontoMedicao = isset($_GET['cd_ponto_medicao']) ? (int)$_GET['cd_ponto_medicao'] : 0;
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

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

    if (!empty($busca)) {
        $where .= " AND (
            CONCAT(RIGHT('000' + CAST(P.CD_CODIGO AS VARCHAR(10)), 3), '-', CAST(P.CD_ANO AS VARCHAR(10)), '/', CAST(P.ID_TIPO_PROGRAMACAO AS VARCHAR(10))) LIKE :busca
            OR PM.DS_NOME LIKE :busca_nome
            OR L.CD_LOCALIDADE LIKE :busca_loc
        )";
        $params[':busca'] = '%' . $busca . '%';
        $params[':busca_nome'] = '%' . $busca . '%';
        $params[':busca_loc'] = '%' . $busca . '%';
    }

    $sql = "SELECT TOP 100
                P.CD_CHAVE,
                P.CD_CODIGO,
                P.CD_ANO,
                P.ID_TIPO_PROGRAMACAO,
                P.ID_SITUACAO,
                P.CD_PONTO_MEDICAO,
                P.CD_USUARIO_RESPONSAVEL,
                P.DS_SOLICITANTE,
                P.DS_SOLICITACAO,
                P.DT_SOLICITACAO,
                P.DT_PROGRAMACAO,
                PM.DS_NOME AS DS_PONTO_MEDICAO,
                PM.ID_TIPO_MEDIDOR,
                L.CD_CHAVE AS CD_LOCALIDADE_CHAVE,
                L.CD_LOCALIDADE,
                L.DS_NOME AS DS_LOCALIDADE,
                L.CD_UNIDADE,
                U.DS_NOME AS DS_UNIDADE,
                UR.DS_NOME AS DS_RESPONSAVEL
            FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO P
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON P.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            LEFT JOIN SIMP.dbo.USUARIO UR ON P.CD_USUARIO_RESPONSAVEL = UR.CD_USUARIO
            $where
            ORDER BY P.CD_ANO DESC, P.CD_CODIGO DESC";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $programacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $programacoes
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar programações: ' . $e->getMessage(),
        'data' => []
    ]);
}