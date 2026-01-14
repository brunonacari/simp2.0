<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Buscar Localidades por Unidade
 */

header('Content-Type: application/json; charset=utf-8');

// Capturar erros
$errors = [];
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errors) {
    $errors[] = "[$errno] $errstr em $errfile:$errline";
    return true;
});

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // Verificação de autenticação
    require_once '../verificarAuth.php';
    verificarPermissaoAjax('CADASTRO DE PONTO', ACESSO_LEITURA);

    include_once '../conexao.php';
    
    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }

    $cdUnidade = filter_input(INPUT_GET, 'cd_unidade', FILTER_SANITIZE_NUMBER_INT);

    if (empty($cdUnidade)) {
        echo json_encode([
            'success' => false,
            'message' => 'Unidade não informada',
            'data' => []
        ]);
        exit;
    }

    $sql = "SELECT 
                CD_CHAVE,
                CD_UNIDADE,
                DS_NOME,
                CD_LOCALIDADE,
                CD_ENTIDADE_VALOR_ID
            FROM SIMP.dbo.LOCALIDADE
            WHERE CD_UNIDADE = :cd_unidade
            ORDER BY DS_NOME";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cd_unidade' => $cdUnidade]);
    $localidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'success' => true,
        'data' => $localidades
    ];
    
    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }
    
    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar localidades: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'data' => []
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ]);
}