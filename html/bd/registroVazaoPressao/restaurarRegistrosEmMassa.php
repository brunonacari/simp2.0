<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Restauração em Massa
 * 
 * Lógica: ID_SITUACAO = 2 → transformar em ID_SITUACAO = 1
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

try {
    require_once '../verificarAuth.php';
    verificarPermissaoAjax('REGISTRO DE VAZÃO', ACESSO_ESCRITA);
    
    include_once '../conexao.php';
    
    // Ler dados - tentar JSON primeiro, depois POST
    $chaves = [];
    
    $input = file_get_contents('php://input');
    
    if (!empty($input)) {
        $data = json_decode($input, true);
        if ($data && isset($data['chaves']) && is_array($data['chaves'])) {
            $chaves = $data['chaves'];
        }
    }
    
    // Fallback para POST tradicional
    if (empty($chaves) && isset($_POST['chaves'])) {
        if (is_array($_POST['chaves'])) {
            $chaves = $_POST['chaves'];
        } else {
            $chaves = json_decode($_POST['chaves'], true);
        }
    }
    
    if (empty($chaves) || !is_array($chaves)) {
        throw new Exception('Nenhum registro selecionado para restauração');
    }
    
    // Limitar quantidade por segurança (máx 10.000 registros por vez)
    if (count($chaves) > 10000) {
        throw new Exception('Máximo de 10.000 registros por operação');
    }
    
    // Validar que todas as chaves são inteiros
    $chavesValidas = [];
    foreach ($chaves as $chave) {
        if (is_numeric($chave) && $chave > 0) {
            $chavesValidas[] = (int)$chave;
        }
    }
    
    if (empty($chavesValidas)) {
        throw new Exception('Nenhuma chave válida encontrada');
    }
    
    $cdUsuario = $_SESSION['cd_usuario'] ?? null;
    
    // Iniciar transação
    $pdoSIMP->beginTransaction();
    
    try {
        $restaurados = 0;
        
        // Processar em lotes de 1000
        $lotes = array_chunk($chavesValidas, 1000);
        
        foreach ($lotes as $lote) {
            $placeholders = implode(',', array_fill(0, count($lote), '?'));
            
            $sqlUpdate = "UPDATE SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
                          SET ID_SITUACAO = 1,
                              DT_ULTIMA_ATUALIZACAO = GETDATE(),
                              CD_USUARIO_ULTIMA_ATUALIZACAO = ?
                          WHERE CD_CHAVE IN ($placeholders) 
                          AND ID_SITUACAO = 2";
            
            $stmtUpdate = $pdoSIMP->prepare($sqlUpdate);
            $params = array_merge([$cdUsuario], $lote);
            $stmtUpdate->execute($params);
            $restaurados += $stmtUpdate->rowCount();
        }
        
        // Commit da transação
        $pdoSIMP->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Operação concluída com sucesso',
            'restaurados' => $restaurados,
            'solicitados' => count($chavesValidas)
        ]);
        
    } catch (PDOException $e) {
        $pdoSIMP->rollBack();
        throw $e;
    } 
    
} catch (PDOException $e) {
    if (isset($pdoSIMP) && $pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao processar restauração: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($pdoSIMP) && $pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}