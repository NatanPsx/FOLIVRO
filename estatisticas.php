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
    ['icone' => folivro_icone('conquista-primeiro-passo', 'folivro-icon-lg'), 'titulo' => 'Primeiro Passo', 'desc' => 'Adicione 1+ livro na biblioteca', 'ok' => $totalAcervoReal >= 1],
    ['icone' => folivro_icone('conquista-finalizador', 'folivro-icon-lg'), 'titulo' => 'Leitor Finalizador', 'desc' => 'Conclua pelo menos 1 livro', 'ok' => (int) $stats['livros_lidos'] >= 1],
    ['icone' => folivro_icone('conquista-maratonista', 'folivro-icon-lg'), 'titulo' => 'Maratonista (500+)', 'desc' => 'Leia 500 páginas registradas', 'ok' => (int) $stats['paginas_lidas'] >= 500],
    ['icone' => folivro_icone('conquista-critico', 'folivro-icon-lg'), 'titulo' => 'Crítico Literário', 'desc' => 'Escreva 1+ resenha na comunidade', 'ok' => (int) $stats['total_resenhas'] >= 1],
    ['icone' => folivro_icone('conquista-colecionador', 'folivro-icon-lg'), 'titulo' => 'Colecionador', 'desc' => 'Reúna 5+ títulos na estante', 'ok' => $totalAcervoReal >= 5],
];

$csrf = folivro_escape(folivro_csrf_token());
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Metas e Estatísticas | FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261125_01">
    <link rel="stylesheet" href="estatisticas.css?v=20261125_01">
</head>

