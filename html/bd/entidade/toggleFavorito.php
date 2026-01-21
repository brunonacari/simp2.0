<?php
/**
 * SIMP - Toggle Favorito de Unidade Operacional
 * Adiciona ou remove uma unidade operacional dos favoritos do usuário
 * 
 * @author Bruno
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';
    
    // Iniciar sessão para pegar usuário logado
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar se usuário está logado
    $cdUsuario = isset($_SESSION['cd_usuario']) ? (int)$_SESSION['cd_usuario'] : null;
    if (!$cdUsuario) {
        throw new Exception('Usuário não autenticado');
    }
    
    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }
    
    // Receber dados (POST ou GET)
    $cdEntidadeValor = isset($_REQUEST['cdEntidadeValor']) ? (int)$_REQUEST['cdEntidadeValor'] : 0;
    
    if (!$cdEntidadeValor) {
        throw new Exception('Código da unidade operacional não informado');
    }
    
    // Verificar se já é favorito
    $sqlCheck = "SELECT CD_CHAVE FROM SIMP.dbo.ENTIDADE_VALOR_FAVORITO 
                 WHERE CD_USUARIO = :cdUsuario AND CD_ENTIDADE_VALOR = :cdEntidadeValor";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([
        ':cdUsuario' => $cdUsuario,
        ':cdEntidadeValor' => $cdEntidadeValor
    ]);
    $favorito = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($favorito) {
        // Remover dos favoritos
        $sqlDelete = "DELETE FROM SIMP.dbo.ENTIDADE_VALOR_FAVORITO 
                      WHERE CD_USUARIO = :cdUsuario AND CD_ENTIDADE_VALOR = :cdEntidadeValor";
        $stmtDelete = $pdoSIMP->prepare($sqlDelete);
        $stmtDelete->execute([
            ':cdUsuario' => $cdUsuario,
            ':cdEntidadeValor' => $cdEntidadeValor
        ]);
        
        echo json_encode([
            'success' => true,
            'favorito' => false,
            'message' => 'Removido dos favoritos'
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // Adicionar aos favoritos
        $sqlInsert = "INSERT INTO SIMP.dbo.ENTIDADE_VALOR_FAVORITO 
                      (CD_USUARIO, CD_ENTIDADE_VALOR, DT_CRIACAO) 
                      VALUES (:cdUsuario, :cdEntidadeValor, GETDATE())";
        $stmtInsert = $pdoSIMP->prepare($sqlInsert);
        $stmtInsert->execute([
            ':cdUsuario' => $cdUsuario,
            ':cdEntidadeValor' => $cdEntidadeValor
        ]);
        
        echo json_encode([
            'success' => true,
            'favorito' => true,
            'message' => 'Adicionado aos favoritos'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (PDOException $e) {
    // Verificar se é erro de tabela inexistente
    if (strpos($e->getMessage(), 'Invalid object name') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Tabela ENTIDADE_VALOR_FAVORITO não encontrada. Execute o script SQL para criá-la.'
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