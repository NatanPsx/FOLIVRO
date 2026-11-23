<?php
/**
 * Menu lateral principal — FOLIVRO
 *
 * Defina $menuAtivo antes de incluir este arquivo:
 * $menuAtivo = 'inicio' | 'pesquisar' | 'biblioteca' | 'perfil' | 'estatisticas' | 'moderacao' | 'loja' | 'escrever' | 'biblioteca-autor' | ''
 */
$menuAtivo = $menuAtivo ?? '';
require_once __DIR__ . '/icones-folivro.php';
$_buscaRaw = isset($_GET['busca']) && is_string($_GET['busca']) ? trim($_GET['busca']) : '';
$_hrefBusca = 'pesquisa.php' . ($_buscaRaw !== '' ? '?busca=' . rawurlencode($_buscaRaw) : '');
?>
<aside class="menu-lateral" aria-label="Navegação principal">
    <a href="#conteudo-principal" class="link-pular-conteudo">Pular para o conteúdo principal</a>

    <div class="menu-topo-marca">
        <a href="home.php" class="marca-folivro" title="FOLIVRO — Página inicial">
            <?= folivro_icone('emblema-f') ?>
            <span>FOLIVRO</span>
        </a>
    </div>

    <nav class="menu-links-principais" aria-label="Menu do leitor">
        <a href="home.php"
           class="item-menu<?= $menuAtivo === 'inicio' ? ' ativo' : '' ?>"
           title="Início"
           <?= $menuAtivo === 'inicio' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu"><?= folivro_icone('livro-aberto-f') ?></span>
            <span class="rotulo-menu">Início</span>
        </a>

        <a href="<?= folivro_escape($_hrefBusca) ?>"
           class="item-menu<?= $menuAtivo === 'pesquisar' ? ' ativo' : '' ?>"
           title="Pesquisar no catálogo"
           <?= $menuAtivo === 'pesquisar' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu"><?= folivro_icone('folhas-voando-f') ?></span>
            <span class="rotulo-menu">Buscar</span>
        </a>

        <a href="minha-biblioteca.php"
           class="item-menu<?= $menuAtivo === 'biblioteca' ? ' ativo' : '' ?>"
           title="Minha biblioteca"
           <?= $menuAtivo === 'biblioteca' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu"><?= folivro_icone('pasta-biblioteca') ?></span>
            <span class="rotulo-menu">Biblioteca</span>
        </a>

        <a href="estatisticas.php"
           class="item-menu<?= $menuAtivo === 'estatisticas' ? ' ativo' : '' ?>"
           title="Estatísticas e metas"
           <?= $menuAtivo === 'estatisticas' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu"><?= folivro_icone('folha-metas-f') ?></span>
            <span class="rotulo-menu">Metas</span>
        </a>

        <a href="chat.php"
           class="item-menu<?= $menuAtivo === 'chat' ? ' ativo' : '' ?>"
           title="Chat literário seguro"
           <?= $menuAtivo === 'chat' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu icone-menu-chat" aria-hidden="true">
                <?= folivro_icone('folha-lista-f') ?>
                <span class="badge-chat-notificacao" id="badge-chat-notificacao" hidden aria-label="Mensagens não lidas">0</span>
            </span>
            <span class="rotulo-menu">Chat</span>
        </a>

        <a href="usuario.php"
           class="item-menu<?= $menuAtivo === 'perfil' ? ' ativo' : '' ?>"
           title="Meu perfil"
           <?= $menuAtivo === 'perfil' ? 'aria-current="page"' : '' ?>>
            <span class="icone-menu"><?= folivro_icone('pagina-marcador') ?></span>
            <span class="rotulo-menu">Perfil</span>
        </a>
    </nav>

</aside>
<script>
(() => {
    const badgeGlobal = document.getElementById('badge-chat-notificacao');
    if (!badgeGlobal) return;

    async function atualizarNotificacoesChat() {
        if (document.hidden) return;
        try {
            const resp = await fetch('api/chat.php?acao=nao_lidas', { credentials: 'same-origin' });
            if (!resp.ok) return;
            const dados = await resp.json();
            if (!dados || !dados.ok) return;

            const total = Number(dados.total || 0);
            if (total > 0) {
                badgeGlobal.textContent = total > 99 ? '99+' : String(total);
                badgeGlobal.hidden = false;
                badgeGlobal.setAttribute('aria-label', `${total} notificação(ões) no chat`);
            } else {
                badgeGlobal.hidden = true;
            }

            const porConv = dados.por_conversa || {};
            document.querySelectorAll('[data-conversa-badge]').forEach((el) => {
                const idC = el.getAttribute('data-conversa-badge');
                const link = el.closest('.item-conversa-link');
                const ehAtiva = link && link.classList.contains('ativa');
                const qtd = ehAtiva ? 0 : Number(porConv[idC] || 0);
                if (qtd > 0) {
                    el.textContent = qtd > 99 ? '99+' : String(qtd);
                    el.hidden = false;
                } else {
                    el.hidden = true;
                }
            });
        } catch (_) {}
    }

    atualizarNotificacoesChat();
    setInterval(atualizarNotificacoesChat, 8000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) atualizarNotificacoesChat();
    });
})();
</script>
<?php require __DIR__ . '/configuracoes-modal.php'; ?>
