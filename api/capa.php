<?php
declare(strict_types=1);

/**
 * Endpoint de Cache Local e Fallback Editorial de Capas — FOLIVRO
 *
 * Evita falhas de carregamento, bloqueios de SSL/CORS ou lentidão do Archive.org / Open Library:
 * 1. Se a capa da Open Library (id + s) já estiver salva em uploads/capas/cache_ol/, entrega o arquivo local imediatamente.
 * 2. Se ainda não estiver em cache, baixa uma única vez no servidor, valida (> 800 bytes) e salva em uploads/capas/cache_ol/.
 * 3. Caso a rede externa falhe ou o livro não possua capa, gera instantaneamente uma capa editorial SVG de alta qualidade com título e autoria.
 */

$coverId = isset($_GET['id']) ? preg_replace('/[^0-9]/', '', (string) $_GET['id']) : '';
$size = isset($_GET['s']) && in_array(strtoupper((string) $_GET['s']), ['S', 'M', 'L'], true)
    ? strtoupper((string) $_GET['s'])
    : 'M';
$rawUrl = isset($_GET['u']) && is_string($_GET['u']) ? trim($_GET['u']) : '';
$titulo = isset($_GET['t']) && is_string($_GET['t']) ? trim($_GET['t']) : 'Obra Literária';
$autor = isset($_GET['a']) && is_string($_GET['a']) ? trim($_GET['a']) : 'Acervo FOLIVRO';

if ($coverId === '' && $rawUrl !== '') {
    if (preg_match('#covers\.openlibrary\.org/b/id/([0-9]+)-([SML])\.jpg#i', $rawUrl, $m)) {
        $coverId = $m[1];
        $size = strtoupper($m[2]);
    }
}

$cacheDir = dirname(__DIR__) . '/uploads/capas/cache_ol';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}

if ($coverId !== '') {
    $localFile = $cacheDir . '/' . $coverId . '-' . $size . '.jpg';
    if (is_file($localFile) && filesize($localFile) > 800) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=2592000, immutable');
        header('Content-Length: ' . (string) filesize($localFile));
        readfile($localFile);
        exit;
    }

    $remoteUrl = 'https://covers.openlibrary.org/b/id/' . $coverId . '-' . $size . '.jpg';
    $imgBytes = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($remoteUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'FOLIVRO-CoverCache/1.0',
        ]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200 && is_string($res) && strlen($res) > 800) {
            $imgBytes = $res;
        }
    }

    if ($imgBytes !== null) {
        @file_put_contents($localFile, $imgBytes);
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=2592000, immutable');
        header('Content-Length: ' . (string) strlen($imgBytes));
        echo $imgBytes;
        exit;
    }
}

// Fallback Editorial SVG (0 falhas visuais no navegador)
if ($titulo === '') {
    $titulo = 'Obra Literária';
}
if ($autor === '') {
    $autor = 'Acervo FOLIVRO';
}

$paletas = [
    ['bg1' => '#3b0a07', 'bg2' => '#1e0403', 'accent' => '#d6a85a'],
    ['bg1' => '#10253f', 'bg2' => '#081322', 'accent' => '#d8b26e'],
    ['bg1' => '#183324', 'bg2' => '#0b1a12', 'accent' => '#cfab68'],
    ['bg1' => '#331c38', 'bg2' => '#190c1c', 'accent' => '#dbb678'],
    ['bg1' => '#3d2210', 'bg2' => '#1f1007', 'accent' => '#e0b872'],
    ['bg1' => '#2b1b17', 'bg2' => '#140c0a', 'accent' => '#d4a359'],
];
$hash = abs(crc32(mb_strtolower($titulo, 'UTF-8')));
$pal = $paletas[$hash % count($paletas)];

// Quebra o título em até 4 linhas equilibradas
$palavras = preg_split('/\s+/u', $titulo) ?: [$titulo];
$linhas = [];
$linhaAtual = '';
foreach ($palavras as $p) {
    $teste = $linhaAtual === '' ? $p : $linhaAtual . ' ' . $p;
    if (mb_strlen($teste, 'UTF-8') > 17 && $linhaAtual !== '') {
        $linhas[] = $linhaAtual;
        $linhaAtual = $p;
        if (count($linhas) >= 3) {
            break;
        }
    } else {
        $linhaAtual = $teste;
    }
}
if ($linhaAtual !== '' && count($linhas) < 4) {
    if (mb_strlen($linhaAtual, 'UTF-8') > 20) {
        $linhaAtual = mb_substr($linhaAtual, 0, 18, 'UTF-8') . '…';
    }
    $linhas[] = $linhaAtual;
}

$autorCurto = mb_strtoupper(mb_substr($autor, 0, 28, 'UTF-8'), 'UTF-8');
$esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');

$yBase = 235 - (count($linhas) - 1) * 16;
$tspans = '';
foreach ($linhas as $i => $l) {
    $y = $yBase + ($i * 34);
    $tspans .= '<text x="160" y="' . $y . '" text-anchor="middle" fill="#f7f1e8" font-family="Georgia, \'Times New Roman\', serif" font-size="25" font-weight="bold">' . $esc($l) . '</text>' . "\n";
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');

echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 480" width="320" height="480">
    <defs>
        <radialGradient id="glow" cx="50%" cy="38%" r="65%">
            <stop offset="0%" stop-color="{$pal['bg1']}"/>
            <stop offset="100%" stop-color="{$pal['bg2']}"/>
        </radialGradient>
    </defs>
    <rect width="320" height="480" rx="10" fill="url(#glow)"/>
    <rect x="18" y="18" width="284" height="444" rx="6" fill="none" stroke="{$pal['accent']}" stroke-width="1.5" stroke-opacity="0.65"/>
    <rect x="26" y="26" width="268" height="428" rx="4" fill="none" stroke="{$pal['accent']}" stroke-width="0.8" stroke-opacity="0.38"/>
    <text x="160" y="64" text-anchor="middle" fill="{$pal['accent']}" font-family="Georgia, serif" font-size="11.5" letter-spacing="2.5">{$esc($autorCurto)}</text>
    <line x1="88" y1="80" x2="232" y2="80" stroke="{$pal['accent']}" stroke-width="0.9" stroke-opacity="0.5"/>
    <circle cx="160" cy="138" r="28" fill="none" stroke="{$pal['accent']}" stroke-width="1.2" stroke-opacity="0.6"/>
    <path d="M146 130 Q153 125 160 132 V149 Q153 142 146 143 Z M174 130 Q167 125 160 132 V149 Q167 142 174 143 Z" fill="none" stroke="{$pal['accent']}" stroke-width="1.5"/>
    {$tspans}
    <line x1="105" y1="340" x2="215" y2="340" stroke="{$pal['accent']}" stroke-width="0.9" stroke-opacity="0.5"/>
    <circle cx="160" cy="340" r="3" fill="{$pal['accent']}"/>
    <text x="160" y="428" text-anchor="middle" fill="{$pal['accent']}" font-family="Georgia, serif" font-size="10.5" letter-spacing="3" opacity="0.85">EDIÇÃO FOLIVRO</text>
</svg>
SVG;
