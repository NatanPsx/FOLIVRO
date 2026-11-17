<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];
$erroMeta = '';
$sucessoMeta = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $erroMeta = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $acao = (string) ($_POST['acao'] ?? '');

        if ($acao === 'salvar_meta') {
            $quantidade = filter_input(INPUT_POST, 'quantidade_metas', FILTER_VALIDATE_INT);
            $tipo = ($_POST['tipo_metas'] ?? 'livros') === 'paginas' ? 'paginas' : 'livros';
            if (!is_int($quantidade) || $quantidade < 1 || $quantidade > 100000) {
                $erroMeta = 'Informe uma meta válida (entre 1 e 100.000).';
            } else {
                $ano = date('Y');
                $titulo = 'Meta anual ' . $ano;
                $inicio = $ano . '-01-01';
                $fim = $ano . '-12-31';

                $checkMeta = $pdo->prepare(
                    "SELECT id_metas_leitura FROM metas_leitura
                      WHERE id_usuario = ? AND titulo_metas = ? AND status_metas IN ('em_andamento', 'concluida')
                   ORDER BY id_metas_leitura DESC LIMIT 1"
                );
                $checkMeta->execute([$uid, $titulo]);
                $idMetaExistente = $checkMeta->fetchColumn();

                if ($idMetaExistente) {
                    $pdo->prepare(
                        'UPDATE metas_leitura SET tipo_metas = ?, quantidade_metas = ?, data_inicio_metas = ?, data_fim_metas = ?
                          WHERE id_metas_leitura = ? AND id_usuario = ?'
                    )->execute([$tipo, $quantidade, $inicio, $fim, (int) $idMetaExistente, $uid]);
                } else {
                    $pdo->prepare(
                        "INSERT INTO metas_leitura (id_usuario, titulo_metas, tipo_metas, quantidade_metas, progresso_metas, data_inicio_metas, data_fim_metas, status_metas)
                         VALUES (?, ?, ?, ?, 0, ?, ?, 'em_andamento')"
                    )->execute([$uid, $titulo, $tipo, $quantidade, $inicio, $fim]);
                }
                $sucessoMeta = 'Meta anual atualizada com sucesso.';
            }
        } elseif ($acao === 'criar_meta_extra') {
            $tituloExtra = trim((string) ($_POST['titulo_metas'] ?? ''));
            $tipoExtra = ($_POST['tipo_metas'] ?? 'livros') === 'paginas' ? 'paginas' : 'livros';
            $qtdExtra = filter_input(INPUT_POST, 'quantidade_metas', FILTER_VALIDATE_INT);
            $dataFim = trim((string) ($_POST['data_fim_metas'] ?? ''));
            $hoje = date('Y-m-d');

            if (mb_strlen($tituloExtra, 'UTF-8') < 2 || mb_strlen($tituloExtra, 'UTF-8') > 120) {
                $erroMeta = 'Informe um título entre 2 e 120 caracteres para o desafio.';
            } elseif (!is_int($qtdExtra) || $qtdExtra < 1 || $qtdExtra > 100000) {
                $erroMeta = 'Informe uma quantidade válida (entre 1 e 100.000).';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim) || $dataFim < $hoje) {
                $erroMeta = 'Escolha uma data limite igual ou posterior a hoje.';
            } else {
                $pdo->prepare(
                    "INSERT INTO metas_leitura (id_usuario, titulo_metas, tipo_metas, quantidade_metas, progresso_metas, data_inicio_metas, data_fim_metas, status_metas)
                     VALUES (?, ?, ?, ?, 0, ?, ?, 'em_andamento')"
                )->execute([$uid, $tituloExtra, $tipoExtra, $qtdExtra, $hoje, $dataFim]);
                $sucessoMeta = 'Novo desafio de leitura criado!';
            }
        } elseif ($acao === 'cancelar_meta_extra') {
            $idMetaRem = filter_input(INPUT_POST, 'id_metas_leitura', FILTER_VALIDATE_INT) ?: 0;
            if ($idMetaRem > 0) {
                $pdo->prepare("UPDATE metas_leitura SET status_metas = 'cancelada' WHERE id_metas_leitura = ? AND id_usuario = ?")
                    ->execute([$idMetaRem, $uid]);
                $sucessoMeta = 'Meta removida.';
            }
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS livros_na_biblioteca,
            COALESCE(SUM(l.status_livraria = 'lido'), 0) AS livros_lidos,
            COALESCE(SUM(l.status_livraria = 'lendo'), 0) AS livros_lendo,
            COALESCE(SUM(l.status_livraria = 'quero_ler'), 0) AS livros_quero_ler,
            COALESCE(SUM(l.status_livraria = 'abandonado'), 0) AS livros_abandonados,
            COALESCE(SUM(p.pagina_atual_progressao), 0) AS paginas_lidas,
            (SELECT COALESCE(pontos_contador_de_pontos, 0) FROM contador_pontos WHERE id_usuario = ?) AS pontos_leitura,
            (SELECT COUNT(*) FROM livraria_digital WHERE id_usuario = ?) AS obras_adquiridas,
            (SELECT COUNT(*) FROM resenha WHERE id_usuario = ?) AS total_resenhas
       FROM livraria l
  LEFT JOIN progressao_leitura p ON p.id_usuario = l.id_usuario AND p.id_livro = l.id_livro
      WHERE l.id_usuario = ?"
);
$stmt->execute([$uid, $uid, $uid, $uid]);
$stats = $stmt->fetch() ?: [
    'livros_na_biblioteca' => 0,
    'livros_lidos' => 0,
    'livros_lendo' => 0,
    'livros_quero_ler' => 0,
    'livros_abandonados' => 0,
    'paginas_lidas' => 0,
    'pontos_leitura' => 0,
    'obras_adquiridas' => 0,
    'total_resenhas' => 0,
];

