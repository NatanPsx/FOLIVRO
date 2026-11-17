<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/loja.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$pdo = folivro_database();

// Preserva nomes exatos da query string (PHP converte '.' em '_' em $_GET, ex.: data.id -> data_id).
$query = [];
$qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
if ($qs !== '') {
    foreach (explode('&', $qs) as $par) {
        if ($par === '') {
            continue;
        }
        $kv = explode('=', $par, 2);
        $k = urldecode($kv[0]);
        $v = isset($kv[1]) ? urldecode($kv[1]) : '';
        $query[$k] = $v;
    }
}

$headers = [];
if (function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) {
        $headers[strtolower((string) $k)] = (string) $v;
    }
}
foreach ($_SERVER as $k => $v) {
    if (str_starts_with($k, 'HTTP_') && is_string($v)) {
        $nome = strtolower(str_replace('_', '-', substr($k, 5)));
        $headers[$nome] = $v;
    }
}

$body = (string) file_get_contents('php://input');
$gateway = trim((string) ($query['gateway'] ?? $_GET['gateway'] ?? 'mercadopago'));
$ip = folivro_loja_ip();

$res = folivro_loja_processar_webhook($pdo, $gateway, $headers, $body, $query, $ip);
http_response_code($res['http']);
echo json_encode(['status' => $res['resultado']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
