<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Vincular instrumento a um ponto de medição
 * 
 * Atualiza CD_PONTO_MEDICAO na tabela do instrumento correspondente.
 * Antes de vincular, desvincula qualquer instrumento anterior do mesmo tipo
 * que esteja vinculado ao ponto (garante 1 instrumento por ponto por tipo).
 * 
 * Parâmetros POST:
 *   - cd_chave (int): Código do instrumento a vincular
 *   - cd_ponto_medicao (int): Código do ponto de medição
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
    $cdChave = isset($_POST['cd_chave']) && $_POST['cd_chave'] !== '' ? (int)$_POST['cd_chave'] : null;
    $cdPontoMedicao = isset($_POST['cd_ponto_medicao']) && $_POST['cd_ponto_medicao'] !== '' ? (int)$_POST['cd_ponto_medicao'] : null;
    $idTipoMedidor = isset($_POST['id_tipo_medidor']) && $_POST['id_tipo_medidor'] !== '' ? (int)$_POST['id_tipo_medidor'] : null;

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
    // Verifica se o instrumento existe
    // ============================================
    $sqlCheck = "SELECT CD_CHAVE, CD_PONTO_MEDICAO FROM SIMP.dbo.{$tabela} WHERE CD_CHAVE = ?";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([$cdChave]);
    $instrumento = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$instrumento) {
        throw new Exception('Instrumento não encontrado (CD_CHAVE: ' . $cdChave . ')');
    }

    // ============================================
    // Verifica se o instrumento já está vinculado a outro ponto
    // ============================================
    if ($instrumento['CD_PONTO_MEDICAO'] && $instrumento['CD_PONTO_MEDICAO'] != $cdPontoMedicao) {
        throw new Exception('Este instrumento já está vinculado ao ponto de medição CD: ' . $instrumento['CD_PONTO_MEDICAO'] . '. Desvincule-o primeiro.');
    }

    // ============================================
    // Desvincula qualquer instrumento anterior do mesmo tipo
    // vinculado a este ponto (garante 1:1)
    // ============================================
    $sqlDesvincula = "UPDATE SIMP.dbo.{$tabela} 
                      SET CD_PONTO_MEDICAO = NULL 
                      WHERE CD_PONTO_MEDICAO = ? AND CD_CHAVE != ?";
    $stmtDesvincula = $pdoSIMP->prepare($sqlDesvincula);
    $stmtDesvincula->execute([$cdPontoMedicao, $cdChave]);
    $desvinculados = $stmtDesvincula->rowCount();

    // ============================================
    // Vincula o instrumento ao ponto
    // ============================================
    $sqlVincula = "UPDATE SIMP.dbo.{$tabela} 
                   SET CD_PONTO_MEDICAO = ? 
                   WHERE CD_CHAVE = ?";
    $stmtVincula = $pdoSIMP->prepare($sqlVincula);
    $stmtVincula->execute([$cdPontoMedicao, $cdChave]);

    $msg = 'Instrumento vinculado com sucesso!';
    if ($desvinculados > 0) {
        $msg .= ' (' . $desvinculados . ' instrumento(s) anterior(es) desvinculado(s))';
    }

    echo json_encode([
        'success' => true,
        'message' => $msg,
        'cd_chave' => $cdChave
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}