<body>

    <?php $menuAtivo = 'estatisticas'; require __DIR__ . '/includes/menu-lateral.php'; ?>
    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <section class="painel painel-metas">
            <header class="cabecalho">
                <h1 class="titulo-com-icone-folivro"><?= folivro_icone('folha-metas-f', 'folivro-icon-lg') ?> Metas e Estatísticas de Leitura</h1>
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

            <section class="meta-anual secao-desafios" aria-labelledby="titulo-desafios-extras">
                <div class="desafios-cabecalho">
                    <div class="desafios-cabecalho-texto">
                        <h2 id="titulo-desafios-extras">Desafios personalizados</h2>
                        <p class="desafios-subtitulo">Defina metas extras de curto prazo — como maratonas mensais, férias ou desafios de páginas.</p>
                    </div>
                    <button type="button" id="btn-toggle-novo-desafio" class="botao-adicionar-livro btn-novo-desafio-toggle" aria-expanded="false" aria-controls="painel-novo-desafio">
                        + Novo desafio
                    </button>
                </div>

                <div id="painel-novo-desafio" class="painel-novo-desafio" hidden>
                    <div class="desafios-sugestoes">
                        <span class="desafios-sugestoes-rotulo">Sugestões rápidas:</span>
                        <div class="desafios-sugestoes-lista">
                            <button type="button" class="chip-sugestao-desafio" data-nome="Maratona de Férias" data-tipo="livros" data-alvo="3" data-dias="30">Maratona de Férias (3 livros)</button>
                            <button type="button" class="chip-sugestao-desafio" data-nome="Desafio 500 Páginas" data-tipo="paginas" data-alvo="500" data-dias="30">Desafio 500 Páginas</button>
                            <button type="button" class="chip-sugestao-desafio" data-nome="Clássicos Brasileiros" data-tipo="livros" data-alvo="2" data-dias="45">Clássicos Brasileiros (2 livros)</button>
                            <button type="button" class="chip-sugestao-desafio" data-nome="Sprint Semanal" data-tipo="paginas" data-alvo="150" data-dias="14">Sprint 150 Páginas (14 dias)</button>
                        </div>
                    </div>

                    <form method="post" class="form-novo-desafio">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="acao" value="criar_meta_extra">
                        <div class="grade-form-desafio">
                            <label for="titulo_extra" class="campo-desafio-nome">Nome do desafio
                                <input id="titulo_extra" name="titulo_metas" maxlength="120" required placeholder="Ex.: Maratona de Férias">
                            </label>
                            <label for="tipo_extra">Medir por
                                <select id="tipo_extra" name="tipo_metas">
                                    <option value="livros">Livros concluídos</option>
                                    <option value="paginas">Páginas lidas</option>
                                </select>
                            </label>
                            <label for="qtd_extra">Alvo
                                <input id="qtd_extra" type="number" name="quantidade_metas" min="1" max="100000" value="5" required>
                            </label>
                            <label for="fim_extra">Prazo final
                                <input id="fim_extra" type="date" name="data_fim_metas" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                            </label>
                        </div>
                        <div class="acoes-form-desafio">
                            <button type="submit" class="botao-adicionar-livro">Criar desafio</button>
                            <button type="button" id="btn-cancelar-novo-desafio" class="filtro-pilula">Cancelar</button>
                        </div>
                    </form>
                </div>

                <?php if (!$metasExtras): ?>
                    <div class="desafios-estado-vazio">
                        <p class="desafios-vazio-titulo">Nenhum desafio personalizado ativo no momento</p>
                        <p class="desafios-vazio-desc">Crie uma meta rápida de férias, leitura mensal ou páginas lidas para acompanhar seu ritmo.</p>
                        <button type="button" class="filtro-pilula btn-abrir-desafio-vazio">Criar meu primeiro desafio</button>
                    </div>
                <?php else: ?>
                    <div class="lista-metas-personalizadas">
                        <?php foreach ($metasExtras as $mx): ?>
                            <?php
                            $alvoX = max(1, (int) $mx['quantidade_metas']);
                            $atualX = $mx['tipo_metas'] === 'paginas' ? (int) $stats['paginas_lidas'] : (int) $stats['livros_lidos'];
                            $pctX = min(100, (int) round(($atualX / $alvoX) * 100));
                            $unidX = $mx['tipo_metas'] === 'paginas' ? 'páginas' : 'livros';
                            $concluidoX = $pctX >= 100;
                            $fimTs = strtotime((string) $mx['data_fim_metas'] . ' 23:59:59');
                            $diasRest = $fimTs ? (int) ceil(($fimTs - time()) / 86400) : 0;
                            if ($concluidoX) {
                                $badgeTexto = 'Concluído ✓';
                                $badgeClasse = 'concluido';
                            } elseif ($diasRest < 0) {
                                $badgeTexto = 'Prazo encerrado';
                                $badgeClasse = 'encerrado';
                            } elseif ($diasRest === 0) {
                                $badgeTexto = 'Termina hoje';
                                $badgeClasse = 'alerta';
                            } else {
                                $badgeTexto = $diasRest . ($diasRest === 1 ? ' dia restante' : ' dias restantes');
                                $badgeClasse = 'ativo';
                            }
                            ?>
                            <article class="card-meta-extra <?= $concluidoX ? 'card-meta-concluido' : '' ?>">
                                <div class="card-meta-extra-topo">
                                    <div class="card-meta-extra-cabecalho">
                                        <span class="badge-desafio-status badge-desafio-<?= $badgeClasse ?>"><?= folivro_escape($badgeTexto) ?></span>
                                        <h3><?= folivro_escape($mx['titulo_metas']) ?></h3>
                                        <small class="prazo-desafio">Prazo: <?= date('d/m/Y', strtotime((string) $mx['data_fim_metas'])) ?></small>
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="acao" value="cancelar_meta_extra">
                                        <input type="hidden" name="id_metas_leitura" value="<?= (int) $mx['id_metas_leitura'] ?>">
                                        <button type="submit" class="btn-remover-desafio" title="Remover desafio">Remover</button>
                                    </form>
                                </div>
                                <div class="informacoes-meta" style="margin-bottom:8px;">
                                    <small><?= number_format($atualX, 0, ',', '.') ?> / <?= number_format($alvoX, 0, ',', '.') ?> <?= $unidX ?></small>
                                    <strong><?= $pctX ?>%</strong>
                                </div>
                                <div class="barra" style="height:10px;">
                                    <div class="progresso <?= $concluidoX ? 'progresso-concluido' : '' ?>" style="width: <?= $pctX ?>%"></div>
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
    <script>
        (function () {
            const btnToggle = document.getElementById('btn-toggle-novo-desafio');
            const btnCancelar = document.getElementById('btn-cancelar-novo-desafio');
            const painel = document.getElementById('painel-novo-desafio');
            const inputNome = document.getElementById('titulo_extra');
            const selectTipo = document.getElementById('tipo_extra');
            const inputAlvo = document.getElementById('qtd_extra');
            const inputFim = document.getElementById('fim_extra');

            function setAberto(abrir) {
                if (!painel || !btnToggle) return;
                painel.hidden = !abrir;
                btnToggle.setAttribute('aria-expanded', abrir ? 'true' : 'false');
                btnToggle.textContent = abrir ? 'Fechar formulário' : '+ Novo desafio';
                if (abrir && inputNome) inputNome.focus();
            }

            if (btnToggle) {
                btnToggle.addEventListener('click', () => setAberto(painel.hidden));
            }
            if (btnCancelar) {
                btnCancelar.addEventListener('click', () => setAberto(false));
            }
            document.querySelectorAll('.btn-abrir-desafio-vazio').forEach((btn) => {
                btn.addEventListener('click', () => setAberto(true));
            });

            document.querySelectorAll('.chip-sugestao-desafio').forEach((chip) => {
                chip.addEventListener('click', () => {
                    if (inputNome) inputNome.value = chip.dataset.nome || '';
                    if (selectTipo) selectTipo.value = chip.dataset.tipo || 'livros';
                    if (inputAlvo) inputAlvo.value = chip.dataset.alvo || '5';
                    if (inputFim) {
                        const dias = Number(chip.dataset.dias) || 30;
                        const dt = new Date();
                        dt.setDate(dt.getDate() + dias);
                        inputFim.value = dt.toISOString().slice(0, 10);
                    }
                    document.querySelectorAll('.chip-sugestao-desafio').forEach((c) => c.classList.remove('ativo'));
                    chip.classList.add('ativo');
                });
            });
        })();
    </script>
</body>

</html>
