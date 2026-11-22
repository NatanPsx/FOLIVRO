<?php
declare(strict_types=1);
require_once __DIR__ . '/config/loja.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];

$codigo = trim((string) ($_GET['codigo'] ?? ''));
$pedido = folivro_loja_pedido_do_comprador($pdo, $codigo, $uid);
if ($pedido === null) {
    http_response_code(404);
}

$erro = '';
$sucesso = '';

if ($pedido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $erro = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $acao = (string) ($_POST['acao'] ?? '');
        if ($acao === 'confirmar_pix_simulado') {
            $res = folivro_loja_simular_pagamento_pix($pdo, $codigo, $uid);
            if ($res['ok']) {
                $sucesso = 'Pagamento simulado confirmado! A obra foi liberada na sua biblioteca.';
            } else {
                $erro = (string) ($res['erro'] ?? 'Não foi possível confirmar o pagamento.');
            }
            $pedido = folivro_loja_pedido_do_comprador($pdo, $codigo, $uid);
        } elseif ($acao === 'cancelar_pedido') {
            if (folivro_loja_cancelar_pendente($pdo, $codigo, $uid)) {
                $sucesso = 'Pedido cancelado.';
            } else {
                $erro = 'Não foi possível cancelar este pedido.';
            }
            $pedido = folivro_loja_pedido_do_comprador($pdo, $codigo, $uid);
        }
    }
}

if ($pedido && $pedido['status_pedido'] === 'pendente') {
    $sinc = folivro_loja_sincronizar_com_gateway($pdo, $pedido, true);
    $pedido = folivro_loja_pedido_do_comprador($pdo, $codigo, $uid) ?? $sinc;
}

$csrf = folivro_escape(folivro_csrf_token());
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pedido <?= $pedido ? folivro_escape($pedido['codigo_pedido']) : '' ?> | Loja FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261117_1">
    <link rel="stylesheet" href="home.css?v=20261117_1">
    <link rel="stylesheet" href="loja.css?v=20261117_1">
