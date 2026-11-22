<?php
declare(strict_types=1);
require_once __DIR__ . '/config/loja.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];

$filtros = [
    'q'       => trim((string) ($_GET['q'] ?? '')),
    'genero'  => trim((string) ($_GET['genero'] ?? '')),
    'preco'   => trim((string) ($_GET['preco'] ?? '')),
    'autor'   => (int) ($_GET['autor'] ?? 0),
    'ordenar' => trim((string) ($_GET['ordenar'] ?? 'recentes')),
];
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

$resultado = folivro_loja_listar_vitrine($pdo, $filtros, $pagina, 12);
$itens = $resultado['itens'];
$autores = folivro_loja_autores_vitrine($pdo);

$ownedStmt = $pdo->prepare("SELECT DISTINCT id_produto FROM loja_pedidos WHERE id_comprador = ? AND status_pedido = 'pago'");
$ownedStmt->execute([$uid]);
$owned = array_map('intval', $ownedStmt->fetchAll(PDO::FETCH_COLUMN));

$idadeUsuario = folivro_usuario_idade($pdo, $uid);
$maiorDeIdade = folivro_usuario_maior_de_idade($pdo, $uid);
$ehAutor = folivro_loja_eh_autor($pdo, $uid);
$infoModo = folivro_loja_info_modo();
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Loja de Autores | FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261117_1">
    <link rel="stylesheet" href="home.css?v=20261117_1">
    <link rel="stylesheet" href="loja.css?v=20261117_1">
    <link rel="stylesheet" href="estante.css?v=20261117_4">
</head>

