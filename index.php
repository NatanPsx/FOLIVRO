<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

try {
    folivro_database();
    folivro_start_session();
} catch (Throwable $error) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Não foi possível iniciar o FOLIVRO. Confira se Apache e MySQL/MariaDB estão ativos no XAMPP.\n");
}

header('Location: ' . (!empty($_SESSION['id_usuario']) ? 'home.php' : 'login.php'));
exit;
