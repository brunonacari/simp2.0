<?php
// bd/funcionalidades/salvarFuncionalidade.php
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
    $sqlCheck = "SELECT CD_FUNCIONALIDADE FROM SIMP.dbo.FUNCIONALIDADE WHERE DS_NOME = :nome";
    if ($id) {
        $sqlCheck .= " AND CD_FUNCIONALIDADE != :id";
    }
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->bindValue(':nome', $nome);
    if ($id) {
        $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
    }
    $stmtCheck->execute();
    
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe uma funcionalidade com este nome']);
        exit;
    }
    
    if ($id) {
        // UPDATE
        $sql = "UPDATE SIMP.dbo.FUNCIONALIDADE SET 
                    DS_NOME = :nome
                WHERE CD_FUNCIONALIDADE = :id";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->bindValue(':nome', $nome);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Funcionalidade atualizada com sucesso!']);
    } else {
        // Buscar próximo código
        $sqlMax = "SELECT ISNULL(MAX(CD_FUNCIONALIDADE), 0) + 1 AS PROXIMO FROM SIMP.dbo.FUNCIONALIDADE";
        $stmtMax = $pdoSIMP->query($sqlMax);
        $proximoCodigo = $stmtMax->fetch(PDO::FETCH_ASSOC)['PROXIMO'];
        
        // INSERT com código gerado
        $sql = "INSERT INTO SIMP.dbo.FUNCIONALIDADE (CD_FUNCIONALIDADE, DS_NOME) VALUES (:id, :nome)";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->bindValue(':id', $proximoCodigo, PDO::PARAM_INT);
        $stmt->bindValue(':nome', $nome);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Funcionalidade cadastrada com sucesso!']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar funcionalidade: ' . $e->getMessage()
    ]);
}