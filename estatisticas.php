<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];
$erroMeta = '';
$sucessoMeta = '';

// Criação ou atualização da meta anual de leitura (tabela metas_leitura - RF09).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_meta') {
    if (!folivro_verify_csrf()) {
        $erroMeta = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $quantidade = filter_input(INPUT_POST, 'quantidade_metas', FILTER_VALIDATE_INT);
        $tipo = ($_POST['tipo_metas'] ?? 'livros') === 'paginas' ? 'paginas' : 'livros';
        if (!is_int($quantidade) || $quantidade < 1 || $quantidade > 100000) {
            $erroMeta = 'Informe uma meta válida (entre 1 e 100.000).';
        } else {
            $ano = date('Y');
            $titulo = 'Meta anual ' . $ano;
            $inicio = $ano . '-01-01';
            $fim = $ano . '-12-31';

            // Verifica se já existe meta ativa ou concluída para o usuário.
            $checkMeta = $pdo->prepare(
                "SELECT id_metas_leitura FROM metas_leitura WHERE id_usuario = ? AND status_metas IN ('em_andamento', 'concluida') ORDER BY id_metas_leitura DESC LIMIT 1"
            );
            $checkMeta->execute([$uid]);
            $idMetaExistente = $checkMeta->fetchColumn();

            if ($idMetaExistente) {
                $upd = $pdo->prepare(
                    'UPDATE metas_leitura SET titulo_metas = ?, tipo_metas = ?, quantidade_metas = ?, data_inicio_metas = ?, data_fim_metas = ? WHERE id_metas_leitura = ? AND id_usuario = ?'
                );
                $upd->execute([$titulo, $tipo, $quantidade, $inicio, $fim, (int) $idMetaExistente, $uid]);
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO metas_leitura (id_usuario, titulo_metas, tipo_metas, quantidade_metas, progresso_metas, data_inicio_metas, data_fim_metas, status_metas)
                     VALUES (?, ?, ?, ?, 0, ?, ?, 'em_andamento')"
                );
                $ins->execute([$uid, $titulo, $tipo, $quantidade, $inicio, $fim]);
            }
            $sucessoMeta = 'Meta anual atualizada com sucesso.';
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
            (SELECT COUNT(*) FROM livraria_digital WHERE id_usuario = ?) AS obras_adquiridas
       FROM livraria l
  LEFT JOIN progressao_leitura p ON p.id_usuario = l.id_usuario AND p.id_livro = l.id_livro
      WHERE l.id_usuario = ?"
);
$stmt->execute([$uid, $uid, $uid]);
$stats = $stmt->fetch() ?: [
    'livros_na_biblioteca' => 0,
    'livros_lidos' => 0,
    'livros_lendo' => 0,
    'livros_quero_ler' => 0,
    'livros_abandonados' => 0,
    'paginas_lidas' => 0,
    'pontos_leitura' => 0,
    'obras_adquiridas' => 0,
];

$totalAcervo = max(1, (int) $stats['livros_na_biblioteca'] + (int) $stats['obras_adquiridas']);
$barrasDistribuicao = [
    ['rotulo' => 'Concluídos (Lidos)', 'qtd' => (int) $stats['livros_lidos'], 'cor' => '#4f7a3a'],
    ['rotulo' => 'Em leitura (Lendo)', 'qtd' => (int) $stats['livros_lendo'], 'cor' => '#6b130d'],
    ['rotulo' => 'Quero ler', 'qtd' => (int) $stats['livros_quero_ler'], 'cor' => '#9b5b28'],
    ['rotulo' => 'Obras autorais adquiridas', 'qtd' => (int) $stats['obras_adquiridas'], 'cor' => '#5a151f'],
    ['rotulo' => 'Abandonados', 'qtd' => (int) $stats['livros_abandonados'], 'cor' => '#777777'],
];

// Busca a meta ativa do usuário (RF09).
$metaStmt = $pdo->prepare(
    "SELECT id_metas_leitura, titulo_metas, tipo_metas, quantidade_metas, progresso_metas, status_metas
       FROM metas_leitura
      WHERE id_usuario = ? AND status_metas IN ('em_andamento', 'concluida')
   ORDER BY id_metas_leitura DESC
      LIMIT 1"
);
$metaStmt->execute([$uid]);
$meta = $metaStmt->fetch();

$tipoMeta = $meta['tipo_metas'] ?? 'livros';
$alvoMeta = $meta ? max(1, (int) $meta['quantidade_metas']) : 50;
$atualMeta = $tipoMeta === 'paginas' ? (int) $stats['paginas_lidas'] : (int) $stats['livros_lidos'];
$pctMeta = $meta ? min(100, (int) round(($atualMeta / $alvoMeta) * 100)) : min(100, (int) round(((int) $stats['livros_lidos'] / 50) * 100));
$unidadeMeta = $tipoMeta === 'paginas' ? 'páginas' : 'livros';

