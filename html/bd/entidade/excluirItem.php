<?php
/**
 * SIMP - Excluir Item de Entidade
 */

header('Content-Type: application/json; charset=utf-8');

// Verificar permissão de edição
require_once __DIR__ . '/../../includes/auth.php';
if (!podeEditarTela('Cadastro de Entidade')) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta operação']);
    exit;
}

try {
    include_once '../conexao.php';

    $cd = isset($_POST['cd']) ? (int)$_POST['cd'] : 0;

    if ($cd <= 0) {
        throw new Exception('ID do item não informado');
    }

    $sql = "DELETE FROM SIMP.dbo.ENTIDADE_VALOR_ITEM WHERE CD_CHAVE = ?";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([$cd]);

    echo json_encode([
        'success' => true,
        'message' => 'Vínculo removido com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
