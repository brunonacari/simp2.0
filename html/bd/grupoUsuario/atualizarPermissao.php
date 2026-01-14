<?php
// bd/grupoUsuario/atualizarPermissao.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $cdChave = isset($_POST['cd_chave']) ? (int)$_POST['cd_chave'] : 0;
    $tipoAcesso = isset($_POST['tipo_acesso']) ? (int)$_POST['tipo_acesso'] : 0;
    
    if ($cdChave <= 0) {
        echo json_encode(['success' => false, 'message' => 'Registro não informado']);
        exit;
    }
    
    if ($tipoAcesso < 1 || $tipoAcesso > 2) {
        echo json_encode(['success' => false, 'message' => 'Tipo de acesso inválido']);
        exit;
    }
    
    $sql = "UPDATE SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE 
            SET ID_TIPO_ACESSO = ? 
            WHERE CD_CHAVE = ?";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([$tipoAcesso, $cdChave]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Permissão atualizada com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar permissão: ' . $e->getMessage()
    ]);
}
