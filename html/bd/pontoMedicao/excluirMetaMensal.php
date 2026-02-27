<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Excluir Meta Mensal do Ponto de Medição
 */

header('Content-Type: application/json; charset=utf-8');

session_start();
include_once '../conexao.php';
@include_once '../logHelper.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    $cdChave = isset($_POST['cd_chave']) && $_POST['cd_chave'] !== '' ? (int)$_POST['cd_chave'] : null;

    if (empty($cdChave)) {
        throw new Exception('Meta não informada');
    }

    // Busca dados antes de excluir para log
    $sqlCheck = "SELECT CD_CHAVE, CD_PONTO_MEDICAO, MES_META, ANO_META, ID_TIPO_MEDIDOR FROM SIMP.dbo.META_MENSAL_PONTO_MEDICAO WHERE CD_CHAVE = :cd_chave";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([':cd_chave' => $cdChave]);
    $dadosMeta = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$dadosMeta) {
        throw new Exception('Meta não encontrada');
    }

    // Exclui
    $sql = "DELETE FROM SIMP.dbo.META_MENSAL_PONTO_MEDICAO WHERE CD_CHAVE = :cd_chave";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cd_chave' => $cdChave]);

    // Log de exclusão
    try {
        if (function_exists('registrarLogDelete')) {
            registrarLogDelete('Ponto de Medição', 'Meta Mensal', $cdChave,
                "Ponto {$dadosMeta['CD_PONTO_MEDICAO']} - {$dadosMeta['MES_META']}/{$dadosMeta['ANO_META']}", $dadosMeta);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => true,
        'message' => 'Meta excluída com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}