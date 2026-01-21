<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Ativar Ponto de Medição
 * COM REGISTRO DE LOG
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
require_once '../logHelper.php';

verificarPermissaoAjax('CADASTRO DE PONTO', ACESSO_ESCRITA);

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

    // Buscar dados do ponto (para log e validações)
    $sqlBusca = "SELECT PM.*, L.CD_UNIDADE 
                 FROM SIMP.dbo.PONTO_MEDICAO PM
                 LEFT JOIN SIMP.dbo.LOCALIDADE L ON L.CD_CHAVE = PM.CD_LOCALIDADE
                 WHERE PM.CD_PONTO_MEDICAO = :id";
    $stmtBusca = $pdoSIMP->prepare($sqlBusca);
    $stmtBusca->execute([':id' => $cdPontoMedicao]);
    $dadosPonto = $stmtBusca->fetch(PDO::FETCH_ASSOC);
    
    if (!$dadosPonto) {
        throw new Exception('Ponto de medição não encontrado');
    }

    $identificador = $dadosPonto['DS_NOME'] ?? "ID: $cdPontoMedicao";
    $cdUnidadeLog = $dadosPonto['CD_UNIDADE'] ?? null;

    // Verifica se já está ativo
    if (empty($dadosPonto['DT_DESATIVACAO'])) {
        throw new Exception('Este ponto de medição já está ativo');
    }

    // Ativa o registro (remove data de desativação)
    $sql = "UPDATE SIMP.dbo.PONTO_MEDICAO SET DT_DESATIVACAO = NULL WHERE CD_PONTO_MEDICAO = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $cdPontoMedicao]);

    // Registrar log de ATIVAÇÃO
    try {
        $dadosLog = [
            'CD_PONTO_MEDICAO' => $cdPontoMedicao,
            'DS_NOME' => $dadosPonto['DS_NOME'] ?? null,
            'CD_LOCALIDADE' => $dadosPonto['CD_LOCALIDADE'] ?? null,
            'ID_TIPO_MEDIDOR' => $dadosPonto['ID_TIPO_MEDIDOR'] ?? null,
            'DT_DESATIVACAO_ANTERIOR' => $dadosPonto['DT_DESATIVACAO'] ?? null,
            'acao' => 'REATIVAÇÃO'
        ];
        registrarLogUpdate('Cadastro de Ponto de Medição', 'Ponto de Medição', $cdPontoMedicao, $identificador . ' (Reativado)', $dadosLog, $cdUnidadeLog);
    } catch (Exception $logEx) {
        error_log('Erro ao registrar log de ATIVAÇÃO Ponto de Medição: ' . $logEx->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ponto de medição ativado com sucesso!'
    ]);

} catch (Exception $e) {
    // Registrar log de erro
    try {
        registrarLogErro('Cadastro de Ponto de Medição', 'ATIVAR', $e->getMessage(), ['cd_ponto_medicao' => $cdPontoMedicao ?? null]);
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}