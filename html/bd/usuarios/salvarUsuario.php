<?php
/**
 * SIMP - Salvar usuário (INSERT ou UPDATE)
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';
    @include_once '../logHelper.php';

    // Captura dados
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $login = isset($_POST['login']) ? trim($_POST['login']) : '';
    $matricula = isset($_POST['matricula']) ? trim($_POST['matricula']) : '';
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $cdGrupo = isset($_POST['cd_grupo']) && $_POST['cd_grupo'] !== '' ? (int)$_POST['cd_grupo'] : null;
    $bloqueado = isset($_POST['bloqueado']) ? (int)$_POST['bloqueado'] : 0;

    // Validações
    if ($login === '') {
        throw new Exception('O login é obrigatório');
    }

    if ($nome === '') {
        throw new Exception('O nome é obrigatório');
    }

    if ($cdGrupo === null) {
        throw new Exception('O grupo de usuário é obrigatório');
    }

    // Verificar se login já existe (para outro usuário)
    $sqlVerifica = "SELECT CD_USUARIO FROM SIMP.dbo.USUARIO WHERE DS_LOGIN = :login";
    if ($id !== null) {
        $sqlVerifica .= " AND CD_USUARIO <> :id";
    }
    $stmtVerifica = $pdoSIMP->prepare($sqlVerifica);
    $paramsVerifica = [':login' => $login];
    if ($id !== null) {
        $paramsVerifica[':id'] = $id;
    }
    $stmtVerifica->execute($paramsVerifica);
    
    if ($stmtVerifica->fetch()) {
        throw new Exception('Já existe outro usuário com este login');
    }

    // Configurar PDO para lançar exceções
    $pdoSIMP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($id !== null) {
        // Buscar dados anteriores para log
        $dadosAnteriores = null;
        try {
            $stmtAnt = $pdoSIMP->prepare("SELECT DS_LOGIN, DS_MATRICULA, DS_NOME, DS_EMAIL, CD_GRUPO_USUARIO, OP_BLOQUEADO FROM SIMP.dbo.USUARIO WHERE CD_USUARIO = ?");
            $stmtAnt->execute([$id]);
            $dadosAnteriores = $stmtAnt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // UPDATE
        $sql = "UPDATE SIMP.dbo.USUARIO
                SET DS_LOGIN = ?,
                    DS_MATRICULA = ?,
                    DS_NOME = ?,
                    DS_EMAIL = ?,
                    CD_GRUPO_USUARIO = ?,
                    OP_BLOQUEADO = ?
                WHERE CD_USUARIO = ?";

        $stmt = $pdoSIMP->prepare($sql);
        $resultado = $stmt->execute([
            $login,
            $matricula ?: null,
            $nome,
            $email ?: null,
            $cdGrupo,
            $bloqueado,
            $id
        ]);

        if (!$resultado || $stmt->rowCount() === 0) {
            throw new Exception('Nenhum registro foi atualizado. Verifique se o usuário existe.');
        }

        // Log de atualização
        try {
            if (function_exists('registrarLogUpdate')) {
                registrarLogUpdate('Cadastros Administrativos', 'Usuário', $id, $nome,
                    ['anterior' => $dadosAnteriores, 'novo' => ['DS_LOGIN' => $login, 'DS_MATRICULA' => $matricula, 'DS_NOME' => $nome, 'DS_EMAIL' => $email, 'CD_GRUPO_USUARIO' => $cdGrupo, 'OP_BLOQUEADO' => $bloqueado]]);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => 'Usuário atualizado com sucesso!',
            'id' => $id,
            'rowsAffected' => $stmt->rowCount()
        ]);

    } else {
        // INSERT - Deixar o banco gerar o CD_USUARIO (IDENTITY)
        $sql = "INSERT INTO SIMP.dbo.USUARIO 
                (DS_LOGIN, DS_MATRICULA, DS_NOME, DS_EMAIL, CD_GRUPO_USUARIO, OP_BLOQUEADO)
                VALUES 
                (?, ?, ?, ?, ?, ?)";

        $params = [
            $login,
            $matricula ?: null,
            $nome,
            $email ?: null,
            $cdGrupo,
            $bloqueado
        ];

        $stmt = $pdoSIMP->prepare($sql);
        $resultado = $stmt->execute($params);

        if (!$resultado) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('Erro ao inserir: ' . $errorInfo[2]);
        }

        // Recuperar o ID gerado
        $sqlId = "SELECT SCOPE_IDENTITY() AS novo_id";
        $stmtId = $pdoSIMP->query($sqlId);
        $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['novo_id'];

        // Log de inserção
        try {
            if (function_exists('registrarLogInsert')) {
                registrarLogInsert('Cadastros Administrativos', 'Usuário', $novoId, $nome,
                    ['DS_LOGIN' => $login, 'DS_MATRICULA' => $matricula, 'DS_NOME' => $nome, 'DS_EMAIL' => $email, 'CD_GRUPO_USUARIO' => $cdGrupo, 'OP_BLOQUEADO' => $bloqueado]);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => 'Usuário cadastrado com sucesso!',
            'id' => $novoId
        ]);
    }

} catch (PDOException $e) {
    try {
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastros Administrativos', $id ? 'UPDATE_USUARIO' : 'INSERT_USUARIO', $e->getMessage(),
                ['login' => $login ?? '', 'nome' => $nome ?? '', 'id' => $id ?? '']);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => false,
        'message' => 'Erro de banco de dados: ' . $e->getMessage(),
        'code' => $e->getCode()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}