// Sincroniza progresso_metas e status_metas se houver meta cadastrada.
if ($meta) {
    $novoStatus = $atualMeta >= $alvoMeta ? 'concluida' : 'em_andamento';
    if ((int) $meta['progresso_metas'] !== $atualMeta || $meta['status_metas'] !== $novoStatus) {
        $pdo->prepare('UPDATE metas_leitura SET progresso_metas = ?, status_metas = ? WHERE id_metas_leitura = ?')
            ->execute([$atualMeta, $novoStatus, (int) $meta['id_metas_leitura']]);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estatísticas | FOLIVRO</title>
    <link rel="stylesheet" href="estatisticas.css?v=20261022_4">
    <link rel="stylesheet" href="layout.css?v=20261022_11">
</head>

<body>

    <?php $menuAtivo = 'estatisticas'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <section class="painel">
            <header class="cabecalho">
                <h1>Estatísticas Gerais</h1>
                <p>Resumo da sua jornada de leitura</p>
            </header>

            <section class="grade-estatisticas">
                <div class="estatistica">
                    <h2>Páginas lidas (total)</h2>
                    <p class="numero"><?= (int) $stats['paginas_lidas'] ?></p>
                    <span>páginas registradas</span>
                </div>
                <div class="estatistica">
                    <h2>Livros lidos (total)</h2>
                    <p class="numero"><?= (int) $stats['livros_lidos'] ?></p>
                    <span>livros concluídos</span>
                </div>
                <div class="estatistica">
                    <h2>Livros na biblioteca</h2>
                    <p class="numero"><?= (int) $stats['livros_na_biblioteca'] + (int) $stats['obras_adquiridas'] ?></p>
                    <span>títulos no acervo</span>
                </div>
                <div class="estatistica">
                    <h2>Pontos de leitura</h2>
                    <p class="numero"><?= (int) $stats['pontos_leitura'] ?></p>
                    <span>pontos acumulados</span>
                </div>
            </section>

            <!-- Meta anual de leitura (RF09 + layout do Figma) -->
            <section class="meta-anual" aria-labelledby="titulo-meta-anual">
                <h2 id="titulo-meta-anual">Meta anual de leitura</h2>

                <?php if ($erroMeta): ?>
                    <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape($erroMeta) ?></p>
                <?php endif; ?>
                <?php if ($sucessoMeta): ?>
                    <p class="estado-vazio estado-sucesso" role="status"><?= folivro_escape($sucessoMeta) ?></p>
                <?php endif; ?>

                <div class="informacoes-meta">
                    <p>Sua meta: <span id="meta"><?= $alvoMeta ?></span> <?= folivro_escape($unidadeMeta) ?></p>
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

                <details class="configurar-meta">
                    <summary><?= $meta ? 'Alterar meta anual' : 'Definir meta anual' ?></summary>
                    <form method="post" class="progresso-form">
                        <input type="hidden" name="csrf_token" value="<?= folivro_escape(folivro_csrf_token()) ?>">
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
                        <button class="botao-adicionar-livro" type="submit">Salvar meta</button>
                    </form>
                </details>
            </section>

            <!-- Gráfico visual de distribuição do acervo (alinhado ao Figma) -->
            <section class="meta-anual grafico-distribuicao" aria-labelledby="titulo-distribuicao">
                <h2 id="titulo-distribuicao">Distribuição da sua biblioteca</h2>
                <div class="lista-barras-status">
                    <?php foreach ($barrasDistribuicao as $itemBarra): ?>
                        <?php $pctBarra = min(100, (int) round(($itemBarra['qtd'] / $totalAcervo) * 100)); ?>
                        <div class="linha-barra-status">
                            <div class="cabecalho-barra-status">
                                <span><?= folivro_escape($itemBarra['rotulo']) ?></span>
                                <strong><?= $itemBarra['qtd'] ?> (<?= $pctBarra ?>%)</strong>
                            </div>
                            <div class="trilho-barra-status" role="progressbar" aria-valuenow="<?= $pctBarra ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="preenchimento-barra-status" style="width: <?= $pctBarra ?>%; background: <?= $itemBarra['cor'] ?>;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <p class="nota-estatisticas">As páginas, pontos e livros lidos consideram os títulos adicionados à biblioteca e o progresso informado pelo usuário.</p>
        </section>
    </main>

</body>

</html>
