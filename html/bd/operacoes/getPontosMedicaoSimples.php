<?php
/**
 * SIMP - Buscar Pontos de Medição (Simplificado)
 * Busca direta na tabela PONTO_MEDICAO por CD_PONTO_MEDICAO e DS_NOME
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

    $sql = "SELECT TOP 50
                PM.CD_PONTO_MEDICAO,
                PM.DS_NOME,
                PM.ID_TIPO_MEDIDOR,
                L.CD_LOCALIDADE,
                L.CD_UNIDADE
            FROM SIMP.dbo.PONTO_MEDICAO PM
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            WHERE PM.DT_DESATIVACAO IS NULL";
    
    $params = [];
    
    // Se tem busca, filtrar por código ou nome
    if (!empty($busca)) {
        // Remove zeros à esquerda para comparar com o código numérico
        $buscaSemZeros = ltrim($busca, '0');
        
        $sql .= " AND (
            PM.DS_NOME LIKE :busca 
            OR CAST(PM.CD_PONTO_MEDICAO AS VARCHAR) LIKE :busca2
            OR RIGHT('000000' + CAST(PM.CD_PONTO_MEDICAO AS VARCHAR(10)), 6) LIKE :busca3
            OR CAST(PM.CD_PONTO_MEDICAO AS VARCHAR) = :buscaSemZeros
        )";
        $params[':busca'] = '%' . $busca . '%';
        $params[':busca2'] = '%' . $busca . '%';
        $params[':busca3'] = '%' . $busca . '%';
        $params[':buscaSemZeros'] = $buscaSemZeros !== '' ? $buscaSemZeros : $busca;
    }
    
    $sql .= " ORDER BY PM.DS_NOME";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $pontos
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ]);
}