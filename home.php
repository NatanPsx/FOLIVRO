<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = folivro_database();
folivro_require_login();

// "Continuar lendo": livros com status "lendo" na biblioteca pessoal.
$continua = $pdo->prepare(
    "SELECT b.id_livro, b.titulo_livro, b.autor_livro, b.capa_livro,
            COALESCE(b.classificacao_livro, 'livre') AS classificacao_livro,
            p.porcentagem_progressao
       FROM livraria l
       JOIN livro b ON b.id_livro = l.id_livro
  LEFT JOIN progressao_leitura p ON p.id_usuario = l.id_usuario AND p.id_livro = l.id_livro
      WHERE l.id_usuario = ? AND l.status_livraria = 'lendo'
   ORDER BY l.data_modificacao_livraria DESC
      LIMIT 6"
);
$continua->execute([(int) $_SESSION['id_usuario']]);
$continuando = $continua->fetchAll();
$maiorDeIdade = folivro_usuario_maior_de_idade();
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
    <link rel="stylesheet" href="home.css?v=20261104_20">
    <meta name="csrf-token" content="<?= folivro_escape(folivro_csrf_token()) ?>">
</head>

<body>

    <?php $menuAtivo = 'inicio'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">

        <section class="destaque-principal">
            <div class="texto">
                <span class="selo-status">Demonstração acadêmica original · Direitos autorais protegidos</span>
                <h1>Crônicas da Biblioteca de Papel</h1>
                <h3>Obra demonstrativa original criada para o projeto FOLIVRO</h3>
                <p>
                    Acompanhe suas leituras por metadados de catálogo ou publique textos autorais inéditos com declaração de titularidade, escolha de licença e transparência de moderação.
                </p>
                <div class="acoes-obra">
                    <a href="loja.php" class="botao-mais">Explorar loja autoral</a>
                    <a href="escrever.php" class="botao-mais">Publicar obra original</a>
                </div>
            </div>

            <div class="imagem">
                <div class="capa-placeholder" role="img" aria-label="Espaço reservado editorial: Crônicas da Biblioteca de Papel (Demonstração original FOLIVRO)">
                    <strong>Crônicas da<br>Biblioteca</strong>
                    <span> Conteúdo original<br>de demonstração </span>
                </div>
            </div>
        </section>

        <section class="livros-destaque">
            <h2>Livros mais populares e bem avaliados (Catálogo aberto)</h2>
            <div class="lista-livros grade-livros-duplo" id="catalogo-destaque" aria-live="polite">
                <p class="estado-vazio">Carregando sugestões de livros…</p>
            </div>
            <div class="container-ver-mais" id="container-ver-mais-catalogo" style="display: none;">
                <button type="button" class="botao-ver-mais" id="btn-ver-mais-catalogo">
                    <img src="img/icones/expand_more.png" alt="" class="icone-ver-mais-png"> Ver mais…
                </button>
            </div>
        </section>

        <section class="continuar-lendo">
            <h2>Continuar lendo</h2>
            <div class="biblioteca-lista grade-continuar-lendo">
                <?php if (!$continuando): ?>
                    <p class="estado-vazio">Adicione um livro à biblioteca para acompanhar sua leitura por aqui.</p>
                <?php else: ?>
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
                                        <span class="icone-olho-bloqueado-circulo">
                                            <?= folivro_icone_olho_bloqueado_svg(32) ?>
                                        </span>
                                        <strong class="titulo-bloqueio-capa">Classificação +18</strong>
                                        <span class="motivo-bloqueio-capa">Bloqueado: exclusivo para maiores de 18 anos</span>
                                    </div>
                                <?php else: ?>
                                    <a href="livro.php?id=<?= (int) $livro['id_livro'] ?>" class="link-livro-capa" title="Ver detalhes e críticas de <?= folivro_escape($livro['titulo_livro']) ?>">
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
                                        <span class="titulo-oculto-idade" title="Título oculto por restrição de faixa etária (+18)">Título oculto (+18)</span>
                                    <?php else: ?>
                                        <a href="livro.php?id=<?= (int) $livro['id_livro'] ?>" class="link-titulo-livro" title="Ver detalhes e críticas">
                                            <?= folivro_escape($livro['titulo_livro']) ?>
                                        </a>
                                    <?php endif; ?>
                                </h3>
                                <p class="autor-card-livro"><?= folivro_escape($livro['autor_livro']) ?></p>

                                <div class="rodape-card-leitura">
                                    <?php if ($bloqueadoIdade): ?>
                                        <p class="texto-motivo-bloqueio-card">Obra oculta: requer 18 anos ou mais.</p>
                                        <button class="botao-adicionar-livro botao-bloqueado-idade" type="button" disabled>Bloqueado (+18)</button>
                                    <?php else: ?>
                                        <div class="bloco-progresso-card">
                                            <div class="topo-progresso-card">
                                                <span>Progresso</span>
                                                <strong><?= $pctLivro ?>%</strong>
                                            </div>
                                            <div class="mini-progresso" role="progressbar" aria-valuenow="<?= $pctLivro ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progresso de leitura">
                                                <span style="width: <?= $pctLivro ?>%"></span>
                                            </div>
                                        </div>
                                        <a class="botao-adicionar-livro btn-card-principal" href="leitor.php?id=<?= (int) $livro['id_livro'] ?>">Continuar leitura</a>
                                        <div class="acoes-secundarias-card">
                                            <a class="link-ver-detalhes" href="livro.php?id=<?= (int) $livro['id_livro'] ?>">Ficha e críticas &rarr;</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <p class="api-credit">
            <strong>Aviso sobre direitos autorais e catálogo externo:</strong> Os cartões acima exibem exclusivamente <em>metadados bibliográficos</em> (título, autoria e ano) consultados na <a href="https://openlibrary.org" target="_blank" rel="noopener noreferrer">Open Library</a> para organização pessoal de leitura. A presença no catálogo não implica domínio público nem autoriza cópia ou redistribuição de texto integral, traduções ou capas protegidas (Lei nº 9.610/1998).
        </p>

    </main>

    <script src="livros.js?v=20261104_19"></script>
</body>

</html>
