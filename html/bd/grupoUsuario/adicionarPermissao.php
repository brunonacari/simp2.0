<?php
// bd/grupoUsuario/adicionarPermissao.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $cdGrupo = isset($_POST['cd_grupo']) ? (int)$_POST['cd_grupo'] : 0;
    $cdFuncionalidade = isset($_POST['cd_funcionalidade']) ? (int)$_POST['cd_funcionalidade'] : 0;
    $tipoAcesso = isset($_POST['tipo_acesso']) ? (int)$_POST['tipo_acesso'] : 1; // Default: Somente leitura
    
    if ($cdGrupo <= 0) {
        echo json_encode(['success' => false, 'message' => 'Grupo não informado']);
        exit;
    }
    
    if ($cdFuncionalidade <= 0) {
        echo json_encode(['success' => false, 'message' => 'Funcionalidade não informada']);
        exit;
    }
    
    if ($tipoAcesso < 1 || $tipoAcesso > 2) {
        $tipoAcesso = 1;
    }
    
    // Verificar se já existe
    $sqlCheck = "SELECT CD_CHAVE FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE 
                 WHERE CD_GRUPO_USUARIO = ? AND CD_FUNCIONALIDADE = ?";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([$cdGrupo, $cdFuncionalidade]);
    
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Esta funcionalidade já está vinculada ao grupo']);
        exit;
    }
    
    // INSERT - tentar sem CD_CHAVE primeiro (caso seja IDENTITY)
    try {
        $sql = "INSERT INTO SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE 
                (CD_GRUPO_USUARIO, CD_FUNCIONALIDADE, ID_TIPO_ACESSO) 
                VALUES (?, ?, ?)";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([$cdGrupo, $cdFuncionalidade, $tipoAcesso]);
        
        echo json_encode(['success' => true, 'message' => 'Permissão adicionada com sucesso!']);
    } catch (PDOException $e) {
        // Se falhou, tentar com CD_CHAVE manual
        $sqlMax = "SELECT ISNULL(MAX(CD_CHAVE), 0) + 1 AS PROXIMO FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE";
        $stmtMax = $pdoSIMP->query($sqlMax);
        $proximoCodigo = (int)$stmtMax->fetch(PDO::FETCH_ASSOC)['PROXIMO'];
        
        $sql = "INSERT INTO SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE 
                (CD_CHAVE, CD_GRUPO_USUARIO, CD_FUNCIONALIDADE, ID_TIPO_ACESSO) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([$proximoCodigo, $cdGrupo, $cdFuncionalidade, $tipoAcesso]);
        
        echo json_encode(['success' => true, 'message' => 'Permissão adicionada com sucesso!']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao adicionar permissão: ' . $e->getMessage()
    ]);
}