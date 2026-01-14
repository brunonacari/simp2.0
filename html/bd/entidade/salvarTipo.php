<?php
/**
 * SIMP - Salvar Tipo de Entidade (INSERT ou UPDATE)
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

    $cd = isset($_POST['cd']) && $_POST['cd'] !== '' ? (int)$_POST['cd'] : null;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $idExterno = isset($_POST['idExterno']) ? trim($_POST['idExterno']) : null;
    $descarte = isset($_POST['descarte']) ? (int)$_POST['descarte'] : 0;

    if ($nome === '') {
        throw new Exception('O nome é obrigatório');
    }
    
    if ($idExterno === null || $idExterno === '') {
        throw new Exception('O ID Externo é obrigatório');
    }

    if ($cd !== null) {
        // UPDATE
        $sql = "UPDATE SIMP.dbo.ENTIDADE_TIPO 
                SET DS_NOME = :nome, 
                    CD_ENTIDADE_TIPO_ID = :idExterno,
                    DESCARTE = :descarte
                WHERE CD_CHAVE = :cd";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':nome' => $nome,
            ':idExterno' => $idExterno,
            ':descarte' => $descarte,
            ':cd' => $cd
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Tipo de entidade atualizado com sucesso!'
        ]);
    } else {
        // INSERT
        $sql = "INSERT INTO SIMP.dbo.ENTIDADE_TIPO (DS_NOME, CD_ENTIDADE_TIPO_ID, DESCARTE) VALUES (:nome, :idExterno, :descarte)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':nome' => $nome,
            ':idExterno' => $idExterno,
            ':descarte' => $descarte
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Tipo de entidade cadastrado com sucesso!'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
