<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/loja.php';

$pdo = folivro_database();
folivro_require_login();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE));
}

$uid = (int) $_SESSION['id_usuario'];
$codigo = trim((string) ($_GET['codigo'] ?? ''));

$pedido = folivro_loja_pedido_do_comprador($pdo, $codigo, $uid);
if ($pedido === null) {
    http_response_code(404);
    exit(json_encode(['error' => 'Pedido não encontrado.'], JSON_UNESCAPED_UNICODE));
}

if ($pedido['status_pedido'] === 'pendente') {
    $pedido = folivro_loja_sincronizar_com_gateway($pdo, $pedido, false);
}

echo json_encode([
    'codigo' => $pedido['codigo_pedido'],
    'status' => $pedido['status_pedido'],
    'label'  => folivro_loja_status_pedido_label((string) $pedido['status_pedido'], $pedido['motivo_status'] ?? null),
    'pago_em' => $pedido['pago_em'],
    'motivo' => $pedido['motivo_status'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
