<?php
declare(strict_types=1);
require_once __DIR__ . '/config/loja.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];
$nomeUsuario = (string) ($_SESSION['nome_usuario'] ?? 'Leitor(a)');
$primeiroNome = trim(explode(' ', $nomeUsuario)[0] ?: $nomeUsuario);
$maiorDeIdade = folivro_usuario_maior_de_idade($pdo, $uid);

// "Continuar lendo": livros com status "lendo" na biblioteca pessoal (excluindo obras removidas pela moderação).
$continua = $pdo->prepare(
    "SELECT b.id_livro, b.titulo_livro, b.autor_livro, b.capa_livro,
            COALESCE(b.classificacao_livro, 'livre') AS classificacao_livro,
            p.porcentagem_progressao
       FROM livraria l
       JOIN livro b ON b.id_livro = l.id_livro
  LEFT JOIN progressao_leitura p ON p.id_usuario = l.id_usuario AND p.id_livro = l.id_livro
  LEFT JOIN loja_produtos lp ON lp.id_livro = b.id_livro
  LEFT JOIN autor_dos_livros adl ON adl.id_autor_dos_livros = lp.id_autor_dos_livros
      WHERE l.id_usuario = ? AND l.status_livraria = 'lendo'
        AND (adl.id_autor_dos_livros IS NULL OR adl.status_autor_dos_livros <> 'removido')
   ORDER BY l.data_modificacao_livraria DESC
      LIMIT 6"
);
$continua->execute([$uid]);
$continuando = $continua->fetchAll(PDO::FETCH_ASSOC);

// Resumo rápido para os KPIs do início
$stKpi = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM livraria WHERE id_usuario = ?) +
        (SELECT COUNT(*) FROM livraria_digital ld JOIN autor_dos_livros adl2 ON adl2.id_autor_dos_livros = ld.id_autor_dos_livros WHERE ld.id_usuario = ? AND adl2.status_autor_dos_livros <> 'removido') AS total_estante,
        (SELECT COUNT(*) FROM livraria WHERE id_usuario = ? AND status_livraria = 'lido') AS livros_lidos,
        (SELECT COALESCE(SUM(p.pagina_atual_progressao), 0) FROM progressao_leitura p WHERE p.id_usuario = ?) AS paginas_lidas,
        (SELECT COUNT(*)
           FROM loja_produtos lp2
           JOIN usuario u2 ON u2.id_usuario = lp2.id_autor
      LEFT JOIN autor_dos_livros a2 ON a2.id_autor_dos_livros = lp2.id_autor_dos_livros
      LEFT JOIN historias h2 ON h2.id_historias = a2.id_historias
          WHERE lp2.status_produto = 'publicado' AND u2.status_usuario = 'ativo'
            " . (folivro_pagamentos_ativos() ? '' : 'AND lp2.preco_centavos = 0') . "
            AND (lp2.id_autor_dos_livros IS NULL OR (a2.status_autor_dos_livros = 'a_venda' AND a2.revisao_publicacao = 'aprovada' AND h2.status_historias = 'publicada'))
            AND (lp2.id_autor_dos_livros IS NULL OR NOT EXISTS (SELECT 1 FROM denuncias d2 WHERE d2.tipo_denuncias = 'obra_autoral' AND d2.item_denuncia = lp2.id_autor_dos_livros AND d2.status_denuncias IN ('aberta', 'em_analise') AND d2.suspensao_preventiva = 1))
        ) AS total_loja,
        (SELECT COUNT(*) FROM chat_conversas WHERE (id_usuario_menor = ? OR id_usuario_maior = ?) AND status_conversa = 'pendente' AND id_solicitante <> ?) AS solicitacoes_chat,
        (SELECT COUNT(*)
           FROM chat_mensagens cm
           JOIN chat_conversas cc ON cc.id_conversa = cm.id_conversa
          WHERE (cc.id_usuario_menor = ? OR cc.id_usuario_maior = ?)
            AND COALESCE(cc.status_conversa, 'aceita') = 'aceita'
            AND cm.status_mensagem = 'ativa'
            AND cm.id_remetente <> ?
            AND cm.lida_em IS NULL) AS mensagens_nao_lidas"
);
$stKpi->execute([$uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid]);
$kpisHome = $stKpi->fetch(PDO::FETCH_ASSOC) ?: ['total_estante' => 0, 'livros_lidos' => 0, 'paginas_lidas' => 0, 'total_loja' => 0, 'solicitacoes_chat' => 0, 'mensagens_nao_lidas' => 0];

