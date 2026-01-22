<?php
/**
 * SIMP - Logout com Registro de Log
 * Preserva preferência de ambiente para desenvolvedores
 */

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// PRESERVAR PREFERÊNCIA DE AMBIENTE
// ============================================
// Salva o ambiente antes de destruir a sessão (para desenvolvedores)
$ambientePreservado = $_SESSION['ambiente_forcado'] ?? null;

// Tentar registrar log de logout (com tratamento de erro)
try {
    $logHelperPath = __DIR__ . '/bd/logHelper.php';
    if (file_exists($logHelperPath)) {
        require_once $logHelperPath;
        
        if (isset($_SESSION['cd_usuario'])) {
            registrarLogLogout();
        }
    }
} catch (Exception $e) {
    error_log('Erro ao registrar log de logout: ' . $e->getMessage());
}

// Destruir sessão
$_SESSION = [];

// Remove apenas o cookie de sessão (não o de ambiente)
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();

// ============================================
// REFORÇAR COOKIE DE AMBIENTE (se existia)
// ============================================
// Garante que o cookie de ambiente persista após o logout
if ($ambientePreservado && in_array($ambientePreservado, ['HOMOLOGAÇÃO', 'PRODUÇÃO'])) {
    setcookie(
        'simp_ambiente_preferido',
        $ambientePreservado,
        [
            'expires' => time() + (30 * 24 * 60 * 60), // 30 dias
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

header('Location: login.php');
exit();