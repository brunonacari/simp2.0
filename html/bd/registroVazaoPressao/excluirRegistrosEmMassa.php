<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Descartar Registros em Massa (Exclusão Lógica)
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
        throw new Exception('Nenhum registro selecionado para descarte');
    }
    
    // Limitar quantidade por segurança (máx 10000 registros por vez)
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
    
    // Exclusão lógica em lotes de 1000 para evitar problemas de performance
    $descartados = 0;
    $lotes = array_chunk($chavesValidas, 1000);
    
    foreach ($lotes as $lote) {
        $placeholdersLote = implode(',', array_fill(0, count($lote), '?'));
        
        // UPDATE para setar ID_SITUACAO = 2 (Descartado)
        $sqlUpdate = "UPDATE SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
                      SET ID_SITUACAO = 2,
                          DT_ULTIMA_ATUALIZACAO = GETDATE(),
                          CD_USUARIO_ULTIMA_ATUALIZACAO = ?
                      WHERE CD_CHAVE IN ($placeholdersLote) 
                      AND ID_SITUACAO = 1";
        
        $stmtUpdate = $pdoSIMP->prepare($sqlUpdate);
        
        // Primeiro parâmetro é o cd_usuario, depois as chaves
        $params = array_merge([$cdUsuario], $lote);
        $stmtUpdate->execute($params);
        $descartados += $stmtUpdate->rowCount();
    }
    
    // Commit
    $pdoSIMP->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Registros descartados com sucesso',
        'excluidos' => $descartados,
        'solicitados' => count($chavesValidas)
    ]);
    
} catch (PDOException $e) {
    if (isset($pdoSIMP) && $pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
    }
    
    // Verificar se é erro da trigger
    if (strpos($e->getMessage(), '9999998') !== false) {
        echo json_encode([
            'success' => false, 
            'message' => 'Alguns registros não podem ser descartados porque já foram exportados para SIGAO.'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Erro ao descartar registros: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    if (isset($pdoSIMP) && $pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}