</head>
<body>
    <?php $menuAtivo = 'loja'; require __DIR__ . '/includes/menu-lateral.php'; ?>
    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <p><a href="minhas-compras.php">&larr; Ir para Minhas compras</a> · <a href="loja.php">Voltar para a loja</a></p>

        <?php if ($pedido === null): ?>
            <p class="estado-vazio estado-erro" role="alert">Pedido não encontrado.</p>
        <?php else: ?>
            <div class="loja-topo-cabecalho">
                <div>
                    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:10px;">
                        <h1 style="margin:0;">Pedido <?= folivro_escape($pedido['codigo_pedido']) ?></h1>
                        <span class="selo-modo-pagamento selo-modo-<?= folivro_escape($pedido['modo_pagamento']) ?>">
                            <?= $pedido['modo_pagamento'] === 'simulado' ? 'MODO SIMULADO' : 'MODO REAL' ?>
                        </span>
                        <span class="selo-status selo-pedido-<?= folivro_escape($pedido['status_pedido']) ?>" id="badge-status-pedido">
                            <?= folivro_escape(folivro_loja_status_pedido_label((string) $pedido['status_pedido'], $pedido['motivo_status'] ?? null)) ?>
                        </span>
                    </div>
                    <p>Obra: <strong><?= folivro_escape($pedido['titulo_produto_snapshot']) ?></strong> · Autor(a): <?= folivro_escape($pedido['vendedor_nome']) ?></p>
                </div>
            </div>

            <?php if ($erro): ?>
                <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape($erro) ?></p>
            <?php endif; ?>
            <?php if ($sucesso): ?>
                <p class="estado-vazio estado-sucesso" role="status"><?= folivro_escape($sucesso) ?></p>
            <?php endif; ?>

            <div class="loja-checkout-grade">
                <section class="loja-card-destaque">
                    <h2>Detalhes do pedido</h2>
                    <div class="loja-resumo-linha">
                        <span>Código</span>
                        <code><?= folivro_escape($pedido['codigo_pedido']) ?></code>
                    </div>
                    <div class="loja-resumo-linha">
                        <span>Método</span>
                        <span><?= strtoupper(folivro_escape((string) $pedido['metodo_pagamento'])) ?></span>
                    </div>
                    <div class="loja-resumo-linha">
                        <span>Valor</span>
                        <strong><?= (int) $pedido['valor_bruto_centavos'] === 0 ? 'Gratuito (R$ 0,00)' : folivro_escape(folivro_loja_formatar((int) $pedido['valor_bruto_centavos'])) ?></strong>
                    </div>
                    <div class="loja-resumo-linha">
                        <span>Criado em</span>
                        <span><?= date('d/m/Y H:i', strtotime((string) $pedido['criado_em'])) ?></span>
                    </div>
                    <?php if (!empty($pedido['pago_em'])): ?>
                        <div class="loja-resumo-linha">
                            <span>Pago em</span>
                            <span><?= date('d/m/Y H:i', strtotime((string) $pedido['pago_em'])) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pedido['motivo_status'])): ?>
                        <p class="estado-vazio" style="margin-top:12px;"><small><?= folivro_escape($pedido['motivo_status']) ?></small></p>
                    <?php endif; ?>
                </section>

                <section class="loja-card-destaque" id="painel-status-pagamento">
                    <?php if ($pedido['status_pedido'] === 'pago'): ?>
                        <h2>Pagamento confirmado!</h2>
                        <?php $idAdlPed = (int) ($pedido['id_autor_dos_livros'] ?? 0); ?>
                        <p>A obra já foi adicionada à sua biblioteca pessoal e está liberada para leitura.</p>
                        <div class="loja-acoes-card">
                            <a class="botao-adicionar-livro" href="<?= $idAdlPed > 0 ? 'obra.php?id=' . $idAdlPed : 'loja-obra.php?id=' . (int) $pedido['id_produto'] ?>">Ler obra</a>
                            <a class="filtro-pilula" href="loja-obra.php?id=<?= (int) $pedido['id_produto'] ?>">Avaliar obra</a>
                            <a class="filtro-pilula" href="minha-biblioteca.php">Minha biblioteca</a>
                        </div>

                    <?php elseif ($pedido['status_pedido'] === 'pendente'): ?>
                        <h2>Aguardando pagamento</h2>
                        <?php if ($pedido['metodo_pagamento'] === 'pix'): ?>
                            <div class="loja-pix-box">
                                <?php if (!empty($pedido['pix_qr_base64'])): ?>
                                    <img class="loja-pix-qr" src="data:image/png;base64,<?= folivro_escape($pedido['pix_qr_base64']) ?>" alt="QR Code Pix">
                                <?php endif; ?>
                                <?php if (!empty($pedido['pix_copia_cola'])): ?>
                                    <p><strong>Pix Copia e Cola</strong> <?= $pedido['modo_pagamento'] === 'simulado' ? '(fictício — não pagável)' : '' ?></p>
                                    <textarea class="loja-pix-codigo" id="pix-copia-cola" rows="3" readonly><?= folivro_escape($pedido['pix_copia_cola']) ?></textarea>
                                    <div class="loja-acoes-card" style="justify-content:center; margin-top:10px;">
                                        <button type="button" class="filtro-pilula" id="btn-copiar-pix">Copiar código Pix</button>
                                        <?php if ($pedido['modo_pagamento'] === 'simulado'): ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                <input type="hidden" name="acao" value="confirmar_pix_simulado">
                                                <button type="submit" class="botao-adicionar-livro">Confirmar pagamento simulado</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($pedido['expira_em'])): ?>
                                    <p style="margin-top:10px;"><small>Expira em: <strong><?= date('d/m/Y H:i:s', strtotime((string) $pedido['expira_em'])) ?></strong> (verificando status automaticamente...)</small></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p>Seu pagamento está em processamento pelo gateway. A página será atualizada automaticamente assim que houver confirmação.</p>
                        <?php endif; ?>

                        <form method="post" style="margin-top:14px;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="acao" value="cancelar_pedido">
                            <button type="submit" class="filtro-pilula">Cancelar pedido pendente</button>
                        </form>

                        <script>
                            const btnCopiar = document.getElementById('btn-copiar-pix');
                            if (btnCopiar) {
                                btnCopiar.addEventListener('click', async () => {
                                    const campo = document.getElementById('pix-copia-cola');
                                    try {
                                        await navigator.clipboard.writeText(campo.value);
                                        btnCopiar.textContent = 'Código copiado!';
                                    } catch (e) {
                                        campo.select();
                                        document.execCommand('copy');
                                        btnCopiar.textContent = 'Código copiado!';
                                    }
                                });
                            }

                            // Polling de status (Pix / pagamento em análise)
                            setInterval(async () => {
                                try {
                                    const r = await fetch('api/loja-pedido-status.php?codigo=' + encodeURIComponent(<?= json_encode($pedido['codigo_pedido']) ?>));
                                    if (!r.ok) return;
                                    const data = await r.json();
                                    if (data.status && data.status !== 'pendente') {
                                        window.location.reload();
                                    }
                                } catch (e) {}
                            }, 3000);
                        </script>

                    <?php else: ?>
                        <h2><?= folivro_escape(folivro_loja_status_pedido_label((string) $pedido['status_pedido'], $pedido['motivo_status'] ?? null)) ?></h2>
                        <p><?= folivro_escape((string) ($pedido['motivo_status'] ?: 'Este pedido não foi concluído.')) ?></p>
                        <div class="loja-acoes-card">
                            <a class="botao-adicionar-livro" href="loja-checkout.php?id=<?= (int) $pedido['id_produto'] ?>">Tentar comprar novamente</a>
                            <a class="filtro-pilula" href="loja.php">Voltar à loja</a>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
