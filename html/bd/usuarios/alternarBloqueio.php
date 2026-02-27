<?php
/**
 * SIMP - Alternar status de ativo/inativo do usuário
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';
    @include_once '../logHelper.php';

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        throw new Exception('ID do usuário não informado');
    }

    // Buscar status atual
    $sqlAtual = "SELECT OP_BLOQUEADO, DS_NOME FROM SIMP.dbo.USUARIO WHERE CD_USUARIO = :id";
    $stmtAtual = $pdoSIMP->prepare($sqlAtual);
    $stmtAtual->execute([':id' => $id]);
    $usuario = $stmtAtual->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new Exception('Usuário não encontrado');
    }

    // Alternar status (se for 1 = inativo, vai para 0 = ativo; senão vai para 1)
    $statusAtual = (int)$usuario['OP_BLOQUEADO'];
    $novoStatus = ($statusAtual === 1) ? 0 : 1;
    $statusTexto = ($novoStatus === 1) ? 'desativado' : 'ativado';

    // Atualizar
    $sql = "UPDATE SIMP.dbo.USUARIO SET OP_BLOQUEADO = :status WHERE CD_USUARIO = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([
        ':status' => $novoStatus,
        ':id' => $id
    ]);

    // Log de alteração de bloqueio
    try {
        if (function_exists('registrarLogUpdate')) {
            registrarLogUpdate('Cadastros Administrativos', 'Usuário (Bloqueio)', $id, $usuario['DS_NOME'],
                ['anterior' => ['OP_BLOQUEADO' => $statusAtual], 'novo' => ['OP_BLOQUEADO' => $novoStatus], 'acao' => $statusTexto]);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => true,
        'message' => "Usuário {$statusTexto} com sucesso!",
        'novo_status' => $novoStatus
    ]);

} catch (Exception $e) {
    try {
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastros Administrativos', 'ALTERNAR_BLOQUEIO', $e->getMessage(), ['id' => $id ?? '']);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => false,
        'message' => 'Erro ao alterar status: ' . $e->getMessage()
    ]);
}