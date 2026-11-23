<?php
require_once dirname(__DIR__) . '/config/loja.php';
require_once __DIR__ . '/icones-folivro.php';
$menuAtivo = $menuAtivo ?? '';
$pagamentosAtivosMenu = folivro_pagamentos_ativos();
?>
<nav class="atalhos-extra" aria-label="Loja e área do autor">
    <a class="atalho-loja<?= $menuAtivo === 'loja' ? ' ativo' : '' ?>"
       href="loja.php"
       title="Loja de obras independentes"
       <?= $menuAtivo === 'loja' ? 'aria-current="page"' : '' ?>>
        <span class="icone-menu" aria-hidden="true">
            <?= folivro_icone('livro-lombada') ?>
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
                <?= folivro_icone('notebook-livros') ?>
            </span>
            <span class="rotulo-menu">Escrever</span>
        </a>
        <a href="biblioteca-autor.php"
           class="<?= $menuAtivo === 'biblioteca-autor' ? 'ativo' : '' ?>"
           title="Livros escritos no estúdio"
           <?= $menuAtivo === 'biblioteca-autor' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu" aria-hidden="true">
                <?= folivro_icone('livro-indice-f') ?>
            </span>
            <span class="rotulo-menu">Obras</span>
        </a>
        <?php if ($pagamentosAtivosMenu): ?>
        <a href="painel-vendas.php"
           class="<?= in_array($menuAtivo, ['painel-vendas', 'loja-autor'], true) ? 'ativo' : '' ?>"
           title="Painel de vendas, repasses e obras na loja"
           <?= in_array($menuAtivo, ['painel-vendas', 'loja-autor'], true) ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu" aria-hidden="true">
                <?= folivro_icone('etiqueta-f') ?>
            </span>
            <span class="rotulo-menu">Vendas</span>
        </a>
        <?php endif; ?>
    </div>
</nav>
