<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/dominio_publico.php';
require_once __DIR__ . '/../config/traducao.php';
folivro_require_login();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método não permitido.']));
}
if (!folivro_verify_csrf()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Token de segurança inválido. Atualize a página.'], JSON_UNESCAPED_UNICODE));
}
$data = json_decode(file_get_contents('php://input') ?: '', true);
$workId = is_array($data) && isset($data['workId']) && is_string($data['workId']) ? $data['workId'] : '';
$title = is_array($data) && isset($data['title']) && is_string($data['title']) ? trim($data['title']) : '';
if (!preg_match('/^OL[0-9]+W$/', $workId) || $title === '' || mb_strlen($title, 'UTF-8') > 800) {
    http_response_code(422);
    exit(json_encode(['error' => 'Dados do livro inválidos.'], JSON_UNESCAPED_UNICODE));
}

$obraDp = folivro_dp_encontrar_obra($workId, $title);
if ($obraDp !== null) {
    $title = (string) $obraDp['title'];
    $classNorm = folivro_normalizar_classificacao((string) ($obraDp['classificacao'] ?? 'livre'));
} else {
    $title = mb_substr($title, 0, 200, 'UTF-8');
    $infoTitulo = folivro_localizar_titulo($title);
    $title = $infoTitulo['titulo'];
    $classNorm = folivro_estimar_classificacao_catalogo($title, $workId);
}

if (folivro_classificacao_eh_maior18($classNorm) && !folivro_usuario_maior_de_idade()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Conteúdo bloqueado (+18): sua conta possui menos de 18 anos com base na data de nascimento cadastrada.'], JSON_UNESCAPED_UNICODE));
}
$authors = $obraDp !== null
    ? $obraDp['authors']
    : (is_array($data['authors'] ?? null) ? array_values(array_filter($data['authors'], 'is_string')) : []);
$author = mb_substr(trim(implode(', ', array_slice($authors, 0, 3))), 0, 150, 'UTF-8');
$year = $obraDp !== null ? (int) $obraDp['year'] : filter_var($data['year'] ?? null, FILTER_VALIDATE_INT);
$pages = $obraDp !== null ? count($obraDp['capitulos'] ?? []) : filter_var($data['pages'] ?? 0, FILTER_VALIDATE_INT);
$pages = is_int($pages) && $pages > 0 ? min($pages, 100000) : 0;
$cover = $obraDp !== null
    ? ($obraDp['coverUrl'] ?? null)
    : (is_string($data['coverUrl'] ?? null) && strlen($data['coverUrl']) <= 255 ? $data['coverUrl'] : null);
if ($cover !== null && !preg_match('#^uploads/capas/[a-zA-Z0-9._-]+\.(svg|png|jpe?g|webp)$#', $cover)) {
    $parts = parse_url($cover);
    if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'covers.openlibrary.org') {
        $cover = null;
    }
}
try {
    $pdo = folivro_database();
    $find = $pdo->prepare('SELECT id_livro FROM livro WHERE id_externo_livro = ? LIMIT 1');
    $find->execute([$workId]);
    $idLivro = $find->fetchColumn();
    $publishDate = is_int($year) && $year >= 1000 && $year <= 9999 ? sprintf('%04d-01-01', $year) : null;
    $autorFinal = $author !== '' ? $author : 'Autoria não informada';
    if (!$idLivro) {
        $insert = $pdo->prepare(
            "INSERT INTO livro (titulo_livro, autor_livro, capa_livro, data_publicacao_livro, paginas_livro, classificacao_livro, fonte_livro, id_externo_livro)
             VALUES (?, ?, ?, ?, ?, ?, 'openlibrary', ?)"
        );
        $insert->execute([$title, $autorFinal, $cover, $publishDate, $pages ?: null, $classNorm, $workId]);
        $idLivro = (int) $pdo->lastInsertId();
    } else {
        $idLivro = (int) $idLivro;
        $pdo->prepare(
            "UPDATE livro
                SET titulo_livro = ?,
                    autor_livro = ?,
                    capa_livro = COALESCE(?, capa_livro),
                    data_publicacao_livro = COALESCE(?, data_publicacao_livro),
                    paginas_livro = COALESCE(?, paginas_livro),
                    classificacao_livro = ?
              WHERE id_livro = ?"
        )->execute([$title, $autorFinal, $cover, $publishDate, $pages ?: null, $classNorm, $idLivro]);
    }
    $userId = (int) $_SESSION['id_usuario'];
    $library = $pdo->prepare("INSERT IGNORE INTO livraria (id_usuario, id_livro, status_livraria) VALUES (?, ?, 'lendo')");
    $library->execute([$userId, $idLivro]);
    $progress = $pdo->prepare('INSERT IGNORE INTO progressao_leitura (id_usuario, id_livro, total_paginas_progressao) VALUES (?, ?, ?)');
    $progress->execute([$userId, $idLivro, $pages]);
    $readerUrl = ($obraDp !== null ? 'leitor.php?id=' : 'livro.php?id=') . $idLivro;
    echo json_encode(['ok' => true, 'id_livro' => $idLivro, 'readerUrl' => $readerUrl], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível salvar o livro na biblioteca.'], JSON_UNESCAPED_UNICODE);
}
