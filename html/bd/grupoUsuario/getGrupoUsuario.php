<?php
// bd/grupoUsuario/getGrupoUsuario.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID não informado']);
        exit;
    }
    
    $sql = "SELECT CD_GRUPO_USUARIO, DS_NOME
            FROM SIMP.dbo.GRUPO_USUARIO 
            WHERE CD_GRUPO_USUARIO = :id";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dados) {
        echo json_encode(['success' => true, 'data' => $dados]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar grupo de usuário: ' . $e->getMessage()
    ]);
}
