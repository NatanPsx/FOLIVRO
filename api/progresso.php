<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
folivro_require_login();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método não permitido.']));
}
if (!folivro_verify_csrf()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Token de segurança inválido.'], JSON_UNESCAPED_UNICODE));
}
$data = json_decode(file_get_contents('php://input') ?: '', true);
$idLivro = filter_var($data['id_livro'] ?? null, FILTER_VALIDATE_INT);
$page = filter_var($data['pagina_atual'] ?? null, FILTER_VALIDATE_INT);
$total = filter_var($data['total_paginas'] ?? null, FILTER_VALIDATE_INT);
if (!is_int($idLivro) || $idLivro < 1 || !is_int($page) || $page < 0 || !is_int($total) || $total < 0 || $total > 100000) {
    http_response_code(422);
    exit(json_encode(['error' => 'Informe páginas válidas.'], JSON_UNESCAPED_UNICODE));
}
if ($page > $total) {
    http_response_code(422);
    exit(json_encode(['error' => 'A página atual não pode ultrapassar o total informado.'], JSON_UNESCAPED_UNICODE));
}
$pdo = folivro_database();
$userId = (int) $_SESSION['id_usuario'];
$owned = $pdo->prepare('SELECT 1 FROM livraria WHERE id_usuario = ? AND id_livro = ? LIMIT 1');
$owned->execute([$userId, $idLivro]);
if (!$owned->fetchColumn()) {
    http_response_code(404);
    exit(json_encode(['error' => 'Livro não encontrado na sua biblioteca.'], JSON_UNESCAPED_UNICODE));
}
$percent = $total > 0 ? min(100, round(($page / $total) * 100, 2)) : 0;
$upsert = $pdo->prepare(
    'INSERT INTO progressao_leitura (id_usuario, id_livro, pagina_atual_progressao, total_paginas_progressao, porcentagem_progressao)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE pagina_atual_progressao = VALUES(pagina_atual_progressao), total_paginas_progressao = VALUES(total_paginas_progressao), porcentagem_progressao = VALUES(porcentagem_progressao)'
);
$upsert->execute([$userId, $idLivro, $page, $total, $percent]);
$status = $total > 0 && $page >= $total ? 'lido' : 'lendo';
$updateLibrary = $pdo->prepare('UPDATE livraria SET status_livraria = ? WHERE id_usuario = ? AND id_livro = ?');
$updateLibrary->execute([$status, $userId, $idLivro]);

// Atualiza contador de pontos (1 ponto por página lida + 20 por livro concluído).
$totais = $pdo->prepare(
    "SELECT COALESCE(SUM(l.status_livraria = 'lido'), 0) AS lidos,
            COALESCE(SUM(p.pagina_atual_progressao), 0) AS paginas
       FROM livraria l
  LEFT JOIN progressao_leitura p ON p.id_usuario = l.id_usuario AND p.id_livro = l.id_livro
      WHERE l.id_usuario = ?"
);
$totais->execute([$userId]);
$resumo = $totais->fetch() ?: ['lidos' => 0, 'paginas' => 0];
$pontos = ((int) $resumo['paginas']) + (((int) $resumo['lidos']) * 20);

$upPontos = $pdo->prepare(
    'INSERT INTO contador_pontos (id_usuario, pontos_contador_de_pontos) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE pontos_contador_de_pontos = VALUES(pontos_contador_de_pontos)'
);
$upPontos->execute([$userId, $pontos]);

// Atualiza metas_leitura em andamento do usuário.
$pdo->prepare(
    "UPDATE metas_leitura
        SET progresso_metas = CASE WHEN tipo_metas = 'paginas' THEN ? ELSE ? END,
            status_metas = CASE
                WHEN (tipo_metas = 'paginas' AND ? >= quantidade_metas) OR (tipo_metas = 'livros' AND ? >= quantidade_metas)
                THEN 'concluida'
                ELSE 'em_andamento'
            END
      WHERE id_usuario = ? AND status_metas IN ('em_andamento', 'concluida')"
)->execute([(int) $resumo['paginas'], (int) $resumo['lidos'], (int) $resumo['paginas'], (int) $resumo['lidos'], $userId]);

echo json_encode(['ok' => true, 'porcentagem' => $percent, 'status' => $status, 'statusLabel' => folivro_status_label($status)], JSON_UNESCAPED_UNICODE);
