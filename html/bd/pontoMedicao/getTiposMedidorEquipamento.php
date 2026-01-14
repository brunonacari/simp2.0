<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Buscar Tipos de Medidor (para select)
 */

header('Content-Type: application/json; charset=utf-8');

include_once '../conexao.php';

try {
    $sql = "SELECT CD_CHAVE, DS_NOME, ID_TIPO_CALCULO 
            FROM SIMP.dbo.TIPO_MEDIDOR 
            ORDER BY DS_NOME";

    $stmt = $pdoSIMP->query($sql);
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $tipos
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar tipos: ' . $e->getMessage(),
        'data' => []
    ]);
}