<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];

$msgAviso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $msgAviso = 'Sessão expirada. Tente novamente.';
    } elseif (($_POST['acao'] ?? '') === 'remover_catalogo') {
        $idLivroRem = filter_input(INPUT_POST, 'id_livro', FILTER_VALIDATE_INT) ?: 0;
        if ($idLivroRem > 0) {
            $pdo->prepare('DELETE FROM livraria WHERE id_usuario = ? AND id_livro = ?')->execute([$uid, $idLivroRem]);
            $msgAviso = 'Livro removido da sua biblioteca.';
        }
    }
}

// Biblioteca pessoal (livros do catálogo) + biblioteca digital (obras autorais adquiridas).
// Obras removidas definitivamente pela moderação não são exibidas; obras sob análise refletem seu estado atual.
$stmt = $pdo->prepare(
    'SELECT COALESCE(adl.id_autor_dos_livros, b.id_livro) AS item_id, b.id_livro AS id_livro_ficha,
            b.titulo_livro AS titulo, b.autor_livro AS autor, b.capa_livro AS capa,
            CASE
                WHEN adl.status_autor_dos_livros = "suspenso" THEN "suspenso"
                WHEN adl.status_autor_dos_livros = "rascunho" THEN "rascunho"
                WHEN adl.id_autor_dos_livros IS NOT NULL THEN "comprado"
                ELSE l.status_livraria
            END AS status,
            p.porcentagem_progressao AS progresso,
            CASE WHEN adl.id_autor_dos_livros IS NOT NULL THEN 1 ELSE 0 END AS obra_autoral,
            COALESCE(adl.motivo_bloqueio_autor, "") AS motivo_bloqueio, COALESCE(b.classificacao_livro, "livre") AS classificacao,
            COALESCE(b.genero_livro, "") AS generos
       FROM livraria l
       JOIN livro b ON b.id_livro = l.id_livro
  LEFT JOIN progressao_leitura p ON p.id_usuario = l.id_usuario AND p.id_livro = l.id_livro
  LEFT JOIN loja_produtos lp ON lp.id_livro = b.id_livro
  LEFT JOIN autor_dos_livros adl ON adl.id_autor_dos_livros = lp.id_autor_dos_livros
      WHERE l.id_usuario = ?
        AND (adl.id_autor_dos_livros IS NULL OR adl.status_autor_dos_livros <> "removido")
  UNION ALL
     SELECT a.id_autor_dos_livros, COALESCE(lp3.id_livro, 0) AS id_livro_ficha,
            h.titulo_historias, u.nome_usuario, h.capa_historias,
            CASE
                WHEN a.status_autor_dos_livros = "suspenso" THEN "suspenso"
                WHEN a.status_autor_dos_livros = "rascunho" THEN "rascunho"
                ELSE "comprado"
            END,
            100, 1,
            COALESCE(a.motivo_bloqueio_autor, ""), COALESCE(h.aviso_conteudo_historias, "livre"),
            COALESCE(h.generos_historias, "")
       FROM livraria_digital d
       JOIN autor_dos_livros a ON a.id_autor_dos_livros = d.id_autor_dos_livros
       JOIN historias h ON h.id_historias = a.id_historias
       JOIN usuario u ON u.id_usuario = a.id_autor
  LEFT JOIN loja_produtos lp3 ON lp3.id_autor_dos_livros = a.id_autor_dos_livros
      WHERE d.id_usuario = ? AND a.status_autor_dos_livros <> "removido"
        AND NOT EXISTS (
            SELECT 1 FROM livraria l2
            JOIN loja_produtos lp2 ON lp2.id_livro = l2.id_livro
            WHERE l2.id_usuario = d.id_usuario AND lp2.id_autor_dos_livros = a.id_autor_dos_livros
        )'
);
$stmt->execute([$uid, $uid]);
$books = $stmt->fetchAll();
$maiorDeIdade = folivro_usuario_maior_de_idade();
$csrf = folivro_escape(folivro_csrf_token());
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minha biblioteca digital | FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261104_20">
    <link rel="stylesheet" href="home.css?v=20261104_20">
    <link rel="stylesheet" href="estante.css?v=20261117_4">
</head>

