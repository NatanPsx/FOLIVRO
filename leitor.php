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
    <link rel="stylesheet" href="layout.css?v=20261104_20">
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
        const idLivro = <?= (int) $book['id_livro'] ?>;
        const statusBox = document.getElementById('leitor-status');

        function linkExterno(href, texto) {
            const a = document.createElement('a');
            a.href = href;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.textContent = texto;
            return a;
        }

        // Consulta se a Open Library/Internet Archive libera leitura online desta obra.
        if (workId) {
            fetch(`api/leitura-disponivel.php?work=${encodeURIComponent(workId)}`)
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
                        statusBox.append(' ', linkExterno('https://openlibrary.org/works/' + encodeURIComponent(workId), 'Abrir catálogo'));
                    }
                })
                .catch(() => { statusBox.textContent = 'Não foi possível consultar a disponibilidade agora.'; });
        } else {
            statusBox.textContent = 'O leitor integrado está disponível para títulos da Open Library com leitura online liberada.';
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
