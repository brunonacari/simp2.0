<?php
header('Content-Type: application/json; charset=utf-8');
include_once '../conexao.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Verificar se o registro existe
    $sqlCheck = "SELECT DS_NOME FROM SIMP.dbo.CONJUNTO_MOTOR_BOMBA WHERE CD_CHAVE = :id";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([':id' => $id]);
    $registro = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        throw new Exception('Registro não encontrado');
    }

    // Forçar modo de exceções para capturar erros de FK
    $pdoSIMP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "DELETE FROM SIMP.dbo.CONJUNTO_MOTOR_BOMBA WHERE CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);

    echo json_encode([
        'success' => true, 
        'message' => 'Conjunto Motor-Bomba "' . $registro['DS_NOME'] . '" excluído com sucesso!'
    ]);

} catch (PDOException $e) {
    $mensagem = $e->getMessage();
    
    // Tratar erro de FK de forma mais amigável
    if (strpos($mensagem, 'REFERENCE constraint') !== false || strpos($mensagem, 'FK_') !== false) {
        // Extrair nome da tabela relacionada se possível
        if (preg_match('/table "([^"]+)"/', $mensagem, $matches)) {
            $tabelaRelacionada = $matches[1];
            $mensagemAmigavel = "Não é possível excluir este Conjunto Motor-Bomba pois possui registros vinculados na tabela \"$tabelaRelacionada\". Remova os registros vinculados primeiro.";
        } else {
            $mensagemAmigavel = "Não é possível excluir este Conjunto Motor-Bomba pois possui registros vinculados no sistema.";
        }
        
        echo json_encode([
            'success' => false, 
            'message' => $mensagemAmigavel,
            'erro_sql' => $mensagem
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Erro no banco de dados: ' . $mensagem
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}