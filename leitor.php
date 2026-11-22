<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

// Só abre livros que estão na biblioteca pessoal do usuário logado.
$stmt = $pdo->prepare(
    'SELECT b.*, l.status_livraria, p.pagina_atual_progressao, p.total_paginas_progressao, p.porcentagem_progressao
       FROM livraria l
       JOIN livro b ON b.id_livro = l.id_livro
  LEFT JOIN progressao_leitura p ON p.id_usuario = l.id_usuario AND p.id_livro = l.id_livro
      WHERE l.id_usuario = ? AND b.id_livro = ?'
);
$stmt->execute([$uid, $id]);
$book = $stmt->fetch();
if (!$book) {
    http_response_code(404);
    exit('Livro não encontrado na sua biblioteca.');
}

$stProdLeitor = $pdo->prepare(
    'SELECT p.id_produto, p.id_autor, p.id_autor_dos_livros, p.preco_centavos, p.arquivo_produto, p.arquivo_tipo, p.status_produto,
            a.status_autor_dos_livros AS estudio_status
       FROM loja_produtos p
  LEFT JOIN autor_dos_livros a ON a.id_autor_dos_livros = p.id_autor_dos_livros
      WHERE p.id_livro = ? LIMIT 1'
);
$stProdLeitor->execute([$id]);
$produtoLoja = $stProdLeitor->fetch(PDO::FETCH_ASSOC) ?: null;
$acessoLojaLeitor = false;
$bloqueadoModLeitor = false;
if ($produtoLoja) {
    require_once __DIR__ . '/config/loja.php';
    if (empty($produtoLoja['id_autor_dos_livros'])) {
        $produtoLoja['id_autor_dos_livros'] = folivro_loja_garantir_obra_autoral($pdo, (int) $produtoLoja['id_produto']);
    }
    $ehAutorProd = (int) $produtoLoja['id_autor'] === $uid;
    $bloqueadoModLeitor = (($produtoLoja['estudio_status'] ?? '') === 'removido')
        || (($produtoLoja['estudio_status'] ?? '') === 'suspenso' && !$ehAutorProd);
    if (!$bloqueadoModLeitor) {
        $acessoLojaLeitor = $ehAutorProd
            || folivro_loja_ja_possui($pdo, $uid, (int) $produtoLoja['id_produto'])
            || ((int) $produtoLoja['preco_centavos'] === 0 && $produtoLoja['status_produto'] === 'publicado');
    }
    if ($acessoLojaLeitor && !$bloqueadoModLeitor && !empty($produtoLoja['id_autor_dos_livros'])) {
        header('Location: obra.php?id=' . (int) $produtoLoja['id_autor_dos_livros']);
        exit;
    }
}

$classLivroLeitor = !empty($book['classificacao_livro']) && $book['classificacao_livro'] !== 'livre'
    ? folivro_normalizar_classificacao((string) $book['classificacao_livro'])
    : folivro_estimar_classificacao_catalogo((string) ($book['titulo_livro'] ?? ''), (string) ($book['id_externo_livro'] ?? ''));

if (folivro_classificacao_eh_maior18($classLivroLeitor) && !folivro_usuario_maior_de_idade($pdo, $uid)) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Leitura Bloqueada (+18) | FOLIVRO</title>
        <link rel="stylesheet" href="layout.css?v=20261104_19">
        <link rel="stylesheet" href="home.css?v=20261104_19">
    </head>
    <body>
        <?php $menuAtivo = 'biblioteca'; require __DIR__ . '/includes/menu-lateral.php'; ?>
        <?php require __DIR__ . '/includes/menu-autor.php'; ?>
        <main class="conteudo" id="conteudo-principal">
            <article class="livro-card livro-card-bloqueado-idade" style="max-width: 560px; margin: 28px auto;">
                <div class="livro-capa capa-bloqueada-18" style="height: 240px;">
                    <?php if (!empty($book['capa_livro'])): ?>
                        <img src="<?= folivro_escape($book['capa_livro']) ?>" alt="Capa bloqueada por classificação indicativa +18">
                    <?php else: ?>
                        <div class="capa-fallback">Obra +18</div>
                    <?php endif; ?>
                    <div class="capa-bloqueio-idade-overlay" role="img" aria-label="Conteúdo bloqueado: exclusivo para maiores de 18 anos">
                        <span class="icone-olho-bloqueado-circulo">
                            <?= folivro_icone_olho_bloqueado_svg(36) ?>
                        </span>
                        <strong class="titulo-bloqueio-capa">Classificação +18</strong>
                        <span class="motivo-bloqueio-capa">Bloqueado: exclusivo para maiores de 18 anos</span>
                    </div>
                    <span class="selo-classificacao-idade selo-classificacao-capa selo-idade-18">+18</span>
                </div>
                <div class="livro-info">
                    <span class="selo-classificacao-idade selo-idade-18">+18 · Proibido para menores de 18 anos</span>
                    <h2><span class="titulo-oculto-idade">Título oculto (+18)</span></h2>
                    <p class="texto-motivo-bloqueio-card">
                        <strong>Por que esta leitura está bloqueada?</strong><br>
                        Este livro possui classificação indicativa <strong>+18</strong> e sua conta possui menos de 18 anos (de acordo com a data de nascimento informada no cadastro).
                    </p>
                    <p><a class="botao-adicionar-livro" href="minha-biblioteca.php">Voltar para Minha Biblioteca</a></p>
                </div>
            </article>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$erroResenha = '';
