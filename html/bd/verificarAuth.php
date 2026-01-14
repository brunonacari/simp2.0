<?php
/**
 * SIMP - Verificação de Autenticação para Endpoints AJAX
 * Incluir no início de cada endpoint que requer autenticação
 */

// Carrega funções de autenticação
require_once __DIR__ . '/../includes/auth.php';

// Inicia sessão de forma segura
iniciarSessaoSegura();

/**
 * Verifica se o usuário está autenticado
 * Retorna JSON de erro se não estiver
 */
function verificarAutenticacaoAjax() {
    if (!isset($_SESSION['sucesso']) || $_SESSION['sucesso'] != 1) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Sessão expirada. Faça login novamente.',
            'redirect' => 'index.php'
        ]);
        exit();
    }
}

/**
 * Verifica permissão para endpoints AJAX (por nome da tela)
 * @param string $nomeTela - Nome ou parte do nome da funcionalidade
 * @param int|null $tipoAcessoMinimo
 */
function verificarPermissaoAjax($nomeTela, $tipoAcessoMinimo = null) {
    verificarAutenticacaoAjax();
    
    if (!temPermissaoTela($nomeTela, $tipoAcessoMinimo)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Você não tem permissão para realizar esta ação.'
        ]);
        exit();
    }
}

// Executa verificação automaticamente ao incluir este arquivo
verificarAutenticacaoAjax();