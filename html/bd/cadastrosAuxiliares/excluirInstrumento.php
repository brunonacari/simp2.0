<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Excluir instrumento
 * 
 * Remove o registro da tabela de equipamento correspondente.
 * Não permite exclusão se o instrumento estiver vinculado a um ponto.
 * 
 * Parâmetros POST:
 *   - cd_chave (int): Código do instrumento
 *   - id_tipo_medidor (int): Tipo do medidor (determina a tabela)
 * 
 * @author Bruno - SIMP
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

try {
    require_once '../verificarAuth.php';
    verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

    include_once '../conexao.php';
    @include_once '../logHelper.php';

    $cdChave = isset($_POST['cd_chave']) && $_POST['cd_chave'] !== '' ? (int)$_POST['cd_chave'] : null;
    $idTipoMedidor = isset($_POST['id_tipo_medidor']) && $_POST['id_tipo_medidor'] !== '' ? (int)$_POST['id_tipo_medidor'] : null;

    if (empty($cdChave)) throw new Exception('Instrumento não informado');
    if (empty($idTipoMedidor)) throw new Exception('Tipo de medidor não informado');

    // ============================================
    // Determina a tabela
    // ============================================
    $tabelas = [
        1 => 'MACROMEDIDOR',
        2 => 'ESTACAO_PITOMETRICA',
        4 => 'MEDIDOR_PRESSAO',
        6 => 'NIVEL_RESERVATORIO',
        8 => 'HIDROMETRO'
    ];

    if (!isset($tabelas[$idTipoMedidor])) {
        throw new Exception('Tipo de medidor não suportado');
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
        throw new Exception('Instrumento não encontrado');
    }

    // ============================================
    // Impede exclusão se vinculado a um ponto
    // ============================================
    if (!empty($instrumento['CD_PONTO_MEDICAO'])) {
        throw new Exception('Não é possível excluir um instrumento vinculado a um ponto de medição (CD Ponto: ' . $instrumento['CD_PONTO_MEDICAO'] . '). Desvincule-o primeiro.');
    }

    // ============================================
    // Exclui o instrumento
    // ============================================
    $sqlDel = "DELETE FROM SIMP.dbo.{$tabela} WHERE CD_CHAVE = ?";
    $stmtDel = $pdoSIMP->prepare($sqlDel);
    $stmtDel->execute([$cdChave]);

    // Log
    if (function_exists('registrarLogDelete')) {
        try {
            registrarLogDelete('Cadastros Auxiliares', 'Instrumento (' . $tabela . ')', $cdChave, 'CD:' . $cdChave);
        } catch (Exception $e) {}
    }

    echo json_encode([
        'success' => true,
        'message' => 'Instrumento excluído com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}