<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];

// Consulta as obras do autor com contagem de capítulos e resumo de vendas simuladas (RF21).
$q = $pdo->prepare(
    "SELECT a.id_autor_dos_livros, a.titulo_autor_dos_livros, a.preco_dos_livros_autor, a.status_autor_dos_livros,
            a.modalidade_distribuicao, a.revisao_publicacao, a.motivo_bloqueio_autor,
            a.declaracao_autoria, h.sinopse_historias, h.capa_historias, h.generos_historias, h.visualizacoes_historias,
            h.aviso_conteudo_historias,
            (SELECT COUNT(*) FROM capitulos c WHERE c.id_historias = h.id_historias) AS capitulos,
            (SELECT COUNT(*) FROM compra cp WHERE cp.id_autor_dos_livros = a.id_autor_dos_livros AND cp.status_compra = 'concluida') AS total_vendas,
            (SELECT COALESCE(SUM(cp.ingresso_autor_compra), 0) FROM compra cp WHERE cp.id_autor_dos_livros = a.id_autor_dos_livros AND cp.status_compra = 'concluida') AS receita_autor
       FROM autor_dos_livros a
       JOIN historias h ON h.id_historias = a.id_historias
      WHERE a.id_autor = ?
   ORDER BY a.data_atualizacao_autor DESC, a.id_autor_dos_livros DESC"
);
$q->execute([$uid]);
$works = $q->fetchAll();

$vendasTotais = 0;
$receitaTotal = 0.0;
$visualizacoesTotais = 0;
foreach ($works as $item) {
    $vendasTotais += (int) $item['total_vendas'];
    $receitaTotal += (float) $item['receita_autor'];
    $visualizacoesTotais += (int) $item['visualizacoes_historias'];
}
$qualificacaoPainelAutor = folivro_avaliar_qualificacao_autor($pdo, $uid, true);
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Livros escritos | FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261104_20">
    <link rel="stylesheet" href="home.css?v=20261104_20">
</head>

<body>

    <?php $menuAtivo = 'biblioteca-autor'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <h1>Livros escritos e painel de vendas</h1>
        <p>Esta biblioteca reúne as obras que você criou. A publicação e o acompanhamento de vendas simuladas são gerenciados por obra.</p>

        <div class="banner-modalidade-publicacao modal-pub-<?= folivro_escape($qualificacaoPainelAutor['modalidade']) ?>" role="status">
            <strong>Modalidade de publicação:</strong>
            <span><?= folivro_escape($qualificacaoPainelAutor['mensagem_interface']) ?></span>
        </div>

        <section class="resumo-autor-grade" aria-label="Resumo do autor (simulação acadêmica)">
            <article class="card-kpi-autor">
                <span>Obras criadas</span>
                <strong><?= count($works) ?></strong>
            </article>
            <article class="card-kpi-autor">
                <span>Visualizações totais</span>
                <strong><?= $visualizacoesTotais ?></strong>
            </article>
            <article class="card-kpi-autor">
                <span>Vendas concluídas</span>
                <strong><?= $vendasTotais ?></strong>
            </article>
            <article class="card-kpi-autor">
                <span>Repasse acumulado (80%)</span>
                <strong>R$ <?= number_format($receitaTotal, 2, ',', '.') ?></strong>
            </article>
        </section>

        <p><a class="botao-adicionar-livro" href="escrever.php">Escrever novo livro</a></p>

        <div class="biblioteca-lista">
            <?php if (!$works): ?>
                <p class="estado-vazio">Você ainda não escreveu livros. Use “Escrever” para começar.</p>
            <?php else: ?>
                <?php foreach ($works as $w): ?>
                    <article class="livro-card">
                        <div class="livro-capa">
                            <?php if (!empty($w['capa_historias'])): ?>
                                <img src="<?= folivro_escape($w['capa_historias']) ?>" alt="Capa de <?= folivro_escape($w['titulo_autor_dos_livros']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="capa-fallback"><?= folivro_escape($w['titulo_autor_dos_livros']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="livro-info">
                            <?php $classWork = folivro_normalizar_classificacao((string) ($w['aviso_conteudo_historias'] ?? 'livre')); ?>
                            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                                <span class="selo-status <?= $w['status_autor_dos_livros'] === 'a_venda' ? 'selo-comprado' : ($w['status_autor_dos_livros'] === 'publicada' ? 'selo-lido' : '') ?>">
                                    <?= folivro_escape(folivro_status_label($w['status_autor_dos_livros'])) ?>
                                </span>
                                <span class="selo-status <?= ($w['revisao_publicacao'] ?? '') === 'aprovada' ? 'selo-lido' : (($w['revisao_publicacao'] ?? '') === 'pendente' ? 'selo-comprado' : '') ?>">
                                    Revisão: <?= folivro_escape(folivro_status_label((string) ($w['revisao_publicacao'] ?? 'nao_solicitada'))) ?>
                                </span>
                                <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($classWork) ?>" title="<?= folivro_escape(folivro_aviso_conteudo_label($classWork)) ?>">
                                    <?= folivro_escape(folivro_classificacao_sigla($classWork)) ?>
                                </span>
                            </div>
                            <h2><?= folivro_escape($w['titulo_autor_dos_livros']) ?></h2>
                            <?php $generosAutorCard = folivro_generos_lista((string) ($w['generos_historias'] ?? '')); ?>
                            <?php if ($generosAutorCard): ?>
                                <div class="tags-generos-card" aria-label="Gêneros da obra">
                                    <?php foreach ($generosAutorCard as $gAutorItem): ?>
                                        <span class="tag-genero-card"><?= folivro_escape($gAutorItem) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <p><?= folivro_escape($w['sinopse_historias']) ?></p>
                            <?php if (!empty($w['motivo_bloqueio_autor'])): ?>
                                <p class="aviso-direitos"><small><strong>Nota editorial:</strong> <?= folivro_escape($w['motivo_bloqueio_autor']) ?></small></p>
                            <?php endif; ?>
                            <p><?= (int) $w['capitulos'] ?> capítulo(s) · <?= ($w['modalidade_distribuicao'] ?? '') === 'gratuita' ? 'Gratuita' : 'R$ ' . number_format((float) $w['preco_dos_livros_autor'], 2, ',', '.') ?></p>
                            <p><?= (int) $w['visualizacoes_historias'] ?> leitura(s) · <?= (int) $w['total_vendas'] ?> venda(s) (R$ <?= number_format((float) $w['receita_autor'], 2, ',', '.') ?>)</p>
                            <a class="botao-adicionar-livro" href="gerenciar-obra.php?id=<?= (int) $w['id_autor_dos_livros'] ?>">Gerenciar, publicar ou vender</a>
                            <a href="obra.php?id=<?= (int) $w['id_autor_dos_livros'] ?>">Visualizar obra e capítulos</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

</body>

</html>