$totalAcervo = max(1, (int) $stats['livros_na_biblioteca'] + (int) $stats['obras_adquiridas']);
$barrasDistribuicao = [
    ['rotulo' => 'Concluídos (Lidos)', 'qtd' => (int) $stats['livros_lidos'], 'cor' => '#2f7a44'],
    ['rotulo' => 'Em leitura (Lendo)', 'qtd' => (int) $stats['livros_lendo'], 'cor' => '#861a11'],
    ['rotulo' => 'Quero ler', 'qtd' => (int) $stats['livros_quero_ler'], 'cor' => '#c77d2b'],
    ['rotulo' => 'Obras da loja', 'qtd' => (int) $stats['obras_adquiridas'], 'cor' => '#6b3fa0'],
    ['rotulo' => 'Abandonados', 'qtd' => (int) $stats['livros_abandonados'], 'cor' => '#6e6a68'],
];

// Meta anual vigente
$tituloAnual = 'Meta anual ' . date('Y');
$metaStmt = $pdo->prepare(
    "SELECT id_metas_leitura, titulo_metas, tipo_metas, quantidade_metas, progresso_metas, status_metas
       FROM metas_leitura
      WHERE id_usuario = ? AND status_metas IN ('em_andamento', 'concluida')
   ORDER BY (titulo_metas = ?) DESC, id_metas_leitura DESC
      LIMIT 1"
);
$metaStmt->execute([$uid, $tituloAnual]);
$meta = $metaStmt->fetch();

$tipoMeta = $meta['tipo_metas'] ?? 'livros';
$alvoMeta = $meta ? max(1, (int) $meta['quantidade_metas']) : 12;
$atualMeta = $tipoMeta === 'paginas' ? (int) $stats['paginas_lidas'] : (int) $stats['livros_lidos'];
$pctMeta = min(100, (int) round(($atualMeta / $alvoMeta) * 100));
$unidadeMeta = $tipoMeta === 'paginas' ? 'páginas' : 'livros';

if ($meta) {
    $novoStatus = $atualMeta >= $alvoMeta ? 'concluida' : 'em_andamento';
    if ((int) $meta['progresso_metas'] !== $atualMeta || $meta['status_metas'] !== $novoStatus) {
        $pdo->prepare('UPDATE metas_leitura SET progresso_metas = ?, status_metas = ? WHERE id_metas_leitura = ?')
            ->execute([$atualMeta, $novoStatus, (int) $meta['id_metas_leitura']]);
    }
}