$sucessoResenha = '';

// Registro ou edição de avaliação e resenha do livro (tabela resenha - RF08).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'resenha') {
    if (!folivro_verify_csrf()) {
        $erroResenha = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $nota = filter_input(INPUT_POST, 'nota_resenha', FILTER_VALIDATE_INT);
        $tituloResenha = trim((string) ($_POST['titulo_resenha'] ?? ''));
        $textoResenha = trim((string) ($_POST['resenha_resenha'] ?? ''));

        if (!is_int($nota) || $nota < 1 || $nota > 5) {
            $erroResenha = 'Selecione uma nota de 1 a 5 estrelas.';
        } elseif ($textoResenha === '' || mb_strlen($textoResenha, 'UTF-8') > 5000 || mb_strlen($tituloResenha, 'UTF-8') > 150) {
            $erroResenha = 'Escreva sua resenha (até 5.000 caracteres) e um título de até 150 caracteres.';
        } else {
            $upResenha = $pdo->prepare(
                'INSERT INTO resenha (id_usuario, id_livro, nota_resenha, titulo_resenha, resenha_resenha)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    nota_resenha = VALUES(nota_resenha),
                    titulo_resenha = VALUES(titulo_resenha),
                    resenha_resenha = VALUES(resenha_resenha),
                    data_avaliacao_resenha = CURRENT_TIMESTAMP'
            );
            $upResenha->execute([$uid, $id, $nota, $tituloResenha !== '' ? $tituloResenha : null, $textoResenha]);
            require_once __DIR__ . '/config/loja.php';
            folivro_loja_sincronizar_resenha_verificada($pdo, $uid, $id);
            $sucessoResenha = 'Sua avaliação e resenha foram salvas.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir_resenha') {
    if (!folivro_verify_csrf()) {
        $erroResenha = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $pdo->prepare('DELETE FROM resenha WHERE id_usuario = ? AND id_livro = ?')->execute([$uid, $id]);
        $minhaResenha = ['nota_resenha' => 5, 'titulo_resenha' => '', 'resenha_resenha' => ''];
        $sucessoResenha = 'Sua avaliação e resenha foram excluídas.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'alterar_status') {
    if (!folivro_verify_csrf()) {
        $erroResenha = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $novoStatus = (string) ($_POST['status_livraria'] ?? 'lendo');
        if (in_array($novoStatus, ['quero_ler', 'lendo', 'lido', 'abandonado'], true)) {
            $pdo->prepare('UPDATE livraria SET status_livraria = ? WHERE id_usuario = ? AND id_livro = ?')
                ->execute([$novoStatus, $uid, $id]);
            $book['status_livraria'] = $novoStatus;
            $sucessoResenha = 'Status de leitura atualizado para: ' . folivro_status_label($novoStatus) . '.';
        }
    }
}

// Busca a resenha do próprio usuário (se existir) e as resenhas da comunidade.
$minhaStmt = $pdo->prepare('SELECT nota_resenha, titulo_resenha, resenha_resenha FROM resenha WHERE id_usuario = ? AND id_livro = ? LIMIT 1');
$minhaStmt->execute([$uid, $id]);
$minhaResenha = $minhaStmt->fetch() ?: ['nota_resenha' => 5, 'titulo_resenha' => '', 'resenha_resenha' => ''];

$listaStmt = $pdo->prepare(
    'SELECT r.nota_resenha, r.titulo_resenha, r.resenha_resenha, r.data_avaliacao_resenha, u.nome_usuario
       FROM resenha r
       JOIN usuario u ON u.id_usuario = r.id_usuario
      WHERE r.id_livro = ?
   ORDER BY r.data_avaliacao_resenha DESC
      LIMIT 20'
);
$listaStmt->execute([$id]);
$resenhas = $listaStmt->fetchAll();

$titulo = folivro_escape($book['titulo_livro']);
$csrf = folivro_escape(folivro_csrf_token());
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $csrf ?>">
    <title><?= $titulo ?> | Leitor · FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261122_01">
    <link rel="stylesheet" href="home.css?v=20261104_20">
</head>

<body>

    <?php $menuAtivo = 'biblioteca'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo conteudo-leitura-confortavel" id="conteudo-principal">
        <div class="leitura-imersiva-container leitor-catalogo-container">
            <?php
            $pctAtual = max(0, min(100, (int) round((float) ($book['porcentagem_progressao'] ?? 0))));
            $siglaIdadeLeitor = folivro_classificacao_sigla($classLivroLeitor);
            $labelIdadeLeitor = folivro_aviso_conteudo_label($classLivroLeitor);
            ?>

            <nav class="navegacao-topo-leitura" aria-label="Navegação do leitor">
                <div class="links-retorno-leitura">
                    <a href="minha-biblioteca.php" class="link-retorno-suave">← Minha Biblioteca</a>
                    <span class="separador-nav">·</span>
                    <a href="livro.php?id=<?= (int) $book['id_livro'] ?>" class="link-retorno-suave">Ver ficha completa e críticas</a>
                </div>
                <button type="button" class="btn-conforto-item btn-modo-foco-leitura" id="btn-modo-foco-catalogo" title="Ocultar menus laterais para focar na leitura">
                    <img src="img/icones/leaf.png" alt="" class="icone-btn-png"> Modo Foco
                </button>
            </nav>

            <header class="cabecalho-leitor-livro">
                <div class="livro-capa">
                    <?php if (!empty($book['capa_livro'])): ?>
                        <img src="<?= folivro_escape($book['capa_livro']) ?>" alt="Capa de <?= $titulo ?>">
                    <?php else: ?>
                        <div class="capa-fallback"><?= $titulo ?></div>
                    <?php endif; ?>
                </div>
                <div class="cabecalho-leitor-info">
                    <div class="linha-selos-leitura">
                        <span class="selo-status <?= ($book['status_livraria'] ?? '') === 'lido' ? 'selo-lido' : '' ?>">
                            <?= folivro_escape(folivro_status_label((string) ($book['status_livraria'] ?? 'lendo'))) ?>
                        </span>
                        <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($classLivroLeitor) ?>" title="<?= folivro_escape($labelIdadeLeitor) ?>">
                            <?= folivro_escape($siglaIdadeLeitor) ?> · <?= folivro_escape($labelIdadeLeitor) ?>
                        </span>
                    </div>
                    <h1><?= $titulo ?></h1>
                    <p><strong><?= folivro_escape($book['autor_livro']) ?></strong> · Acompanhamento de leitura sem distrações. O leitor integrado exibe edições cujo acesso online é disponibilizado oficialmente pela Open Library / Internet Archive.</p>
                    <div class="bloco-progresso-leitor">
                        <div class="topo-progresso-leitor-flex">
                            <span id="progresso-rotulo-topo">Progresso atual: <?= $pctAtual ?>%</span>
                            <span class="paginas-rotulo-topo">
                                Pág. <?= (int) ($book['pagina_atual_progressao'] ?? 0) ?> de <?= (int) ($book['total_paginas_progressao'] ?: $book['paginas_livro'] ?: 1) ?>
                            </span>
                        </div>
                        <div class="mini-progresso" aria-label="Progresso de leitura">
                            <span id="progresso-barra-preenchimento" style="width: <?= $pctAtual ?>%"></span>
                        </div>
                    </div>
                </div>
            </header>

            <section id="leitor-status" class="estado-vazio" role="status">Verificando disponibilidade do leitor…</section>

            <!-- LEITOR NATIVO DE CAPÍTULOS — ACERVO NACIONAL DE DOMÍNIO PÚBLICO (LEI Nº 9.610/1998) -->
            <section id="leitor-dominio-publico" hidden aria-label="Leitor de Capítulos em Domínio Público">
                <div class="barra-ferramentas-leitura" role="toolbar" aria-label="Opções de conforto visual e navegação de capítulos">
                    <div class="grupo-conforto seletor-capitulo-barra">
                        <label for="dp-select-capitulo" class="rotulo-conforto">Capítulo:</label>
                        <select id="dp-select-capitulo" class="select-capitulo-rapido"></select>
                    </div>
                    <div class="controles-tipografia-direita">
                        <div class="grupo-conforto" role="group" aria-label="Tamanho e tipo de fonte">
                            <span class="rotulo-conforto">Letra:</span>
                            <button type="button" class="btn-conforto-item" id="dp-btn-fonte-menor" title="Diminuir fonte">A−</button>
                            <span class="indicador-tamanho-fonte" id="dp-indicador-px">18px</span>
                            <button type="button" class="btn-conforto-item" id="dp-btn-fonte-maior" title="Aumentar fonte">A+</button>
                            <button type="button" class="btn-conforto-item" id="dp-btn-alternar-fonte" title="Alternar fonte com ou sem serifa">Sem serifa</button>
                        </div>
                        <div class="grupo-conforto" role="group" aria-label="Tema do papel">
                            <span class="rotulo-conforto">Papel:</span>
                            <button type="button" class="btn-conforto-item ativo" data-dp-tema="padrao">Padrão</button>
                            <button type="button" class="btn-conforto-item" data-dp-tema="sepia">Sépia</button>
                            <button type="button" class="btn-conforto-item" data-dp-tema="noturno">Noturno</button>
                        </div>
                    </div>
                </div>

                <div class="painel-leitura-unificado" id="dp-painel-papel">
                    <article class="folha-leitura-capitulo" id="dp-folha-capitulo">
                        <header class="cabecalho-capitulo-folha">
                            <span class="numero-capitulo-folha" id="dp-meta-capitulo">CAPÍTULO 1 · ~2 MIN DE LEITURA</span>
                            <h2 id="dp-titulo-capitulo">Carregando capítulo…</h2>
                        </header>
                        <div class="texto-capitulo-formatado" id="dp-conteudo-capitulo"></div>
                        <footer class="rodape-fim-capitulo">
                            <span id="dp-fim-capitulo-label">✦ Fim do Capítulo 1 ✦</span>
                            <div style="display:flex; justify-content:center; gap:12px; margin-top:16px; flex-wrap:wrap;">
                                <button type="button" class="filtro-pilula" id="dp-btn-cap-anterior" disabled>← Capítulo anterior</button>
                                <button type="button" class="botao-adicionar-livro" id="dp-btn-cap-proximo" disabled>Próximo capítulo →</button>
                            </div>
                            <p id="dp-referencia-legal" style="margin-top:16px; font-size:12px; opacity:0.78;"></p>
                        </footer>
                    </article>
                </div>
            </section>

            <section id="leitor-pdf-container" class="leitor-pdf-container" hidden aria-label="Leitor FOLIVRO">
                <div class="leitor-pdf-barra">
                    <div class="leitor-pdf-controles">
                        <button type="button" class="filtro-pilula" id="pdf-prev" disabled>← Anterior</button>
                        <span class="leitor-pdf-paginacao" id="pdf-page-info">Página 1 de 1</span>
                        <button type="button" class="filtro-pilula" id="pdf-next" disabled>Próxima →</button>
                    </div>
                    <div class="leitor-pdf-controles">
                        <button type="button" class="filtro-pilula" id="pdf-zoom-out" title="Diminuir zoom">−</button>
                        <span class="leitor-pdf-zoom" id="pdf-zoom-info">100%</span>
                        <button type="button" class="filtro-pilula" id="pdf-zoom-in" title="Aumentar zoom">+</button>
                    </div>
                </div>
                <div class="leitor-pdf-viewport" oncontextmenu="return false;">
                    <canvas id="leitor-pdf-canvas"></canvas>
                </div>
            </section>
            <iframe id="leitor-embed" class="leitor-embed" title="Leitor de <?= $titulo ?>" hidden allow="fullscreen" referrerpolicy="no-referrer"></iframe>

            <!-- PAINEL DUPLO ALINHADO: REGISTRAR PROGRESSO + STATUS NA BIBLIOTECA -->
            <div class="grade-painel-progresso-leitor">
                <form id="form-progresso" class="progresso-form form-largura-total card-progresso-nivelado">
                    <h2>Registrar progresso de páginas</h2>
                    <p class="texto-apoio-pequeno" style="margin:0;">Atualize a página em que você parou para sincronizar sua barra de leitura.</p>
                    <div class="grade-campos-dupla">
                        <label for="pagina_atual">Página atual
                            <input id="pagina_atual" type="number" name="pagina_atual" min="0" value="<?= (int) ($book['pagina_atual_progressao'] ?? 0) ?>" required>
                        </label>
                        <label for="total_paginas">Total de páginas
                            <input id="total_paginas" type="number" name="total_paginas" min="1" value="<?= (int) ($book['total_paginas_progressao'] ?: $book['paginas_livro'] ?: 1) ?>" required>
                        </label>
                    </div>
                    <div class="rodape-card-nivelado">
                        <button class="botao-adicionar-livro" type="submit">Salvar progresso</button>
                        <span id="progresso-mensagem" class="msg-inline-progresso" role="status"></span>
                    </div>
                </form>

                <form method="post" class="progresso-form form-largura-total card-progresso-nivelado">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="acao" value="alterar_status">
                    <h2>Status na biblioteca</h2>
                    <p class="texto-apoio-pequeno" style="margin:0;">Organize em qual estante este livro fica na sua biblioteca pessoal.</p>
                    <label for="status_livraria">Situação atual da leitura
                        <select id="status_livraria" name="status_livraria">
                            <?php foreach (['quero_ler' => 'Quero ler', 'lendo' => 'Lendo atualmente', 'lido' => 'Concluído (Lido)', 'abandonado' => 'Pausado / Abandonado'] as $val => $rotulo): ?>
                                <option value="<?= $val ?>" <?= ($book['status_livraria'] ?? 'lendo') === $val ? 'selected' : '' ?>><?= $rotulo ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="rodape-card-nivelado">
                        <button class="botao-adicionar-livro" type="submit">Atualizar status</button>
                    </div>
                </form>
            </div>

            <section class="secao-interacao secao-largura-total elemento-ocultavel-foco" aria-labelledby="titulo-avaliacao">
                <form method="post" class="progresso-form form-largura-total">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="acao" value="resenha">
                    <h2 id="titulo-avaliacao" style="margin:0;">Avaliar e resenhar livro</h2>

                    <?php if ($erroResenha): ?>
                        <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape($erroResenha) ?></p>
                    <?php endif; ?>
                    <?php if ($sucessoResenha): ?>
                        <p class="estado-vazio estado-sucesso" role="status"><?= folivro_escape($sucessoResenha) ?></p>
                    <?php endif; ?>

                    <div class="grade-campos-dupla">
                        <label for="nota_resenha">Nota (1 a 5 estrelas)
                            <select id="nota_resenha" name="nota_resenha" required>
                                <?php for ($n = 5; $n >= 1; $n--): ?>
                                    <option value="<?= $n ?>" <?= (int) ($minhaResenha['nota_resenha'] ?? 5) === $n ? 'selected' : '' ?>>
                                        <?= $n ?> <?= str_repeat('★', $n) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </label>

                        <label for="titulo_resenha">Título da resenha (opcional)
                            <input id="titulo_resenha" name="titulo_resenha" maxlength="150" placeholder="Resumo da sua impressão..." value="<?= folivro_escape((string) ($minhaResenha['titulo_resenha'] ?? '')) ?>">
                        </label>
                    </div>

                    <label for="resenha_resenha">Sua resenha
                        <textarea id="resenha_resenha" name="resenha_resenha" rows="4" maxlength="5000" required placeholder="Escreva sua avaliação sobre a leitura..."><?= folivro_escape((string) ($minhaResenha['resenha_resenha'] ?? '')) ?></textarea>
                    </label>

                    <div class="acoes-obra" style="margin: 4px 0 0;">
                        <button class="botao-adicionar-livro" type="submit">Salvar avaliação</button>
                        <?php if (!empty($minhaResenha['resenha_resenha'])): ?>
                            <button type="submit" name="acao" value="excluir_resenha" class="filtro-pilula" style="border:none; cursor:pointer;" onclick="return confirm('Deseja excluir sua avaliação?');">Excluir minha avaliação</button>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="lista-interacoes">
                    <h2>Avaliações dos leitores (<?= count($resenhas) ?>)</h2>
                    <?php if (!$resenhas): ?>
                        <p class="estado-vazio">Nenhuma resenha registrada para este livro ainda.</p>
                    <?php else: ?>
                        <?php foreach ($resenhas as $r): ?>
                            <article class="card-interacao">
                                <header>
                                    <strong><?= folivro_escape($r['nome_usuario']) ?></strong>
                                    <span class="estrelas" aria-label="Nota <?= (int) $r['nota_resenha'] ?> de 5"><?= str_repeat('★', (int) ($r['nota_resenha'] ?? 0)) ?></span>
                                    <small><?= date('d/m/Y', strtotime($r['data_avaliacao_resenha'])) ?></small>
                                </header>
                                <?php if (!empty($r['titulo_resenha'])): ?>
                                    <h3><?= folivro_escape($r['titulo_resenha']) ?></h3>
                                <?php endif; ?>
                                <p><?= folivro_escape($r['resenha_resenha']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <script>
        const workId = <?= json_encode($book['id_externo_livro'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const tituloLivro = <?= json_encode((string) ($book['titulo_livro'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const idLivro = <?= (int) $book['id_livro'] ?>;
        const produtoLoja = <?= json_encode($produtoLoja ? [
            'idProduto' => (int) $produtoLoja['id_produto'],
            'idAutorDosLivros' => (int) ($produtoLoja['id_autor_dos_livros'] ?? 0),
            'tipo' => (string) ($produtoLoja['arquivo_tipo'] ?? ''),
            'temArquivo' => !empty($produtoLoja['arquivo_produto']),
            'acesso' => $acessoLojaLeitor,
            'bloqueadoMod' => $bloqueadoModLeitor,
        ] : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const statusBox = document.getElementById('leitor-status');

        function linkExterno(href, texto) {
            const a = document.createElement('a');
            a.href = href;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.textContent = texto;
            return a;
        }

        function linkInterno(href, texto, classe = 'botao-adicionar-livro') {
            const a = document.createElement('a');
            a.href = href;
            a.className = classe;
            a.style.marginLeft = '8px';
            a.style.display = 'inline-block';
            a.textContent = texto;
            return a;
        }

        function carregarScript(src) {
            return new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = src;
                s.onload = resolve;
                s.onerror = reject;
                document.head.appendChild(s);
            });
        }

        async function iniciarLeitorPdfFolivro(pdfUrl) {
            const container = document.getElementById('leitor-pdf-container');
            const canvas = document.getElementById('leitor-pdf-canvas');
            const ctx = canvas.getContext('2d');
            const btnPrev = document.getElementById('pdf-prev');
            const btnNext = document.getElementById('pdf-next');
            const pageInfo = document.getElementById('pdf-page-info');
            const btnZoomOut = document.getElementById('pdf-zoom-out');
            const btnZoomIn = document.getElementById('pdf-zoom-in');
            const zoomInfo = document.getElementById('pdf-zoom-info');
            const inputPagAtual = document.getElementById('pagina_atual');
            const inputTotalPag = document.getElementById('total_paginas');

            try {
                if (!window.pdfjsLib) {
                    await carregarScript('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js');
                }
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

                const pdfDoc = await window.pdfjsLib.getDocument({ url: pdfUrl, withCredentials: true }).promise;
                const totalPages = pdfDoc.numPages;
                let currentPage = Math.max(1, Math.min(totalPages, Number(inputPagAtual?.value) || 1));
                let scale = 1.25;
                let renderTask = null;

                if (inputTotalPag) {
                    inputTotalPag.value = String(totalPages);
                }

                async function renderPage(num) {
                    currentPage = num;
                    if (renderTask) {
                        try { renderTask.cancel(); } catch (_) {}
                    }
                    const page = await pdfDoc.getPage(num);
                    const viewport = page.getViewport({ scale });
                    const dpr = window.devicePixelRatio || 1;
                    canvas.width = Math.floor(viewport.width * dpr);
                    canvas.height = Math.floor(viewport.height * dpr);
                    canvas.style.width = `${Math.floor(viewport.width)}px`;
                    canvas.style.height = `${Math.floor(viewport.height)}px`;

                    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                    renderTask = page.render({ canvasContext: ctx, viewport });
                    await renderTask.promise;

                    pageInfo.textContent = `Página ${currentPage} de ${totalPages}`;
                    zoomInfo.textContent = `${Math.round((scale / 1.25) * 100)}%`;
                    btnPrev.disabled = currentPage <= 1;
                    btnNext.disabled = currentPage >= totalPages;
                    if (inputPagAtual) {
                        inputPagAtual.value = String(currentPage);
                    }
                }

                btnPrev.addEventListener('click', () => {
                    if (currentPage > 1) renderPage(currentPage - 1);
                });
                btnNext.addEventListener('click', () => {
                    if (currentPage < totalPages) renderPage(currentPage + 1);
                });
                btnZoomOut.addEventListener('click', () => {
                    if (scale > 0.75) {
                        scale = Math.max(0.75, scale - 0.2);
                        renderPage(currentPage);
                    }
                });
                btnZoomIn.addEventListener('click', () => {
                    if (scale < 2.5) {
                        scale = Math.min(2.5, scale + 0.2);
                        renderPage(currentPage);
                    }
                });

                container.hidden = false;
                statusBox.textContent = 'Leitura da obra digital FOLIVRO carregada.';
                await renderPage(currentPage);
            } catch (err) {
                const frame = document.getElementById('leitor-embed');
                frame.src = `${pdfUrl}#toolbar=0&navpanes=0`;
                frame.hidden = false;
                statusBox.textContent = 'Leitura da obra digital FOLIVRO carregada.';
            }
        }

        if (produtoLoja) {
            if (produtoLoja.bloqueadoMod) {
                statusBox.className = 'estado-vazio estado-erro';
                statusBox.textContent = 'Esta obra foi bloqueada ou suspensa pela moderação e não está disponível para leitura.';
            } else if (!produtoLoja.acesso) {
                statusBox.textContent = 'Adquira esta obra na loja para liberar a leitura completa.';
                statusBox.append(' ', linkInterno(`loja-obra.php?id=${produtoLoja.idProduto}`, 'Ver na loja'));
            } else {
                const frame = document.getElementById('leitor-embed');
                if (produtoLoja.temArquivo && produtoLoja.tipo === 'pdf') {
                    statusBox.textContent = 'Carregando obra no leitor FOLIVRO…';
                    iniciarLeitorPdfFolivro(`loja-download.php?id=${produtoLoja.idProduto}&inline=1`);
                } else if (produtoLoja.idAutorDosLivros > 0) {
                    statusBox.textContent = 'Esta obra autoral possui leitor de capítulos interativo no FOLIVRO.';
                } else {
                    statusBox.textContent = 'Obra disponível para download no formato ' + (produtoLoja.tipo || 'digital').toUpperCase() + '.';
                }
                if (produtoLoja.idAutorDosLivros > 0) {
                    statusBox.append(' ', linkInterno(`obra.php?id=${produtoLoja.idAutorDosLivros}`, 'Ler capítulos online'));
                }
                if (produtoLoja.temArquivo && produtoLoja.tipo !== 'pdf') {
                    statusBox.append(' ', linkInterno(`loja-download.php?id=${produtoLoja.idProduto}`, 'Baixar arquivo', 'filtro-pilula'));
                }
            }
        } else {
            // Tenta primeiro carregar do Acervo Nacional de Domínio Público (Lei nº 9.610/1998 — Wikisource PT) para leitura nativa no FOLIVRO.
            iniciarLeitorDominioPublico(workId || '', tituloLivro || '');
        }

        async function iniciarLeitorDominioPublico(wId, tit) {
            const secaoDp = document.getElementById('leitor-dominio-publico');
            const selCap = document.getElementById('dp-select-capitulo');
            const metaCap = document.getElementById('dp-meta-capitulo');
            const titCap = document.getElementById('dp-titulo-capitulo');
            const contCap = document.getElementById('dp-conteudo-capitulo');
            const fimLabel = document.getElementById('dp-fim-capitulo-label');
            const refLegal = document.getElementById('dp-referencia-legal');
            const btnAnt = document.getElementById('dp-btn-cap-anterior');
            const btnProx = document.getElementById('dp-btn-cap-proximo');
            const inputPagAtual = document.getElementById('pagina_atual');
            const inputTotalPag = document.getElementById('total_paginas');

            let capInicial = Math.max(1, Number(inputPagAtual?.value) || 1);

            async function carregarCapituloDp(numCap) {
                statusBox.textContent = 'Carregando capítulo do acervo de Domínio Público…';
                const r = await fetch(`api/dominio-publico.php?work=${encodeURIComponent(wId)}&titulo=${encodeURIComponent(tit)}&cap=${numCap}`);
                const d = await r.json();
                if (!d || !d.disponivel) {
                    return false;
                }

                if (selCap.options.length !== d.totalCapitulos) {
                    selCap.innerHTML = '';
                    (d.listaCapitulos || []).forEach((c) => {
                        const opt = document.createElement('option');
                        opt.value = String(c.ordem);
                        opt.textContent = c.titulo;
                        selCap.appendChild(opt);
                    });
                }
                const ordem = d.capituloAtual.ordem;
                selCap.value = String(ordem);
                metaCap.textContent = `CAPÍTULO ${ordem} DE ${d.totalCapitulos} · ~${d.capituloAtual.tempoMin} MIN DE LEITURA`;
                titCap.textContent = d.capituloAtual.titulo;
                contCap.innerHTML = d.capituloAtual.conteudoHtml;
                fimLabel.textContent = `✦ Fim do Capítulo ${ordem} ✦`;
                refLegal.textContent = `${d.baseLegal} (${d.edicaoReferencia})`;
                btnAnt.disabled = ordem <= 1;
                btnProx.disabled = ordem >= d.totalCapitulos;
                btnAnt.onclick = () => { if (ordem > 1) carregarCapituloDp(ordem - 1); };
                btnProx.onclick = () => { if (ordem < d.totalCapitulos) carregarCapituloDp(ordem + 1); };

                if (inputPagAtual && inputTotalPag) {
                    inputPagAtual.value = String(ordem);
                    inputTotalPag.value = String(d.totalCapitulos);
                }

                secaoDp.hidden = false;
                statusBox.textContent = `${d.baseLegal}`;
                return true;
            }

            try {
                const okDp = await carregarCapituloDp(capInicial);
                if (okDp) {
                    selCap.addEventListener('change', () => carregarCapituloDp(Number(selCap.value) || 1));

                    // Controles de fonte e tema iguais ao leitor original (obra.php)
                    let fontPx = 18;
                    let semSerifa = false;
                    const indPx = document.getElementById('dp-indicador-px');
                    const btnMenor = document.getElementById('dp-btn-fonte-menor');
                    const btnMaior = document.getElementById('dp-btn-fonte-maior');
                    const btnFonte = document.getElementById('dp-btn-alternar-fonte');
                    const painelPapel = document.getElementById('dp-painel-papel');

                    function aplicarTipografiaDp() {
                        contCap.style.fontSize = `${fontPx}px`;
                        contCap.style.fontFamily = semSerifa ? 'system-ui, -apple-system, sans-serif' : 'Georgia, "Times New Roman", serif';
                        indPx.textContent = `${fontPx}px`;
                        btnFonte.textContent = semSerifa ? 'Com serifa' : 'Sem serifa';
                    }
                    btnMenor.addEventListener('click', () => { fontPx = Math.max(14, fontPx - 2); aplicarTipografiaDp(); });
                    btnMaior.addEventListener('click', () => { fontPx = Math.min(26, fontPx + 2); aplicarTipografiaDp(); });
                    btnFonte.addEventListener('click', () => { semSerifa = !semSerifa; aplicarTipografiaDp(); });

                    document.querySelectorAll('[data-dp-tema]').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            document.querySelectorAll('[data-dp-tema]').forEach((b) => b.classList.remove('ativo'));
                            btn.classList.add('ativo');
                            const tema = btn.getAttribute('data-dp-tema');
                            painelPapel.classList.remove('tema-papel-sepia', 'tema-papel-noturno');
                            if (tema === 'sepia') painelPapel.classList.add('tema-papel-sepia');
                            if (tema === 'noturno') painelPapel.classList.add('tema-papel-noturno');
                        });
                    });
                    aplicarTipografiaDp();
                    return;
                }
            } catch (_) {}

            // Fallback: consulta disponibilidade na Open Library / Internet Archive
            if (wId && /^OL[0-9]+W$/.test(wId)) {
                fetch(`api/leitura-disponivel.php?work=${encodeURIComponent(wId)}`)
                    .then((r) => r.json())
                    .then((data) => {
                        if (data.availability === 'full_access') {
                            const frame = document.getElementById('leitor-embed');
                            frame.src = data.readerUrl;
                            frame.hidden = false;
                            statusBox.textContent = 'Leitura online disponível.';
                        } else if (data.availability === 'borrow') {
                            statusBox.textContent = 'Esta edição requer empréstimo. Abra o registro da Open Library para verificar as opções de acesso.';
                            statusBox.append(' ', linkExterno(data.externalUrl, 'Ver opções de leitura/empréstimo'));
                        } else {
                            statusBox.textContent = 'A Open Library não disponibiliza um leitor online para esta edição. Você pode consultar a obra no catálogo.';
                            statusBox.append(' ', linkExterno('https://openlibrary.org/works/' + encodeURIComponent(wId), 'Abrir catálogo'));
                        }
                    })
                    .catch(() => { statusBox.textContent = 'Não foi possível consultar a disponibilidade agora.'; });
            } else {
                statusBox.textContent = 'Este livro não possui conteúdo de leitura online disponível no momento.';
            }
        }

        // Salva o progresso informado pelo usuário (api/progresso.php) e atualiza a barra visual.
        document.getElementById('form-progresso').addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const f = ev.currentTarget;
            const msg = document.getElementById('progresso-mensagem');
            try {
                const r = await fetch('api/progresso.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        id_livro: idLivro,
                        pagina_atual: Number(f.pagina_atual.value),
                        total_paginas: Number(f.total_paginas.value)
                    })
                });
                const d = await r.json();
                if (!r.ok) throw new Error(d.error || 'Falha ao salvar.');
                const pct = Math.max(0, Math.min(100, Math.round(Number(d.porcentagem) || 0)));
                msg.textContent = `Progresso salvo: ${pct}%.`;
                const barra = document.getElementById('progresso-barra-preenchimento');
                const rotulo = document.getElementById('progresso-rotulo-topo');
                if (barra) barra.style.width = `${pct}%`;
                if (rotulo) rotulo.textContent = `Progresso atual: ${pct}%`;
            } catch (e) {
                msg.textContent = e.message || 'Não foi possível salvar.';
            }
        });

        const btnFocoCatalogo = document.getElementById('btn-modo-foco-catalogo');
        if (btnFocoCatalogo) {
            btnFocoCatalogo.addEventListener('click', () => {
                const focoAtivo = document.body.classList.toggle('modo-leitura-foco');
                btnFocoCatalogo.classList.toggle('ativo', focoAtivo);
                btnFocoCatalogo.innerHTML = focoAtivo
                    ? '<img src="img/icones/book.png" alt="" class="icone-btn-png icone-btn-branco"> Sair do Foco'
                    : '<img src="img/icones/leaf.png" alt="" class="icone-btn-png"> Modo Foco';
            });
        }
    </script>
</body>

</html>