<body>

    <?php $menuAtivo = 'loja'; require __DIR__ . '/includes/menu-lateral.php'; ?>
    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <div class="loja-topo-cabecalho">
            <div>
                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-bottom:6px;">
                    <h1 style="margin:0;">Loja de Obras Independentes</h1>
                    <?= folivro_loja_selo_modo_html() ?>
                </div>
                <p><?= folivro_pagamentos_ativos()
                    ? 'Obras digitais vendidas diretamente por autores da comunidade. ' . folivro_escape($infoModo['descricao'])
                    : 'Obras digitais publicadas gratuitamente por autores da comunidade.' ?></p>
            </div>
            <div class="loja-atalhos-topo">
                <?php if (folivro_pagamentos_ativos()): ?>
                    <a class="filtro-pilula" href="minhas-compras.php">Minhas compras</a>
                <?php endif; ?>
                <a class="filtro-pilula" href="minha-biblioteca.php">Minha biblioteca</a>
                <a class="botao-adicionar-livro" href="loja-autor-obras.php"><?= folivro_pagamentos_ativos() ? ($ehAutor ? 'Vender / Minhas obras' : 'Quero vender obras') : ($ehAutor ? 'Minhas obras' : 'Publicar obras') ?></a>
            </div>
        </div>

        <form method="get" class="loja-filtros-barra" role="search" aria-label="Filtrar obras da loja">
            <input type="search" name="q" value="<?= folivro_escape($filtros['q']) ?>" placeholder="Buscar por título, autor ou gênero..." aria-label="Buscar obras">

            <select name="genero" aria-label="Filtrar por gênero">
                <option value="">Todos os gêneros</option>
                <?php foreach (folivro_generos_disponiveis() as $gOpt): ?>
                    <option value="<?= folivro_escape($gOpt) ?>" <?= $filtros['genero'] === $gOpt ? 'selected' : '' ?>>
                        <?= folivro_escape($gOpt) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if (folivro_pagamentos_ativos()): ?>
            <select name="preco" aria-label="Filtrar por preço">
                <option value="">Qualquer preço</option>
                <option value="gratis" <?= $filtros['preco'] === 'gratis' ? 'selected' : '' ?>>Gratuitas (R$ 0,00)</option>
                <option value="ate20" <?= $filtros['preco'] === 'ate20' ? 'selected' : '' ?>>Até R$ 20,00</option>
                <option value="20a50" <?= $filtros['preco'] === '20a50' ? 'selected' : '' ?>>R$ 20,01 a R$ 50,00</option>
                <option value="acima50" <?= $filtros['preco'] === 'acima50' ? 'selected' : '' ?>>Acima de R$ 50,00</option>
            </select>
            <?php endif; ?>

            <select name="autor" aria-label="Filtrar por autor">
                <option value="0">Todos os autores</option>
                <?php foreach ($autores as $aut): ?>
                    <option value="<?= (int) $aut['id_usuario'] ?>" <?= $filtros['autor'] === (int) $aut['id_usuario'] ? 'selected' : '' ?>>
                        <?= folivro_escape($aut['nome_usuario']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="ordenar" aria-label="Ordenar obras">
                <option value="recentes" <?= $filtros['ordenar'] === 'recentes' ? 'selected' : '' ?>>Mais recentes</option>
                <?php if (folivro_pagamentos_ativos()): ?>
                <option value="vendas" <?= $filtros['ordenar'] === 'vendas' ? 'selected' : '' ?>>Mais vendidas</option>
                <?php endif; ?>
                <option value="avaliacao" <?= $filtros['ordenar'] === 'avaliacao' ? 'selected' : '' ?>>Melhor avaliadas</option>
                <?php if (folivro_pagamentos_ativos()): ?>
                <option value="preco_asc" <?= $filtros['ordenar'] === 'preco_asc' ? 'selected' : '' ?>>Menor preço</option>
                <option value="preco_desc" <?= $filtros['ordenar'] === 'preco_desc' ? 'selected' : '' ?>>Maior preço</option>
                <?php endif; ?>
                <option value="titulo" <?= $filtros['ordenar'] === 'titulo' ? 'selected' : '' ?>>Título (A–Z)</option>
            </select>

            <div style="display:flex; gap:6px;">
                <button type="submit" class="botao-adicionar-livro">Filtrar</button>
                <?php if ($filtros['q'] !== '' || $filtros['genero'] !== '' || $filtros['preco'] !== '' || $filtros['autor'] > 0): ?>
                    <a class="filtro-pilula" href="loja.php">Limpar</a>
                <?php endif; ?>
            </div>
        </form>

        <p class="banner-status-idade-catalogo <?= $maiorDeIdade ? 'idade-liberada' : 'idade-restrita' ?>" role="status">
            <?php if ($maiorDeIdade): ?>
                <strong>Classificação indicativa ativa:</strong> Sua conta possui 18 anos ou mais (<?= (int) $idadeUsuario ?> anos). Todas as classificações estão liberadas.
            <?php elseif ($idadeUsuario !== null): ?>
                <?= folivro_icone_olho_bloqueado_svg(18) ?>
                <span><strong>Proteção etária ativa (<?= (int) $idadeUsuario ?> anos):</strong> Obras +18 ficam com a capa e o título ocultos.</span>
            <?php else: ?>
                <?= folivro_icone_olho_bloqueado_svg(18) ?>
                <span><strong>Proteção etária ativa:</strong> Informe sua data de nascimento no <a href="usuario.php">Perfil</a> para liberar obras +18.</span>
            <?php endif; ?>
        </p>

        <p><small>Exibindo <strong><?= count($itens) ?></strong> de <strong><?= (int) $resultado['total'] ?></strong> obra(s) publicada(s).</small></p>

        <div class="biblioteca-lista estante-biblioteca" id="lista-loja">
            <?php if (!$itens): ?>
                <p class="estado-vazio">Nenhuma obra encontrada com os filtros selecionados.</p>
            <?php else: ?>
                <?php foreach ($itens as $w): ?>
                    <?php
                    $idProd = (int) $w['id_produto'];
                    $isAutor = ((int) $w['id_autor'] === $uid);
                    $jaComprou = in_array($idProd, $owned, true);
                    $precoCentavos = (int) $w['preco_centavos'];
                    $ehGratuita = ($precoCentavos === 0);
                    $classifObra = folivro_normalizar_classificacao((string) ($w['classificacao_produto'] ?? 'livre'));
                    $bloqueadoIdade = (!$maiorDeIdade && folivro_classificacao_eh_maior18($classifObra));
                    $listaGenerosCard = folivro_generos_lista((string) ($w['generos_produto'] ?? ''));
                    $mediaNota = round((float) ($w['media_nota'] ?? 0), 1);
                    $totalResenhas = (int) ($w['total_resenhas'] ?? 0);
                    ?>
                    <article class="livro-card <?= $bloqueadoIdade ? 'livro-card-bloqueado-idade' : '' ?>">
                        <div class="livro-capa <?= $bloqueadoIdade ? 'capa-bloqueada-18' : '' ?>">
                            <?php if (!empty($w['capa_produto'])): ?>
                                <img src="<?= folivro_escape($w['capa_produto']) ?>" alt="<?= $bloqueadoIdade ? 'Capa bloqueada +18' : 'Capa de ' . folivro_escape($w['titulo_produto']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="capa-fallback"><?= $bloqueadoIdade ? 'Conteúdo Restrito +18' : folivro_escape($w['titulo_produto']) ?></div>
                            <?php endif; ?>
                            <span class="selo-classificacao-idade selo-classificacao-capa selo-idade-<?= folivro_escape($classifObra) ?>" title="<?= folivro_escape(folivro_aviso_conteudo_label($classifObra)) ?>">
                                <?= folivro_escape(folivro_classificacao_sigla($classifObra)) ?>
                            </span>
                            <?php if ($bloqueadoIdade): ?>
                                <div class="capa-bloqueio-idade-overlay" role="img" aria-label="Capa bloqueada: obra exclusiva para maiores de 18 anos">
                                    <div class="icone-olho-bloqueado-circulo"><?= folivro_icone_olho_bloqueado_svg(34) ?></div>
                                    <strong class="titulo-bloqueio-capa">Conteúdo +18</strong>
                                    <span class="motivo-bloqueio-capa">Exclusivo para maiores de 18 anos</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="livro-info">
                            <div class="linha-selos-card">
                                <?php if ($isAutor): ?>
                                    <span class="selo-status">Sua obra</span>
                                <?php elseif ($jaComprou): ?>
                                    <span class="selo-status selo-lido">Adquirida</span>
                                <?php elseif ($ehGratuita): ?>
                                    <span class="selo-status selo-lido">Gratuita</span>
                                <?php else: ?>
                                    <span class="selo-status selo-comprado"><?= folivro_escape(folivro_loja_formatar($precoCentavos)) ?></span>
                                <?php endif; ?>

                                <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($classifObra) ?>" title="<?= folivro_escape(folivro_aviso_conteudo_label($classifObra)) ?>">
                                    <?= folivro_escape(folivro_classificacao_sigla($classifObra)) ?>
                                </span>
                            </div>

                            <?php if ($bloqueadoIdade): ?>
                                <h2 class="titulo-oculto-idade"><?= folivro_icone_olho_bloqueado_svg(18) ?> <span>Título oculto (+18)</span></h2>
                                <p>Por <?= folivro_escape($w['autor_nome']) ?></p>
                                <p class="texto-motivo-bloqueio-card">Restrito para menores de 18 anos.</p>
                                <button class="botao-adicionar-livro botao-bloqueado-idade" type="button" disabled>Bloqueado (+18)</button>
                            <?php else: ?>
                                <h2><a href="loja-obra.php?id=<?= $idProd ?>" class="link-titulo-livro"><?= folivro_escape($w['titulo_produto']) ?></a></h2>
                                <p>Por <strong><?= folivro_escape($w['autor_nome']) ?></strong></p>

                                <?php if ($listaGenerosCard): ?>
                                    <div class="tags-generos-card">
                                        <?php foreach ($listaGenerosCard as $genTag): ?>
                                            <span class="tag-genero-card"><?= folivro_escape($genTag) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <p><?= folivro_escape(mb_strimwidth((string) ($w['descricao_produto'] ?? ''), 0, 140, '...', 'UTF-8')) ?></p>
                                <p class="livro-ano">
                                    <?= $totalResenhas > 0 ? '★ ' . number_format($mediaNota, 1, ',', '.') . ' (' . $totalResenhas . ')' : 'Sem avaliações ainda' ?>
                                    · <?= (int) $w['total_vendas'] ?> aquisição(ões)
                                </p>

                                <div class="loja-acoes-card">
                                    <a class="filtro-pilula" href="loja-obra.php?id=<?= $idProd ?>">Ver obra e prévia</a>
                                    <?php if ($isAutor): ?>
                                        <a class="botao-adicionar-livro" href="loja-autor-obras.php?editar=<?= $idProd ?>">Gerenciar</a>
                                    <?php elseif ($jaComprou): ?>
                                        <?php $idAdlCard = (int) ($w['id_autor_dos_livros'] ?? 0); ?>
                                        <a class="botao-adicionar-livro" href="<?= $idAdlCard > 0 ? 'obra.php?id=' . $idAdlCard : 'loja-obra.php?id=' . $idProd ?>">Ler obra</a>
                                    <?php else: ?>
                                        <a class="botao-adicionar-livro" href="loja-checkout.php?id=<?= $idProd ?>">
                                            <?= $ehGratuita ? 'Obter grátis' : 'Comprar (' . folivro_escape(folivro_loja_formatar($precoCentavos)) . ')' ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($resultado['paginas'] > 1): ?>
            <nav class="loja-paginacao" aria-label="Paginação da loja">
                <?php for ($p = 1; $p <= $resultado['paginas']; $p++): ?>
                    <?php $qs = http_build_query(array_merge($filtros, ['pagina' => $p])); ?>
                    <a class="filtro-pilula <?= $p === $resultado['pagina'] ? 'ativo' : '' ?>" href="loja.php?<?= folivro_escape($qs) ?>"><?= $p ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </main>
</body>
</html>
