<?php
// bd/grupoUsuario/salvarGrupoUsuario.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    
    // Validações
    if (empty($nome)) {
        echo json_encode(['success' => false, 'message' => 'O nome é obrigatório']);
        exit;
    }
    
    // Verificar duplicidade de nome
    if ($id) {
        $sqlCheck = "SELECT CD_GRUPO_USUARIO FROM SIMP.dbo.GRUPO_USUARIO WHERE DS_NOME = ? AND CD_GRUPO_USUARIO != ?";
        $stmtCheck = $pdoSIMP->prepare($sqlCheck);
        $stmtCheck->execute([$nome, $id]);
    } else {
        $sqlCheck = "SELECT CD_GRUPO_USUARIO FROM SIMP.dbo.GRUPO_USUARIO WHERE DS_NOME = ?";
        $stmtCheck = $pdoSIMP->prepare($sqlCheck);
        $stmtCheck->execute([$nome]);
    }
    
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe um grupo com este nome']);
        exit;
    }
    
    if ($id) {
        // UPDATE
        $sql = "UPDATE SIMP.dbo.GRUPO_USUARIO SET DS_NOME = ? WHERE CD_GRUPO_USUARIO = ?";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([$nome, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Grupo de usuário atualizado com sucesso!']);
    } else {
        // INSERT - coluna CD_GRUPO_USUARIO é IDENTITY
        $sql = "INSERT INTO SIMP.dbo.GRUPO_USUARIO (DS_NOME) VALUES (?)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([$nome]);
        
        // Buscar o ID inserido
        $sqlId = "SELECT SCOPE_IDENTITY() AS ID";
        $stmtId = $pdoSIMP->query($sqlId);
        $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];
        
        echo json_encode(['success' => true, 'message' => 'Grupo de usuário cadastrado com sucesso!', 'id' => $novoId]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar grupo de usuário: ' . $e->getMessage()
    ]);
}