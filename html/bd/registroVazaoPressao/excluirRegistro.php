<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Excluir Registro Individual (Exclusão Lógica)
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

try {
    require_once '../verificarAuth.php';
    verificarPermissaoAjax('REGISTRO DE VAZÃO', ACESSO_ESCRITA);

    include_once '../conexao.php';

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    // Exclusão lógica: setar ID_SITUACAO = 2 (Descartado)
    $sql = "UPDATE SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
            SET ID_SITUACAO = 2, 
                DT_ULTIMA_ATUALIZACAO = GETDATE(),
                CD_USUARIO_ULTIMA_ATUALIZACAO = :cd_usuario
            WHERE CD_CHAVE = :id AND ID_SITUACAO = 1";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':cd_usuario' => $_SESSION['cd_usuario'] ?? null
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Registro descartado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado ou já descartado']);
    }
} catch (PDOException $e) {
    // Verificar se é erro da trigger
    if (strpos($e->getMessage(), '9999998') !== false) {
        echo json_encode([
            'success' => false, 
            'message' => 'Este registro não pode ser descartado porque já foi exportado para SIGAO.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao descartar: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao descartar: ' . $e->getMessage()]);
}