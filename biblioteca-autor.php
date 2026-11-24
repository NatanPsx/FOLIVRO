<?php
declare(strict_types=1);
require_once __DIR__ . '/config/loja.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];

$cfgLoja = folivro_loja_config();
$pctAutor = round(100 - (float) ($cfgLoja['taxa_percentual'] ?? 10.0), 0);

// Consulta as obras do estúdio integrando vendas reais/simuladas da loja (loja_pedidos + fallback compra legado).
$q = $pdo->prepare(
    "SELECT a.id_autor_dos_livros, a.titulo_autor_dos_livros, a.preco_dos_livros_autor, a.status_autor_dos_livros,
            a.modalidade_distribuicao, a.revisao_publicacao, a.motivo_bloqueio_autor,
            a.declaracao_autoria, h.sinopse_historias, h.capa_historias, h.generos_historias, h.visualizacoes_historias,
            h.aviso_conteudo_historias,
            (SELECT COUNT(*) FROM capitulos c WHERE c.id_historias = h.id_historias) AS capitulos,
            lp.id_produto AS id_produto_loja,
            (
                COALESCE((SELECT COUNT(*) FROM loja_pedidos op WHERE op.id_produto = lp.id_produto AND op.status_pedido = 'pago'), 0)
                + CASE WHEN lp.id_produto IS NULL
                       THEN (SELECT COUNT(*) FROM compra cp WHERE cp.id_autor_dos_livros = a.id_autor_dos_livros AND cp.status_compra = 'concluida')
                       ELSE 0 END
            ) AS total_vendas,
            (
                COALESCE((SELECT SUM(op.valor_liquido_centavos) FROM loja_pedidos op WHERE op.id_produto = lp.id_produto AND op.status_pedido = 'pago'), 0) / 100.0
                + CASE WHEN lp.id_produto IS NULL
                       THEN COALESCE((SELECT SUM(cp.ingresso_autor_compra) FROM compra cp WHERE cp.id_autor_dos_livros = a.id_autor_dos_livros AND cp.status_compra = 'concluida'), 0)
                       ELSE 0 END
            ) AS receita_autor
       FROM autor_dos_livros a
       JOIN historias h ON h.id_historias = a.id_historias
  LEFT JOIN loja_produtos lp ON lp.id_autor_dos_livros = a.id_autor_dos_livros
      WHERE a.id_autor = ?
   ORDER BY a.data_atualizacao_autor DESC, a.id_autor_dos_livros DESC"
);
$q->execute([$uid]);
$works = $q->fetchAll(PDO::FETCH_ASSOC);

$vendasTotais = 0;
$receitaTotal = 0.0;
$visualizacoesTotais = 0;
foreach ($works as $item) {
    $vendasTotais += (int) $item['total_vendas'];
    $receitaTotal += (float) $item['receita_autor'];
    $visualizacoesTotais += (int) $item['visualizacoes_historias'];
}

$qualificacaoPainelAutor = folivro_avaliar_qualificacao_autor($pdo, $uid, true);
$aprovAutoGlobal = folivro_aprovacao_automatica_obras_ativa($pdo);
$pubImediata = ($aprovAutoGlobal || !empty($qualificacaoPainelAutor['qualificado_automatico']));
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minhas Obras | FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261124_01">
    <link rel="stylesheet" href="home.css?v=20261123_01">
    <link rel="stylesheet" href="estante.css?v=20261124_01">
</head>