// Ritmo necessário para bater a meta anual
$fimAno = new DateTimeImmutable(date('Y') . '-12-31');
$hojeDt = new DateTimeImmutable('today');
$diasRestantes = max(1, (int) $hojeDt->diff($fimAno)->days + 1);
$faltam = max(0, $alvoMeta - $atualMeta);
$mesesRestantes = max(1, (int) ceil($diasRestantes / 30));

if ($faltam === 0) {
    $seloRitmo = 'Meta concluída!';
    $classeSeloRitmo = 'concluida';
    $textoRitmo = 'Parabéns! Você já atingiu sua meta anual de ' . $alvoMeta . ' ' . $unidadeMeta . '. Que tal aumentar o desafio?';
} else {
    $classeSeloRitmo = '';
    if ($tipoMeta === 'paginas') {
        $porDia = max(1, (int) ceil($faltam / $diasRestantes));
        $seloRitmo = $porDia . ' pág./dia';
        $textoRitmo = 'Faltam ' . $faltam . ' página(s) nos próximos ' . $diasRestantes . ' dias — cerca de ' . $porDia . ' página(s) por dia.';
    } else {
        $porMes = round($faltam / $mesesRestantes, 1);
        $seloRitmo = number_format($porMes, 1, ',', '.') . ' livro(s)/mês';
        $textoRitmo = 'Faltam ' . $faltam . ' livro(s) até 31/12/' . date('Y') . ' — ritmo de aproximadamente ' . number_format($porMes, 1, ',', '.') . ' livro(s) por mês.';
    }
}

// Desafios extras (outras metas ativas além da anual principal)
$extrasStmt = $pdo->prepare(
    "SELECT * FROM metas_leitura
      WHERE id_usuario = ? AND status_metas IN ('em_andamento', 'concluida') AND id_metas_leitura <> ?
   ORDER BY data_fim_metas ASC, id_metas_leitura DESC"
);
$extrasStmt->execute([$uid, (int) ($meta['id_metas_leitura'] ?? 0)]);
$metasExtras = $extrasStmt->fetchAll(PDO::FETCH_ASSOC);

// Conquistas de leitura
$totalAcervoReal = (int) $stats['livros_na_biblioteca'] + (int) $stats['obras_adquiridas'];
$conquistas = [
    ['icone' => '📖', 'titulo' => 'Primeiro Passo', 'desc' => 'Adicione 1+ livro na biblioteca', 'ok' => $totalAcervoReal >= 1],
    ['icone' => '🏁', 'titulo' => 'Leitor Finalizador', 'desc' => 'Conclua pelo menos 1 livro', 'ok' => (int) $stats['livros_lidos'] >= 1],
    ['icone' => '🔥', 'titulo' => 'Maratonista (500+)', 'desc' => 'Leia 500 páginas registradas', 'ok' => (int) $stats['paginas_lidas'] >= 500],
    ['icone' => '✍️', 'titulo' => 'Crítico Literário', 'desc' => 'Escreva 1+ resenha na comunidade', 'ok' => (int) $stats['total_resenhas'] >= 1],
    ['icone' => '📚', 'titulo' => 'Colecionador', 'desc' => 'Reúna 5+ títulos na estante', 'ok' => $totalAcervoReal >= 5],
];

$csrf = folivro_escape(folivro_csrf_token());
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Metas e Estatísticas | FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261104_20">
    <link rel="stylesheet" href="estatisticas.css?v=20261117_2">
</head>

