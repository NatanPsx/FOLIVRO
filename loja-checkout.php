<?php
declare(strict_types=1);
require_once __DIR__ . '/config/loja.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if (!folivro_pagamentos_ativos()) {
    header('Location: ' . ($id > 0 ? 'loja-obra.php?id=' . $id : 'loja.php'));
    exit;
}
$produto = $id > 0 ? folivro_loja_produto($pdo, $id) : null;
$erroBloqueio = folivro_loja_validar_compra($pdo, $uid, $produto);

$infoModo = folivro_loja_info_modo();
$cfg = folivro_loja_config();
$valores = $produto ? folivro_loja_calcular_valores((int) $produto['preco_centavos'], $cfg['taxa_percentual']) : null;

$erroPost = '';

if ($erroBloqueio === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $erroPost = 'Sessão expirada. Atualize a página e tente novamente.';
    } elseif (!folivro_loja_rate_limit($pdo, 'checkout:' . $uid, 15, 60)) {
        $erroPost = 'Muitas tentativas de checkout em sequência. Aguarde 1 minuto e tente novamente.';
    } elseif (!$infoModo['disponivel']) {
        $erroPost = (string) ($infoModo['erro'] ?? 'Gateway de pagamento indisponível.');
    } else {
        $precoBanco = (int) $produto['preco_centavos'];
        if ($precoBanco === 0) {
            $resPed = folivro_loja_criar_pedido($pdo, $uid, $id, 'gratuito');
            if ($resPed['ok'] && !empty($resPed['pedido'])) {
                header('Location: loja-pedido.php?codigo=' . urlencode((string) $resPed['pedido']['codigo_pedido']));
                exit;
            }
            $erroPost = (string) ($resPed['erro'] ?? 'Não foi possível concluir a aquisição.');
        } else {
            $metodo = (string) ($_POST['metodo'] ?? '');
            if (!in_array($metodo, ['pix', 'cartao'], true)) {
                $erroPost = 'Selecione um método de pagamento válido (Pix ou Cartão).';
            } else {
                $resPed = folivro_loja_criar_pedido($pdo, $uid, $id, $metodo);
                if (!$resPed['ok'] || empty($resPed['pedido'])) {
                    $erroPost = (string) ($resPed['erro'] ?? 'Não foi possível criar o pedido.');
                } else {
                    $pedido = $resPed['pedido'];
                    $dadosGateway = [];
                    if ($pedido['gateway'] === 'simulado') {
                        $cenario = (string) ($_POST['cenario_simulado'] ?? '');
                        if (SimulatedGateway::metodoDoCenario($cenario) !== $metodo) {
                            $cenario = $metodo === 'pix' ? 'pix' : 'cartao_aprovado';
                        }
                        $dadosGateway = ['cenario' => $cenario];
                    } elseif ($pedido['gateway'] === 'mercadopago' && $metodo === 'cartao') {
                        // Recebe APENAS o token gerado pelo SDK JS do Mercado Pago no navegador.
                        $dadosGateway = [
                            'token' => (string) ($_POST['token'] ?? ''),
                            'payment_method_id' => (string) ($_POST['payment_method_id'] ?? ''),
                            'installments' => (int) ($_POST['installments'] ?? 1),
                            'issuer_id' => (string) ($_POST['issuer_id'] ?? ''),
                            'identification_type' => (string) ($_POST['identification_type'] ?? ''),
                            'identification_number' => (string) ($_POST['identification_number'] ?? ''),
                        ];
                    }

                    $resCob = folivro_loja_iniciar_cobranca($pdo, $pedido, $dadosGateway);
                    $pedidoAtual = $resCob['pedido'] ?? $pedido;
                    header('Location: loja-pedido.php?codigo=' . urlencode((string) $pedidoAtual['codigo_pedido']));
                    exit;
                }
            }
        }
    }
}

$comprador = folivro_loja_usuario($pdo, $uid);
$csrf = folivro_escape(folivro_csrf_token());
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout | Loja FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261117_1">
    <link rel="stylesheet" href="home.css?v=20261117_1">
    <link rel="stylesheet" href="loja.css?v=20261117_1">
