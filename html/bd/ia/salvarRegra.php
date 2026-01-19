<?php
/**
 * SIMP - API para Salvar Regras da IA
 * Salva ou atualiza o conteúdo único de instruções
 * 
 * @author Bruno
 * @version 2.0
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';
    
    // Iniciar sessão para pegar usuário logado
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }

    // Receber dados JSON
    $rawInput = file_get_contents('php://input');
    $dados = json_decode($rawInput, true);

    if (!$dados || !isset($dados['conteudo'])) {
        throw new Exception('Conteúdo não informado');
    }

    $conteudo = trim($dados['conteudo']);

    if (empty($conteudo)) {
        throw new Exception('O conteúdo das instruções é obrigatório');
    }

    // Usuário logado
    $cdUsuario = isset($_SESSION['cd_usuario']) ? (int)$_SESSION['cd_usuario'] : null;

    // Verificar se já existe registro
    $sqlCheck = "SELECT TOP 1 CD_CHAVE FROM SIMP.dbo.IA_REGRAS ORDER BY CD_CHAVE DESC";
    $stmtCheck = $pdoSIMP->query($sqlCheck);
    $registro = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($registro) {
        // UPDATE - Atualizar registro existente
        $sql = "UPDATE SIMP.dbo.IA_REGRAS SET
                    DS_CONTEUDO = :conteudo,
                    CD_USUARIO_ATUALIZACAO = :usuario,
                    DT_ATUALIZACAO = GETDATE()
                WHERE CD_CHAVE = :cdChave";

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':conteudo' => $conteudo,
            ':usuario' => $cdUsuario,
            ':cdChave' => $registro['CD_CHAVE']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Instruções atualizadas com sucesso!',
            'cdChave' => $registro['CD_CHAVE']
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // INSERT - Criar novo registro
        $sql = "INSERT INTO SIMP.dbo.IA_REGRAS 
                (DS_CONTEUDO, CD_USUARIO_CRIACAO, DT_CRIACAO)
                VALUES 
                (:conteudo, :usuario, GETDATE())";

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':conteudo' => $conteudo,
            ':usuario' => $cdUsuario
        ]);

        // Pegar ID gerado
        $cdChave = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS id")->fetch(PDO::FETCH_ASSOC)['id'];

        echo json_encode([
            'success' => true,
            'message' => 'Instruções salvas com sucesso!',
            'cdChave' => $cdChave
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    // Verificar se é erro de tabela inexistente
    if (strpos($e->getMessage(), 'Invalid object name') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Tabela IA_REGRAS não encontrada. Execute o script SQL para criar a tabela.'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erro de banco de dados: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
