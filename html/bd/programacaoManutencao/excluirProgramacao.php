<?php
// bd/programacaoManutencao/excluirProgramacao.php
header('Content-Type: application/json');
include_once '../conexao.php';

try {
    $cdChave = isset($_POST['cd_chave']) ? (int)$_POST['cd_chave'] : 0;

    if ($cdChave <= 0) {
        throw new Exception('Programação não informada.');
    }

    // Verifica se a programação existe
    $sqlVerifica = "SELECT CD_CODIGO, CD_ANO FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO WHERE CD_CHAVE = :cd_chave";
    $stmtVerifica = $pdoSIMP->prepare($sqlVerifica);
    $stmtVerifica->execute([':cd_chave' => $cdChave]);
    $programacao = $stmtVerifica->fetch(PDO::FETCH_ASSOC);

    if (!$programacao) {
        throw new Exception('Programação não encontrada.');
    }

    $codigoFormatado = str_pad($programacao['CD_CODIGO'], 3, '0', STR_PAD_LEFT) . '/' . $programacao['CD_ANO'];

    // Exclui a programação
    $sql = "DELETE FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO WHERE CD_CHAVE = :cd_chave";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cd_chave' => $cdChave]);

    echo json_encode([
        'success' => true,
        'message' => "Programação $codigoFormatado excluída com sucesso!"
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir programação: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
