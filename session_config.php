<?php
// Configuration des sessions
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

function verify_session() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['employe_id'])) {
        // Stocker la page demandée avant redirection
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header('Location: /login.php?redirect=' . $redirect);
        exit;
    }
    
    // Vérification de sécurité supplémentaire
    if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] || 
        $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        header('Location: /login.php');
        exit;
    }
}