<body>

    <?php $menuAtivo = 'biblioteca-autor'; require __DIR__ . '/includes/menu-lateral.php'; ?>
    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:14px; margin-bottom:14px;">
            <div>
                <h1 class="titulo-com-icone-folivro" style="margin:0 0 6px;"><?= folivro_icone('livro-indice-f', 'folivro-icon-lg') ?> Minhas Obras</h1>
                <p style="margin:0; color:var(--texto-secundario);">Gerencie seus livros escritos, acompanhe capítulos, vendas e publique diretamente na loja.</p>
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                <a class="botao-adicionar-livro" href="escrever.php">+ Escrever novo livro</a>
                <a class="filtro-pilula" href="loja-autor-obras.php">Gerenciar obras na loja</a>
                <?php if (folivro_pagamentos_ativos()): ?>
                <a class="filtro-pilula" href="painel-vendas.php">Painel de vendas</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="banner-modalidade-publicacao modal-pub-<?= $pubImediata ? 'automatica' : folivro_escape($qualificacaoPainelAutor['modalidade']) ?>" role="status">
            <strong>Publicação:</strong>
            <span>
                <?= $pubImediata
                    ? 'Publicação imediata ativa — suas obras originais são liberadas automaticamente na loja.'
                    : folivro_escape($qualificacaoPainelAutor['mensagem_interface']) ?>
            </span>
        </div>

        <section class="resumo-autor-grade" aria-label="Resumo do autor">
            <article class="card-kpi-autor">
                <span>Obras criadas</span>
                <strong><?= count($works) ?></strong>
            </article>
            <article class="card-kpi-autor">
                <span>Visualizações totais</span>
                <strong><?= $visualizacoesTotais ?></strong>
            </article>
            <?php if (folivro_pagamentos_ativos()): ?>
            <article class="card-kpi-autor">
                <span>Vendas concluídas</span>
                <strong><?= $vendasTotais ?></strong>
            </article>
            <article class="card-kpi-autor">
                <span>Receita líquida (<?= (int) $pctAutor ?>%)</span>
                <strong>R$ <?= number_format($receitaTotal, 2, ',', '.') ?></strong>
            </article>
            <?php endif; ?>
        </section>

        <?php if ($works): ?>
            <div class="filtros-biblioteca" style="margin-top:16px;" role="toolbar" aria-label="Filtrar obras">
                <input type="search" id="filtro-texto-obras-autor" placeholder="Buscar obra por título..." aria-label="Buscar obra por título" style="padding:8px 12px; border-radius:999px; border:1px solid var(--borda-forte); background:var(--fundo-superficie); color:var(--texto-principal); font:inherit; min-width:220px;">
                <button type="button" class="filtro-pilula ativo" data-filtro-obra="todas">Todas (<?= count($works) ?>)</button>
                <button type="button" class="filtro-pilula" data-filtro-obra="a_venda">Publicadas</button>
                <button type="button" class="filtro-pilula" data-filtro-obra="rascunho">Rascunhos</button>
                <button type="button" class="filtro-pilula" data-filtro-obra="pendente">Em revisão</button>
            </div>
        <?php endif; ?>

        <div class="biblioteca-lista estante-biblioteca" id="lista-obras-autor" style="margin-top:14px;">
            <?php if (!$works): ?>
                <p class="estado-vazio">Você ainda não escreveu livros. Use “Escrever novo livro” para começar.</p>
            <?php else: ?>
                <?php foreach ($works as $w): ?>
                    <?php
                    $classWork = folivro_normalizar_classificacao((string) ($w['aviso_conteudo_historias'] ?? 'livre'));
                    $statusObra = (string) $w['status_autor_dos_livros'];
                    $revObra = (string) ($w['revisao_publicacao'] ?? 'nao_solicitada');
                    $ehPublicada = ($statusObra === 'a_venda' && $revObra === 'aprovada');
                    ?>
                    <article class="livro-card"
                             data-titulo="<?= folivro_escape(mb_strtolower((string) $w['titulo_autor_dos_livros'], 'UTF-8')) ?>"
                             data-status-obra="<?= folivro_escape($statusObra) ?>"
                             data-revisao="<?= folivro_escape($revObra) ?>">
                        <div class="livro-capa">
                            <?php if (!empty($w['capa_historias'])): ?>
                                <img src="<?= folivro_escape($w['capa_historias']) ?>" alt="Capa de <?= folivro_escape($w['titulo_autor_dos_livros']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="capa-fallback"><?= folivro_escape($w['titulo_autor_dos_livros']) ?></div>
                            <?php endif; ?>
                            <span class="selo-classificacao-idade selo-classificacao-capa selo-idade-<?= folivro_escape($classWork) ?>" title="<?= folivro_escape(folivro_aviso_conteudo_label($classWork)) ?>">
                                <?= folivro_escape(folivro_classificacao_sigla($classWork)) ?>
                            </span>
                        </div>
                        <div class="livro-info">
                            <div class="linha-selos-card">
                                <span class="selo-status <?= $ehPublicada ? 'selo-lido' : ($statusObra === 'a_venda' ? 'selo-comprado' : '') ?>">
                                    <?= folivro_escape(folivro_status_label($statusObra)) ?>
                                </span>
                                <?php if ($revObra !== 'aprovada'): ?>
                                    <span class="selo-status <?= $revObra === 'pendente' ? 'selo-comprado' : '' ?>">
                                        <?= folivro_escape(folivro_status_label($revObra)) ?>
                                    </span>
                                <?php endif; ?>
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

                            <p><?= folivro_escape(mb_strimwidth((string) ($w['sinopse_historias'] ?? ''), 0, 120, '...', 'UTF-8')) ?></p>

                            <?php if (!empty($w['motivo_bloqueio_autor'])): ?>
                                <p class="aviso-direitos"><small><strong>Nota:</strong> <?= folivro_escape($w['motivo_bloqueio_autor']) ?></small></p>
                            <?php endif; ?>

                            <p style="font-size:0.9rem; color:var(--texto-secundario);">
                                <?= (int) $w['capitulos'] ?> cap. · <?= ($w['modalidade_distribuicao'] ?? '') === 'gratuita' ? 'Gratuita' : 'R$ ' . number_format((float) $w['preco_dos_livros_autor'], 2, ',', '.') ?>
                                <br>
                                <?= (int) $w['visualizacoes_historias'] ?> leitura(s) · <strong><?= (int) $w['total_vendas'] ?></strong> venda(s) (R$ <?= number_format((float) $w['receita_autor'], 2, ',', '.') ?>)
                            </p>

                            <div class="rodape-card-leitura" style="display:flex; flex-direction:column; gap:6px;">
                                <a class="botao-adicionar-livro btn-card-principal" href="gerenciar-obra.php?id=<?= (int) $w['id_autor_dos_livros'] ?>">Gerenciar obra e capítulos</a>
                                <div style="display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap; font-size:0.88rem;">
                                    <a href="obra.php?id=<?= (int) $w['id_autor_dos_livros'] ?>">Ler capítulos</a>
                                    <?php if (!empty($w['id_produto_loja']) && $ehPublicada): ?>
                                        <a href="loja-obra.php?id=<?= (int) $w['id_produto_loja'] ?>">Ver na loja &rarr;</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        (function () {
            const inputBusca = document.getElementById('filtro-texto-obras-autor');
            const botoes = document.querySelectorAll('[data-filtro-obra]');
            const cards = document.querySelectorAll('#lista-obras-autor .livro-card');
            let filtroAtivo = 'todas';

            function aplicarFiltro() {
                const q = (inputBusca ? inputBusca.value : '').trim().toLowerCase();
                cards.forEach((card) => {
                    const tit = card.dataset.titulo || '';
                    const st = card.dataset.statusObra || '';
                    const rev = card.dataset.revisao || '';
                    const bateTexto = q === '' || tit.includes(q);
                    let bateStatus = true;
                    if (filtroAtivo === 'a_venda') bateStatus = (st === 'a_venda' && rev === 'aprovada');
                    else if (filtroAtivo === 'rascunho') bateStatus = (st === 'rascunho');
                    else if (filtroAtivo === 'pendente') bateStatus = (rev === 'pendente');
                    card.hidden = !(bateTexto && bateStatus);
                });
            }

            if (inputBusca) inputBusca.addEventListener('input', aplicarFiltro);
            botoes.forEach((b) => {
                b.addEventListener('click', () => {
                    filtroAtivo = b.dataset.filtroObra || 'todas';
                    botoes.forEach((btn) => btn.classList.toggle('ativo', btn === b));
                    aplicarFiltro();
                });
            });
        })();
    </script>

</body>

</html>
