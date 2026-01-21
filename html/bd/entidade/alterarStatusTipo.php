<?php
/**
 * SIMP - Alterar Status do Tipo de Entidade (Ativar/Desativar)
 * Seta DT_EXC_ENTIDADE_TIPO com GETDATE() para desativar ou NULL para ativar
 */

header('Content-Type: application/json; charset=utf-8');

// Verificar permissão de edição
require_once __DIR__ . '/../../includes/auth.php';
if (!podeEditarTela('Cadastro de Entidade')) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta operação']);
    exit;
}

try {
    include_once '../conexao.php';

    $cd = isset($_POST['cd']) ? (int)$_POST['cd'] : 0;
    $acao = isset($_POST['acao']) ? trim($_POST['acao']) : '';

    if ($cd <= 0) {
        throw new Exception('ID não informado');
    }

    if (!in_array($acao, ['ativar', 'desativar'])) {
        throw new Exception('Ação inválida');
    }

    // Buscar dados para log
    $identificador = "ID: $cd";
    try {
        $sqlBusca = "SELECT DS_NOME, CD_ENTIDADE_TIPO_ID FROM SIMP.dbo.ENTIDADE_TIPO WHERE CD_CHAVE = :cd";
        $stmtBusca = $pdoSIMP->prepare($sqlBusca);
        $stmtBusca->execute([':cd' => $cd]);
        $dadosTipo = $stmtBusca->fetch(PDO::FETCH_ASSOC);
        if ($dadosTipo) {
            $identificador = ($dadosTipo['CD_ENTIDADE_TIPO_ID'] ?? '') . ' - ' . ($dadosTipo['DS_NOME'] ?? "ID: $cd");
        }
    } catch (Exception $e) {}

    if ($acao === 'desativar') {
        $sql = "UPDATE SIMP.dbo.ENTIDADE_TIPO SET DT_EXC_ENTIDADE_TIPO = GETDATE() WHERE CD_CHAVE = :cd";
        $mensagem = 'Tipo de entidade desativado com sucesso!';
        $acaoLog = 'DESATIVACAO';
    } else {
        $sql = "UPDATE SIMP.dbo.ENTIDADE_TIPO SET DT_EXC_ENTIDADE_TIPO = NULL WHERE CD_CHAVE = :cd";
        $mensagem = 'Tipo de entidade ativado com sucesso!';
        $acaoLog = 'ATIVACAO';
    }

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cd' => $cd]);

    // Log (isolado)
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogUpdate')) {
            $descricaoAcao = $acao === 'desativar' ? 'Desativou' : 'Ativou';
            registrarLogUpdate('Cadastro de Entidade', 'Tipo de Unidade Operacional', $cd, $identificador,
                ['acao' => $acaoLog, 'descricao' => "$descricaoAcao tipo: $identificador"]);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => true,
        'message' => $mensagem
    ]);

} catch (Exception $e) {
    // Log de erro (isolado)
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastro de Entidade', 'ALTERACAO_STATUS', $e->getMessage(), ['cd' => $cd ?? null, 'acao' => $acao ?? null]);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}