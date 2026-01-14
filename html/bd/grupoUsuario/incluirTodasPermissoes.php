<?php
// bd/grupoUsuario/incluirTodasPermissoes.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $cdGrupo = isset($_POST['cd_grupo']) ? (int)$_POST['cd_grupo'] : 0;
    $tipoAcesso = isset($_POST['tipo_acesso']) ? (int)$_POST['tipo_acesso'] : 1;
    
    if ($cdGrupo <= 0) {
        echo json_encode(['success' => false, 'message' => 'Grupo não informado']);
        exit;
    }
    
    if ($tipoAcesso < 1 || $tipoAcesso > 2) {
        $tipoAcesso = 1;
    }
    
    // Buscar funcionalidades que ainda não estão vinculadas ao grupo
    $sqlDisponiveis = "SELECT CD_FUNCIONALIDADE 
                       FROM SIMP.dbo.FUNCIONALIDADE 
                       WHERE CD_FUNCIONALIDADE NOT IN (
                           SELECT CD_FUNCIONALIDADE 
                           FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE 
                           WHERE CD_GRUPO_USUARIO = ?
                       )";
    $stmtDisponiveis = $pdoSIMP->prepare($sqlDisponiveis);
    $stmtDisponiveis->execute([$cdGrupo]);
    $funcionalidades = $stmtDisponiveis->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($funcionalidades) === 0) {
        echo json_encode(['success' => true, 'message' => 'Todas as funcionalidades já estão vinculadas']);
        exit;
    }
    
    // Inserir todas
    $inseridos = 0;
    
    // Tentar sem CD_CHAVE primeiro (caso seja IDENTITY)
    try {
        $sqlInsert = "INSERT INTO SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE 
                      (CD_GRUPO_USUARIO, CD_FUNCIONALIDADE, ID_TIPO_ACESSO) 
                      VALUES (?, ?, ?)";
        $stmtInsert = $pdoSIMP->prepare($sqlInsert);
        
        foreach ($funcionalidades as $cdFunc) {
            $stmtInsert->execute([$cdGrupo, $cdFunc, $tipoAcesso]);
            $inseridos++;
        }
    } catch (PDOException $e) {
        // Se falhou, tentar com CD_CHAVE manual
        $sqlMax = "SELECT ISNULL(MAX(CD_CHAVE), 0) AS MAX_CHAVE FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE";
        $stmtMax = $pdoSIMP->query($sqlMax);
        $proximoCodigo = (int)$stmtMax->fetch(PDO::FETCH_ASSOC)['MAX_CHAVE'];
        
        $sqlInsert = "INSERT INTO SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE 
                      (CD_CHAVE, CD_GRUPO_USUARIO, CD_FUNCIONALIDADE, ID_TIPO_ACESSO) 
                      VALUES (?, ?, ?, ?)";
        $stmtInsert = $pdoSIMP->prepare($sqlInsert);
        
        $inseridos = 0;
        foreach ($funcionalidades as $cdFunc) {
            $proximoCodigo++;
            $stmtInsert->execute([$proximoCodigo, $cdGrupo, $cdFunc, $tipoAcesso]);
            $inseridos++;
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $inseridos . ' funcionalidade(s) adicionada(s) com sucesso!'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao incluir permissões: ' . $e->getMessage()
    ]);
}