<body>

    <?php $menuAtivo = 'estatisticas'; require __DIR__ . '/includes/menu-lateral.php'; ?>
    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <section class="painel painel-metas">
            <header class="cabecalho">
                <h1>Metas e Estatísticas de Leitura</h1>
                <p>Acompanhe seu ritmo anual, crie desafios personalizados e visualize o panorama da sua estante.</p>
            </header>

            <?php if ($erroMeta): ?>
                <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape($erroMeta) ?></p>
            <?php endif; ?>
            <?php if ($sucessoMeta): ?>
                <p class="estado-vazio estado-sucesso" role="status"><?= folivro_escape($sucessoMeta) ?></p>
            <?php endif; ?>

            <section class="grade-estatisticas">
                <div class="estatistica">
                    <h2>Páginas lidas</h2>
                    <p class="numero"><?= (int) $stats['paginas_lidas'] ?></p>
                    <span>páginas registradas</span>
                </div>
                <div class="estatistica">
                    <h2>Livros concluídos</h2>
                    <p class="numero"><?= (int) $stats['livros_lidos'] ?></p>
                    <span>títulos finalizados</span>
                </div>
                <div class="estatistica">
                    <h2>Na estante</h2>
                    <p class="numero"><?= $totalAcervoReal ?></p>
                    <span><?= (int) $stats['livros_lendo'] ?> em leitura agora</span>
                </div>
                <div class="estatistica">
                    <h2>Pontos de leitura</h2>
                    <p class="numero"><?= (int) $stats['pontos_leitura'] ?></p>
                    <span><?= (int) $stats['total_resenhas'] ?> resenha(s) publicada(s)</span>
                </div>
            </section>

            <div class="grade-metas-paineis">
                <section class="meta-anual" aria-labelledby="titulo-meta-anual">
                    <div class="meta-cabecalho-linha">
                        <h2 id="titulo-meta-anual">Meta anual <?= date('Y') ?></h2>
                        <span class="selo-ritmo-meta <?= folivro_escape($classeSeloRitmo) ?>">
                            <?= folivro_escape($seloRitmo) ?>
                        </span>
                    </div>

                    <div class="informacoes-meta">
                        <p>Objetivo: <span id="meta"><?= $alvoMeta ?></span> <?= folivro_escape($unidadeMeta) ?></p>
                        <strong>
                            <span id="livrosLidos"><?= $atualMeta ?></span> /
                            <span id="metaResumo"><?= $alvoMeta ?></span> <?= folivro_escape($unidadeMeta) ?>
                        </strong>
                    </div>

                    <div class="linha-meta">
                        <div class="barra" role="progressbar" aria-valuenow="<?= $pctMeta ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progresso" id="progresso" style="width: <?= $pctMeta ?>%"></div>
                        </div>
                        <strong class="porcentagem" id="porcentagem"><?= $pctMeta ?>%</strong>
                    </div>

                    <div class="meta-ritmo-detalhe">
                        <?= folivro_escape($textoRitmo) ?>
                    </div>

                    <details class="configurar-meta" style="margin-top:14px;">
                        <summary><?= $meta ? 'Ajustar meta anual' : 'Definir meta anual' ?></summary>
                        <form method="post" class="progresso-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="acao" value="salvar_meta">
                            <label for="tipo_metas">Tipo de meta
                                <select id="tipo_metas" name="tipo_metas">
                                    <option value="livros" <?= $tipoMeta === 'livros' ? 'selected' : '' ?>>Livros lidos no ano</option>
                                    <option value="paginas" <?= $tipoMeta === 'paginas' ? 'selected' : '' ?>>Páginas lidas no ano</option>
                                </select>
                            </label>
                            <label for="quantidade_metas">Quantidade desejada
                                <input id="quantidade_metas" type="number" name="quantidade_metas" min="1" max="100000" value="<?= $alvoMeta ?>" required>
                            </label>
                            <button class="botao-adicionar-livro" type="submit">Salvar meta anual</button>
                        </form>
                    </details>
                </section>

                <section class="meta-anual" aria-labelledby="titulo-distribuicao">
                    <h2 id="titulo-distribuicao">Distribuição da estante</h2>
                    <?php if ($totalAcervoReal === 0): ?>
                        <p class="estado-vazio">Adicione livros à sua biblioteca para visualizar o gráfico de distribuição.</p>
                    <?php else: ?>
                        <div class="grafico-rosca-box">
                            <canvas id="grafico-distribuicao-acervo"></canvas>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="meta-anual" aria-labelledby="titulo-desafios-extras">
                <div class="meta-cabecalho-linha">
                    <h2 id="titulo-desafios-extras">Desafios personalizados</h2>
                    <details class="configurar-meta" style="margin:0;">
                        <summary>+ Novo desafio</summary>
                        <form method="post" class="progresso-form" style="margin-top:10px;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="acao" value="criar_meta_extra">
                            <label for="titulo_extra">Nome do desafio
                                <input id="titulo_extra" name="titulo_metas" maxlength="120" required placeholder="Ex.: Maratona de Férias">
                            </label>
                            <div class="grade-campos-dupla">
                                <label for="tipo_extra">Medir por
                                    <select id="tipo_extra" name="tipo_metas">
                                        <option value="livros">Livros concluídos</option>
                                        <option value="paginas">Páginas lidas</option>
                                    </select>
                                </label>
                                <label for="qtd_extra">Alvo
                                    <input id="qtd_extra" type="number" name="quantidade_metas" min="1" max="100000" value="5" required>
                                </label>
                            </div>
                            <label for="fim_extra">Prazo final
                                <input id="fim_extra" type="date" name="data_fim_metas" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                            </label>
                            <button type="submit" class="botao-adicionar-livro">Criar desafio</button>
                        </form>
                    </details>
                </div>

                <?php if (!$metasExtras): ?>
                    <p><small>Você ainda não criou desafios extras. Use “+ Novo desafio” para definir metas mensais ou de férias.</small></p>
                <?php else: ?>
                    <div class="lista-metas-personalizadas">
                        <?php foreach ($metasExtras as $mx): ?>
                            <?php
                            $alvoX = max(1, (int) $mx['quantidade_metas']);
                            $atualX = $mx['tipo_metas'] === 'paginas' ? (int) $stats['paginas_lidas'] : (int) $stats['livros_lidos'];
                            $pctX = min(100, (int) round(($atualX / $alvoX) * 100));
                            $unidX = $mx['tipo_metas'] === 'paginas' ? 'páginas' : 'livros';
                            ?>
                            <article class="card-meta-extra">
                                <div class="card-meta-extra-topo">
                                    <div>
                                        <h3><?= folivro_escape($mx['titulo_metas']) ?></h3>
                                        <small>Até <?= date('d/m/Y', strtotime((string) $mx['data_fim_metas'])) ?></small>
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="acao" value="cancelar_meta_extra">
                                        <input type="hidden" name="id_metas_leitura" value="<?= (int) $mx['id_metas_leitura'] ?>">
                                        <button type="submit" class="filtro-pilula" style="padding:4px 10px; font-size:12px;">Remover</button>
                                    </form>
                                </div>
                                <div class="informacoes-meta" style="margin-bottom:6px;">
                                    <small><?= $atualX ?> / <?= $alvoX ?> <?= $unidX ?></small>
                                    <strong><?= $pctX ?>%</strong>
                                </div>
                                <div class="barra" style="height:12px;">
                                    <div class="progresso" style="width: <?= $pctX ?>%"></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="meta-anual" aria-labelledby="titulo-conquistas">
                <h2 id="titulo-conquistas">Conquistas de leitura</h2>
                <div class="grade-conquistas">
                    <?php foreach ($conquistas as $cq): ?>
                        <div class="card-conquista <?= $cq['ok'] ? 'desbloqueada' : '' ?>">
                            <div class="icone-conquista" aria-hidden="true"><?= $cq['icone'] ?></div>
                            <div>
                                <strong><?= folivro_escape($cq['titulo']) ?></strong>
                                <small><?= folivro_escape($cq['desc']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <p class="nota-estatisticas">Os indicadores, conquistas e projeções de ritmo são calculados automaticamente a partir dos livros na sua estante, progresso de leitura e resenhas publicadas.</p>
        </section>
    </main>

    <?php if ($totalAcervoReal > 0): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                const canvas = document.getElementById('grafico-distribuicao-acervo');
                if (!canvas || typeof Chart === 'undefined') return;
                const itens = <?= json_encode($barrasDistribuicao, JSON_UNESCAPED_UNICODE) ?>;
                new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels: itens.map(i => i.rotulo),
                        datasets: [{
                            data: itens.map(i => i.qtd),
                            backgroundColor: itens.map(i => i.cor),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            })();
        </script>
    <?php endif; ?>
</body>

</html>
