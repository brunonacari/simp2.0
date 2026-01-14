<?php
// bd/grupoUsuario/excluirGrupoUsuario.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID não informado']);
        exit;
    }
    
    // Verificar se existem usuários vinculados
    $sqlCheckUsuarios = "SELECT COUNT(*) as total FROM SIMP.dbo.USUARIO WHERE CD_GRUPO_USUARIO = :id";
    $stmtCheck = $pdoSIMP->prepare($sqlCheckUsuarios);
    $stmtCheck->execute([':id' => $id]);
    $totalUsuarios = $stmtCheck->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($totalUsuarios > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Não é possível excluir. Existem {$totalUsuarios} usuário(s) vinculado(s) a este grupo."
        ]);
        exit;
    }
    
    // Excluir permissões vinculadas
    $sqlDeletePermissoes = "DELETE FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE WHERE CD_GRUPO_USUARIO = :id";
    $stmtPerm = $pdoSIMP->prepare($sqlDeletePermissoes);
    $stmtPerm->execute([':id' => $id]);
    
    // Excluir grupo
    $sql = "DELETE FROM SIMP.dbo.GRUPO_USUARIO WHERE CD_GRUPO_USUARIO = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Grupo de usuário excluído com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir grupo de usuário: ' . $e->getMessage()
    ]);
}
