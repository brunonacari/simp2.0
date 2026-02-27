<?php
/**
 * SIMP - Excluir usuário
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

    // Configurar PDO para lançar exceções
    $pdoSIMP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar se o usuário existe
    $sqlVerifica = "SELECT DS_NOME FROM SIMP.dbo.USUARIO WHERE CD_USUARIO = ?";
    $stmtVerifica = $pdoSIMP->prepare($sqlVerifica);
    $stmtVerifica->execute([$id]);
    $usuario = $stmtVerifica->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new Exception('Usuário não encontrado');
    }

    // Verificar vínculos em outras tabelas
    $vinculos = [];

    // Verificar REGISTRO_VAZAO_PRESSAO (responsável)
    $sql1 = "SELECT COUNT(*) as total FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO WHERE CD_USUARIO_RESPONSAVEL = ?";
    $stmt1 = $pdoSIMP->prepare($sql1);
    $stmt1->execute([$id]);
    $count1 = (int)$stmt1->fetch(PDO::FETCH_ASSOC)['total'];
    if ($count1 > 0) {
        $vinculos[] = "$count1 registro(s) de vazão/pressão (responsável)";
    }

    // Verificar REGISTRO_VAZAO_PRESSAO (cadastro)
    $sql2 = "SELECT COUNT(*) as total FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO WHERE CD_USUARIO_RESPONSAVEL = ?";
    $stmt2 = $pdoSIMP->prepare($sql2);
    $stmt2->execute([$id]);
    $count2 = (int)$stmt2->fetch(PDO::FETCH_ASSOC)['total'];
    if ($count2 > 0) {
        $vinculos[] = "$count2 registro(s) de vazão/pressão (cadastro)";
    }

    // Verificar PONTO_MEDICAO
    $sql3 = "SELECT COUNT(*) as total FROM SIMP.dbo.PONTO_MEDICAO WHERE CD_USUARIO_RESPONSAVEL = ?";
    $stmt3 = $pdoSIMP->prepare($sql3);
    $stmt3->execute([$id]);
    $count3 = (int)$stmt3->fetch(PDO::FETCH_ASSOC)['total'];
    if ($count3 > 0) {
        $vinculos[] = "$count3 ponto(s) de medição";
    }

    // Verificar REGISTRO_MANUTENCAO
    $sql4 = "SELECT COUNT(*) as total FROM SIMP.dbo.REGISTRO_MANUTENCAO WHERE CD_TECNICO = ?";
    $stmt4 = $pdoSIMP->prepare($sql4);
    $stmt4->execute([$id]);
    $count4 = (int)$stmt4->fetch(PDO::FETCH_ASSOC)['total'];
    if ($count4 > 0) {
        $vinculos[] = "$count4 registro(s) de manutenção";
    }

    // Verificar PROGRAMACAO_MANUTENCAO
    $sql5 = "SELECT COUNT(*) as total FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO WHERE CD_TECNICO = ?";
    $stmt5 = $pdoSIMP->prepare($sql5);
    $stmt5->execute([$id]);
    $count5 = (int)$stmt5->fetch(PDO::FETCH_ASSOC)['total'];
    if ($count5 > 0) {
        $vinculos[] = "$count5 programação(ões) de manutenção";
    }

    // Se houver vínculos, não permitir exclusão
    if (count($vinculos) > 0) {
        $listaVinculos = implode(', ', $vinculos);
        echo json_encode([
            'success' => false,
            'message' => "Não é possível excluir o usuário \"{$usuario['DS_NOME']}\" pois possui vínculos: {$listaVinculos}. Considere bloquear o usuário em vez de excluí-lo.",
            'vinculos' => $vinculos
        ]);
        exit;
    }

    // Buscar dados completos do usuário para log antes de excluir
    $dadosUsuario = null;
    try {
        $stmtDados = $pdoSIMP->prepare("SELECT DS_LOGIN, DS_MATRICULA, DS_NOME, DS_EMAIL, CD_GRUPO_USUARIO, OP_BLOQUEADO FROM SIMP.dbo.USUARIO WHERE CD_USUARIO = ?");
        $stmtDados->execute([$id]);
        $dadosUsuario = $stmtDados->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    // Excluir o usuário
    $sql = "DELETE FROM SIMP.dbo.USUARIO WHERE CD_USUARIO = ?";
    $stmt = $pdoSIMP->prepare($sql);
    $resultado = $stmt->execute([$id]);

    if (!$resultado || $stmt->rowCount() === 0) {
        throw new Exception('Nenhum registro foi excluído');
    }

    // Log de exclusão
    try {
        if (function_exists('registrarLogDelete')) {
            registrarLogDelete('Cadastros Administrativos', 'Usuário', $id, $usuario['DS_NOME'], $dadosUsuario ?: []);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => true,
        'message' => 'Usuário "' . $usuario['DS_NOME'] . '" excluído com sucesso!'
    ]);

} catch (PDOException $e) {
    try {
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastros Administrativos', 'DELETE_USUARIO', $e->getMessage(), ['id' => $id ?? '']);
        }
    } catch (Exception $logEx) {}

    // Tratar erro de FK de forma amigável
    if (strpos($e->getMessage(), 'REFERENCE constraint') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Não é possível excluir este usuário pois possui registros vinculados no sistema. Considere bloquear o usuário em vez de excluí-lo.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erro de banco: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}