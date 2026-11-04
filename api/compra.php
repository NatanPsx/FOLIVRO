<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

folivro_database();
folivro_require_login();
header('Content-Type: application/json; charset=utf-8');

function compra_resposta(int $status, array $body): void
{
    http_response_code($status);
    exit(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    compra_resposta(405, ['error' => 'Método não permitido.']);
}
if (!folivro_verify_csrf()) {
    compra_resposta(403, ['error' => 'Token de segurança inválido.']);
}

$data = json_decode(file_get_contents('php://input') ?: '', true);
$id = filter_var(is_array($data) ? ($data['id_obra'] ?? null) : null, FILTER_VALIDATE_INT);
if (!is_int($id) || $id < 1) {
    compra_resposta(422, ['error' => 'Obra inválida.']);
}

$pdo = folivro_database();
$uid = (int) $_SESSION['id_usuario'];
$readerUrl = 'obra.php?id=' . $id;

// A obra precisa estar aprovada na revisão humana, à venda, publicada e com declarações válidas, sem suspensão preventiva.
$q = $pdo->prepare(
    "SELECT a.id_autor_dos_livros, a.id_autor, a.preco_dos_livros_autor, h.aviso_conteudo_historias
       FROM autor_dos_livros a
       JOIN historias h ON h.id_historias = a.id_historias
      WHERE a.id_autor_dos_livros = ? AND a.status_autor_dos_livros = 'a_venda'
        AND a.revisao_publicacao = 'aprovada'
        AND a.declaracao_autoria = 1
        AND (a.modalidade_distribuicao = 'gratuita' OR a.declaracao_comercializacao = 1)
        AND h.status_historias = 'publicada'
        AND NOT EXISTS (
            SELECT 1 FROM denuncias d
             WHERE d.tipo_denuncias = 'obra_autoral'
               AND d.item_denuncia = a.id_autor_dos_livros
               AND d.status_denuncias IN ('aberta', 'em_analise')
               AND d.suspensao_preventiva = 1
        )
      LIMIT 1"
);
$q->execute([$id]);
$obra = $q->fetch();
if (!$obra) {
    compra_resposta(404, ['error' => 'Esta obra não está disponível para compra.']);
}
if ((int) $obra['id_autor'] === $uid) {
    compra_resposta(403, ['error' => 'Você não pode comprar sua própria obra.']);
}
if (folivro_classificacao_eh_maior18((string) ($obra['aviso_conteudo_historias'] ?? 'livre')) && !folivro_usuario_maior_de_idade($pdo, $uid)) {
    compra_resposta(403, ['error' => 'Compra bloqueada (+18): esta obra é restrita para maiores de 18 anos de acordo com sua data de nascimento cadastrada.']);
}

$owned = $pdo->prepare('SELECT 1 FROM livraria_digital WHERE id_usuario = ? AND id_autor_dos_livros = ?');
$owned->execute([$uid, $id]);
if ($owned->fetchColumn()) {
    compra_resposta(200, ['ok' => true, 'readerUrl' => $readerUrl]);
}

// Compra simulada: registro da compra + liberação na biblioteca digital na mesma transação.
$pdo->beginTransaction();
try {
    $price = (float) $obra['preco_dos_livros_autor'];
    $insert = $pdo->prepare(
        "INSERT INTO compra (id_usuario, id_autor_dos_livros, valor_pago_compra, percentual_autor_compra,
                             ingresso_autor_compra, percentual_plataforma_compra, status_compra)
         VALUES (?, ?, ?, 80, ?, 20, 'concluida')"
    );
    $insert->execute([$uid, $id, $price, round($price * 0.8, 2)]);
    $purchase = (int) $pdo->lastInsertId();

    $grant = $pdo->prepare('INSERT INTO livraria_digital (id_usuario, id_autor_dos_livros, id_compra) VALUES (?, ?, ?)');
    $grant->execute([$uid, $id, $purchase]);

    $pdo->commit();
    compra_resposta(200, ['ok' => true, 'readerUrl' => $readerUrl]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // 23000 = violação de UNIQUE (uq_ld_usuario_obra): clique duplo/concorrência; a obra já foi liberada.
    if ($e instanceof PDOException && $e->getCode() === '23000') {
        compra_resposta(200, ['ok' => true, 'readerUrl' => $readerUrl]);
    }
    compra_resposta(500, ['error' => 'Não foi possível concluir a compra simulada.']);
}
