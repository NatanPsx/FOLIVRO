<?php
declare(strict_types=1);
require_once __DIR__ . '/config/loja.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];
$ehAutor = folivro_loja_eh_autor($pdo, $uid);

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $erro = 'Sessão expirada. Atualize a página e tente novamente.';
    } elseif (!$ehAutor) {
        $erro = 'Apenas autores podem acessar ações de vendas e repasses.';
    } else {
        $acao = (string) ($_POST['acao'] ?? '');
        if ($acao === 'reembolsar_pedido') {
            $idPed = filter_input(INPUT_POST, 'id_pedido', FILTER_VALIDATE_INT) ?: 0;
            $motivo = (string) ($_POST['motivo'] ?? 'Reembolso solicitado pelo autor');
            $res = folivro_loja_reembolsar($pdo, $idPed, $uid, $motivo);
            if ($res['ok']) {
                $sucesso = 'Reembolso processado! O acesso do leitor à obra foi revogado e o estorno foi lançado no seu extrato.';
            } else {
                $erro = (string) ($res['erro'] ?? 'Não foi possível reembolsar o pedido.');
            }
        } elseif ($acao === 'solicitar_repasse') {
            $valorTxt = (string) ($_POST['valor_repasse'] ?? '');
            $valorCentavos = folivro_loja_preco_para_centavos($valorTxt);
            if ($valorCentavos === null) {
                $erro = 'Informe um valor de repasse válido (ex.: 20,00).';
            } else {
                $res = folivro_loja_solicitar_repasse($pdo, $uid, $valorCentavos);
                if ($res['ok']) {
                    $sucesso = 'Repasse registrado com sucesso!';
                } else {
                    $erro = (string) ($res['erro'] ?? 'Não foi possível solicitar o repasse.');
                }
            }
        }
    }
}

$resumo = $ehAutor ? folivro_loja_resumo_vendas($pdo, $uid) : null;
$saldo = $ehAutor ? folivro_loja_saldo_autor($pdo, $uid) : null;

$pedidos = [];
$repasses = [];
$extrato = [];
if ($ehAutor) {
    $stPed = $pdo->prepare(
        'SELECT o.*, u.nome_usuario AS comprador_nome
           FROM loja_pedidos o
           JOIN usuario u ON u.id_usuario = o.id_comprador
          WHERE o.id_vendedor = ?
       ORDER BY o.criado_em DESC, o.id_pedido DESC
          LIMIT 50'
    );
    $stPed->execute([$uid]);
    $pedidos = $stPed->fetchAll(PDO::FETCH_ASSOC);

    $stRep = $pdo->prepare('SELECT * FROM loja_repasses WHERE id_autor = ? ORDER BY solicitado_em DESC LIMIT 20');
    $stRep->execute([$uid]);
    $repasses = $stRep->fetchAll(PDO::FETCH_ASSOC);

    $stExt = $pdo->prepare('SELECT * FROM loja_ledger_autor WHERE id_autor = ? ORDER BY criado_em DESC, id_lancamento DESC LIMIT 30');
    $stExt->execute([$uid]);
    $extrato = $stExt->fetchAll(PDO::FETCH_ASSOC);
}

$csrf = folivro_escape(folivro_csrf_token());
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel de Vendas | FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261117_1">
    <link rel="stylesheet" href="home.css?v=20261117_1">
    <link rel="stylesheet" href="loja.css?v=20261117_1">
