<?php
/**
 * SIMP - Limpar TAGs do Ponto de Medição
 * 
 * Seta como NULL as TAGs informadas com valor vazio.
 * Usado ao trocar o Tipo de Medidor para garantir
 * que um ponto jamais tenha mais de uma TAG.
 * 
 * @author Bruno - SIMP
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

try {
    require_once '../verificarAuth.php';
    verificarPermissaoAjax('CADASTRO DE PONTO', ACESSO_ESCRITA);

    include_once '../conexao.php';
    @include_once '../logHelper.php';

    $cdPonto = isset($_POST['cd_ponto_medicao']) && $_POST['cd_ponto_medicao'] !== ''
        ? (int) $_POST['cd_ponto_medicao'] : null;

    if (!$cdPonto) {
        throw new Exception('Ponto de medição não informado');
    }

    // Colunas TAG permitidas (whitelist contra SQL injection)
    $colunasPermitidas = [
        'ds_tag_vazao' => 'DS_TAG_VAZAO',
        'ds_tag_pressao' => 'DS_TAG_PRESSAO',
        'ds_tag_volume' => 'DS_TAG_VOLUME',
        'ds_tag_reservatorio' => 'DS_TAG_RESERVATORIO',
        'ds_tag_temp_agua' => 'DS_TAG_TEMP_AGUA',
        'ds_tag_temp_ambiente' => 'DS_TAG_TEMP_AMBIENTE'
    ];

    $setClauses = [];

    foreach ($colunasPermitidas as $postKey => $dbCol) {
        // Se o campo foi enviado com valor vazio, seta NULL
        if (isset($_POST[$postKey]) && $_POST[$postKey] === '') {
            $setClauses[] = "{$dbCol} = NULL";
        }
    }

    if (empty($setClauses)) {
        echo json_encode(['success' => true, 'message' => 'Nenhuma TAG para limpar']);
        exit;
    }

    $sql = "UPDATE SIMP.dbo.PONTO_MEDICAO SET " . implode(', ', $setClauses) . " WHERE CD_PONTO_MEDICAO = ?";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([$cdPonto]);

    // Log de limpeza de TAGs
    try {
        if (function_exists('registrarLogUpdate')) {
            registrarLogUpdate('Ponto de Medição', 'Limpar TAGs', $cdPonto, "Ponto $cdPonto",
                ['tags_removidas' => $setClauses, 'quantidade' => count($setClauses)]);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => true,
        'message' => count($setClauses) . ' TAG(s) removida(s)',
        'campos_limpos' => count($setClauses)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}