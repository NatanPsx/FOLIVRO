<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/dominio_publico.php';

folivro_require_login();
header('Content-Type: application/json; charset=utf-8');

$workId = isset($_GET['work']) && is_string($_GET['work']) ? trim($_GET['work']) : '';
$titulo = isset($_GET['titulo']) && is_string($_GET['titulo']) ? trim($_GET['titulo']) : '';
$cap = isset($_GET['cap']) ? max(1, (int) $_GET['cap']) : 1;

$obra = folivro_dp_encontrar_obra($workId, $titulo);
if ($obra === null) {
    echo json_encode(['disponivel' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$capitulos = $obra['capitulos'] ?? [];
$totalCapitulos = count($capitulos);
$capIndex = max(0, min($totalCapitulos - 1, $cap - 1));
$capMeta = $capitulos[$capIndex] ?? ['ordem' => 1, 'titulo' => 'Capítulo I', 'wikiPage' => ''];

$conteudoHtml = null;
if (!empty($capMeta['wikiPage'])) {
    $conteudoHtml = folivro_dp_buscar_html_wikisource((string) $capMeta['wikiPage']);
}
if ($conteudoHtml === null || trim($conteudoHtml) === '') {
    $conteudoHtml = (string) ($obra['fallbackCap1'] ?? '<p>Texto em domínio público.</p>');
}

$palavras = str_word_count(strip_tags($conteudoHtml));
$tempoMin = max(1, (int) ceil($palavras / 180));

echo json_encode([
    'disponivel' => true,
    'workId' => $obra['workId'],
    'titulo' => $obra['title'],
    'autor' => $obra['authors'][0] ?? 'Autor Clássico',
    'anoFalecimentoAutor' => $obra['authorDeathYear'],
    'anoPublicacao' => $obra['year'],
    'edicaoReferencia' => $obra['edicaoReferencia'],
    'baseLegal' => 'Domínio Público Brasileiro — Art. 41 da Lei nº 9.610/1998 (mais de 70 anos do falecimento do autor) · Fonte: Acervo Wikisource PT / Domínio Público.',
    'capituloAtual' => [
        'ordem' => (int) $capMeta['ordem'],
        'titulo' => (string) $capMeta['titulo'],
        'tempoMin' => $tempoMin,
        'conteudoHtml' => $conteudoHtml,
    ],
    'totalCapitulos' => $totalCapitulos,
    'listaCapitulos' => array_map(static fn(array $c): array => [
        'ordem' => (int) $c['ordem'],
        'titulo' => (string) $c['titulo'],
    ], $capitulos),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
