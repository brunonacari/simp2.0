<?php
// bd/registroManutencao/excluirRegistro.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $cdChave = isset($_POST['cd_chave']) ? (int)$_POST['cd_chave'] : 0;

    if ($cdChave <= 0) {
        throw new Exception('Registro não informado.');
    }

    // Verifica se o registro existe
    $sqlVerifica = "SELECT CD_CHAVE FROM SIMP.dbo.REGISTRO_MANUTENCAO WHERE CD_CHAVE = :cd_chave";
    $stmtVerifica = $pdoSIMP->prepare($sqlVerifica);
    $stmtVerifica->execute([':cd_chave' => $cdChave]);

    if (!$stmtVerifica->fetch()) {
        throw new Exception('Registro não encontrado.');
    }

    // Exclui o registro
    $sql = "DELETE FROM SIMP.dbo.REGISTRO_MANUTENCAO WHERE CD_CHAVE = :cd_chave";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cd_chave' => $cdChave]);

    echo json_encode([
        'success' => true,
        'message' => 'Registro excluído com sucesso!'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir registro: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