<body>

    <?php $menuAtivo = 'biblioteca'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <h1>Minha biblioteca digital</h1>
        <p>Livros salvos e obras independentes adquiridas. <a href="loja.php">Visitar a loja</a></p>

        <?php if ($msgAviso): ?>
            <p class="estado-vazio estado-sucesso" role="status"><?= folivro_escape($msgAviso) ?></p>
        <?php endif; ?>

        <?php if ($books): ?>
            <div class="filtros-biblioteca" role="toolbar" aria-label="Filtrar livros da biblioteca">
                <button type="button" class="filtro-pilula ativo" data-filtro="todos" aria-pressed="true">Todos (<?= count($books) ?>)</button>
                <button type="button" class="filtro-pilula" data-filtro="lendo" aria-pressed="false">Lendo</button>
                <button type="button" class="filtro-pilula" data-filtro="quero_ler" aria-pressed="false">Quero ler</button>
                <button type="button" class="filtro-pilula" data-filtro="lido" aria-pressed="false">Lidos</button>
                <button type="button" class="filtro-pilula" data-filtro="comprado" aria-pressed="false">Obras adquiridas</button>
            </div>
        <?php endif; ?>

        <div class="biblioteca-lista estante-biblioteca" id="grade-biblioteca">
            <?php if (!$books): ?>
                <p class="estado-vazio">Sua biblioteca está vazia. Explore a loja ou adicione livros da Open Library.</p>
            <?php else: ?>
                <?php foreach ($books as $b): ?>
                    <?php
                    $statusRaw = (string) $b['status'];
                    $pctItem = min(100, max(0, (int) round((float) ($b['progresso'] ?? 0))));
                    $ehAutoral = (int) $b['obra_autoral'] === 1;
                    $classNorm = folivro_normalizar_classificacao((string) ($b['classificacao'] ?? 'livre'));
                    $siglaIdade = folivro_classificacao_sigla($classNorm);
                    $labelIdade = folivro_aviso_conteudo_label($classNorm);
                    $bloqueadoIdade = !$maiorDeIdade && folivro_classificacao_eh_maior18($classNorm);
                    $urlDestino = $ehAutoral ? 'obra.php?id=' . (int) $b['item_id'] : 'livro.php?id=' . (int) $b['item_id'];
                    ?>
                    <article class="livro-card <?= $bloqueadoIdade ? 'livro-card-bloqueado-idade' : '' ?>" data-status="<?= folivro_escape($statusRaw) ?>">
                        <div class="livro-capa <?= $bloqueadoIdade ? 'capa-bloqueada-18' : '' ?>">
                            <?php if ($bloqueadoIdade): ?>
                                <?php if ($b['capa']): ?>
                                    <img src="<?= folivro_escape($b['capa']) ?>" alt="Capa bloqueada por classificação indicativa +18" loading="lazy">
                                <?php else: ?>
                                    <div class="capa-fallback">Obra +18</div>
                                <?php endif; ?>
                                <div class="capa-bloqueio-idade-overlay" role="img" aria-label="Conteúdo bloqueado: exclusivo para maiores de 18 anos">
                                    <span class="icone-olho-bloqueado-circulo">
                                        <?= folivro_icone_olho_bloqueado_svg(32) ?>
                                    </span>
                                    <strong class="titulo-bloqueio-capa">Classificação +18</strong>
                                    <span class="motivo-bloqueio-capa">Bloqueado: conteúdo exclusivo para maiores de 18 anos</span>
                                </div>
                            <?php else: ?>
                                <a href="<?= folivro_escape($urlDestino) ?>" class="link-livro-capa" title="Ver detalhes de <?= folivro_escape($b['titulo']) ?>">
                                    <?php if ($b['capa']): ?>
                                        <img src="<?= folivro_escape($b['capa']) ?>" alt="Capa de <?= folivro_escape($b['titulo']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="capa-fallback"><?= folivro_escape($b['titulo']) ?></div>
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                            <span class="selo-classificacao-idade selo-classificacao-capa selo-idade-<?= folivro_escape($classNorm) ?>" title="<?= folivro_escape($labelIdade) ?>">
                                <?= folivro_escape($siglaIdade) ?>
                            </span>
                        </div>
                        <div class="livro-info">
                            <div class="linha-selos-card">
                                <span class="selo-status selo-<?= folivro_escape($statusRaw) ?>">
                                    <?= folivro_escape(folivro_status_label($statusRaw)) ?>
                                </span>
                                <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($classNorm) ?>" title="<?= folivro_escape($labelIdade) ?>">
                                    <?= folivro_escape($siglaIdade) ?>
                                </span>
                            </div>
                            <h2>
                                <?php if ($bloqueadoIdade): ?>
                                    <span class="titulo-oculto-idade" title="Título oculto por restrição de faixa etária (+18)">Título oculto (+18)</span>
                                <?php else: ?>
                                    <a href="<?= folivro_escape($urlDestino) ?>" class="link-titulo-livro" title="Ver detalhes e críticas">
                                        <?= folivro_escape($b['titulo']) ?>
                                    </a>
                                <?php endif; ?>
                            </h2>
                            <p class="autor-card-livro"><?= folivro_escape($b['autor']) ?></p>
                            <?php $generosBibCard = !$bloqueadoIdade ? folivro_generos_lista((string) ($b['generos'] ?? '')) : []; ?>
                            <?php if ($generosBibCard): ?>
                                <div class="tags-generos-card" aria-label="Gêneros da obra">
                                    <?php foreach ($generosBibCard as $gBibItem): ?>
                                        <span class="tag-genero-card"><?= folivro_escape($gBibItem) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="rodape-card-leitura">
                                <?php if ($bloqueadoIdade): ?>
                                    <p class="texto-motivo-bloqueio-card">
                                        Obra oculta: sua conta possui menos de 18 anos e este livro tem classificação <strong>+18</strong>.
                                    </p>
                                    <button class="botao-adicionar-livro botao-bloqueado-idade btn-card-principal" type="button" disabled title="Conteúdo restrito para maiores de 18 anos">
                                        Bloqueado (+18)
                                    </button>
                                    <?php if (!$ehAutoral): ?>
                                        <form method="post" class="form-remover-card">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="acao" value="remover_catalogo">
                                            <input type="hidden" name="id_livro" value="<?= (int) $b['item_id'] ?>">
                                            <button type="submit" class="btn-remover-card-sutil" onclick="return confirm('Deseja remover este livro da sua biblioteca?');">Remover da biblioteca</button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($ehAutoral): ?>
                                    <?php if ($statusRaw === 'suspenso'): ?>
                                        <p class="aviso-direitos"><small>Esta obra está temporariamente suspensa para análise humana de direitos autorais.</small></p>
                                    <?php elseif ($statusRaw === 'rascunho'): ?>
                                        <p class="aviso-direitos"><small>Esta obra foi editada pelo autor e aguarda nova revisão da moderação.</small></p>
                                    <?php else: ?>
                                        <a class="botao-adicionar-livro btn-card-principal" href="obra.php?id=<?= (int) $b['item_id'] ?>">Ler obra</a>
                                    <?php endif; ?>
                                    <?php $idLivroCriticas = (int) ($b['id_livro_ficha'] ?? 0); ?>
                                    <div class="acoes-secundarias-card">
                                        <a class="link-ver-detalhes" href="<?= $idLivroCriticas > 0 ? 'livro.php?id=' . $idLivroCriticas : 'obra.php?id=' . (int) $b['item_id'] . '#secao-comunidade-obra' ?>">Críticas &rarr;</a>
                                    </div>
                                <?php else: ?>
                                    <div class="bloco-progresso-card">
                                        <div class="topo-progresso-card">
                                            <span>Progresso</span>
                                            <strong><?= $pctItem ?>% lido</strong>
                                        </div>
                                        <div class="mini-progresso" role="progressbar" aria-valuenow="<?= $pctItem ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progresso de leitura">
                                            <span style="width: <?= $pctItem ?>%"></span>
                                        </div>
                                    </div>
                                    <a class="botao-adicionar-livro btn-card-principal" href="leitor.php?id=<?= (int) $b['item_id'] ?>">Continuar leitura</a>
                                    <div class="acoes-secundarias-card">
                                        <a class="link-ver-detalhes" href="livro.php?id=<?= (int) $b['item_id'] ?>">Críticas &rarr;</a>
                                        <form method="post" class="form-remover-card">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="acao" value="remover_catalogo">
                                            <input type="hidden" name="id_livro" value="<?= (int) $b['item_id'] ?>">
                                            <button type="submit" class="btn-remover-card-sutil" onclick="return confirm('Deseja remover este livro da sua biblioteca?');">Remover</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.querySelectorAll('.filtro-pilula').forEach((btn) => {
            btn.addEventListener('click', () => {
                const filtro = btn.dataset.filtro;
                document.querySelectorAll('.filtro-pilula').forEach((b) => {
                    b.classList.toggle('ativo', b === btn);
                    b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
                });
                document.querySelectorAll('#grade-biblioteca .livro-card').forEach((card) => {
                    card.hidden = (filtro !== 'todos' && card.dataset.status !== filtro);
                });
            });
        });
    </script>
</body>

</html>
