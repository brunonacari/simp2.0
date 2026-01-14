<?php
// bd/grupoUsuario/excluirPermissao.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $cdChave = isset($_POST['cd_chave']) ? (int)$_POST['cd_chave'] : 0;
    
    if ($cdChave <= 0) {
        echo json_encode(['success' => false, 'message' => 'Registro não informado']);
        exit;
    }
    
    $sql = "DELETE FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE WHERE CD_CHAVE = ?";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([$cdChave]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Permissão removida com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir permissão: ' . $e->getMessage()
    ]);
}
