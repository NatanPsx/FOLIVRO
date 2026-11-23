<?php
declare(strict_types=1);
require_once __DIR__ . '/config/loja.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$produto = $id > 0 ? folivro_loja_produto($pdo, $id) : null;
$jaComprou = $produto ? folivro_loja_ja_possui($pdo, $uid, $id) : false;
$ehAutor = $produto && (int) $produto['id_autor'] === $uid;

// Só exibe se publicada e sem bloqueio de moderação (ou se o usuário já comprou / é o autor).
if ($produto === null || (!folivro_loja_produto_disponivel_publico($produto) && !$jaComprou && !$ehAutor)) {
    http_response_code(404);
    $produto = null;
}

$erro = '';
$sucesso = '';

if ($produto && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $erro = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $acao = (string) ($_POST['acao'] ?? '');
        if ($acao === 'obter_gratis' && (int) $produto['preco_centavos'] === 0) {
            $resPed = folivro_loja_criar_pedido($pdo, $uid, $id, 'gratuito');
            if ($resPed['ok'] && !empty($resPed['pedido'])) {
                header('Location: loja-pedido.php?codigo=' . urlencode((string) $resPed['pedido']['codigo_pedido']));
                exit;
            }
            $erro = (string) ($resPed['erro'] ?? 'Não foi possível concluir a aquisição gratuita.');
        } elseif ($acao === 'salvar_resenha') {
            $nota = (int) ($_POST['nota_resenha'] ?? 0);
            $tituloRes = (string) ($_POST['titulo_resenha'] ?? '');
            $textoRes = (string) ($_POST['resenha_resenha'] ?? '');
            $resRev = folivro_loja_salvar_resenha($pdo, $uid, $id, $nota, $tituloRes, $textoRes);
            if ($resRev['ok']) {
                $sucesso = 'Sua avaliação foi publicada com o selo de compra verificada!';
            } else {
                $erro = (string) ($resRev['erro'] ?? 'Não foi possível salvar sua avaliação.');
            }
        }
    }
}

$maiorDeIdade = folivro_usuario_maior_de_idade($pdo, $uid);
$bloqueadoIdade = $produto && !$ehAutor && !$jaComprou && !$maiorDeIdade && folivro_classificacao_eh_maior18((string) $produto['classificacao_produto']);
$resenhas = ($produto && !empty($produto['id_livro'])) ? folivro_loja_resenhas($pdo, (int) $produto['id_livro']) : [];
$minhaResenha = ['nota_resenha' => 5, 'titulo_resenha' => '', 'resenha_resenha' => ''];
foreach ($resenhas as $r) {
    if ((int) $r['id_usuario'] === $uid) {
        $minhaResenha = $r;
        break;
    }
}
$mediaNota = 0.0;
$distNotas = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$totalResenhasLoja = count($resenhas);
if ($resenhas) {
    $soma = 0;
    foreach ($resenhas as $r) {
        $n = max(1, min(5, (int) ($r['nota_resenha'] ?? 0)));
        $soma += $n;
        $distNotas[$n]++;
    }
    $mediaNota = round($soma / $totalResenhasLoja, 1);
}
$pontosCritica = $totalResenhasLoja > 0 ? (int) round(($mediaNota / 5.0) * 100) : null;
$classeCritica = 'score-neutro';
$rotuloCritica = 'Aguardando avaliações';
$descricaoCritica = 'Esta obra original do FOLIVRO ainda não possui avaliações suficientes da comunidade.';
if ($pontosCritica !== null) {
    if ($pontosCritica >= 80) {
        $classeCritica = 'score-excelente';
        $rotuloCritica = 'Aclamação da Comunidade FOLIVRO';
        $descricaoCritica = 'Obra altamente recomendada pelos leitores verificados da plataforma.';
    } elseif ($pontosCritica >= 60) {
        $classeCritica = 'score-favoravel';
        $rotuloCritica = 'Recepção Geralmente Favorável';
        $descricaoCritica = 'Avaliações positivas com boa aceitação entre os leitores.';
    } elseif ($pontosCritica >= 40) {
        $classeCritica = 'score-misto';
        $rotuloCritica = 'Avaliações Mistas ou Médias';
        $descricaoCritica = 'A obra divide opiniões entre os leitores da comunidade.';
    } else {
        $classeCritica = 'score-baixo';
        $rotuloCritica = 'Recepção Desfavorável';
        $descricaoCritica = 'A maioria das avaliações registradas está abaixo da média.';
    }
}
$csrf = folivro_escape(folivro_csrf_token());
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $produto && !$bloqueadoIdade ? folivro_escape($produto['titulo_produto']) . ' | Loja FOLIVRO' : 'Obra | Loja FOLIVRO' ?></title>
    <link rel="stylesheet" href="layout.css?v=20261123_03">
    <link rel="stylesheet" href="home.css?v=20261123_01">
    <link rel="stylesheet" href="loja.css?v=20261123_01">