</head>
<body>
    <?php $menuAtivo = 'loja'; require __DIR__ . '/includes/menu-lateral.php'; ?>
    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <p><a href="<?= $id > 0 ? 'loja-obra.php?id=' . $id : 'loja.php' ?>">&larr; Voltar</a></p>

        <div class="loja-topo-cabecalho">
            <div>
                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:10px;">
                    <h1 style="margin:0;">Finalizar compra</h1>
                    <?= folivro_loja_selo_modo_html() ?>
                </div>
                <p><?= folivro_escape($infoModo['descricao']) ?></p>
            </div>
        </div>

        <?php if ($erroBloqueio !== null): ?>
            <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape($erroBloqueio) ?></p>
            <p><a class="botao-adicionar-livro" href="loja.php">Voltar para a vitrine</a></p>
        <?php else: ?>
            <?php if ($erroPost): ?>
                <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape($erroPost) ?></p>
            <?php endif; ?>
            <?php if (!$infoModo['disponivel']): ?>
                <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape((string) $infoModo['erro']) ?></p>
            <?php endif; ?>

            <div class="loja-checkout-grade">
                <section class="loja-card-destaque" aria-labelledby="titulo-resumo-pedido">
                    <h2 id="titulo-resumo-pedido">Resumo do pedido</h2>
                    <div class="loja-resumo-linha">
                        <span>Obra</span>
                        <strong><?= folivro_escape($produto['titulo_produto']) ?></strong>
                    </div>
                    <div class="loja-resumo-linha">
                        <span>Autor(a)</span>
                        <span><?= folivro_escape($produto['autor_nome']) ?></span>
                    </div>
                    <div class="loja-resumo-linha">
                        <span>Formato</span>
                        <span><?= strtoupper(folivro_escape((string) $produto['arquivo_tipo'])) ?></span>
                    </div>
                    <div class="loja-resumo-linha">
                        <span>Repasse ao autor (<?= number_format(100 - $valores['taxa_percentual'], 2, ',', '.') ?>%)</span>
                        <span><?= folivro_escape(folivro_loja_formatar($valores['liquido'])) ?></span>
                    </div>
                    <div class="loja-resumo-linha">
                        <span>Taxa da plataforma (<?= number_format($valores['taxa_percentual'], 2, ',', '.') ?>%)</span>
                        <span><?= folivro_escape(folivro_loja_formatar($valores['taxa'])) ?></span>
                    </div>
                    <div class="loja-resumo-linha total">
                        <span>Total a pagar</span>
                        <span><?= (int) $produto['preco_centavos'] === 0 ? 'Gratuito (R$ 0,00)' : folivro_escape(folivro_loja_formatar($valores['bruto'])) ?></span>
                    </div>
                    <p><small>O valor é calculado exclusivamente no servidor a partir do preço cadastrado pelo autor.</small></p>
                </section>

                <section class="loja-card-destaque" aria-labelledby="titulo-pagamento">
                    <h2 id="titulo-pagamento">Forma de pagamento</h2>

                    <?php if ((int) $produto['preco_centavos'] === 0): ?>
                        <p>Esta obra é <strong>gratuita</strong>. Ela será adicionada à sua biblioteca sem passar pelo gateway de pagamento.</p>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="botao-adicionar-livro">Confirmar aquisição gratuita</button>
                        </form>

                    <?php elseif ($infoModo['modo'] === 'simulado'): ?>
                        <form method="post" id="form-checkout-simulado" class="progresso-form form-largura-total">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <div class="loja-metodo-opcoes">
                                <label class="loja-metodo-card ativo">
                                    <input type="radio" name="metodo" value="pix" checked>
                                    <div>
                                        <strong>Pix (Simulado)</strong><br>
                                        <small>Gera QR/Copia e Cola fictício</small>
                                    </div>
                                </label>
                                <label class="loja-metodo-card">
                                    <input type="radio" name="metodo" value="cartao">
                                    <div>
                                        <strong>Cartão (Simulado)</strong><br>
                                        <small>Sem solicitação de dados reais</small>
                                    </div>
                                </label>
                            </div>

                            <div id="opcoes-simulado-pix">
                                <label for="cenario_pix">Cenário da demonstração (Pix)
                                    <select id="cenario_pix" name="cenario_simulado">
                                        <option value="pix">Pix pendente (confirmar na próxima tela)</option>
                                        <option value="pix_expira">Pix com expiração curta (demonstra expiração)</option>
                                    </select>
                                </label>
                            </div>

                            <div id="opcoes-simulado-cartao" hidden>
                                <label for="cenario_cartao">Cenário da demonstração (Cartão)
                                    <select id="cenario_cartao" name="cenario_simulado" disabled>
                                        <option value="cartao_aprovado">Cartão aprovado imediatamente</option>
                                        <option value="cartao_recusado">Cartão recusado (saldo insuficiente simulado)</option>
                                    </select>
                                </label>
                                <p><small>Modo simulado: nenhum número de cartão é solicitado.</small></p>
                            </div>

                            <button type="submit" class="botao-adicionar-livro">Gerar pagamento simulado</button>
                        </form>

                        <script>
                            const radiosSim = document.querySelectorAll('#form-checkout-simulado input[name="metodo"]');
                            const boxPix = document.getElementById('opcoes-simulado-pix');
                            const boxCartao = document.getElementById('opcoes-simulado-cartao');
                            const selPix = document.getElementById('cenario_pix');
                            const selCartao = document.getElementById('cenario_cartao');

                            function atualizarMetodoSimulado() {
                                const metodo = document.querySelector('#form-checkout-simulado input[name="metodo"]:checked').value;
                                document.querySelectorAll('.loja-metodo-card').forEach((card) => {
                                    card.classList.toggle('ativo', card.querySelector('input').checked);
                                });
                                const ehPix = metodo === 'pix';
                                boxPix.hidden = !ehPix;
                                selPix.disabled = !ehPix;
                                boxCartao.hidden = ehPix;
                                selCartao.disabled = ehPix;
                            }
                            radiosSim.forEach((r) => r.addEventListener('change', atualizarMetodoSimulado));
                        </script>

                    <?php else: ?>
                        <!-- MODO REAL: Mercado Pago (Pix ou Cartão tokenizado via SDK JS v2) -->
                        <div class="loja-metodo-opcoes">
                            <label class="loja-metodo-card ativo">
                                <input type="radio" name="metodo_real_escolha" value="pix" checked>
                                <div>
                                    <strong>Pix (Mercado Pago)</strong><br>
                                    <small>Aprovação imediata por QR Code</small>
                                </div>
                            </label>
                            <label class="loja-metodo-card">
                                <input type="radio" name="metodo_real_escolha" value="cartao">
                                <div>
                                    <strong>Cartão de crédito</strong><br>
                                    <small>Tokenizado direto no Mercado Pago</small>
                                </div>
                            </label>
                        </div>

                        <form method="post" id="form-real-pix">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="metodo" value="pix">
                            <p><small>Ao continuar, geraremos o QR Code Pix oficial do Mercado Pago.</small></p>
                            <button type="submit" class="botao-adicionar-livro" <?= !$infoModo['disponivel'] ? 'disabled' : '' ?>>Gerar Pix</button>
                        </form>

                        <div id="container-real-cartao" hidden>
                            <div id="mp-card-brick"></div>
                            <form method="post" id="form-real-cartao-token">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="metodo" value="cartao">
                                <input type="hidden" name="token" id="mp_token">
                                <input type="hidden" name="payment_method_id" id="mp_payment_method_id">
                                <input type="hidden" name="installments" id="mp_installments">
                                <input type="hidden" name="issuer_id" id="mp_issuer_id">
                                <input type="hidden" name="identification_type" id="mp_id_type">
                                <input type="hidden" name="identification_number" id="mp_id_number">
                            </form>
                            <p><small>Os dados do cartão são criptografados diretamente pelo SDK do Mercado Pago e nunca passam pelo nosso servidor.</small></p>
                        </div>

                        <?php if ($infoModo['disponivel']): ?>
                            <script src="https://sdk.mercadopago.com/js/v2"></script>
                            <script>
                                const formRealPix = document.getElementById('form-real-pix');
                                const boxRealCartao = document.getElementById('container-real-cartao');
                                let mpBrickCarregado = false;

                                document.querySelectorAll('input[name="metodo_real_escolha"]').forEach((r) => {
                                    r.addEventListener('change', () => {
                                        document.querySelectorAll('.loja-metodo-card').forEach((card) => {
                                            card.classList.toggle('ativo', card.querySelector('input').checked);
                                        });
                                        const ehPix = r.value === 'pix';
                                        formRealPix.hidden = !ehPix;
                                        boxRealCartao.hidden = ehPix;
                                        if (!ehPix && !mpBrickCarregado) {
                                            mpBrickCarregado = true;
                                            const mp = new MercadoPago(<?= json_encode($infoModo['public_key']) ?>, { locale: 'pt-BR' });
                                            mp.bricks().create('cardPayment', 'mp-card-brick', {
                                                initialization: {
                                                    amount: <?= json_encode(round($valores['bruto'] / 100, 2)) ?>,
                                                    payer: { email: <?= json_encode((string) ($comprador['email_usuario'] ?? '')) ?> }
                                                },
                                                callbacks: {
                                                    onReady: () => {},
                                                    onSubmit: (formData) => {
                                                        document.getElementById('mp_token').value = formData.token || '';
                                                        document.getElementById('mp_payment_method_id').value = formData.payment_method_id || '';
                                                        document.getElementById('mp_installments').value = formData.installments || 1;
                                                        document.getElementById('mp_issuer_id').value = formData.issuer_id || '';
                                                        document.getElementById('mp_id_type').value = (formData.payer && formData.payer.identification && formData.payer.identification.type) ? formData.payer.identification.type : '';
                                                        document.getElementById('mp_id_number').value = (formData.payer && formData.payer.identification && formData.payer.identification.number) ? formData.payer.identification.number : '';
                                                        document.getElementById('form-real-cartao-token').submit();
                                                    },
                                                    onError: (err) => {
                                                        console.error('Erro no Brick Mercado Pago', err);
                                                    }
                                                }
                                            });
                                        }
                                    });
                                });
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
