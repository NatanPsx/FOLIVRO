<?php
declare(strict_types=1);
require_once __DIR__ . '/config/loja.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];

$st = $pdo->prepare(
    'SELECT o.*, p.arquivo_tipo, p.capa_produto, p.id_livro, u.nome_usuario AS vendedor_nome
       FROM loja_pedidos o
       JOIN loja_produtos p ON p.id_produto = o.id_produto
       JOIN usuario u ON u.id_usuario = o.id_vendedor
      WHERE o.id_comprador = ?
   ORDER BY o.criado_em DESC, o.id_pedido DESC'
);
$st->execute([$uid]);
$pedidos = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minhas Compras | FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261117_1">
    <link rel="stylesheet" href="home.css?v=20261117_1">
    <link rel="stylesheet" href="loja.css?v=20261117_1">
</head>
<body>
    <?php $menuAtivo = 'loja'; require __DIR__ . '/includes/menu-lateral.php'; ?>
    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <div class="loja-topo-cabecalho">
            <div>
                <h1>Minhas compras e recibos</h1>
                <p>Histórico de pedidos realizados na loja, acesso ao download das obras adquiridas e status de pagamentos.</p>
            </div>
            <div class="loja-atalhos-topo">
                <a class="filtro-pilula" href="loja.php">Voltar à loja</a>
                <a class="filtro-pilula" href="minha-biblioteca.php">Minha biblioteca</a>
            </div>
        </div>

        <section class="loja-card-destaque">
            <?php if (!$pedidos): ?>
                <p class="estado-vazio">Você ainda não realizou nenhum pedido na loja. <a href="loja.php">Explorar obras</a></p>
            <?php else: ?>
                <div class="loja-tabela-wrap">
                    <table class="loja-tabela">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Obra / Autor</th>
                                <th>Data</th>
                                <th>Modo / Método</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $ped): ?>
                                <tr>
                                    <td><code><?= folivro_escape($ped['codigo_pedido']) ?></code></td>
                                    <td>
                                        <strong><a href="loja-obra.php?id=<?= (int) $ped['id_produto'] ?>"><?= folivro_escape($ped['titulo_produto_snapshot']) ?></a></strong><br>
                                        <small>Por <?= folivro_escape($ped['vendedor_nome']) ?></small>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime((string) $ped['criado_em'])) ?></td>
                                    <td>
                                        <?= $ped['modo_pagamento'] === 'simulado' ? 'Simulado' : 'Real' ?> · <?= strtoupper(folivro_escape((string) $ped['metodo_pagamento'])) ?>
                                    </td>
                                    <td><?= (int) $ped['valor_bruto_centavos'] === 0 ? 'Gratuito' : folivro_escape(folivro_loja_formatar((int) $ped['valor_bruto_centavos'])) ?></td>
                                    <td>
                                        <span class="selo-status selo-pedido-<?= folivro_escape($ped['status_pedido']) ?>">
                                            <?= folivro_escape(folivro_loja_status_pedido_label((string) $ped['status_pedido'], $ped['motivo_status'] ?? null)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                            <a class="filtro-pilula" href="loja-pedido.php?codigo=<?= urlencode((string) $ped['codigo_pedido']) ?>">Recibo / Detalhes</a>
                                            <?php if ($ped['status_pedido'] === 'pago'): ?>
                                                <a class="botao-adicionar-livro" href="loja-download.php?id=<?= (int) $ped['id_produto'] ?>">
                                                    Baixar <?= strtoupper(folivro_escape((string) $ped['arquivo_tipo'])) ?>
                                                </a>
                                                <a class="filtro-pilula" href="loja-obra.php?id=<?= (int) $ped['id_produto'] ?>#titulo-avaliacoes">Avaliar</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
