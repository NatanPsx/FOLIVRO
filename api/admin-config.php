<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/loja.php';

$pdo = folivro_database();
folivro_require_login();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE));
}

if (($_SESSION['nivel_usuario'] ?? '') !== 'admin') {
    http_response_code(403);
    exit(json_encode(['error' => 'Acesso restrito a administradores.'], JSON_UNESCAPED_UNICODE));
}

if (!folivro_verify_csrf()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Token de segurança inválido. Atualize a página.'], JSON_UNESCAPED_UNICODE));
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}

$acao = (string) ($body['acao'] ?? '');
$uidAdmin = (int) $_SESSION['id_usuario'];

if ($acao === 'alternar_pagamento') {
    $simuladoAtivo = !empty($body['simulado_ativo']);
    $novoModo = $simuladoAtivo ? 'simulated' : 'real';

    folivro_sistema_config_salvar($pdo, 'payment_mode', $novoModo, $uidAdmin);
    folivro_loja_log('admin_payment_mode', ['modo' => $novoModo, 'admin' => $uidAdmin]);

    $avisoCredenciais = '';
    if ($novoModo === 'real') {
        $token = trim((string) folivro_env('MP_ACCESS_TOKEN', ''));
        $pubKey = trim((string) folivro_env('MP_PUBLIC_KEY', ''));
        $placeholder = str_starts_with($token, 'TEST-00000000') || str_starts_with($pubKey, 'TEST-00000000');
        if ($token === '' || $pubKey === '' || $placeholder) {
            $avisoCredenciais = ' Atenção: preencha MP_ACCESS_TOKEN e MP_PUBLIC_KEY no arquivo .env para processar cobranças reais/sandbox.';
        }
    }

    echo json_encode([
        'ok' => true,
        'simulado_ativo' => $simuladoAtivo,
        'payment_mode' => $novoModo,
        'mensagem' => ($simuladoAtivo
            ? 'Pagamento simulado ATIVADO (modo acadêmico, sem cobrança real).'
            : 'Pagamento simulado DESATIVADO (modo real via Mercado Pago ativo).') . $avisoCredenciais,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($acao === 'alternar_aprovacao_automatica') {
    $ativo = !empty($body['aprovacao_automatica']);
    $valor = $ativo ? '1' : '0';
    folivro_sistema_config_salvar($pdo, 'aprovacao_automatica_obras', $valor, $uidAdmin);
    folivro_loja_log('admin_auto_approval', ['ativo' => $ativo, 'admin' => $uidAdmin]);

    echo json_encode([
        'ok' => true,
        'aprovacao_automatica' => $ativo,
        'mensagem' => $ativo
            ? 'Aprovação automática de obras postadas ATIVADA (obras seguras são publicadas imediatamente).'
            : 'Aprovação automática DESATIVADA (obras novas passam por revisão humana ou critérios de confiança).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
exit(json_encode(['error' => 'Ação administrativa inválida.'], JSON_UNESCAPED_UNICODE));
