<?php
declare(strict_types=1);
require_once __DIR__ . '/config/loja.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];
$ehAutor = folivro_loja_eh_autor($pdo, $uid);

$erro = '';
$sucesso = '';
$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
$obraEdicao = ($ehAutor && $editarId > 0) ? folivro_loja_produto_do_autor($pdo, $editarId, $uid) : null;
if ($editarId > 0 && $ehAutor && $obraEdicao === null) {
    $erro = 'Obra não encontrada ou você não tem permissão para editá-la.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $erro = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $acao = (string) ($_POST['acao'] ?? '');

        if ($acao === 'ativar_autor') {
            if (($_POST['aceite_termo_autor'] ?? '') !== '1') {
                $erro = 'Você precisa aceitar a declaração de titularidade e os termos de comercialização (Lei 9.610/98) para se tornar autor.';
            } else {
                folivro_loja_tornar_autor($pdo, $uid);
                $ehAutor = true;
                $sucesso = 'Perfil de autor ativado! Agora você pode cadastrar e vender suas obras digitais na loja.';
            }
        } elseif (!$ehAutor) {
            $erro = 'Ative seu perfil de autor antes de gerenciar obras na loja.';
        } elseif ($acao === 'salvar_produto') {
            $idProdPost = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT) ?: null;
            $dadosProdutoPost = $_POST;
            if (!folivro_pagamentos_ativos()) {
                $dadosProdutoPost['preco'] = '0,00';
            }
            $res = folivro_loja_salvar_produto($pdo, $uid, $idProdPost, $dadosProdutoPost, $_FILES, true);
            if ($res['ok']) {
                $idSalvo = (int) $res['id'];
                $sucesso = $idProdPost ? 'Obra atualizada com sucesso.' : 'Obra cadastrada como rascunho. Revise os dados e clique em "Publicar" quando estiver pronta.';
                $obraEdicao = folivro_loja_produto_do_autor($pdo, $idSalvo, $uid);
                $editarId = $idSalvo;
            } else {
                $erro = (string) ($res['erro'] ?? 'Não foi possível salvar a obra.');
                if ($idProdPost) {
                    $obraEdicao = folivro_loja_produto_do_autor($pdo, $idProdPost, $uid);
                    $editarId = $idProdPost;
                }
            }
        } elseif ($acao === 'alterar_status') {
            $idProdPost = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT) ?: 0;
            $novoStatus = (string) ($_POST['novo_status'] ?? '');
            $res = folivro_loja_alterar_status_produto($pdo, $uid, $idProdPost, $novoStatus);
            if ($res['ok']) {
                $sucesso = 'Status da obra alterado para: ' . folivro_loja_status_produto_label($novoStatus) . '.';
            } else {
                $erro = (string) ($res['erro'] ?? 'Não foi possível alterar o status.');
            }
        }
    }
}

$minhasObras = [];
$obrasEstudio = [];
if ($ehAutor) {
    $stObras = $pdo->prepare(
        "SELECT p.*,
                (SELECT COUNT(*) FROM loja_pedidos o WHERE o.id_produto = p.id_produto AND o.status_pedido = 'pago') AS total_vendas,
                (SELECT COALESCE(SUM(o.valor_liquido_centavos), 0) FROM loja_pedidos o WHERE o.id_produto = p.id_produto AND o.status_pedido = 'pago') AS receita_liquida
           FROM loja_produtos p
          WHERE p.id_autor = ?
       ORDER BY p.data_criacao DESC, p.id_produto DESC"
    );
    $stObras->execute([$uid]);
    $minhasObras = $stObras->fetchAll(PDO::FETCH_ASSOC);

    $stEst = $pdo->prepare(
        "SELECT id_autor_dos_livros, titulo_autor_dos_livros FROM autor_dos_livros
          WHERE id_autor = ? AND status_autor_dos_livros <> 'removido'
       ORDER BY id_autor_dos_livros DESC"
    );
    $stEst->execute([$uid]);
    $obrasEstudio = $stEst->fetchAll(PDO::FETCH_ASSOC);
}

