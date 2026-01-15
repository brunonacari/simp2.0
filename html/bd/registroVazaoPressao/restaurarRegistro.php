<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Restaurar Registro Individual
 * 
 * Lógica: Se ID_SITUACAO = 2, transforma em ID_SITUACAO = 1
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

    // Verificar o status atual do registro
    $sqlVerificar = "SELECT ID_SITUACAO FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO WHERE CD_CHAVE = :id";
    $stmtVerificar = $pdoSIMP->prepare($sqlVerificar);
    $stmtVerificar->execute([':id' => $id]);
    $resultado = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

    if (!$resultado) {
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
        exit;
    }

    $idSituacao = $resultado['ID_SITUACAO'];

    if ($idSituacao != 2) {
        echo json_encode(['success' => false, 'message' => 'Apenas registros descartados podem ser restaurados']);
        exit;
    }

    // Restaurar: ID_SITUACAO = 2 → 1
    $sqlUpdate = "UPDATE SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
                  SET ID_SITUACAO = 1, 
                      DT_ULTIMA_ATUALIZACAO = GETDATE(),
                      CD_USUARIO_ULTIMA_ATUALIZACAO = :cd_usuario
                  WHERE CD_CHAVE = :id AND ID_SITUACAO = 2";
    
    $stmt = $pdoSIMP->prepare($sqlUpdate);
    $stmt->execute([
        ':id' => $id,
        ':cd_usuario' => $_SESSION['cd_usuario'] ?? null
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Registro restaurado com sucesso'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao restaurar registro']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar restauração: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar restauração: ' . $e->getMessage()]);
}