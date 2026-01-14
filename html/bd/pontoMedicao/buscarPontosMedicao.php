<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint centralizado para Autocomplete de Pontos de Medição
 * 
 * Este endpoint é usado por várias telas do sistema para buscar pontos de medição
 * de forma padronizada e com código formatado.
 * 
 * Parâmetros aceitos (GET):
 * - busca: texto para filtrar (nome, código, localidade, código formatado)
 * - cd_unidade: filtrar por unidade (opcional)
 * - cd_localidade: filtrar por localidade - usa CD_CHAVE (opcional)
 * - limite: quantidade máxima de registros (padrão: 100, máximo: 500)
 * 
 * Retorno:
 * - CD_PONTO_MEDICAO: ID do ponto
 * - DS_NOME: Nome do ponto
 * - ID_TIPO_MEDIDOR: Tipo do medidor (1=M, 2=E, 4=P, 6=R, 8=H)
 * - CD_LOCALIDADE_CHAVE: Chave da localidade (para relacionamentos)
 * - CD_LOCALIDADE: Código da localidade (para exibição)
 * - DS_LOCALIDADE: Nome da localidade
 * - CD_UNIDADE: ID da unidade
 * - CD_UNIDADE_CODIGO: Código da unidade
 * - DS_UNIDADE: Nome da unidade
 * 
 * Código formatado do ponto: LOCALIDADE-CD_PONTO-LETRA-CD_UNIDADE
 * Exemplo: 5200-000888-E-4
 * 
 * Usado em:
 * - registroManutencao.php (filtro)
 * - registroManutencaoForm.php (formulário)
 * - programacaoManutencao.php (filtro)
 * - programacaoManutencaoForm.php (formulário)
 * - E outras telas que necessitem selecionar ponto de medição
 */

header('Content-Type: application/json');
include_once '../conexao.php';

try {
    // Parâmetros
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $cdUnidade = isset($_GET['cd_unidade']) && $_GET['cd_unidade'] !== '' ? (int)$_GET['cd_unidade'] : 0;
    $cdLocalidade = isset($_GET['cd_localidade']) && $_GET['cd_localidade'] !== '' ? (int)$_GET['cd_localidade'] : 0;
    $limite = isset($_GET['limite']) ? min(500, max(1, (int)$_GET['limite'])) : 100;

    $where = "WHERE 1=1";
    $params = [];

    // Filtro por unidade (opcional)
    if ($cdUnidade > 0) {
        $where .= " AND L.CD_UNIDADE = :cd_unidade";
        $params[':cd_unidade'] = $cdUnidade;
    }

    // Filtro por localidade (opcional) - usa CD_CHAVE da tabela LOCALIDADE
    if ($cdLocalidade > 0) {
        $where .= " AND PM.CD_LOCALIDADE = :cd_localidade";
        $params[':cd_localidade'] = $cdLocalidade;
    }

    // Busca por texto (código do ponto, nome, localidade, código formatado)
    if (!empty($busca)) {
        $where .= " AND (
            PM.DS_NOME LIKE :busca_nome
            OR L.CD_LOCALIDADE LIKE :busca_localidade
            OR L.DS_NOME LIKE :busca_localidade_nome
            OR CAST(PM.CD_PONTO_MEDICAO AS VARCHAR(20)) LIKE :busca_codigo
            OR CONCAT(
                L.CD_LOCALIDADE, '-', 
                RIGHT('000000' + CAST(PM.CD_PONTO_MEDICAO AS VARCHAR(10)), 6)
            ) LIKE :busca_codigo_formatado
        )";
        $buscaTermo = '%' . $busca . '%';
        $params[':busca_nome'] = $buscaTermo;
        $params[':busca_localidade'] = $buscaTermo;
        $params[':busca_localidade_nome'] = $buscaTermo;
        $params[':busca_codigo'] = $buscaTermo;
        $params[':busca_codigo_formatado'] = $buscaTermo;
    }

    $sql = "SELECT TOP $limite
                PM.CD_PONTO_MEDICAO,
                PM.DS_NOME,
                PM.ID_TIPO_MEDIDOR,
                PM.CD_LOCALIDADE AS CD_LOCALIDADE_CHAVE,
                L.CD_LOCALIDADE,
                L.DS_NOME AS DS_LOCALIDADE,
                L.CD_UNIDADE,
                U.CD_CODIGO AS CD_UNIDADE_CODIGO,
                U.DS_NOME AS DS_UNIDADE
            FROM SIMP.dbo.PONTO_MEDICAO PM
            INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            $where
            ORDER BY L.CD_LOCALIDADE, PM.DS_NOME";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $pontos,
        'total' => count($pontos)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar pontos de medição: ' . $e->getMessage(),
        'data' => []
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro geral: ' . $e->getMessage(),
        'data' => []
    ]);
}