// Meta anual vigente
$metaRow = $pdo->prepare("SELECT tipo_metas, quantidade_metas FROM metas_leitura WHERE id_usuario = ? AND status_metas IN ('em_andamento','concluida') ORDER BY id_metas_leitura DESC LIMIT 1");
$metaRow->execute([$uid]);
$metaHome = $metaRow->fetch(PDO::FETCH_ASSOC);
$alvoHome = $metaHome ? max(1, (int) $metaHome['quantidade_metas']) : 12;
$atualHome = ($metaHome && $metaHome['tipo_metas'] === 'paginas') ? (int) $kpisHome['paginas_lidas'] : (int) $kpisHome['livros_lidos'];
$pctMetaHome = min(100, (int) round(($atualHome / $alvoHome) * 100));

// Novidades da Loja Independente (4 obras recentes)
$novidadesLoja = folivro_loja_listar_vitrine($pdo, ['ordenar' => 'recentes'], 1, 4)['itens'];

// Destaque lateral do Hero: livro em leitura atual ou primeira novidade da loja
$heroLivro = $continuando[0] ?? null;
$heroBloqueado18 = $heroLivro && !$maiorDeIdade && folivro_classificacao_eh_maior18((string) $heroLivro['classificacao_livro']);
if ($heroBloqueado18) {
    $heroLivro = null;
}
$heroLoja = !$heroLivro && !empty($novidadesLoja) ? $novidadesLoja[0] : null;
if ($heroLoja && !$maiorDeIdade && folivro_classificacao_eh_maior18((string) ($heroLoja['classificacao_produto'] ?? 'livre'))) {
    $heroLoja = null;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Início | FOLIVRO</title>
    <link rel="preconnect" href="https://covers.openlibrary.org" crossorigin>
    <link rel="preconnect" href="https://openlibrary.org">
    <link rel="dns-prefetch" href="https://covers.openlibrary.org">
    <link rel="stylesheet" href="layout.css?v=20261104_20">
    <link rel="stylesheet" href="home.css?v=20261117_2">
    <link rel="stylesheet" href="estante.css?v=20261117_4">
    <meta name="csrf-token" content="<?= folivro_escape(folivro_csrf_token()) ?>">
</head>

<body>

    <?php $menuAtivo = 'inicio'; require __DIR__ . '/includes/menu-lateral.php'; ?>
    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">

        <section class="destaque-principal">
            <div class="texto">
                <span class="selo-status">Bem-vindo(a) de volta, <?= folivro_escape($primeiroNome) ?></span>
                <?php if ($heroLivro): ?>
                    <h1>Continue sua leitura</h1>
                    <h3><?= folivro_escape($heroLivro['titulo_livro']) ?> · <?= folivro_escape($heroLivro['autor_livro']) ?></h3>
                    <p>
                        Você já avançou <strong><?= min(100, max(0, (int) round((float) ($heroLivro['porcentagem_progressao'] ?? 0)))) ?>%</strong> deste livro. Retome exatamente de onde parou ou explore novas obras na loja e no catálogo.
                    </p>
                    <div class="acoes-obra">
                        <a href="leitor.php?id=<?= (int) $heroLivro['id_livro'] ?>" class="botao-mais">Continuar leitura</a>
                        <a href="loja.php" class="botao-mais">Explorar loja</a>
                        <a href="pesquisa.php" class="botao-mais">Buscar livros</a>
                    </div>
                <?php else: ?>
                    <h1>Sua estante literária digital</h1>
                    <h3>Descubra obras independentes, organize suas leituras e acompanhe suas metas</h3>
                    <p>
                        Explore livros de autores da comunidade na loja, acompanhe seu progresso diário, participe de conversas no chat literário e publique suas próprias histórias.
                    </p>
                    <div class="acoes-obra">
                        <a href="loja.php" class="botao-mais">Explorar loja</a>
                        <a href="escrever.php" class="botao-mais">Escrever e publicar</a>
                        <a href="pesquisa.php" class="botao-mais">Buscar no catálogo</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="imagem">
                <?php if ($heroLivro && !empty($heroLivro['capa_livro'])): ?>
                    <a href="leitor.php?id=<?= (int) $heroLivro['id_livro'] ?>" title="Continuar leitura">
                        <img src="<?= folivro_escape($heroLivro['capa_livro']) ?>" alt="Capa de <?= folivro_escape($heroLivro['titulo_livro']) ?>">
                    </a>
                <?php elseif ($heroLoja && !empty($heroLoja['capa_produto'])): ?>
                    <a href="loja-obra.php?id=<?= (int) $heroLoja['id_produto'] ?>" title="Ver na loja">
                        <img src="<?= folivro_escape($heroLoja['capa_produto']) ?>" alt="Capa de <?= folivro_escape($heroLoja['titulo_produto']) ?>">
                    </a>
                <?php else: ?>
                    <div class="capa-placeholder" role="img" aria-label="FOLIVRO — Sua biblioteca literária">
                        <strong><?= $heroLivro ? folivro_escape($heroLivro['titulo_livro']) : ($heroLoja ? folivro_escape($heroLoja['titulo_produto']) : 'FOLIVRO') ?></strong>
                        <span><?= $heroLivro ? folivro_escape($heroLivro['autor_livro']) : 'Leitura, escrita e comunidade' ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="home-resumo-rapido" aria-label="Resumo rápido da sua conta">
            <a href="minha-biblioteca.php" class="home-kpi-atalho">
                <span>Minha estante</span>
                <strong><?= (int) $kpisHome['total_estante'] ?></strong>
                <small><?= (int) $kpisHome['livros_lidos'] ?> concluído(s)</small>
            </a>
            <a href="estatisticas.php" class="home-kpi-atalho">
                <span>Meta anual</span>
                <strong><?= $pctMetaHome ?>%</strong>
                <small><?= $atualHome ?> / <?= $alvoHome ?> <?= ($metaHome && $metaHome['tipo_metas'] === 'paginas') ? 'páginas' : 'livros' ?></small>
            </a>
            <a href="loja.php" class="home-kpi-atalho">
                <span>Loja independente</span>
                <strong><?= (int) $kpisHome['total_loja'] ?></strong>
                <small>Obras disponíveis</small>
            </a>
            <?php $totalNotifChat = (int) ($kpisHome['solicitacoes_chat'] ?? 0) + (int) ($kpisHome['mensagens_nao_lidas'] ?? 0); ?>
            <a href="chat.php" class="home-kpi-atalho">
                <span>Chat literário</span>
                <strong><?= $totalNotifChat > 0 ? $totalNotifChat . ' nova(s)' : 'Ativo' ?></strong>
                <small><?= (int) ($kpisHome['mensagens_nao_lidas'] ?? 0) > 0 ? (int) $kpisHome['mensagens_nao_lidas'] . ' mensagem(ns) não lida(s)' : ((int) ($kpisHome['solicitacoes_chat'] ?? 0) > 0 ? 'Solicitação pendente' : 'Conversas e leitores') ?></small>
            </a>
        </section>

        <?php if ($continuando): ?>
            <section class="continuar-lendo">
                <div class="home-secao-topo">
                    <h2>Continuar lendo</h2>
                    <a href="minha-biblioteca.php">Ver estante completa &rarr;</a>
                </div>
                <div class="biblioteca-lista estante-biblioteca grade-continuar-lendo">
                    <?php foreach ($continuando as $livro): ?>
                        <?php
                        $pctLivro = min(100, max(0, (int) round((float) ($livro['porcentagem_progressao'] ?? 0))));
                        $classNorm = folivro_normalizar_classificacao((string) ($livro['classificacao_livro'] ?? 'livre'));
                        $siglaIdade = folivro_classificacao_sigla($classNorm);
                        $labelIdade = folivro_aviso_conteudo_label($classNorm);
                        $bloqueadoIdade = !$maiorDeIdade && folivro_classificacao_eh_maior18($classNorm);
                        ?>
                        <article class="livro-card <?= $bloqueadoIdade ? 'livro-card-bloqueado-idade' : '' ?>">
                            <div class="livro-capa <?= $bloqueadoIdade ? 'capa-bloqueada-18' : '' ?>">
                                <?php if ($bloqueadoIdade): ?>
                                    <?php if ($livro['capa_livro']): ?>
                                        <img src="<?= folivro_escape($livro['capa_livro']) ?>" alt="Capa bloqueada por classificação indicativa +18" loading="lazy">
                                    <?php else: ?>
                                        <div class="capa-fallback">Obra +18</div>
                                    <?php endif; ?>
                                    <div class="capa-bloqueio-idade-overlay" role="img" aria-label="Conteúdo bloqueado: exclusivo para maiores de 18 anos">
                                        <span class="icone-olho-bloqueado-circulo"><?= folivro_icone_olho_bloqueado_svg(32) ?></span>
                                        <strong class="titulo-bloqueio-capa">Classificação +18</strong>
                                        <span class="motivo-bloqueio-capa">Exclusivo para maiores de 18 anos</span>
                                    </div>
                                <?php else: ?>
                                    <a href="livro.php?id=<?= (int) $livro['id_livro'] ?>" class="link-livro-capa">
                                        <?php if ($livro['capa_livro']): ?>
                                            <img src="<?= folivro_escape($livro['capa_livro']) ?>" alt="Capa de <?= folivro_escape($livro['titulo_livro']) ?>" loading="lazy">
                                        <?php else: ?>
                                            <div class="capa-fallback"><?= folivro_escape($livro['titulo_livro']) ?></div>
                                        <?php endif; ?>
                                    </a>
                                <?php endif; ?>
                                <span class="selo-classificacao-idade selo-classificacao-capa selo-idade-<?= folivro_escape($classNorm) ?>" title="<?= folivro_escape($labelIdade) ?>">
                                    <?= folivro_escape($siglaIdade) ?>
                                </span>
                            </div>
                            <div class="livro-info">
                                <div class="linha-selos-card">
                                    <span class="selo-status selo-lendo">Lendo · <?= $pctLivro ?>%</span>
                                    <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($classNorm) ?>" title="<?= folivro_escape($labelIdade) ?>">
                                        <?= folivro_escape($siglaIdade) ?>
                                    </span>
                                </div>
                                <h3>
                                    <?php if ($bloqueadoIdade): ?>
                                        <span class="titulo-oculto-idade">Título oculto (+18)</span>
                                    <?php else: ?>
                                        <a href="livro.php?id=<?= (int) $livro['id_livro'] ?>" class="link-titulo-livro"><?= folivro_escape($livro['titulo_livro']) ?></a>
                                    <?php endif; ?>
                                </h3>
                                <p class="autor-card-livro"><?= folivro_escape($livro['autor_livro']) ?></p>

                                <div class="rodape-card-leitura">
                                    <?php if ($bloqueadoIdade): ?>
                                        <button class="botao-adicionar-livro botao-bloqueado-idade" type="button" disabled>Bloqueado (+18)</button>
                                    <?php else: ?>
                                        <div class="bloco-progresso-card">
                                            <div class="topo-progresso-card">
                                                <span>Progresso</span>
                                                <strong><?= $pctLivro ?>%</strong>
                                            </div>
                                            <div class="mini-progresso" role="progressbar" aria-valuenow="<?= $pctLivro ?>" aria-valuemin="0" aria-valuemax="100">
                                                <span style="width: <?= $pctLivro ?>%"></span>
                                            </div>
                                        </div>
                                        <a class="botao-adicionar-livro btn-card-principal" href="leitor.php?id=<?= (int) $livro['id_livro'] ?>">Continuar leitura</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($novidadesLoja): ?>
            <section class="continuar-lendo">
                <div class="home-secao-topo">
                    <h2>Destaques da Loja Independente</h2>
                    <a href="loja.php">Ver toda a loja &rarr;</a>
                </div>
                <div class="biblioteca-lista estante-biblioteca">
                    <?php foreach ($novidadesLoja as $nl): ?>
                        <?php
                        $clLoja = folivro_normalizar_classificacao((string) ($nl['classificacao_produto'] ?? 'livre'));
                        $bloqLoja = !$maiorDeIdade && folivro_classificacao_eh_maior18($clLoja);
                        ?>
                        <article class="livro-card <?= $bloqLoja ? 'livro-card-bloqueado-idade' : '' ?>">
                            <div class="livro-capa <?= $bloqLoja ? 'capa-bloqueada-18' : '' ?>">
                                <?php if (!empty($nl['capa_produto'])): ?>
                                    <img src="<?= folivro_escape($nl['capa_produto']) ?>" alt="Capa de <?= folivro_escape($nl['titulo_produto']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="capa-fallback"><?= $bloqLoja ? 'Conteúdo +18' : folivro_escape($nl['titulo_produto']) ?></div>
                                <?php endif; ?>
                                <span class="selo-classificacao-idade selo-classificacao-capa selo-idade-<?= folivro_escape($clLoja) ?>">
                                    <?= folivro_escape(folivro_classificacao_sigla($clLoja)) ?>
                                </span>
                            </div>
                            <div class="livro-info">
                                <div class="linha-selos-card">
                                    <span class="selo-status <?= (int) $nl['preco_centavos'] === 0 ? 'selo-lido' : 'selo-comprado' ?>">
                                        <?= (int) $nl['preco_centavos'] === 0 ? 'Gratuita' : folivro_escape(folivro_loja_formatar((int) $nl['preco_centavos'])) ?>
                                    </span>
                                    <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($clLoja) ?>">
                                        <?= folivro_escape(folivro_classificacao_sigla($clLoja)) ?>
                                    </span>
                                </div>
                                <h3>
                                    <?php if ($bloqLoja): ?>
                                        <span class="titulo-oculto-idade">Título oculto (+18)</span>
                                    <?php else: ?>
                                        <a href="loja-obra.php?id=<?= (int) $nl['id_produto'] ?>" class="link-titulo-livro"><?= folivro_escape($nl['titulo_produto']) ?></a>
                                    <?php endif; ?>
                                </h3>
                                <p class="autor-card-livro"><?= folivro_escape($nl['autor_nome']) ?></p>
                                <?php if (!$bloqLoja): ?>
                                    <a class="botao-adicionar-livro btn-card-principal" href="loja-obra.php?id=<?= (int) $nl['id_produto'] ?>">Ver na loja</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="livros-destaque">
            <div class="home-secao-topo">
                <h2>Livros mais populares e bem avaliados (Catálogo aberto)</h2>
                <a href="pesquisa.php">Explorar categorias &rarr;</a>
            </div>
            <div class="lista-livros grade-livros-duplo" id="catalogo-destaque" aria-live="polite">
                <p class="estado-vazio">Carregando sugestões de livros…</p>
            </div>
            <div class="container-ver-mais" id="container-ver-mais-catalogo" style="display: none;">
                <button type="button" class="botao-ver-mais" id="btn-ver-mais-catalogo">
                    <img src="img/icones/expand_more.png" alt="" class="icone-ver-mais-png"> Ver mais…
                </button>
            </div>
        </section>

        <p class="api-credit">
            <strong>Metadados bibliográficos:</strong> Os cartões do catálogo aberto exibem metadados consultados na <a href="https://openlibrary.org" target="_blank" rel="noopener noreferrer">Open Library</a> apenas para organização pessoal de leitura (Lei nº 9.610/1998).
        </p>

    </main>

    <script src="livros.js?v=20261122_02"></script>
</body>

</html>
