<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Desvincular instrumento de um ponto de medição
 * 
 * Define CD_PONTO_MEDICAO = NULL na tabela do instrumento,
 * removendo o relacionamento com o ponto de medição.
 * 
 * Parâmetros POST:
 *   - cd_chave (int): Código do instrumento a desvincular
 *   - cd_ponto_medicao (int): Código do ponto de medição (validação de segurança)
 *   - id_tipo_medidor (int): Tipo do medidor (determina a tabela)
 * 
 * @author Bruno - SIMP
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    // ============================================
    // Validação dos parâmetros
    // ============================================
    $cdChave = isset($_POST['cd_chave']) && $_POST['cd_chave'] !== '' ? (int) $_POST['cd_chave'] : null;
    $cdPontoMedicao = isset($_POST['cd_ponto_medicao']) && $_POST['cd_ponto_medicao'] !== '' ? (int) $_POST['cd_ponto_medicao'] : null;
    $idTipoMedidor = isset($_POST['id_tipo_medidor']) && $_POST['id_tipo_medidor'] !== '' ? (int) $_POST['id_tipo_medidor'] : null;

    if (empty($cdChave)) {
        throw new Exception('Instrumento não informado');
    }

    if (empty($cdPontoMedicao)) {
        throw new Exception('Ponto de medição não informado');
    }

    if (empty($idTipoMedidor)) {
        throw new Exception('Tipo de medidor não informado');
    }

    // ============================================
    // Determina a tabela baseado no tipo
    // ============================================
    $tabelas = [
        1 => 'MACROMEDIDOR',
        2 => 'ESTACAO_PITOMETRICA',
        4 => 'MEDIDOR_PRESSAO',
        6 => 'NIVEL_RESERVATORIO',
        8 => 'HIDROMETRO'
    ];

    if (!isset($tabelas[$idTipoMedidor])) {
        throw new Exception('Tipo de medidor não suportado: ' . $idTipoMedidor);
    }

    $tabela = $tabelas[$idTipoMedidor];

    // ============================================
    // Verifica se o instrumento está vinculado ao ponto informado
    // (validação de segurança)
    // ============================================
    $sqlCheck = "SELECT CD_CHAVE, CD_PONTO_MEDICAO FROM SIMP.dbo.{$tabela} WHERE CD_CHAVE = ? AND CD_PONTO_MEDICAO = ?";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([$cdChave, $cdPontoMedicao]);
    $instrumento = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$instrumento) {
        throw new Exception('Instrumento não encontrado ou não está vinculado a este ponto');
    }

    // ============================================
    // Desvincula: define CD_PONTO_MEDICAO = NULL
    // ============================================
    $sqlDesvincula = "UPDATE SIMP.dbo.{$tabela} SET CD_PONTO_MEDICAO = NULL WHERE CD_CHAVE = ? AND CD_PONTO_MEDICAO = ?";
    $stmtDesvincula = $pdoSIMP->prepare($sqlDesvincula);
    $stmtDesvincula->execute([$cdChave, $cdPontoMedicao]);

    if ($stmtDesvincula->rowCount() === 0) {
        throw new Exception('Não foi possível desvincular o instrumento');
    }

    // ============================================
    // Limpa TAGs correspondentes no ponto de medição
    // Um ponto desvinculado não deve manter a TAG do instrumento
    // ============================================
    $mapaTag = [
        1 => 'DS_TAG_VAZAO',
        2 => 'DS_TAG_VAZAO',
        4 => 'DS_TAG_PRESSAO',
        6 => 'DS_TAG_RESERVATORIO',
        8 => 'DS_TAG_VAZAO'
    ];

    $colunaTag = $mapaTag[$idTipoMedidor] ?? null;
    if ($colunaTag) {
        $sqlLimpaTag = "UPDATE SIMP.dbo.PONTO_MEDICAO SET {$colunaTag} = NULL WHERE CD_PONTO_MEDICAO = ?";
        $pdoSIMP->prepare($sqlLimpaTag)->execute([$cdPontoMedicao]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Instrumento desvinculado com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}