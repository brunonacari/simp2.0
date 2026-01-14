<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Ativar Ponto de Medição
 */

header('Content-Type: application/json; charset=utf-8');

include_once '../conexao.php';

try {
    // Verifica se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Captura parâmetro
    $cdPontoMedicao = isset($_POST['cd_ponto_medicao']) && $_POST['cd_ponto_medicao'] !== '' ? (int)$_POST['cd_ponto_medicao'] : null;

    if (empty($cdPontoMedicao)) {
        throw new Exception('Código do ponto de medição não informado');
    }

    // Verifica se existe
    $sqlCheck = "SELECT CD_PONTO_MEDICAO, DT_DESATIVACAO FROM SIMP.dbo.PONTO_MEDICAO WHERE CD_PONTO_MEDICAO = :id";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([':id' => $cdPontoMedicao]);
    $ponto = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$ponto) {
        throw new Exception('Ponto de medição não encontrado');
    }

    // Verifica se já está ativo
    if (empty($ponto['DT_DESATIVACAO'])) {
        throw new Exception('Este ponto de medição já está ativo');
    }

    // Ativa o registro (remove data de desativação)
    $sql = "UPDATE SIMP.dbo.PONTO_MEDICAO SET DT_DESATIVACAO = NULL WHERE CD_PONTO_MEDICAO = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $cdPontoMedicao]);

    echo json_encode([
        'success' => true,
        'message' => 'Ponto de medição ativado com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}