</head>
<body>
    <?php $menuAtivo = 'loja'; require __DIR__ . '/includes/menu-lateral.php'; ?>
    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <p><a href="loja.php">&larr; Voltar para a loja</a></p>

        <?php if ($produto === null): ?>
            <p class="estado-vazio estado-erro">Esta obra não está disponível na loja.</p>
        <?php elseif ($bloqueadoIdade): ?>
            <div class="loja-card-destaque">
                <h1>Conteúdo restrito (+18)</h1>
                <p>Esta obra possui classificação indicativa para maiores de 18 anos. Atualize sua data de nascimento no <a href="usuario.php">Perfil</a> caso já tenha 18 anos completos.</p>
            </div>
        <?php else: ?>
            <?php if ($erro): ?>
                <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape($erro) ?></p>
            <?php endif; ?>
            <?php if ($sucesso): ?>
                <p class="estado-vazio estado-sucesso" role="status"><?= folivro_escape($sucesso) ?></p>
            <?php endif; ?>

            <section class="loja-obra-detalhe">
                <div class="loja-obra-capa-grande">
                    <?php if (!empty($produto['capa_produto'])): ?>
                        <img src="<?= folivro_escape($produto['capa_produto']) ?>" alt="Capa de <?= folivro_escape($produto['titulo_produto']) ?>">
                    <?php else: ?>
                        <div class="capa-fallback"><?= folivro_escape($produto['titulo_produto']) ?></div>
                    <?php endif; ?>
                </div>

                <div>
                    <div class="linha-selos-card" style="margin-bottom:8px;">
                        <?= folivro_loja_selo_modo_html() ?>
                        <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($produto['classificacao_produto']) ?>">
                            <?= folivro_escape(folivro_classificacao_sigla($produto['classificacao_produto'])) ?>
                        </span>
                    </div>

                    <h1 style="margin:0 0 6px;"><?= folivro_escape($produto['titulo_produto']) ?></h1>
                    <p style="margin:0 0 10px;">Por <strong><?= folivro_escape($produto['autor_nome']) ?></strong>
                        <?php if ($resenhas): ?>
                            · ★ <?= number_format($mediaNota, 1, ',', '.') ?> (<?= count($resenhas) ?> avaliação(ões))
                        <?php endif; ?>
                    </p>

                    <?php $generos = folivro_generos_lista((string) ($produto['generos_produto'] ?? '')); ?>
                    <?php if ($generos): ?>
                        <div class="tags-generos-card" style="margin-bottom:10px;">
                            <?php foreach ($generos as $g): ?>
                                <span class="tag-genero-card"><?= folivro_escape($g) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="loja-preco-destaque">
                        <?= (int) $produto['preco_centavos'] === 0 || !folivro_pagamentos_ativos() ? 'Gratuito' : folivro_escape(folivro_loja_formatar((int) $produto['preco_centavos'])) ?>
                    </div>

                    <p><?= nl2br(folivro_escape((string) ($produto['descricao_produto'] ?? 'Sem descrição informada.'))) ?></p>

                    <div class="loja-acoes-card" style="margin-top:16px;">
                        <?php
                        $idAdlLink = (int) ($produto['id_autor_dos_livros'] ?? 0);
                        if ($idAdlLink <= 0) {
                            $idAdlLink = (int) (folivro_loja_garantir_obra_autoral($pdo, $id) ?: 0);
                        }
                        $idLivroLink = (int) ($produto['id_livro'] ?? 0);
                        $ehRemovidaMod = ($produto['estudio_status'] ?? '') === 'removido';
                        ?>
                        <?php if ($ehAutor): ?>
                            <a class="botao-adicionar-livro" href="loja-autor-obras.php?editar=<?= $id ?>">Gerenciar minha obra</a>
                            <?php if ($idAdlLink > 0): ?>
                                <a class="filtro-pilula" href="obra.php?id=<?= $idAdlLink ?>">Ler obra</a>
                            <?php endif; ?>
                        <?php elseif ($jaComprou): ?>
                            <?php if ($ehRemovidaMod): ?>
                                <p class="estado-vazio estado-erro">Esta obra foi removida pela moderação e não está mais disponível para leitura.</p>
                            <?php else: ?>
                                <?php if ($idAdlLink > 0): ?>
                                    <a class="botao-adicionar-livro" href="obra.php?id=<?= $idAdlLink ?>">Ler obra</a>
                                <?php elseif ($idLivroLink > 0): ?>
                                    <a class="botao-adicionar-livro" href="leitor.php?id=<?= $idLivroLink ?>">Ler obra</a>
                                <?php endif; ?>
                                <a class="filtro-pilula" href="minha-biblioteca.php">Abrir na minha biblioteca</a>
                            <?php endif; ?>
                            <?php if (folivro_pagamentos_ativos()): ?>
                                <a class="filtro-pilula" href="minhas-compras.php">Ver recibo</a>
                            <?php endif; ?>
                        <?php elseif ((int) $produto['preco_centavos'] === 0): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="acao" value="obter_gratis">
                                <button type="submit" class="botao-adicionar-livro">Obter gratuitamente e adicionar à biblioteca</button>
                            </form>
                        <?php elseif (folivro_pagamentos_ativos()): ?>
                            <a class="botao-adicionar-livro" href="loja-checkout.php?id=<?= $id ?>">Comprar agora</a>
                        <?php endif; ?>
                        <?php if ($idLivroLink > 0): ?>
                            <a class="filtro-pilula" href="livro.php?id=<?= $idLivroLink ?>">Ver ficha crítica completa</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <?php if (!empty($produto['preview_produto'])): ?>
                <section class="loja-card-destaque" aria-labelledby="titulo-preview">
                    <h2 id="titulo-preview">Prévia da obra</h2>
                    <div class="loja-preview-caixa"><?= folivro_escape($produto['preview_produto']) ?></div>
                </section>
            <?php endif; ?>

            <section class="loja-card-destaque" aria-labelledby="titulo-ficha-critica">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
                    <h2 id="titulo-ficha-critica" style="margin:0;">Ficha Crítica &amp; Recepção da Obra</h2>
                    <?php if ($idLivroLink > 0): ?>
                        <a class="filtro-pilula" href="livro.php?id=<?= $idLivroLink ?>">Abrir ficha na comunidade &rarr;</a>
                    <?php endif; ?>
                </div>

                <div class="painel-recepcao-critica" style="margin-bottom:16px;">
                    <div class="cabecalho-critica">
                        <div class="metascore-box <?= folivro_escape($classeCritica) ?>" title="Índice Crítico FOLIVRO">
                            <span class="metascore-valor"><?= $pontosCritica !== null ? $pontosCritica : '—' ?></span>
                            <span class="metascore-label">ÍNDICE FOLIVRO</span>
                        </div>
                        <div class="resumo-consenso">
                            <span class="tag-critica-selo">Índice de Recepção Crítica &amp; Consenso dos Leitores</span>
                            <h3><?= folivro_escape($rotuloCritica) ?></h3>
                            <p><?= folivro_escape($descricaoCritica) ?></p>
                            <?php if ($totalResenhasLoja > 0): ?>
                                <p class="contagem-base-critica">
                                    Baseado em <strong><?= $totalResenhasLoja ?></strong> avaliação(ões) · Média: <strong><?= number_format($mediaNota, 1, ',', '.') ?> / 5,0 ★</strong>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($totalResenhasLoja > 0): ?>
                        <div class="distribuicao-notas-bloco">
                            <h4 class="subtitulo-distribuicao">Distribuição de Notas dos Leitores</h4>
                            <div class="barras-distribuicao">
                                <?php for ($estrela = 5; $estrela >= 1; $estrela--): ?>
                                    <?php
                                    $qtdEst = $distNotas[$estrela];
                                    $pctEst = (int) round(($qtdEst / max(1, $totalResenhasLoja)) * 100);
                                    ?>
                                    <div class="linha-distribuicao">
                                        <span class="rotulo-estrela"><?= $estrela ?> ★</span>
                                        <div class="trilho-barra-distribuicao">
                                            <div class="preenchimento-barra-distribuicao" style="width: <?= $pctEst ?>%;"></div>
                                        </div>
                                        <span class="valor-pct-distribuicao"><?= $pctEst ?>% (<?= $qtdEst ?>)</span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <dl class="lista-detalhes-meta" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin:0;">
                    <div>
                        <dt>Autoria</dt>
                        <dd><?= folivro_escape($produto['autor_nome']) ?></dd>
                    </div>
                    <div>
                        <dt>Origem</dt>
                        <dd>Obra Original FOLIVRO</dd>
                    </div>
                    <div>
                        <dt>Classificação indicativa</dt>
                        <dd><?= folivro_escape(folivro_aviso_conteudo_label((string) $produto['classificacao_produto'])) ?></dd>
                    </div>
                    <div>
                        <dt>Formato</dt>
                        <dd>Leitura online no FOLIVRO</dd>
                    </div>
                    <?php if (!empty($produto['data_publicacao'])): ?>
                        <div>
                            <dt>Publicado em</dt>
                            <dd><?= date('d/m/Y', strtotime((string) $produto['data_publicacao'])) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </section>

            <section class="loja-card-destaque" aria-labelledby="titulo-avaliacoes">
                <h2 id="titulo-avaliacoes">Avaliações dos leitores (<?= count($resenhas) ?>)</h2>

                <?php if ($jaComprou): ?>
                    <form method="post" class="progresso-form form-largura-total" style="margin-bottom:20px;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="acao" value="salvar_resenha">
                        <div class="grade-campos-dupla">
                            <label for="nota_resenha">Sua nota
                                <select id="nota_resenha" name="nota_resenha" required>
                                    <?php for ($n = 5; $n >= 1; $n--): ?>
                                        <option value="<?= $n ?>" <?= (int) ($minhaResenha['nota_resenha'] ?? 5) === $n ? 'selected' : '' ?>>
                                            <?= $n ?> estrela(s)
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </label>
                            <label for="titulo_resenha">Título da avaliação (opcional)
                                <input id="titulo_resenha" name="titulo_resenha" maxlength="150" value="<?= folivro_escape((string) ($minhaResenha['titulo_resenha'] ?? '')) ?>">
                            </label>
                        </div>
                        <label for="resenha_resenha">Sua resenha (será publicada com o selo Compra verificada)
                            <textarea id="resenha_resenha" name="resenha_resenha" rows="3" maxlength="5000" required><?= folivro_escape((string) ($minhaResenha['resenha_resenha'] ?? '')) ?></textarea>
                        </label>
                        <button type="submit" class="botao-adicionar-livro">Publicar avaliação</button>
                    </form>
                <?php elseif (!$ehAutor): ?>
                    <p><small>Adquira esta obra para publicar uma avaliação com selo de compra verificada.</small></p>
                <?php endif; ?>

                <?php if (!$resenhas): ?>
                    <p class="estado-vazio">Nenhuma avaliação registrada para esta obra ainda.</p>
                <?php else: ?>
                    <?php foreach ($resenhas as $r): ?>
                        <article style="padding:12px 0; border-bottom:1px solid var(--borda-suave);">
                            <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                                <strong><?= folivro_escape($r['nome_usuario']) ?></strong>
                                <span aria-label="Nota <?= (int) $r['nota_resenha'] ?> de 5"><?= str_repeat('★', (int) ($r['nota_resenha'] ?? 0)) ?></span>
                                <?php if ((int) ($r['compra_verificada_resenha'] ?? 0) === 1): ?>
                                    <span class="selo-compra-verificada">Compra verificada</span>
                                <?php endif; ?>
                                <small><?= date('d/m/Y', strtotime((string) $r['data_avaliacao_resenha'])) ?></small>
                            </div>
                            <?php if (!empty($r['titulo_resenha'])): ?>
                                <h3 style="margin:6px 0 4px; font-size:1rem;"><?= folivro_escape($r['titulo_resenha']) ?></h3>
                            <?php endif; ?>
                            <p style="margin:0;"><?= nl2br(folivro_escape((string) $r['resenha_resenha'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