</head>
<body>
    <?php $menuAtivo = 'painel-vendas'; require __DIR__ . '/includes/menu-lateral.php'; ?>
    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <div class="loja-topo-cabecalho">
            <div>
                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:10px;">
                    <h1 style="margin:0;">Painel de Vendas do Autor</h1>
                    <?= folivro_loja_selo_modo_html() ?>
                </div>
                <p>Acompanhe suas vendas na loja, receita bruta e líquida, pedidos, estornos e repasses.</p>
            </div>
            <div class="loja-atalhos-topo">
                <a class="botao-adicionar-livro" href="loja-autor-obras.php">Gerenciar obras à venda</a>
                <a class="filtro-pilula" href="loja.php">Ver vitrine</a>
            </div>
        </div>

        <?php if ($erro): ?>
            <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape($erro) ?></p>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <p class="estado-vazio estado-sucesso" role="status"><?= folivro_escape($sucesso) ?></p>
        <?php endif; ?>

        <?php if (!$ehAutor): ?>
            <section class="loja-card-destaque">
                <h2>Você ainda não ativou seu perfil de autor</h2>
                <p>Ative seu perfil de autor em <a href="loja-autor-obras.php">Minhas obras na loja</a> para cadastrar obras e acompanhar suas vendas.</p>
                <a class="botao-adicionar-livro" href="loja-autor-obras.php">Ativar perfil de autor</a>
            </section>
        <?php else: ?>
            <?php $tot = $resumo['totais']; ?>
            <section class="resumo-autor-grade" aria-label="Indicadores de vendas">
                <article class="card-kpi-autor">
                    <span>Vendas confirmadas</span>
                    <strong><?= (int) $tot['vendas'] ?></strong>
                    <small><?= (int) $tot['reembolsos'] ?> reembolso(s) · <?= (int) $tot['pendentes'] ?> pendente(s)</small>
                </article>
                <article class="card-kpi-autor">
                    <span>Receita bruta</span>
                    <strong><?= folivro_escape(folivro_loja_formatar((int) $tot['bruto'])) ?></strong>
                    <small>Taxa plataforma: <?= folivro_escape(folivro_loja_formatar((int) $tot['taxa'])) ?></small>
                </article>
                <article class="card-kpi-autor">
                    <span>Receita líquida</span>
                    <strong><?= folivro_escape(folivro_loja_formatar((int) $tot['liquido'])) ?></strong>
                    <small>Estornos: <?= folivro_escape(folivro_loja_formatar((int) $saldo['estornos'])) ?></small>
                </article>
                <article class="card-kpi-autor">
                    <span>Saldo disponível</span>
                    <strong><?= folivro_escape(folivro_loja_formatar((int) $saldo['disponivel'])) ?></strong>
                    <small>Já repassado: <?= folivro_escape(folivro_loja_formatar(abs((int) $saldo['repasses']))) ?></small>
                </article>
            </section>

            <section class="loja-card-destaque" aria-labelledby="titulo-grafico-vendas">
                <h2 id="titulo-grafico-vendas">Evolução de vendas (últimos 6 meses)</h2>
                <div class="loja-grafico-box">
                    <canvas id="grafico-vendas-autor"></canvas>
                </div>
            </section>

            <section class="loja-card-destaque" aria-labelledby="titulo-repasses">
                <h2 id="titulo-repasses">Saldo e repasses</h2>
                <form method="post" class="progresso-form" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin-bottom:16px;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="acao" value="solicitar_repasse">
                    <label for="valor_repasse">Valor do repasse (R$)
                        <input id="valor_repasse" name="valor_repasse" required placeholder="Ex.: 25,00"
                               value="<?= folivro_escape($saldo['disponivel'] >= FOLIVRO_LOJA_REPASSE_MIN_CENTAVOS ? folivro_centavos_para_input((int) $saldo['disponivel']) : '') ?>">
                    </label>
                    <button type="submit" class="botao-adicionar-livro" <?= $saldo['disponivel'] < FOLIVRO_LOJA_REPASSE_MIN_CENTAVOS ? 'disabled' : '' ?>>
                        Solicitar repasse
                    </button>
                </form>

                <?php if ($repasses): ?>
                    <div class="loja-tabela-wrap">
                        <table class="loja-tabela">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Data</th>
                                    <th>Valor</th>
                                    <th>Modo</th>
                                    <th>Status</th>
                                    <th>Observação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repasses as $rep): ?>
                                    <tr>
                                        <td>#<?= (int) $rep['id_repasse'] ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime((string) $rep['solicitado_em'])) ?></td>
                                        <td><strong><?= folivro_escape(folivro_loja_formatar((int) $rep['valor_centavos'])) ?></strong></td>
                                        <td><?= $rep['modo_pagamento'] === 'simulado' ? 'Simulado' : 'Real' ?></td>
                                        <td><span class="selo-status <?= $rep['status_repasse'] === 'pago' ? 'selo-lido' : '' ?>"><?= folivro_escape(ucfirst((string) $rep['status_repasse'])) ?></span></td>
                                        <td><small><?= folivro_escape((string) ($rep['observacao'] ?? '')) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p><small>Nenhum repasse solicitado até o momento.</small></p>
                <?php endif; ?>
            </section>

            <section class="loja-card-destaque" aria-labelledby="titulo-desempenho-obras">
                <h2 id="titulo-desempenho-obras">Suas obras na loja (<?= count($resumo['produtos']) ?>)</h2>
                <?php if (!$resumo['produtos']): ?>
                    <p class="estado-vazio">Você ainda não cadastrou obras na loja. <a href="loja-autor-obras.php">Cadastrar primeira obra</a></p>
                <?php else: ?>
                    <div class="loja-tabela-wrap">
                        <table class="loja-tabela">
                            <thead>
                                <tr>
                                    <th>Obra</th>
                                    <th>Status</th>
                                    <th>Preço</th>
                                    <th>Vendas</th>
                                    <th>Estornos</th>
                                    <th>Bruto</th>
                                    <th>Líquido</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resumo['produtos'] as $prod): ?>
                                    <tr>
                                        <td><strong><?= folivro_escape($prod['titulo_produto']) ?></strong></td>
                                        <td><span class="selo-status"><?= folivro_escape(folivro_loja_status_produto_label((string) $prod['status_produto'])) ?></span></td>
                                        <td><?= (int) $prod['preco_centavos'] === 0 ? 'Gratuita' : folivro_escape(folivro_loja_formatar((int) $prod['preco_centavos'])) ?></td>
                                        <td><?= (int) $prod['vendas'] ?></td>
                                        <td><?= (int) $prod['reembolsos'] ?></td>
                                        <td><?= folivro_escape(folivro_loja_formatar((int) $prod['bruto'])) ?></td>
                                        <td><strong><?= folivro_escape(folivro_loja_formatar((int) $prod['liquido'])) ?></strong></td>
                                        <td><a class="filtro-pilula" href="loja-autor-obras.php?editar=<?= (int) $prod['id_produto'] ?>">Editar</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="loja-card-destaque" aria-labelledby="titulo-pedidos-recebidos">
                <h2 id="titulo-pedidos-recebidos">Pedidos recebidos (últimos 50)</h2>
                <?php if (!$pedidos): ?>
                    <p class="estado-vazio">Nenhum pedido registrado ainda.</p>
                <?php else: ?>
                    <div class="loja-tabela-wrap">
                        <table class="loja-tabela">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Obra</th>
                                    <th>Comprador</th>
                                    <th>Método</th>
                                    <th>Bruto</th>
                                    <th>Taxa</th>
                                    <th>Líquido</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $ped): ?>
                                    <tr>
                                        <td>
                                            <code><?= folivro_escape($ped['codigo_pedido']) ?></code><br>
                                            <small><?= date('d/m/Y H:i', strtotime((string) $ped['criado_em'])) ?></small>
                                        </td>
                                        <td><?= folivro_escape($ped['titulo_produto_snapshot']) ?></td>
                                        <td><?= folivro_escape($ped['comprador_nome']) ?></td>
                                        <td><?= strtoupper(folivro_escape((string) $ped['metodo_pagamento'])) ?> (<?= $ped['modo_pagamento'] === 'simulado' ? 'Sim.' : 'Real' ?>)</td>
                                        <td><?= folivro_escape(folivro_loja_formatar((int) $ped['valor_bruto_centavos'])) ?></td>
                                        <td><?= folivro_escape(folivro_loja_formatar((int) $ped['valor_taxa_centavos'])) ?></td>
                                        <td><strong><?= folivro_escape(folivro_loja_formatar((int) $ped['valor_liquido_centavos'])) ?></strong></td>
                                        <td>
                                            <span class="selo-status selo-pedido-<?= folivro_escape($ped['status_pedido']) ?>">
                                                <?= folivro_escape(folivro_loja_status_pedido_label((string) $ped['status_pedido'], $ped['motivo_status'] ?? null)) ?>
                                            </span>
                                            <?php if ($ped['status_pedido'] === 'reembolsado' && !empty($ped['reembolsado_em'])): ?>
                                                <br><small>Estornado em <?= date('d/m/Y', strtotime((string) $ped['reembolsado_em'])) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($ped['status_pedido'] === 'pago' && (int) $ped['valor_bruto_centavos'] > 0): ?>
                                                <form method="post" onsubmit="return confirm('Confirma o reembolso deste pedido? O valor líquido será estornado do seu saldo e o acesso do leitor será removido.');">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                    <input type="hidden" name="acao" value="reembolsar_pedido">
                                                    <input type="hidden" name="id_pedido" value="<?= (int) $ped['id_pedido'] ?>">
                                                    <input type="hidden" name="motivo" value="Reembolso solicitado pelo autor no painel">
                                                    <button type="submit" class="filtro-pilula">Estornar</button>
                                                </form>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($extrato): ?>
                <section class="loja-card-destaque" aria-labelledby="titulo-extrato-ledger">
                    <h2 id="titulo-extrato-ledger">Extrato do ledger (últimos 30 lançamentos)</h2>
                    <div class="loja-tabela-wrap">
                        <table class="loja-tabela">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Descrição</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($extrato as $lan): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime((string) $lan['criado_em'])) ?></td>
                                        <td><code><?= folivro_escape($lan['tipo_lancamento']) ?></code></td>
                                        <td><?= folivro_escape($lan['descricao']) ?></td>
                                        <td><strong><?= folivro_escape(folivro_loja_formatar((int) $lan['valor_centavos'])) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
            <script>
                (function () {
                    const canvas = document.getElementById('grafico-vendas-autor');
                    if (!canvas || typeof Chart === 'undefined') return;
                    const meses = <?= json_encode(array_keys($resumo['meses'])) ?>;
                    const dadosBruto = <?= json_encode(array_map(static fn($m) => round($m['bruto'] / 100, 2), array_values($resumo['meses']))) ?>;
                    const dadosLiquido = <?= json_encode(array_map(static fn($m) => round($m['liquido'] / 100, 2), array_values($resumo['meses']))) ?>;

                    new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: meses,
                            datasets: [
                                { label: 'Receita Bruta (R$)', data: dadosBruto, backgroundColor: 'rgba(155, 91, 40, 0.72)', borderColor: '#9b5b28', borderWidth: 1 },
                                { label: 'Receita Líquida (R$)', data: dadosLiquido, backgroundColor: 'rgba(92, 0, 0, 0.85)', borderColor: '#5c0000', borderWidth: 1 }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: { y: { beginAtZero: true } }
                        }
                    });
                })();
            </script>
        <?php endif; ?>
    </main>
</body>
</html>
