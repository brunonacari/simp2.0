<?php
// bd/funcionalidades/excluirFuncionalidade.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID não informado']);
        exit;
    }
    
    // Verificar se existem permissões vinculadas
    $sqlCheck = "SELECT COUNT(*) as total FROM SIMP.dbo.PERMISSAO WHERE CD_FUNCIONALIDADE = :id";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([':id' => $id]);
    $totalVinculados = $stmtCheck->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($totalVinculados > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Não é possível excluir. Existem {$totalVinculados} permissão(ões) vinculada(s) a esta funcionalidade."
        ]);
        exit;
    }
    
    // Excluir
    $sql = "DELETE FROM SIMP.dbo.FUNCIONALIDADE WHERE CD_FUNCIONALIDADE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Funcionalidade excluída com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir funcionalidade: ' . $e->getMessage()
    ]);
}
