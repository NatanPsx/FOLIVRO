<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
folivro_database();
folivro_require_login();
header('Location: minha-biblioteca.php');
exit;
