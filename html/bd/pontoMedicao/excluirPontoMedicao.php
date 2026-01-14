<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Excluir Ponto de Medição
 */

header('Content-Type: application/json; charset=utf-8');

session_start();
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
    $sqlCheck = "SELECT CD_PONTO_MEDICAO FROM SIMP.dbo.PONTO_MEDICAO WHERE CD_PONTO_MEDICAO = :id";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([':id' => $cdPontoMedicao]);
    
    if (!$stmtCheck->fetch()) {
        throw new Exception('Ponto de medição não encontrado');
    }

    // Verifica se existem leituras vinculadas
    $sqlLeituras = "SELECT COUNT(*) as total FROM SIMP.dbo.LEITURA WHERE CD_PONTO_MEDICAO = :id";
    $stmtLeituras = $pdoSIMP->prepare($sqlLeituras);
    $stmtLeituras->execute([':id' => $cdPontoMedicao]);
    $totalLeituras = $stmtLeituras->fetch(PDO::FETCH_ASSOC)['total'];

    if ($totalLeituras > 0) {
        throw new Exception("Não é possível excluir: existem {$totalLeituras} leitura(s) vinculada(s) a este ponto de medição");
    }

    // Exclui o registro
    $sql = "UPDATE SIMP.dbo.PONTO_MEDICAO SET DT_DESATIVACAO = GETDATE() WHERE CD_PONTO_MEDICAO = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $cdPontoMedicao]);

    echo json_encode([
        'success' => true,
        'message' => 'Ponto de medição excluído com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}