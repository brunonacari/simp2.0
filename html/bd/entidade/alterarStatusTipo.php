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

    if ($acao === 'desativar') {
        $sql = "UPDATE SIMP.dbo.ENTIDADE_TIPO SET DT_EXC_ENTIDADE_TIPO = GETDATE() WHERE CD_CHAVE = :cd";
        $mensagem = 'Tipo de entidade desativado com sucesso!';
    } else {
        $sql = "UPDATE SIMP.dbo.ENTIDADE_TIPO SET DT_EXC_ENTIDADE_TIPO = NULL WHERE CD_CHAVE = :cd";
        $mensagem = 'Tipo de entidade ativado com sucesso!';
    }

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cd' => $cd]);

    echo json_encode([
        'success' => true,
        'message' => $mensagem
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