$csrf = folivro_escape(folivro_csrf_token());
$generosPadrao = folivro_generos_disponiveis();
$generosForm = $obraEdicao ? folivro_generos_lista((string) ($obraEdicao['generos_produto'] ?? '')) : [];
$generosExtrasForm = array_values(array_filter($generosForm, static fn(string $g): bool => !in_array($g, $generosPadrao, true)));
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minhas Obras na Loja | FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261117_1">
    <link rel="stylesheet" href="home.css?v=20261117_1">
    <link rel="stylesheet" href="loja.css?v=20261117_1">
</head>
<body>
    <?php $menuAtivo = 'loja-autor'; require __DIR__ . '/includes/menu-lateral.php'; ?>
    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <div class="loja-topo-cabecalho">
            <div>
                <h1>Minhas obras à venda na Loja</h1>
                <p>Cadastre eBooks em PDF ou EPUB, defina o preço (ou ofereça gratuitamente) e gerencie suas publicações.</p>
            </div>
            <div class="loja-atalhos-topo">
                <a class="filtro-pilula" href="loja.php">Vitrine da loja</a>
                <?php if ($ehAutor): ?>
                    <a class="filtro-pilula" href="painel-vendas.php">Painel de vendas</a>
                <?php endif; ?>
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
                <h2>Torne-se um autor vendedor no FOLIVRO</h2>
                <p>Sua conta está atualmente como <strong>leitor</strong>. Para publicar e vender obras digitais (PDF ou EPUB) diretamente aos leitores, ative seu perfil de autor aceitando a declaração de titularidade abaixo.</p>
                <form method="post" class="progresso-form form-largura-total">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="acao" value="ativar_autor">
                    <label class="loja-checkbox-termo">
                        <input type="checkbox" name="aceite_termo_autor" value="1" required>
                        <span>Declaro, sob as penas da Lei nº 9.610/98 (Lei de Direitos Autorais), que apenas publicarei e comercializarei obras das quais sou titular dos direitos autorais patrimoniais ou possuo autorização expressa do titular.</span>
                    </label>
                    <button type="submit" class="botao-adicionar-livro">Ativar perfil de autor</button>
                </form>
            </section>
        <?php else: ?>
            <section class="loja-card-destaque" aria-labelledby="titulo-form-obra">
                <div class="loja-cabecalho-secao">
                    <h2 id="titulo-form-obra"><?= $obraEdicao ? 'Editar obra: ' . folivro_escape($obraEdicao['titulo_produto']) : 'Cadastrar nova obra na loja' ?></h2>
                    <?php if ($obraEdicao): ?>
                        <a class="filtro-pilula" href="loja-autor-obras.php">Cancelar edição / Nova obra</a>
                    <?php endif; ?>
                </div>

                <form method="post" enctype="multipart/form-data" class="progresso-form form-largura-total">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="acao" value="salvar_produto">
                    <?php if ($obraEdicao): ?>
                        <input type="hidden" name="id_produto" value="<?= (int) $obraEdicao['id_produto'] ?>">
                    <?php endif; ?>

                    <div class="grade-campos-dupla">
                        <label for="titulo_produto">Título da obra *
                            <input id="titulo_produto" name="titulo_produto" maxlength="120" required
                                   value="<?= folivro_escape((string) ($obraEdicao['titulo_produto'] ?? ($_POST['titulo_produto'] ?? ''))) ?>">
                        </label>
                        <?php if (folivro_pagamentos_ativos()): ?>
                        <label for="preco">Preço (R$) — informe 0,00 para obra gratuita *
                            <input id="preco" name="preco" maxlength="12" required placeholder="Ex.: 19,90 ou 0,00"
                                   value="<?= folivro_escape($obraEdicao ? folivro_centavos_para_input((int) $obraEdicao['preco_centavos']) : (string) ($_POST['preco'] ?? '0,00')) ?>">
                        </label>
                        <?php else: ?>
                            <input type="hidden" name="preco" value="0,00">
                        <?php endif; ?>
                    </div>

                    <label for="descricao_produto">Sinopse / descrição comercial
                        <textarea id="descricao_produto" name="descricao_produto" rows="3" maxlength="5000" placeholder="Apresente a obra para os leitores..."><?= folivro_escape((string) ($obraEdicao['descricao_produto'] ?? ($_POST['descricao_produto'] ?? ''))) ?></textarea>
                    </label>

                    <label for="preview_produto">Trecho de prévia gratuita (opcional)
                        <textarea id="preview_produto" name="preview_produto" rows="3" maxlength="3000" placeholder="Um parágrafo ou início do primeiro capítulo para degustação na página da obra..."><?= folivro_escape((string) ($obraEdicao['preview_produto'] ?? ($_POST['preview_produto'] ?? ''))) ?></textarea>
                    </label>

                    <div class="bloco-seletor-generos" role="group" aria-labelledby="rotulo-generos-loja">
                        <div class="topo-seletor-generos">
                            <span id="rotulo-generos-loja" class="rotulo-campo-estudio">Gêneros literários</span>
                            <span class="contador-generos-badge">Até 5 gêneros</span>
                        </div>
                        <div class="seletor-generos-grade">
                            <?php foreach ($generosPadrao as $genOpt): ?>
                                <?php $genAtivo = in_array($genOpt, $generosForm, true); ?>
                                <label class="chip-genero-escolha <?= $genAtivo ? 'ativo' : '' ?>">
                                    <input type="checkbox" name="generos_produto[]" value="<?= folivro_escape($genOpt) ?>" <?= $genAtivo ? 'checked' : '' ?>>
                                    <span><?= folivro_escape($genOpt) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" name="generos_personalizados" class="input-genero-personalizado" maxlength="120"
                               value="<?= folivro_escape(implode(', ', $generosExtrasForm)) ?>"
                               placeholder="Outro gênero personalizado (separe por vírgula)...">
                    </div>

                    <div class="grade-campos-dupla">
                        <label for="classificacao_produto">Classificação indicativa (idade)
                            <select id="classificacao_produto" name="classificacao_produto">
                                <?php $classAtual = $obraEdicao['classificacao_produto'] ?? ($_POST['classificacao_produto'] ?? 'livre'); ?>
                                <?php foreach (folivro_classificacoes_idade() as $kCl => $vCl): ?>
                                    <option value="<?= folivro_escape($kCl) ?>" <?= $classAtual === $kCl ? 'selected' : '' ?>><?= folivro_escape($vCl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label for="id_autor_dos_livros">Vincular a uma história do seu estúdio (opcional)
                            <select id="id_autor_dos_livros" name="id_autor_dos_livros">
                                <option value="">— Nenhum vínculo —</option>
                                <?php $adlAtual = (int) ($obraEdicao['id_autor_dos_livros'] ?? 0); ?>
                                <?php foreach ($obrasEstudio as $oe): ?>
                                    <option value="<?= (int) $oe['id_autor_dos_livros'] ?>" <?= $adlAtual === (int) $oe['id_autor_dos_livros'] ? 'selected' : '' ?>>
                                        <?= folivro_escape($oe['titulo_autor_dos_livros']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div class="grade-campos-dupla">
                        <label for="arquivo_obra">Arquivo da obra (PDF ou EPUB, máx. 20 MB) <?= $obraEdicao && !empty($obraEdicao['arquivo_produto']) ? '' : '*' ?>
                            <input id="arquivo_obra" type="file" name="arquivo_obra" accept=".pdf,.epub,application/pdf,application/epub+zip">
                            <small>
                                <?php if ($obraEdicao && !empty($obraEdicao['arquivo_produto'])): ?>
                                    Arquivo atual: <strong><?= folivro_escape((string) $obraEdicao['arquivo_nome_original']) ?></strong> (<?= strtoupper((string) $obraEdicao['arquivo_tipo']) ?>). Envie outro apenas se desejar substituir.
                                <?php else: ?>
                                    O arquivo é armazenado em diretório protegido e nunca fica acessível por URL pública.
                                <?php endif; ?>
                            </small>
                        </label>

                        <label for="capa_produto">Capa da obra (JPG, PNG ou WebP, máx. 2 MB)
                            <input id="capa_produto" type="file" name="capa_produto" accept="image/jpeg,image/png,image/webp">
                            <?php if ($obraEdicao && !empty($obraEdicao['capa_produto'])): ?>
                                <small>Capa atual cadastrada. Envie uma nova imagem apenas para substituí-la.</small>
                            <?php endif; ?>
                        </label>
                    </div>

                    <div class="loja-alerta-conformidade">
                        <label class="loja-checkbox-termo">
                            <input type="checkbox" name="aceite_termo_direitos" value="1" <?= ((int) ($obraEdicao['aceite_termo_direitos'] ?? 0) === 1) ? 'checked' : '' ?> required>
                            <span>
                                <strong>Declaração obrigatória de direitos autorais (Lei nº 9.610/98):</strong>
                                Declaro ser o(a) legítimo(a) autor(a) ou titular dos direitos patrimoniais desta obra e que sua comercialização no FOLIVRO não viola direitos de terceiros.
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="botao-adicionar-livro"><?= $obraEdicao ? 'Salvar alterações' : 'Cadastrar obra (rascunho)' ?></button>
                </form>
            </section>

            <section style="margin-top: 28px;" aria-labelledby="titulo-lista-minhas-obras">
                <h2 id="titulo-lista-minhas-obras">Suas obras cadastradas (<?= count($minhasObras) ?>)</h2>
                <?php if (!$minhasObras): ?>
                    <p class="estado-vazio">Você ainda não cadastrou nenhuma obra na loja.</p>
                <?php else: ?>
                    <div class="biblioteca-lista">
                        <?php foreach ($minhasObras as $w): ?>
                            <article class="livro-card">
                                <div class="livro-capa">
                                    <?php if (!empty($w['capa_produto'])): ?>
                                        <img src="<?= folivro_escape($w['capa_produto']) ?>" alt="Capa de <?= folivro_escape($w['titulo_produto']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="capa-fallback"><?= folivro_escape($w['titulo_produto']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="livro-info">
                                    <div class="linha-selos-card">
                                        <span class="selo-status <?= $w['status_produto'] === 'publicado' ? 'selo-lido' : ($w['status_produto'] === 'arquivado' ? 'selo-suspenso' : '') ?>">
                                            <?= folivro_escape(folivro_loja_status_produto_label((string) $w['status_produto'])) ?>
                                        </span>
                                        <span class="selo-status">
                                            <?= (int) $w['preco_centavos'] === 0 ? 'Gratuita' : folivro_loja_formatar((int) $w['preco_centavos']) ?>
                                        </span>
                                        <?php if (!empty($w['arquivo_tipo'])): ?>
                                            <span class="tag-genero-card"><?= strtoupper(folivro_escape((string) $w['arquivo_tipo'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <h3><?= folivro_escape($w['titulo_produto']) ?></h3>
                                    <p><?= folivro_escape(mb_strimwidth((string) ($w['descricao_produto'] ?? ''), 0, 140, '...', 'UTF-8')) ?></p>
                                    <p><strong><?= (int) $w['total_vendas'] ?></strong> venda(s) · Líquido: <strong><?= folivro_loja_formatar((int) $w['receita_liquida']) ?></strong></p>

                                    <div class="loja-acoes-card">
                                        <a class="filtro-pilula" href="loja-autor-obras.php?editar=<?= (int) $w['id_produto'] ?>">Editar</a>
                                        <?php if ($w['status_produto'] === 'publicado'): ?>
                                            <a class="filtro-pilula" href="loja-obra.php?id=<?= (int) $w['id_produto'] ?>">Ver na loja</a>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                <input type="hidden" name="acao" value="alterar_status">
                                                <input type="hidden" name="id_produto" value="<?= (int) $w['id_produto'] ?>">
                                                <input type="hidden" name="novo_status" value="rascunho">
                                                <button type="submit" class="filtro-pilula">Despublicar</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                <input type="hidden" name="acao" value="alterar_status">
                                                <input type="hidden" name="id_produto" value="<?= (int) $w['id_produto'] ?>">
                                                <input type="hidden" name="novo_status" value="publicado">
                                                <button type="submit" class="botao-adicionar-livro">Publicar na loja</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($w['status_produto'] !== 'arquivado'): ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                <input type="hidden" name="acao" value="alterar_status">
                                                <input type="hidden" name="id_produto" value="<?= (int) $w['id_produto'] ?>">
                                                <input type="hidden" name="novo_status" value="arquivado">
                                                <button type="submit" class="filtro-pilula">Arquivar</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (!empty($w['arquivo_produto'])): ?>
                                            <a class="filtro-pilula" href="loja-download.php?id=<?= (int) $w['id_produto'] ?>">Baixar arquivo</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <script>
        document.querySelectorAll('.chip-genero-escolha input[type="checkbox"]').forEach((cb) => {
            cb.addEventListener('change', () => {
                cb.closest('.chip-genero-escolha').classList.toggle('ativo', cb.checked);
            });
        });
    </script>
</body>
</html>
