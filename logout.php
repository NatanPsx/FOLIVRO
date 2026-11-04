<?php
declare(strict_types=1);
require_once __DIR__ . '/config/auth.php';
folivro_start_session();

// Se enviado via POST, exige token CSRF válido (evita logout forçado por terceiros).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !folivro_verify_csrf()) {
    header('Location: usuario.php');
    exit;
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
