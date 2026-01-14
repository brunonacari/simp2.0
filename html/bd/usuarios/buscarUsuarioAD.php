<?php
/**
 * SIMP - Busca dados do usuário no ADCache pelo login
 * 
 * Relacionamento ADCache.dbo.Users -> SIMP.dbo.USUARIO:
 * - sAMAccountName -> DS_LOGIN
 * - initials -> DS_MATRICULA
 * - displayName -> DS_NOME
 * - mail -> DS_EMAIL
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';

    $login = isset($_GET['login']) ? trim($_GET['login']) : '';
    $idAtual = isset($_GET['id_atual']) && $_GET['id_atual'] !== '' ? (int)$_GET['id_atual'] : null;

    if ($login === '') {
        throw new Exception('Login não informado');
    }

    // Verificar se já existe usuário com este login (ignorando o usuário atual se estiver editando)
    $sqlExiste = "SELECT CD_USUARIO, DS_NOME FROM SIMP.dbo.USUARIO WHERE DS_LOGIN = :login";
    $params = [':login' => $login];
    
    if ($idAtual !== null) {
        $sqlExiste .= " AND CD_USUARIO <> :id_atual";
        $params[':id_atual'] = $idAtual;
    }
    
    $stmtExiste = $pdoSIMP->prepare($sqlExiste);
    $stmtExiste->execute($params);
    $usuarioExistente = $stmtExiste->fetch(PDO::FETCH_ASSOC);

    if ($usuarioExistente) {
        echo json_encode([
            'success' => false,
            'message' => 'Já existe um usuário cadastrado com este login: ' . $usuarioExistente['DS_NOME'],
            'usuario_existente' => $usuarioExistente
        ]);
        exit;
    }

    // Buscar no ADCache
    $sql = "SELECT 
                sAMAccountName AS login,
                initials AS matricula,
                displayName AS nome,
                mail AS email
            FROM ADCache.dbo.Users
            WHERE sAMAccountName = :login";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':login' => $login]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        // Tentar busca parcial
        $sqlParcial = "SELECT TOP 10
                           sAMAccountName AS login,
                           initials AS matricula,
                           displayName AS nome,
                           mail AS email
                       FROM ADCache.dbo.Users
                       WHERE sAMAccountName LIKE :login
                       ORDER BY displayName";
        $stmtParcial = $pdoSIMP->prepare($sqlParcial);
        $stmtParcial->execute([':login' => $login . '%']);
        $sugestoes = $stmtParcial->fetchAll(PDO::FETCH_ASSOC);

        if (count($sugestoes) > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Usuário não encontrado no AD. Sugestões encontradas:',
                'sugestoes' => $sugestoes
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Usuário não encontrado no Active Directory'
            ]);
        }
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $usuario
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar usuário: ' . $e->getMessage()
    ]);
}