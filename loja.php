<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];

// Somente obras aprovadas em revisão humana, publicadas, com declaração de autoria e sem suspensão preventiva aparecem na loja.
$stmt = $pdo->query(
    "SELECT a.id_autor_dos_livros, a.titulo_autor_dos_livros, a.preco_dos_livros_autor, a.id_autor,
            a.modalidade_distribuicao, a.declaracao_comercializacao,
            h.sinopse_historias, h.capa_historias, h.generos_historias, h.licenca_historias, h.aviso_conteudo_historias, u.nome_usuario,
            (SELECT COUNT(*) FROM capitulos c WHERE c.id_historias = h.id_historias AND c.status_capitulos = 'publicado') AS capitulos
       FROM autor_dos_livros a
       JOIN historias h ON h.id_historias = a.id_historias
       JOIN usuario u ON u.id_usuario = a.id_autor
      WHERE a.status_autor_dos_livros = 'a_venda'
        AND h.status_historias = 'publicada'
        AND a.revisao_publicacao = 'aprovada'
        AND a.declaracao_autoria = 1
        AND (a.modalidade_distribuicao = 'gratuita' OR a.declaracao_comercializacao = 1)
        AND NOT EXISTS (
            SELECT 1 FROM denuncias d
             WHERE d.tipo_denuncias = 'obra_autoral'
               AND d.item_denuncia = a.id_autor_dos_livros
               AND d.status_denuncias IN ('aberta', 'em_analise')
               AND d.suspensao_preventiva = 1
        )
   ORDER BY a.data_publicacao_autor DESC"
);
$works = $stmt->fetchAll();

$ownedStmt = $pdo->prepare('SELECT id_autor_dos_livros FROM livraria_digital WHERE id_usuario = ?');
$ownedStmt->execute([$uid]);
$owned = array_map('intval', $ownedStmt->fetchAll(PDO::FETCH_COLUMN));

$idadeUsuario = folivro_usuario_idade($pdo, $uid);
$maiorDeIdade = folivro_usuario_maior_de_idade($pdo, $uid);
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= folivro_escape(folivro_csrf_token()) ?>">
    <title>Loja | FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261022_11">
    <link rel="stylesheet" href="home.css?v=20261022_11">
</head>

