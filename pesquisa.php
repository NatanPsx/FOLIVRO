<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

folivro_database();
folivro_require_login();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pesquisar | FOLIVRO</title>
    <link rel="preconnect" href="https://covers.openlibrary.org" crossorigin>
    <link rel="preconnect" href="https://openlibrary.org">
    <link rel="dns-prefetch" href="https://covers.openlibrary.org">
    <link rel="stylesheet" href="layout.css?v=20261104_20">
    <link rel="stylesheet" href="pesquisa.css?v=20261104_20">
    <meta name="csrf-token" content="<?= folivro_escape(folivro_csrf_token()) ?>">
</head>

<body>

    <?php $menuAtivo = 'pesquisar'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">

        <div class="embrulho-pesquisa-completa">
            <form class="barra-pesquisa" action="pesquisa.php" method="get" data-busca-livros role="search">
                <div class="icone-busca-decorativa" aria-hidden="true">
                    <img src="img/icones/search.png" alt="" class="icone-decorativo-png">
                </div>
                <input type="search" name="busca" id="campo-busca-livros" autocomplete="off" spellcheck="false" placeholder="Pesquisar livro ou autor..." aria-label="Pesquisar livro ou autor">
                <button type="button" class="btn-limpar-pesquisa" id="btn-limpar-pesquisa" aria-label="Limpar pesquisa" title="Limpar busca" style="display: none;">
                    <img src="img/icones/close.png" alt="" class="icone-btn-png">
                </button>
                <button type="submit" class="btn-submeter-pesquisa" id="btn-submeter-pesquisa" aria-label="Pesquisar" title="Pesquisar">
                    <img src="img/icones/search.png" alt="" class="icone-btn-png icone-btn-branco" id="icone-btn-busca">
                </button>
            </form>

            <div class="sugestoes-rapidas-pesquisa" aria-label="Sugestões de pesquisa">
                <span class="rotulo-sugestoes">Dicas rápidas:</span>
                <button type="button" class="chip-sugestao" data-termo="Machado de Assis">Machado de Assis</button>
                <button type="button" class="chip-sugestao" data-termo="Dom Casmurro">Dom Casmurro</button>
                <button type="button" class="chip-sugestao" data-termo="Clarice Lispector">Clarice Lispector</button>
                <button type="button" class="chip-sugestao" data-termo="Crime e Castigo">Crime e Castigo</button>
                <button type="button" class="chip-sugestao" data-termo="Ficção Científica">Ficção Científica</button>
                <button type="button" class="chip-sugestao" data-termo="George Orwell">George Orwell</button>
            </div>
        </div>

        <section class="resultados">
            <h1 class="titulo-resultados">Resultados da pesquisa</h1>
            <div class="area-resultados" id="resultados-livros" aria-live="polite">
                <p class="estado-vazio">Digite um livro ou autor para ver os resultados da pesquisa.</p>
            </div>
        </section>

        <section class="categorias">
            <h2>Categorias</h2>
            <div class="lista-categorias">
                <a href="pesquisa.php?busca=literatura+brasileira">Literatura<br>Nacional</a>
                <a href="pesquisa.php?busca=classicos">Grandes<br>Clássicos</a>
                <a href="pesquisa.php?busca=suspense">Suspense</a>
                <a href="pesquisa.php?busca=drama">Drama</a>
                <a href="pesquisa.php?busca=fantasia">Fantasia</a>
                <a href="pesquisa.php?busca=ficcao+cientifica">Ficção<br>Científica</a>
                <a href="pesquisa.php?busca=romance">Romance</a>
                <a href="pesquisa.php?busca=terror">Terror</a>
            </div>
        </section>

        <p class="api-credit">
            <strong>Metadados bibliográficos:</strong> A pesquisa consulta registros de catálogo da <a href="https://openlibrary.org" target="_blank" rel="noopener noreferrer">Open Library</a> (título, autoria e ano) apenas para organização de leituras. O FOLIVRO não armazena nem distribui cópias de livros de terceiros e não presume domínio público sem verificação legal da edição e jurisdição.
        </p>

    </main>

    <script src="livros.js?v=20261104_19"></script>
</body>

</html>
