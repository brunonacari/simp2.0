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
    @include_once '../logHelper.php';

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

    // ============================================
    // Sincroniza TAG do instrumento com o ponto de medição
    // Tipo 1,2,8 → DS_TAG_VAZAO | 4 → DS_TAG_PRESSAO | 6 → DS_TAG_RESERVATORIO
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
        // Busca DS_TAG do instrumento
        $sqlTag = "SELECT DS_TAG FROM SIMP.dbo.{$tabela} WHERE CD_CHAVE = ?";
        $stmtTag = $pdoSIMP->prepare($sqlTag);
        $stmtTag->execute([$cdChave]);
        $tagRow = $stmtTag->fetch(PDO::FETCH_ASSOC);
        $tagValor = ($tagRow && !empty($tagRow['DS_TAG'])) ? $tagRow['DS_TAG'] : null;

        if ($tagValor) {
            $sqlUpdTag = "UPDATE SIMP.dbo.PONTO_MEDICAO SET {$colunaTag} = ? WHERE CD_PONTO_MEDICAO = ?";
            $pdoSIMP->prepare($sqlUpdTag)->execute([$tagValor, $cdPontoMedicao]);
        }
    }

    // Log de vinculação
    try {
        if (function_exists('registrarLogUpdate')) {
            registrarLogUpdate('Ponto de Medição', "Vincular Instrumento ($tabela)", $cdChave, "Ponto $cdPontoMedicao",
                ['cd_chave' => $cdChave, 'cd_ponto_medicao' => $cdPontoMedicao, 'tabela' => $tabela, 'desvinculados' => $desvinculados]);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => true,
        'message' => $msg,
        'cd_chave' => $cdChave
    ]);

} catch (Exception $e) {
    try {
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Ponto de Medição', 'VINCULAR_INSTRUMENTO', $e->getMessage(),
                ['cd_chave' => $cdChave ?? '', 'cd_ponto_medicao' => $cdPontoMedicao ?? '']);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}