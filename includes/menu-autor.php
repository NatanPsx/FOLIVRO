<?php
$menuAtivo = $menuAtivo ?? '';
?>
<nav class="atalhos-extra" aria-label="Loja e área do autor">
    <a class="atalho-loja<?= $menuAtivo === 'loja' ? ' ativo' : '' ?>"
       href="loja.php"
       title="Loja de obras independentes"
       <?= $menuAtivo === 'loja' ? 'aria-current="page"' : '' ?>>
        <span class="icone-menu" aria-hidden="true">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path>
                <path d="M3 6h18"></path>
                <path d="M16 10a4 4 0 0 1-8 0"></path>
            </svg>
        </span>
        <span class="rotulo-menu">Loja</span>
    </a>
    <div class="menu-autor" aria-label="Área do autor">
        <h2>AUTOR</h2>
        <a href="escrever.php"
           class="<?= $menuAtivo === 'escrever' ? 'ativo' : '' ?>"
           title="Escrever e autopublicar uma obra"
           <?= $menuAtivo === 'escrever' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu" aria-hidden="true">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 20h9"></path>
                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                </svg>
            </span>
            <span class="rotulo-menu">Escrever</span>
        </a>
        <a href="biblioteca-autor.php"
           class="<?= $menuAtivo === 'biblioteca-autor' ? 'ativo' : '' ?>"
           title="Livros escritos no estúdio"
           <?= $menuAtivo === 'biblioteca-autor' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu" aria-hidden="true">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path>
                    <path d="M8 7h6"></path>
                    <path d="M8 11h8"></path>
                </svg>
            </span>
            <span class="rotulo-menu">Obras</span>
        </a>
        <a href="painel-vendas.php"
           class="<?= in_array($menuAtivo, ['painel-vendas', 'loja-autor'], true) ? 'ativo' : '' ?>"
           title="Painel de vendas, repasses e obras na loja"
           <?= in_array($menuAtivo, ['painel-vendas', 'loja-autor'], true) ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu" aria-hidden="true">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 3v18h18"></path>
                    <path d="m19 9-5 5-4-4-3 3"></path>
                </svg>
            </span>
            <span class="rotulo-menu">Vendas</span>
        </a>
    </div>
</nav>
