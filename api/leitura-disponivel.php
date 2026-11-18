<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
folivro_require_login();
header('Content-Type: application/json; charset=utf-8');
$workId = isset($_GET['work']) && is_string($_GET['work']) ? $_GET['work'] : '';
if (!preg_match('/^OL[0-9]+W$/', $workId)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Identificador da obra inválido.'], JSON_UNESCAPED_UNICODE));
}
$pdo = folivro_database();
$check = $pdo->prepare("SELECT 1 FROM livraria l JOIN livro b ON b.id_livro = l.id_livro WHERE l.id_usuario = ? AND b.id_externo_livro = ? LIMIT 1");
$check->execute([(int) $_SESSION['id_usuario'], $workId]);
if (!$check->fetchColumn()) {
    http_response_code(404);
    exit(json_encode(['error' => 'Adicione o livro à biblioteca antes de abrir o leitor.'], JSON_UNESCAPED_UNICODE));
}
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}
if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
    $cacheDir = sys_get_temp_dir() . '/folivro_cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
}
$cacheFile = $cacheDir . '/ol_avail_' . $workId . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
    $cachedJson = @file_get_contents($cacheFile);
    if (is_string($cachedJson) && $cachedJson !== '') {
        echo $cachedJson;
        exit;
    }
}

$url = 'https://openlibrary.org/api/volumes/brief/olid/' . rawurlencode($workId) . '.json';
$response = null;
if (function_exists('curl_init')) {
    foreach ([true, false] as $verifySsl) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_ENCODING => '',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => 'FOLIVRO-Academic-Demo/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $err = curl_errno($ch);
        curl_close($ch);
        if (is_string($response) && $response !== '') {
            break;
        }
        if ($err !== 60) {
            break;
        }
    }
} else {
    $context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true, 'header' => "User-Agent: FOLIVRO-Academic-Demo/1.0\r\nAccept: application/json\r\n"], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $response = @file_get_contents($url, false, $context);
    if (!is_string($response) || $response === '') {
        $contextFallback = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true, 'header' => "User-Agent: FOLIVRO-Academic-Demo/1.0\r\nAccept: application/json\r\n"], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $response = @file_get_contents($url, false, $contextFallback);
    }
}
$payload = is_string($response) ? json_decode($response, true) : null;
$items = is_array($payload) && isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $status = isset($item['status']) && is_string($item['status']) ? $item['status'] : 'restricted';
    $itemUrl = isset($item['itemURL']) && is_string($item['itemURL']) ? $item['itemURL'] : '';
    $parts = parse_url($itemUrl);
    $host = strtolower($parts['host'] ?? '');
    $path = $parts['path'] ?? '';
    if (!in_array($host, ['archive.org', 'www.archive.org'], true) || !preg_match('#^/(stream|details)/[A-Za-z0-9._-]+#', $path)) {
        continue;
    }
    $secureUrl = 'https://www.archive.org' . $path;
    if ($status === 'full access') {
        $out = json_encode(['availability' => 'full_access', 'readerUrl' => $secureUrl . '?ui=embed'], JSON_UNESCAPED_SLASHES);
        @file_put_contents($cacheFile, $out);
        echo $out;
        exit;
    }
    if ($status === 'lendable' || $status === 'checked out') {
        $out = json_encode(['availability' => 'borrow', 'externalUrl' => $secureUrl, 'status' => $status], JSON_UNESCAPED_SLASHES);
        @file_put_contents($cacheFile, $out);
        echo $out;
        exit;
    }
}
$out = json_encode(['availability' => 'unavailable'], JSON_UNESCAPED_UNICODE);
if (is_array($payload)) {
    @file_put_contents($cacheFile, $out);
}
echo $out;