<body>

    <?php $menuAtivo = 'loja'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <h1>Loja e vitrine de obras independentes</h1>
        <p>Descubra histórias originais publicadas por autores da comunidade. <strong>Escopo acadêmico: a compra é 100% simulada, sem cobrança real e sem coleta de dados de cartão ou pagamento.</strong></p>
        <p><small>As licenças e declarações exibidas são informadas sob responsabilidade de cada autor e não substituem verificação jurídica. Em caso de suspeita de violação de direitos autorais, acesse a obra ou utilize a denúncia para suspensão preventiva e análise humana.</small></p>

        <div class="filtros-biblioteca">
            <input type="search" id="filtro-loja" class="campo-filtro-loja" placeholder="Filtrar obras por título, autor ou gênero..." aria-label="Filtrar obras da loja por título, autor ou gênero">
            <select id="filtro-genero-loja" class="select-filtro-genero-loja" aria-label="Filtrar por gênero literário">
                <option value="">Todos os gêneros</option>
                <?php foreach (folivro_generos_disponiveis() as $genFiltro): ?>
                    <option value="<?= folivro_escape(mb_strtolower($genFiltro, 'UTF-8')) ?>"><?= folivro_escape($genFiltro) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="filtro-pilula ativo" data-filtro-tipo="todas">Todas as obras</button>
            <button type="button" class="filtro-pilula" data-filtro-tipo="gratuita">Gratuitas</button>
            <button type="button" class="filtro-pilula" data-filtro-tipo="paga">Venda simulada</button>
            <a class="filtro-pilula" href="minha-biblioteca.php">Minha biblioteca</a>
            <a class="filtro-pilula" href="escrever.php">Publicar minha obra</a>
        </div>

        <p class="banner-status-idade-catalogo <?= $maiorDeIdade ? 'idade-liberada' : 'idade-restrita' ?>" role="status">
            <?php if ($maiorDeIdade): ?>
                <strong>Classificação indicativa ativa:</strong> Sua conta possui 18 anos ou mais (<?= (int) $idadeUsuario ?> anos). Todas as classificações de idade estão liberadas.
            <?php elseif ($idadeUsuario !== null): ?>
                <?= folivro_icone_olho_bloqueado_svg(18) ?>
                <span><strong>Proteção etária ativa (<?= (int) $idadeUsuario ?> anos — menor de 18 anos):</strong> Livros classificados para maiores de 18 anos (+18) estão com a capa embaçada, olhinho bloqueado e título oculto.</span>
            <?php else: ?>
                <?= folivro_icone_olho_bloqueado_svg(18) ?>
                <span><strong>Proteção etária ativa:</strong> Informe sua data de nascimento no <a href="usuario.php">Perfil</a> (ou no cadastro) para verificar sua idade. Enquanto não tiver 18 anos completos, obras +18 permanecem ocultas e bloqueadas.</span>
            <?php endif; ?>
        </p>

        <div id="mensagem-compra" role="status" aria-live="polite"></div>

        <div class="biblioteca-lista" id="lista-loja">
            <?php if (!$works): ?>
                <p class="estado-vazio">Ainda não há obras à venda. Você pode escrever um livro e publicá-lo quando estiver pronto.</p>
            <?php else: ?>
                <?php foreach ($works as $w): ?>
                    <?php
                    $isAutor = ((int) $w['id_autor'] === $uid);
                    $jaComprou = in_array((int) $w['id_autor_dos_livros'], $owned, true);
                    $ehGratuita = (($w['modalidade_distribuicao'] ?? 'venda_simulada') === 'gratuita' || (float) $w['preco_dos_livros_autor'] <= 0.0);
                    $classifObra = folivro_normalizar_classificacao($w['aviso_conteudo_historias'] ?? 'livre');
                    $ehMaior18 = folivro_classificacao_eh_maior18($classifObra);
                    $bloqueadoIdade = (!$maiorDeIdade && $ehMaior18);
                    $listaGenerosCard = folivro_generos_lista((string) ($w['generos_historias'] ?? ''));
                    $generosStrMin = mb_strtolower(implode('|', $listaGenerosCard), 'UTF-8');
                    $termoBusca = $bloqueadoIdade
                        ? mb_strtolower('bloqueado +18 ' . $w['nome_usuario'], 'UTF-8')
                        : mb_strtolower($w['titulo_autor_dos_livros'] . ' ' . $w['nome_usuario'] . ' ' . ($w['generos_historias'] ?? ''), 'UTF-8');
                    ?>
                    <article class="livro-card <?= $bloqueadoIdade ? 'livro-card-bloqueado-idade' : '' ?>" data-busca="<?= folivro_escape($termoBusca) ?>" data-tipo="<?= $ehGratuita ? 'gratuita' : 'paga' ?>" data-generos="<?= folivro_escape($generosStrMin) ?>">
                        <div class="livro-capa <?= $bloqueadoIdade ? 'capa-bloqueada-18' : '' ?>">
                            <?php if ($w['capa_historias']): ?>
                                <img src="<?= folivro_escape($w['capa_historias']) ?>" alt="<?= $bloqueadoIdade ? 'Capa bloqueada por classificação indicativa +18' : 'Capa de ' . folivro_escape($w['titulo_autor_dos_livros']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="capa-fallback"><?= $bloqueadoIdade ? 'Conteúdo Restrito +18' : folivro_escape($w['titulo_autor_dos_livros']) ?></div>
                            <?php endif; ?>
                            <span class="selo-classificacao-capa selo-idade-<?= folivro_escape($classifObra) ?>" title="<?= folivro_escape(folivro_aviso_conteudo_label($classifObra)) ?>">
                                <?= folivro_escape(folivro_classificacao_sigla($classifObra)) ?>
                            </span>
                            <?php if ($bloqueadoIdade): ?>
                                <div class="capa-bloqueio-idade-overlay" role="img" aria-label="Capa bloqueada: obra exclusiva para maiores de 18 anos">
                                    <div class="icone-olho-bloqueado-circulo">
                                        <?= folivro_icone_olho_bloqueado_svg(34) ?>
                                    </div>
                                    <strong class="titulo-bloqueio-capa">Conteúdo +18</strong>
                                    <span class="motivo-bloqueio-capa">Bloqueado: exclusivo para maiores de 18 anos</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="livro-info">
                            <div class="linha-selos-card">
                                <?php if ($isAutor): ?>
                                    <span class="selo-status">Sua obra</span>
                                <?php elseif ($jaComprou): ?>
                                    <span class="selo-status selo-lido">Na sua biblioteca</span>
                                <?php elseif ($ehGratuita): ?>
                                    <span class="selo-status selo-lido">Publicação gratuita</span>
                                <?php else: ?>
                                    <span class="selo-status selo-comprado">R$ <?= number_format((float) $w['preco_dos_livros_autor'], 2, ',', '.') ?> (simulação)</span>
                                <?php endif; ?>
                                <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($classifObra) ?>">
                                    <?= folivro_escape(folivro_classificacao_sigla($classifObra)) ?>
                                </span>
                            </div>

                            <?php if ($bloqueadoIdade): ?>
                                <h2 class="titulo-oculto-idade" title="Título oculto para menores de 18 anos">
                                    <?= folivro_icone_olho_bloqueado_svg(18) ?>
                                    <span>Título oculto (+18)</span>
                                </h2>
                                <p>Por <?= folivro_escape($w['nome_usuario']) ?></p>
                                <p class="texto-motivo-bloqueio-card">Obra bloqueada: você não possui 18 anos completos conforme a data de nascimento do seu cadastro.</p>
                                <p class="livro-ano"><?= folivro_escape(folivro_aviso_conteudo_label($classifObra)) ?></p>
                                <button class="botao-adicionar-livro botao-bloqueado-idade" type="button" disabled>
                                    Bloqueado (+18)
                                </button>
                                <?php if ($isAutor): ?>
                                    <a href="gerenciar-obra.php?id=<?= (int) $w['id_autor_dos_livros'] ?>">Gerenciar minha obra</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <h2><?= folivro_escape($w['titulo_autor_dos_livros']) ?></h2>
                                <p>Por <?= folivro_escape($w['nome_usuario']) ?></p>
                                <?php if ($listaGenerosCard): ?>
                                    <div class="tags-generos-card" aria-label="Gêneros da obra">
                                        <?php foreach ($listaGenerosCard as $genTag): ?>
                                            <span class="tag-genero-card"><?= folivro_escape($genTag) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <p><?= folivro_escape($w['sinopse_historias']) ?></p>
                                <p class="livro-ano"><?= folivro_escape(folivro_licenca_label($w['licenca_historias'] ?? 'direitos_reservados')) ?></p>
                                <p class="livro-ano"><?= folivro_escape(folivro_aviso_conteudo_label($classifObra)) ?></p>
                                <p><?= (int) $w['capitulos'] ?> capítulo(s) · <?= $ehGratuita ? 'Gratuita' : 'R$ ' . number_format((float) $w['preco_dos_livros_autor'], 2, ',', '.') ?></p>
                                <?php if ($isAutor): ?>
                                    <a class="botao-adicionar-livro" href="gerenciar-obra.php?id=<?= (int) $w['id_autor_dos_livros'] ?>">Gerenciar obra</a>
                                <?php elseif ($jaComprou || $ehGratuita): ?>
                                    <a class="botao-adicionar-livro" href="obra.php?id=<?= (int) $w['id_autor_dos_livros'] ?>">Ler obra</a>
                                <?php else: ?>
                                    <button class="botao-adicionar-livro comprar-obra" type="button" data-id="<?= (int) $w['id_autor_dos_livros'] ?>">Comprar (simulação)</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Filtro da loja por texto (título/autor/gênero), gênero selecionado e modalidade (todas/gratuitas/venda simulada).
        const campoFiltroLoja = document.getElementById('filtro-loja');
        const selectGeneroLoja = document.getElementById('filtro-genero-loja');
        let tipoAtivo = 'todas';

        function aplicarFiltrosLoja() {
            const termo = campoFiltroLoja ? campoFiltroLoja.value.trim().toLowerCase() : '';
            const generoFiltro = selectGeneroLoja ? selectGeneroLoja.value.trim().toLowerCase() : '';
            document.querySelectorAll('#lista-loja .livro-card').forEach((card) => {
                const texto = (card.dataset.busca || '').toLowerCase();
                const tipo = card.dataset.tipo || '';
                const generosCard = (card.dataset.generos || '').toLowerCase().split('|').filter(Boolean);
                const bateTexto = termo === '' || texto.includes(termo);
                const bateTipo = tipoAtivo === 'todas' || tipo === tipoAtivo;
                const bateGenero = generoFiltro === '' || generosCard.includes(generoFiltro);
                card.hidden = !(bateTexto && bateTipo && bateGenero);
            });
        }

        if (campoFiltroLoja) {
            campoFiltroLoja.addEventListener('input', aplicarFiltrosLoja);
        }
        if (selectGeneroLoja) {
            selectGeneroLoja.addEventListener('change', aplicarFiltrosLoja);
        }

        document.querySelectorAll('[data-filtro-tipo]').forEach((btn) => {
            btn.addEventListener('click', () => {
                tipoAtivo = btn.dataset.filtroTipo;
                document.querySelectorAll('[data-filtro-tipo]').forEach((b) => b.classList.toggle('ativo', b === btn));
                aplicarFiltrosLoja();
            });
        });

        // Compra simulada: chama api/compra.php e, em caso de sucesso, libera o link de leitura.
        document.querySelectorAll('.comprar-obra').forEach((button) => {
            button.addEventListener('click', async () => {
                const aviso = document.getElementById('mensagem-compra');
                aviso.textContent = '';
                aviso.className = '';
                button.disabled = true;
                try {
                    const response = await fetch('api/compra.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ id_obra: Number(button.dataset.id) })
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.error || 'Não foi possível concluir a compra simulada.');
                    button.textContent = 'Compra simulada concluída';
                    const link = document.createElement('a');
                    link.className = 'botao-adicionar-livro';
                    link.href = data.readerUrl;
                    link.textContent = 'Ler obra';
                    button.after(link);
                    aviso.className = 'estado-vazio estado-sucesso';
                    aviso.textContent = 'Obra liberada na sua biblioteca digital.';
                } catch (error) {
                    aviso.className = 'estado-vazio estado-erro';
                    aviso.textContent = error.message;
                    button.disabled = false;
                }
            });
        });
    </script>
</body>

</html>
