<?php
/**
 * Menu lateral principal — FOLIVRO
 *
 * Defina $menuAtivo antes de incluir este arquivo:
 * $menuAtivo = 'inicio' | 'pesquisar' | 'biblioteca' | 'perfil' | 'estatisticas' | 'moderacao' | 'loja' | 'escrever' | 'biblioteca-autor' | ''
 */
$menuAtivo = $menuAtivo ?? '';
$_buscaRaw = isset($_GET['busca']) && is_string($_GET['busca']) ? trim($_GET['busca']) : '';
$_hrefBusca = 'pesquisa.php' . ($_buscaRaw !== '' ? '?busca=' . rawurlencode($_buscaRaw) : '');
?>
<aside class="menu-lateral" aria-label="Navegação principal">
    <a href="#conteudo-principal" class="link-pular-conteudo">Pular para o conteúdo principal</a>

    <div class="menu-topo-marca">
        <a href="home.php" class="marca-folivro" title="FOLIVRO — Página inicial">
            <img src="img/livro icon.png" alt="">
            <span>FOLIVRO</span>
        </a>
    </div>

    <nav class="menu-links-principais" aria-label="Menu do leitor">
        <a href="home.php"
           class="item-menu<?= $menuAtivo === 'inicio' ? ' ativo' : '' ?>"
           title="Início"
           <?= $menuAtivo === 'inicio' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu"><img src="img/Botao casinha.png" alt=""></span>
            <span class="rotulo-menu">Início</span>
        </a>

        <a href="<?= folivro_escape($_hrefBusca) ?>"
           class="item-menu<?= $menuAtivo === 'pesquisar' ? ' ativo' : '' ?>"
           title="Pesquisar no catálogo"
           <?= $menuAtivo === 'pesquisar' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu"><img src="img/Botao pesquisar.png" alt=""></span>
            <span class="rotulo-menu">Buscar</span>
        </a>

        <a href="minha-biblioteca.php"
           class="item-menu<?= $menuAtivo === 'biblioteca' ? ' ativo' : '' ?>"
           title="Minha biblioteca"
           <?= $menuAtivo === 'biblioteca' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu"><img src="img/Botao Pasta.png" alt=""></span>
            <span class="rotulo-menu">Biblioteca</span>
        </a>

        <a href="estatisticas.php"
           class="item-menu<?= $menuAtivo === 'estatisticas' ? ' ativo' : '' ?>"
           title="Estatísticas e metas"
           <?= $menuAtivo === 'estatisticas' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu"><img src="img/Botao de monitoramento.png" alt=""></span>
            <span class="rotulo-menu">Metas</span>
        </a>

        <a href="chat.php"
           class="item-menu<?= $menuAtivo === 'chat' ? ' ativo' : '' ?>"
           title="Chat literário seguro"
           <?= $menuAtivo === 'chat' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu" aria-hidden="true">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                </svg>
            </span>
            <span class="rotulo-menu">Chat</span>
        </a>

        <a href="usuario.php"
           class="item-menu<?= $menuAtivo === 'perfil' ? ' ativo' : '' ?>"
           title="Meu perfil"
           <?= $menuAtivo === 'perfil' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu"><img src="img/Botao Usuario.png" alt=""></span>
            <span class="rotulo-menu">Perfil</span>
        </a>
    </nav>

</aside>
<?php require __DIR__ . '/configuracoes-modal.php'; ?>
