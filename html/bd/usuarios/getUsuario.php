<?php
/**
 * SIMP - Busca um usuário específico
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        throw new Exception('ID do usuário não informado');
    }

    $sql = "SELECT 
                U.CD_USUARIO,
                U.CD_GRUPO_USUARIO,
                U.DS_MATRICULA,
                U.DS_LOGIN,
                U.DS_NOME,
                U.DS_EMAIL,
                U.OP_BLOQUEADO,
                G.DS_NOME AS DS_GRUPO
            FROM SIMP.dbo.USUARIO U
            LEFT JOIN SIMP.dbo.GRUPO_USUARIO G ON U.CD_GRUPO_USUARIO = G.CD_GRUPO_USUARIO
            WHERE U.CD_USUARIO = :id";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new Exception('Usuário não encontrado');
    }

    echo json_encode([
        'success' => true,
        'data' => $usuario
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}