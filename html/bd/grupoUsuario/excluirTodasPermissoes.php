<?php
// bd/grupoUsuario/excluirTodasPermissoes.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $cdGrupo = isset($_POST['cd_grupo']) ? (int)$_POST['cd_grupo'] : 0;
    
    if ($cdGrupo <= 0) {
        echo json_encode(['success' => false, 'message' => 'Grupo não informado']);
        exit;
    }
    
    // Contar quantas permissões existem
    $sqlCount = "SELECT COUNT(*) AS TOTAL FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE WHERE CD_GRUPO_USUARIO = ?";
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute([$cdGrupo]);
    $total = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['TOTAL'];
    
    if ($total === 0) {
        echo json_encode(['success' => true, 'message' => 'Não há permissões para excluir']);
        exit;
    }
    
    // Excluir todas
    $sqlDelete = "DELETE FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE WHERE CD_GRUPO_USUARIO = ?";
    $stmtDelete = $pdoSIMP->prepare($sqlDelete);
    $stmtDelete->execute([$cdGrupo]);
    
    echo json_encode([
        'success' => true, 
        'message' => $total . ' permissão(ões) excluída(s) com sucesso!'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir permissões: ' . $e->getMessage()
    ]);
}