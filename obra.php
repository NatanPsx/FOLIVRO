<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

$isAdmin = (($_SESSION['nivel_usuario'] ?? '') === 'admin');

$q = $pdo->prepare(
    'SELECT a.id_autor_dos_livros, a.id_autor, a.status_autor_dos_livros, a.modalidade_distribuicao,
            a.declaracao_autoria, a.declaracao_comercializacao, a.motivo_bloqueio_autor,
            a.revisao_publicacao, a.justificativa_revisao,
            h.id_historias, h.titulo_historias, h.sinopse_historias, h.capa_historias, h.generos_historias, h.visualizacoes_historias,
            h.licenca_historias, h.aviso_conteudo_historias, u.nome_usuario,
            u.link_apoio_usuario, u.chave_pix_usuario, u.mensagem_apoio_usuario
       FROM autor_dos_livros a
       JOIN historias h ON h.id_historias = a.id_historias
       JOIN usuario u ON u.id_usuario = a.id_autor
      WHERE a.id_autor_dos_livros = ?'
);
$q->execute([$id]);
$obra = $q->fetch();
if (!$obra) {
    http_response_code(404);
    exit('Obra não encontrada ou removida.');
}

$idHistoria = (int) $obra['id_historias'];
$idAutor    = (int) $obra['id_autor'];
$statusObra = (string) $obra['status_autor_dos_livros'];
$statusRevisao = (string) ($obra['revisao_publicacao'] ?? 'nao_solicitada');

// Obras removidas pela moderação ficam inacessíveis ao público, podendo ser inspecionadas apenas por administradores.
if ($statusObra === 'removido' && !$isAdmin) {
    http_response_code(404);
    exit('Obra não encontrada ou removida pela moderação.');
}
// Rascunhos ou obras não aprovadas na revisão humana só podem ser visualizadas pelo próprio autor ou pela moderação.
if (($statusObra === 'rascunho' || $statusRevisao !== 'aprovada') && $idAutor !== $uid && !$isAdmin) {
    http_response_code(403);
    exit('Esta obra está em rascunho ou aguardando revisão humana da moderação antes da disponibilização pública.');
}

$classObra = folivro_normalizar_classificacao((string) ($obra['aviso_conteudo_historias'] ?? 'livre'));
if (folivro_classificacao_eh_maior18($classObra) && !folivro_usuario_maior_de_idade($pdo, $uid) && $idAutor !== $uid && !$isAdmin) {
    http_response_code(403);
    ?>
    <!doctype html>
    <html lang="pt-br">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Conteúdo Bloqueado (+18) | FOLIVRO</title>
        <link rel="stylesheet" href="layout.css?v=20261124_01">
        <link rel="stylesheet" href="home.css?v=20261123_01">
    </head>
    <body>
        <?php $menuAtivo = 'loja'; require __DIR__ . '/includes/menu-lateral.php'; ?>
        <?php require __DIR__ . '/includes/menu-autor.php'; ?>
        <main class="conteudo" id="conteudo-principal">
            <article class="livro-card livro-card-bloqueado-idade" style="max-width: 560px; margin: 28px auto;">
                <div class="livro-capa capa-bloqueada-18" style="height: 240px;">
                    <?php if (!empty($obra['capa_historias'])): ?>
                        <img src="<?= folivro_escape($obra['capa_historias']) ?>" alt="Capa bloqueada por classificação indicativa +18">
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
                        <strong>Por que este livro está bloqueado?</strong><br>
                        Esta obra possui classificação indicativa <strong>+18</strong> e sua conta está registrada com menos de 18 anos (conforme a data de nascimento informada no cadastro).
                    </p>
                    <p><a class="botao-adicionar-livro" href="loja.php">Voltar para a loja</a></p>
                </div>
            </article>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Verifica se há suspensão preventiva ativa por denúncia de direitos autorais em análise.
$suspCheck = $pdo->prepare(
    "SELECT COUNT(*) FROM denuncias
      WHERE tipo_denuncias = 'obra_autoral' AND item_denuncia = ?
        AND status_denuncias IN ('aberta', 'em_analise') AND suspensao_preventiva = 1"
);
$suspCheck->execute([$id]);
$emSuspensaoDireitos = ((int) $suspCheck->fetchColumn() > 0) || $statusObra === 'removido';

// Acesso: o próprio autor, administrador, obra gratuita aprovada e publicada ou comprador na biblioteca digital / loja.
$access = $idAutor === $uid || $isAdmin || (($obra['modalidade_distribuicao'] ?? 'venda_simulada') === 'gratuita' && $statusObra === 'a_venda' && $statusRevisao === 'aprovada' && (int) $obra['declaracao_autoria'] === 1);
if (!$access && in_array($statusObra, ['a_venda', 'suspenso'], true)) {
    $check = $pdo->prepare(
        "SELECT 1 FROM livraria_digital WHERE id_usuario = ? AND id_autor_dos_livros = ?
         UNION ALL
         SELECT 1 FROM loja_pedidos ped
         JOIN loja_produtos lp ON lp.id_produto = ped.id_produto
         WHERE ped.id_comprador = ? AND lp.id_autor_dos_livros = ? AND ped.status_pedido = 'pago'
         UNION ALL
         SELECT 1 FROM livraria l
         JOIN loja_produtos lp2 ON lp2.id_livro = l.id_livro
         WHERE l.id_usuario = ? AND lp2.id_autor_dos_livros = ? AND lp2.preco_centavos = 0
         LIMIT 1"
    );
    $check->execute([$uid, $id, $uid, $id, $uid, $id]);
    $access = (bool) $check->fetchColumn();
}
if (!$access) {
    http_response_code(403);
    exit('Esta obra é liberada na biblioteca digital após a aquisição simulada.');
}

$msgErro = '';
$msgSucesso = isset($_GET['mod_resolvida']) ? 'Decisão de moderação registrada e estado da obra atualizado.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $msgErro = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $acao = (string) ($_POST['acao'] ?? '');

        if ($acao === 'resolver_contestacao_moderacao' && $isAdmin) {
            $idDenMod = filter_input(INPUT_POST, 'id_denuncias', FILTER_VALIDATE_INT) ?: 0;
            $decisaoMod = (string) ($_POST['decisao'] ?? '');
            $justMod = trim((string) ($_POST['justificativa_moderacao'] ?? ''));

            $denStmt = $pdo->prepare("SELECT * FROM denuncias WHERE id_denuncias = ? AND tipo_denuncias = 'obra_autoral' AND item_denuncia = ? LIMIT 1");
            $denStmt->execute([$idDenMod, $id]);
            $denRow = $denStmt->fetch();

            if (!$denRow) {
                $msgErro = 'Denúncia/contestação não encontrada para esta obra.';
            } elseif (!in_array($decisaoMod, ['improcedente', 'remover_item'], true)) {
                $msgErro = 'Decisão de moderação inválida.';
            } elseif ($justMod === '' || mb_strlen($justMod, 'UTF-8') > 2000) {
                $msgErro = 'Informe uma justificativa fundamentada (até 2.000 caracteres).';
            } else {
                $pdo->beginTransaction();
                try {
                    if ($decisaoMod === 'improcedente') {
                        $pdo->prepare(
                            "UPDATE denuncias
                                SET status_denuncias = 'improcedente',
                                    suspensao_preventiva = 0,
                                    acao_moderacao_aplicada = 'improcedente',
                                    justificativa_moderacao = ?,
                                    data_resposta_denuncia = CURRENT_TIMESTAMP,
                                    retencao_evidencia_ate = COALESCE(retencao_evidencia_ate, DATE_ADD(NOW(), INTERVAL 180 DAY))
                              WHERE id_denuncias = ?"
                        )->execute([$justMod, $idDenMod]);

                        $outrasAtivas = $pdo->prepare(
                            "SELECT COUNT(*) FROM denuncias
                              WHERE tipo_denuncias = 'obra_autoral' AND item_denuncia = ?
                                AND id_denuncias <> ? AND status_denuncias IN ('aberta', 'em_analise') AND suspensao_preventiva = 1"
                        );
                        $outrasAtivas->execute([$id, $idDenMod]);
                        if ((int) $outrasAtivas->fetchColumn() === 0 && in_array($statusObra, ['suspenso', 'removido'], true)) {
                            if ((int) $obra['declaracao_autoria'] === 1 && $statusRevisao === 'aprovada') {
                                $pdo->prepare("UPDATE autor_dos_livros SET status_autor_dos_livros = 'a_venda', motivo_bloqueio_autor = NULL WHERE id_autor_dos_livros = ?")
                                    ->execute([$id]);
                                $pdo->prepare("UPDATE historias SET status_historias = 'publicada' WHERE id_historias = ?")
                                    ->execute([$idHistoria]);
                            } else {
                                $pdo->prepare("UPDATE autor_dos_livros SET status_autor_dos_livros = 'rascunho', motivo_bloqueio_autor = NULL WHERE id_autor_dos_livros = ?")
                                    ->execute([$id]);
                            }
                        }
                    } else {
                        $pdo->prepare(
                            "UPDATE denuncias
                                SET status_denuncias = 'procedente',
                                    suspensao_preventiva = 0,
                                    acao_moderacao_aplicada = 'remover_item',
                                    justificativa_moderacao = ?,
                                    data_resposta_denuncia = CURRENT_TIMESTAMP,
                                    retencao_evidencia_ate = COALESCE(retencao_evidencia_ate, DATE_ADD(NOW(), INTERVAL 180 DAY))
                              WHERE id_denuncias = ?"
                        )->execute([$justMod, $idDenMod]);

                        $pdo->prepare(
                            "UPDATE autor_dos_livros
                                SET status_autor_dos_livros = 'removido',
                                    motivo_bloqueio_autor = ?
                              WHERE id_autor_dos_livros = ?"
                        )->execute(['Removida pela moderação: ' . mb_substr($justMod, 0, 210, 'UTF-8'), $id]);
                        $pdo->prepare("UPDATE historias SET status_historias = 'despublicada' WHERE id_historias = ?")
                            ->execute([$idHistoria]);
                        $pdo->prepare("UPDATE loja_produtos SET status_produto = 'arquivado' WHERE id_autor_dos_livros = ?")
                            ->execute([$id]);
                    }
                    $pdo->commit();
                    require_once __DIR__ . '/config/loja.php';
                    folivro_loja_sincronizar_obra_estudio($pdo, $id);
                    header('Location: obra.php?id=' . $id . '&mod_resolvida=1');
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $msgErro = 'Não foi possível registrar a decisão de moderação.';
                }
            }
        } elseif ($acao === 'curtir') {
            $jaCurtiu = $pdo->prepare('SELECT id_curtidas FROM curtidas WHERE id_usuario = ? AND id_historias = ? LIMIT 1');
            $jaCurtiu->execute([$uid, $idHistoria]);
            if ($jaCurtiu->fetchColumn()) {
                $pdo->prepare('DELETE FROM curtidas WHERE id_usuario = ? AND id_historias = ?')->execute([$uid, $idHistoria]);
                $msgSucesso = 'Curtida removida.';
            } else {
                $pdo->prepare('INSERT INTO curtidas (id_usuario, id_historias) VALUES (?, ?)')->execute([$uid, $idHistoria]);
                $msgSucesso = 'Você curtiu esta obra!';
            }
        } elseif ($acao === 'seguir' && $idAutor !== $uid) {
            $jaSegue = $pdo->prepare('SELECT id_seguidores FROM seguidores WHERE id_seguidor = ? AND id_seguido = ? LIMIT 1');
            $jaSegue->execute([$uid, $idAutor]);
            if ($jaSegue->fetchColumn()) {
                $pdo->prepare('DELETE FROM seguidores WHERE id_seguidor = ? AND id_seguido = ?')->execute([$uid, $idAutor]);
                $msgSucesso = 'Você deixou de seguir o(a) autor(a).';
            } else {
                $pdo->prepare('INSERT INTO seguidores (id_seguidor, id_seguido) VALUES (?, ?)')->execute([$uid, $idAutor]);
                $msgSucesso = 'Agora você segue ' . $obra['nome_usuario'] . '.';
            }
        } elseif ($acao === 'comentar') {
            $texto = trim((string) ($_POST['comentario'] ?? ''));
            if ($texto === '' || mb_strlen($texto, 'UTF-8') > 2000) {
                $msgErro = 'Escreva um comentário válido (até 2.000 caracteres).';
            } else {
                $insCom = $pdo->prepare('INSERT INTO comentarios (id_usuario, id_historias, comentarios_comentario) VALUES (?, ?, ?)');
                $insCom->execute([$uid, $idHistoria, $texto]);
                $msgSucesso = 'Comentário publicado.';
            }
        } elseif ($acao === 'excluir_comentario') {
            $idCom = filter_input(INPUT_POST, 'id_comentario', FILTER_VALIDATE_INT) ?: 0;
            if ($isAdmin) {
                $pdo->prepare('DELETE FROM comentarios WHERE id_comentarios = ? AND id_historias = ?')->execute([$idCom, $idHistoria]);
            } else {
                $pdo->prepare('DELETE FROM comentarios WHERE id_comentarios = ? AND id_usuario = ? AND id_historias = ?')->execute([$idCom, $uid, $idHistoria]);
            }
            $msgSucesso = 'Comentário removido.';
        } elseif ($acao === 'denunciar') {
            $motivo = trim((string) ($_POST['motivo_denuncia'] ?? ''));
            $obraRef = trim((string) ($_POST['obra_referencia_denuncia'] ?? ''));
            $descricao = trim((string) ($_POST['descricao_denuncia'] ?? ''));
            $tipoDen = ($_POST['tipo_denuncias'] ?? 'obra_autoral') === 'comentario' ? 'comentario' : 'obra_autoral';
            $itemDen = $tipoDen === 'comentario'
                ? (filter_input(INPUT_POST, 'item_denuncia', FILTER_VALIDATE_INT) ?: 0)
                : $id;

            if ($tipoDen === 'comentario' && $itemDen > 0) {
                $chkCom = $pdo->prepare('SELECT 1 FROM comentarios WHERE id_comentarios = ? AND id_historias = ? LIMIT 1');
                $chkCom->execute([$itemDen, $idHistoria]);
                if (!$chkCom->fetchColumn()) {
                    $itemDen = 0;
                }
            }

            $motivosDireitos = [
                'Violação de direitos autorais (plágio)',
                'Uso não autorizado de capa, imagem ou tradução',
                'Fanfic ou adaptação comercializada sem autorização',
            ];
            $ehDireitosAutorais = in_array($motivo, $motivosDireitos, true);

            if ($motivo === '' || mb_strlen($motivo, 'UTF-8') > 150 || mb_strlen($obraRef, 'UTF-8') > 255 || mb_strlen($descricao, 'UTF-8') > 2000 || $itemDen < 1) {
                $msgErro = 'Informe o motivo da denúncia e os dados válidos para análise.';
            } elseif ($ehDireitosAutorais && $obraRef === '' && mb_strlen($descricao, 'UTF-8') < 10) {
                $msgErro = 'Para denúncia de direitos autorais, identifique a obra/autor original ou descreva o trecho suspeito (mínimo de 10 caracteres) para fundamentar a análise.';
            } else {
                $prioridade = $ehDireitosAutorais ? 'alta' : 'media';
                // Denúncia plausível de direitos autorais aciona suspensão preventiva imediata da venda e distribuição.
                $suspensaoPrev = ($ehDireitosAutorais && $tipoDen === 'obra_autoral' && ($obraRef !== '' || mb_strlen($descricao, 'UTF-8') >= 10)) ? 1 : 0;

                $pdo->beginTransaction();
                $insDen = $pdo->prepare(
                    "INSERT INTO denuncias (id_usuario, tipo_denuncias, item_denuncia, motivo_denuncia,
                                            obra_referencia_denuncia, descricao_denuncia, prioridade_denuncia,
                                            suspensao_preventiva, status_denuncias)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aberta')"
                );
                $insDen->execute([
                    $uid, $tipoDen, $itemDen, $motivo,
                    $obraRef !== '' ? $obraRef : null,
                    $descricao !== '' ? $descricao : null,
                    $prioridade,
                    $suspensaoPrev,
                ]);

                if ($suspensaoPrev === 1) {
                    $pdo->prepare(
                        "UPDATE autor_dos_livros
                            SET status_autor_dos_livros = 'suspenso',
                                motivo_bloqueio_autor = 'Suspensão preventiva automática: denúncia de direitos autorais aguardando análise humana.'
                          WHERE id_autor_dos_livros = ?"
                    )->execute([$id]);
                    $pdo->prepare("UPDATE historias SET status_historias = 'despublicada' WHERE id_historias = ?")
                        ->execute([$idHistoria]);
                    $emSuspensaoDireitos = true;
                }
                $pdo->commit();

                $msgSucesso = $suspensaoPrev === 1
                    ? 'Denúncia de direitos autorais registrada com prioridade alta. A venda e a distribuição pública desta obra foram suspensas preventivamente até a análise humana.'
                    : 'Denúncia registrada para análise da moderação.';
            }
        }
    }
} else {
    // Incrementa visualizações da história em acessos GET de leitura (quando não suspensa).
    if (!$emSuspensaoDireitos) {
        $pdo->prepare('UPDATE historias SET visualizacoes_historias = visualizacoes_historias + 1 WHERE id_historias = ?')
            ->execute([$idHistoria]);
    }
}

if ($idAutor === $uid || $isAdmin) {
    $chapters = $pdo->prepare("SELECT ordem_capitulos, titulo_capitulos, conteudo_capitulos, status_capitulos, modo_publicacao_capitulos, revisao_capitulos FROM capitulos WHERE id_historias = ? ORDER BY ordem_capitulos");
} else {
    $chapters = $pdo->prepare("SELECT ordem_capitulos, titulo_capitulos, conteudo_capitulos, status_capitulos, modo_publicacao_capitulos, revisao_capitulos FROM capitulos WHERE id_historias = ? AND status_capitulos = 'publicado' ORDER BY ordem_capitulos");
}
$chapters->execute([$idHistoria]);
$rows = $chapters->fetchAll();

// Contadores e estados de curtida / seguidor.
$totalCurtidasStmt = $pdo->prepare('SELECT COUNT(*) FROM curtidas WHERE id_historias = ?');
$totalCurtidasStmt->execute([$idHistoria]);
$totalCurtidas = (int) $totalCurtidasStmt->fetchColumn();

$curtiuStmt = $pdo->prepare('SELECT 1 FROM curtidas WHERE id_usuario = ? AND id_historias = ? LIMIT 1');
$curtiuStmt->execute([$uid, $idHistoria]);
$usuarioCurtiu = (bool) $curtiuStmt->fetchColumn();

$totalSeguidoresStmt = $pdo->prepare('SELECT COUNT(*) FROM seguidores WHERE id_seguido = ?');
$totalSeguidoresStmt->execute([$idAutor]);
$totalSeguidores = (int) $totalSeguidoresStmt->fetchColumn();

$segueStmt = $pdo->prepare('SELECT 1 FROM seguidores WHERE id_seguidor = ? AND id_seguido = ? LIMIT 1');
$segueStmt->execute([$uid, $idAutor]);
$usuarioSegue = (bool) $segueStmt->fetchColumn();

$comStmt = $pdo->prepare(
    'SELECT c.id_comentarios, c.id_usuario, c.comentarios_comentario, c.data_comentario, u.nome_usuario
       FROM comentarios c
       JOIN usuario u ON u.id_usuario = c.id_usuario
      WHERE c.id_historias = ?
   ORDER BY c.data_comentario DESC
      LIMIT 50'
);
$comStmt->execute([$idHistoria]);
$comentarios = $comStmt->fetchAll();

$stLivroVinc = $pdo->prepare('SELECT COALESCE(id_livro, 0) FROM loja_produtos WHERE id_autor_dos_livros = ? LIMIT 1');
$stLivroVinc->execute([$id]);
$idLivroVinculadoObra = (int) $stLivroVinc->fetchColumn();

$progressoObraAtual = 0;
if ($idLivroVinculadoObra > 0) {
    $stProgObra = $pdo->prepare('SELECT pagina_atual_progressao FROM progressao_leitura WHERE id_usuario = ? AND id_livro = ? LIMIT 1');
    $stProgObra->execute([$uid, $idLivroVinculadoObra]);
    $progressoObraAtual = (int) ($stProgObra->fetchColumn() ?: 0);
}

$csrf = folivro_escape(folivro_csrf_token());
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $csrf ?>">
    <title><?= folivro_escape($obra['titulo_historias']) ?> | Leitura Confortável · FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261126_01">
    <link rel="stylesheet" href="home.css?v=20261123_01">
</head>

<body>
    <div class="progresso-leitura-fixo-topo" aria-hidden="true">
        <span id="barra-progresso-scroll-topo"></span>
    </div>

    <?php $menuAtivo = 'biblioteca'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo conteudo-leitura-confortavel" id="conteudo-principal">
        <div class="leitura-imersiva-container" id="container-leitura-obra">

            <!-- Barra de navegação superior discreta -->
            <nav class="navegacao-topo-leitura elemento-ocultavel-foco" aria-label="Navegação de retorno">
                <div class="links-retorno-leitura">
                    <a href="minha-biblioteca.php" class="link-retorno-suave">← Minha Biblioteca</a>
                    <span class="separador-nav">·</span>
                    <a href="loja.php" class="link-retorno-suave">Loja Autoral</a>
                    <?php if ($idLivroVinculadoObra > 0): ?>
                        <span class="separador-nav">·</span>
                        <a href="livro.php?id=<?= $idLivroVinculadoObra ?>" class="link-retorno-suave">Ver ficha completa e críticas</a>
                    <?php endif; ?>
                </div>
                <?php if ($idAutor === $uid): ?>
                    <a href="gerenciar-obra.php?id=<?= (int) $id ?>" class="filtro-pilula" style="font-size:12.5px; min-height:32px; padding:4px 12px;">
                        Editar obra / Adicionar capítulo
                    </a>
                <?php endif; ?>
            </nav>

            <!-- Cabeçalho editorial alinhado e sem poluição visual -->
            <header class="cabecalho-leitura-conforto elemento-ocultavel-foco">
                <?php if (!empty($obra['capa_historias'])): ?>
                    <div class="miniatura-capa-leitura">
                        <img src="<?= folivro_escape($obra['capa_historias']) ?>" alt="Capa de <?= folivro_escape($obra['titulo_historias']) ?>">
                    </div>
                <?php endif; ?>

                <div class="dados-cabecalho-leitura">
                    <div class="linha-selos-leitura">
                        <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($classObra) ?>">
                            <?= folivro_escape(folivro_classificacao_sigla($classObra)) ?> · <?= folivro_escape(folivro_aviso_conteudo_label($classObra)) ?>
                        </span>
                        <span class="selo-status">
                            <?= folivro_escape(folivro_licenca_label($obra['licenca_historias'] ?? 'direitos_reservados')) ?>
                        </span>
                    </div>

                    <h1><?= folivro_escape($obra['titulo_historias']) ?></h1>

                    <p class="autoria-metricas-leitura">
                        Por <strong><?= folivro_escape($obra['nome_usuario']) ?></strong>
                        <span>·</span>
                        <span><?= $totalSeguidores ?> seguidor(es)</span>
                        <span>·</span>
                        <span><?= $totalCurtidas ?> curtida(s)</span>
                        <span>·</span>
                        <span><?= count($rows) ?> capítulo(s)</span>
                    </p>

                    <?php $generosObraLista = folivro_generos_lista((string) ($obra['generos_historias'] ?? '')); ?>
                    <?php if ($generosObraLista): ?>
                        <div class="tags-generos-card" aria-label="Gêneros da obra" style="margin-bottom: 12px;">
                            <?php foreach ($generosObraLista as $genObraItem): ?>
                                <span class="tag-genero-card"><?= folivro_escape($genObraItem) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="acoes-cabecalho-leitura">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="acao" value="curtir">
                            <button class="botao-adicionar-livro btn-acao-compacto" type="submit">
                                <img src="img/icones/<?= $usuarioCurtiu ? 'favorite.png' : 'favorite_border.png' ?>" alt="" class="icone-btn-png icone-btn-branco">
                                <?= $usuarioCurtiu ? 'Curtido (' . $totalCurtidas . ')' : 'Curtir (' . $totalCurtidas . ')' ?>
                            </button>
                        </form>

                        <?php if ($idAutor !== $uid): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="acao" value="seguir">
                                <button class="filtro-pilula btn-acao-compacto" type="submit">
                                    <?= $usuarioSegue ? '✓ Seguindo autor(a)' : '+ Seguir autor(a)' ?>
                                </button>
                            </form>
                            <a class="filtro-pilula btn-acao-compacto" href="chat.php?usuario=<?= (int) $idAutor ?>">
                                <?= folivro_icone('chat-folivro', 'folivro-icon-xs') ?> Conversar
                            </a>
                        <?php endif; ?>

                        <a href="#secao-comunidade-obra" class="filtro-pilula btn-acao-compacto">
                            Comentários (<?= count($comentarios) ?>)
                        </a>

                        <?php if (!empty($obra['link_apoio_usuario']) || !empty($obra['chave_pix_usuario'])): ?>
                            <button type="button" class="btn-apoie-criador btn-acao-compacto js-abrir-apoie-criador">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true">
                                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                </svg>
                                <span>Apoie o criador</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <?php if ($msgErro): ?>
                <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape($msgErro) ?></p>
            <?php endif; ?>
            <?php if ($msgSucesso): ?>
                <p class="estado-vazio estado-sucesso" role="status"><?= folivro_escape($msgSucesso) ?></p>
            <?php endif; ?>

            <?php if ($emSuspensaoDireitos && !$isAdmin): ?>
                <p class="estado-vazio estado-erro" role="alert">
                    <strong>Distribuição temporariamente interrompida:</strong> Esta obra encontra-se sob suspensão preventiva enquanto uma denúncia plausível de violação de direitos autorais é analisada pela moderação humana.
                    <?php if ($idAutor === $uid): ?>
                        Você pode consultar a notificação e enviar sua contestação em <a href="gerenciar-obra.php?id=<?= (int) $id ?>">Gerenciar obra</a>.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <?php if ($emSuspensaoDireitos && $isAdmin): ?>
                    <?php
                    $stDenInsp = $pdo->prepare(
                        "SELECT id_denuncias, motivo_denuncia, status_denuncias, suspensao_preventiva,
                                contestacao_autor, data_contestacao, justificativa_moderacao, data_resposta_denuncia
                           FROM denuncias
                          WHERE tipo_denuncias = 'obra_autoral' AND item_denuncia = ?
                          ORDER BY data_denuncia DESC LIMIT 5"
                    );
                    $stDenInsp->execute([$id]);
                    $denunciasInsp = $stDenInsp->fetchAll();
                    ?>
                    <div class="estado-vazio estado-erro" role="status" style="text-align:left;">
                        <p style="margin:0 0 8px;">
                            <strong>Modo de inspeção da moderação:</strong>
                            <?php if ($statusObra === 'removido'): ?>
                                Esta obra foi bloqueada/removida pela moderação e permanece inacessível ao público.
                            <?php else: ?>
                                Esta obra está com venda e distribuição pública suspensas preventivamente. O texto abaixo é exibido apenas para análise da moderação.
                            <?php endif; ?>
                            <a href="moderacao.php" style="margin-left:8px;">Voltar ao painel de moderação</a>
                        </p>
                        <?php foreach ($denunciasInsp as $di): ?>
                            <?php
                            $pendenteInsp = in_array($di['status_denuncias'], ['aberta', 'em_analise'], true)
                                || (!empty($di['contestacao_autor']) && (empty($di['data_resposta_denuncia']) || strtotime((string) $di['data_contestacao']) > strtotime((string) $di['data_resposta_denuncia'])));
                            if (!$pendenteInsp) {
                                continue;
                            }
                            ?>
                            <div style="margin-top:10px; padding-top:10px; border-top:1px solid rgba(0,0,0,0.12);">
                                <p style="margin:0 0 6px;">
                                    <strong>Denúncia #<?= (int) $di['id_denuncias'] ?>:</strong> <?= folivro_escape($di['motivo_denuncia']) ?>
                                    (Status: <?= folivro_escape(folivro_status_label($di['status_denuncias'])) ?>)
                                </p>
                                <?php if (!empty($di['contestacao_autor'])): ?>
                                    <p style="margin:0 0 8px;"><strong>Contestação do autor:</strong> <?= folivro_escape($di['contestacao_autor']) ?></p>
                                <?php endif; ?>
                                <form method="post" class="progresso-form" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="acao" value="resolver_contestacao_moderacao">
                                    <input type="hidden" name="id_denuncias" value="<?= (int) $di['id_denuncias'] ?>">
                                    <label>Justificativa fundamentada da decisão
                                        <textarea name="justificativa_moderacao" rows="2" maxlength="2000" required placeholder="Registre a fundamentação para aceitar ou rejeitar a contestação..."><?= folivro_escape((string) ($di['justificativa_moderacao'] ?? '')) ?></textarea>
                                    </label>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
                                        <button type="submit" name="decisao" value="improcedente" class="botao-adicionar-livro">Acolher contestação e restaurar obra</button>
                                        <button type="submit" name="decisao" value="remover_item" class="filtro-pilula" style="border:none; cursor:pointer;">Rejeitar contestação e manter bloqueio</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$rows): ?>
                    <p class="estado-vazio">Esta obra ainda não tem capítulos publicados.</p>
                <?php else: ?>
                    <!-- BARRA DE CONFORTO DE LEITURA E NAVEGAÇÃO DE CAPÍTULOS (STICKY) -->
                    <div class="barra-conforto-leitura-moderna" role="toolbar" aria-label="Controles de conforto e navegação de leitura">
                        <div class="grupo-controle-leitura">
                            <label for="seletor-capitulo-leitura" class="rotulo-controle-leitura">Capítulo:</label>
                            <select id="seletor-capitulo-leitura" class="select-capitulo-leitura" aria-label="Selecionar capítulo">
                                <?php foreach ($rows as $idxCap => $chapterNav): ?>
                                    <option value="<?= $idxCap ?>">
                                        Cap. <?= (int) $chapterNav['ordem_capitulos'] ?> — <?= folivro_escape($chapterNav['titulo_capitulos']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if (count($rows) > 1): ?>
                                    <option value="todos">Mostrar todos os capítulos (<?= count($rows) ?>)</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="grupo-controle-leitura">
                            <span class="rotulo-controle-leitura">Letra:</span>
                            <button type="button" class="btn-conforto-item" id="btn-fonte-menor" title="Diminuir tamanho da letra">A−</button>
                            <span id="indicador-tamanho-fonte" class="indicador-fonte-valor">19px</span>
                            <button type="button" class="btn-conforto-item" id="btn-fonte-maior" title="Aumentar tamanho da letra">A+</button>
                            <button type="button" class="btn-conforto-item" id="btn-fonte-familia" title="Alternar entre fonte com serifa e sem serifa">Serifa</button>
                        </div>

                        <div class="grupo-controle-leitura">
                            <span class="rotulo-controle-leitura">Papel:</span>
                            <button type="button" class="btn-papel-cor" data-papel="padrao" title="Papel do tema atual">Padrão</button>
                            <button type="button" class="btn-papel-cor" data-papel="sepia" title="Papel Sépia Aconchegante">Sépia</button>
                            <button type="button" class="btn-papel-cor" data-papel="noturno" title="Leitura Noturna Suave">Noturno</button>
                        </div>

                        <span id="selo-progresso-auto-obra" class="selo-progresso-auto" role="status" aria-live="polite">
                            Progresso automático
                        </span>

                        <button type="button" class="btn-conforto-item btn-modo-foco-leitura" id="btn-modo-foco-leitura" title="Ocultar menus e distrações (Atalho: tecla F)">
                            <?= folivro_icone('livro-aberto-seta', 'folivro-icon-xs') ?> Modo Foco
                        </button>
                    </div>

                    <!-- PÁGINAS DE CAPÍTULOS -->
                    <div class="palco-capitulos-leitura" id="palco-capitulos">
                        <?php foreach ($rows as $idxCap => $chapter): ?>
                            <?php
                            $textoPuroCap = trim(strip_tags((string) $chapter['conteudo_capitulos']));
                            $palavrasCap = $textoPuroCap === '' ? 0 : count(preg_split('/\s+/u', $textoPuroCap));
                            $minutosCap = max(1, (int) ceil($palavrasCap / 200));
                            ?>
                            <article class="capitulo folha-leitura-capitulo" id="capitulo-<?= (int) $chapter['ordem_capitulos'] ?>" data-indice-cap="<?= $idxCap ?>">
                                <header class="cabecalho-folha-capitulo">
                                    <span class="etiqueta-ordem-capitulo">Capítulo <?= (int) $chapter['ordem_capitulos'] ?> de <?= count($rows) ?> · ~<?= $minutosCap ?> min de leitura</span>
                                    <h2><?= folivro_escape($chapter['titulo_capitulos']) ?></h2>
                                    <?php if (($idAutor === $uid || $isAdmin) && ($chapter['status_capitulos'] ?? '') !== 'publicado'): ?>
                                        <p style="margin:6px 0 0;"><span class="selo-status">Visível apenas para autor/moderação · Sua publicação foi encaminhada para revisão humana.</span></p>
                                    <?php elseif (($idAutor === $uid || $isAdmin) && ($chapter['revisao_capitulos'] ?? '') === 'publicado_automatico'): ?>
                                        <p style="margin:6px 0 0;"><span class="selo-status selo-comprado">Este capítulo foi publicado e está sujeito à moderação posterior.</span></p>
                                    <?php endif; ?>
                                </header>
                                <div class="conteudo-capitulo-texto">
                                    <?= folivro_sanitizar_html_capitulo($chapter['conteudo_capitulos']) ?>
                                </div>
                                <footer class="rodape-folha-capitulo">
                                    <span class="marcador-fim-capitulo">✦ Fim do Capítulo <?= (int) $chapter['ordem_capitulos'] ?> ✦</span>
                                    <?php if (count($rows) > 1): ?>
                                        <div class="navegacao-entre-capitulos">
                                            <button type="button" class="filtro-pilula js-btn-cap-anterior" data-alvo="<?= $idxCap - 1 ?>" <?= $idxCap === 0 ? 'disabled' : '' ?>>
                                                ← Capítulo anterior
                                            </button>
                                            <span class="indicador-posicao-cap"><?= $idxCap + 1 ?> / <?= count($rows) ?></span>
                                            <button type="button" class="botao-adicionar-livro js-btn-cap-proximo" data-alvo="<?= $idxCap + 1 ?>" <?= $idxCap === count($rows) - 1 ? 'disabled' : '' ?>>
                                                Próximo capítulo →
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </footer>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- SEÇÃO DE INTERAÇÃO, COMENTÁRIOS E TRANSPARÊNCIA LEGAL (ALINHADA E SEM DISTRAIR A LEITURA) -->
            <section class="secao-interacao secao-comunidade-leitura elemento-ocultavel-foco" id="secao-comunidade-obra" aria-labelledby="titulo-comentarios">
                <details class="quadro-licenca-retratil">
                    <summary>Informações de licença autoral e aviso legal</summary>
                    <div class="card-interacao" style="margin-top:10px;">
                        <p><small><strong>Aviso legal:</strong> As informações de licença (<?= folivro_escape(folivro_licenca_label($obra['licenca_historias'] ?? 'direitos_reservados')) ?>), autoria e classificação indicativa (<?= folivro_escape(folivro_aviso_conteudo_label($classObra)) ?>) foram declaradas pelo(a) autor(a) sob sua responsabilidade. Um selo no sistema não substitui verificação documental ou análise jurídica.</small></p>
                    </div>
                </details>

                <form method="post" class="progresso-form form-largura-total">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="acao" value="comentar">
                    <h2 id="titulo-comentarios" style="margin:0;">Comentários dos leitores (<?= count($comentarios) ?>)</h2>
                    <label for="comentario">Deixe sua impressão sobre a história
                        <textarea id="comentario" name="comentario" rows="3" maxlength="2000" required placeholder="Compartilhe o que achou deste capítulo ou da obra..."></textarea>
                    </label>
                    <div>
                        <button class="botao-adicionar-livro" type="submit">Publicar comentário</button>
                    </div>
                </form>

                <div class="lista-interacoes">
                    <?php if (!$comentarios): ?>
                        <p class="estado-vazio">Seja o primeiro a comentar nesta obra.</p>
                    <?php else: ?>
                        <?php foreach ($comentarios as $com): ?>
                            <article class="card-interacao">
                                <header>
                                    <strong><?= folivro_escape($com['nome_usuario']) ?></strong>
                                    <small><?= date('d/m/Y H:i', strtotime($com['data_comentario'])) ?></small>
                                </header>
                                <p><?= folivro_escape($com['comentarios_comentario']) ?></p>
                                <?php if ((int) $com['id_usuario'] === $uid || $isAdmin): ?>
                                    <form method="post" class="form-inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="acao" value="excluir_comentario">
                                        <input type="hidden" name="id_comentario" value="<?= (int) $com['id_comentarios'] ?>">
                                        <button type="submit" class="link-acao">Excluir comentário</button>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <details class="denuncia-box">
                    <summary>Denunciar conteúdo irregular ou suspeita de violação de direitos autorais</summary>
                    <form method="post" class="progresso-form form-largura-total">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="acao" value="denunciar">
                        <input type="hidden" name="tipo_denuncias" value="obra_autoral">
                        <p><small>Sua identidade como denunciante é mantida em sigilo perante o autor denunciado. Denúncias fundamentadas de direitos autorais acionam suspensão preventiva da venda e distribuição para análise humana.</small></p>
                        <label for="motivo_denuncia">Motivo da denúncia
                            <select id="motivo_denuncia" name="motivo_denuncia" required>
                                <option value="Violação de direitos autorais (plágio)">Violação de direitos autorais (cópia/plágio de texto protegido)</option>
                                <option value="Uso não autorizado de capa, imagem ou tradução">Uso não autorizado de capa, imagem ou tradução de terceiros</option>
                                <option value="Fanfic ou adaptação comercializada sem autorização">Fanfic ou obra derivada comercializada sem autorização</option>
                                <option value="Conteúdo impróprio ou ofensivo">Conteúdo impróprio ou ofensivo</option>
                                <option value="Spam ou fraude">Spam ou fraude</option>
                                <option value="Outro motivo">Outro motivo</option>
                            </select>
                        </label>
                        <label for="obra_referencia_denuncia">Identificação da obra/autor original (obrigatório em suspeita de direitos autorais)
                            <input id="obra_referencia_denuncia" name="obra_referencia_denuncia" maxlength="255" placeholder="Ex.: Título original, autor(a), ISBN ou link oficial de referência">
                        </label>
                        <label for="descricao_denuncia">Evidências e detalhes para análise humana
                            <textarea id="descricao_denuncia" name="descricao_denuncia" rows="3" maxlength="2000" placeholder="Indique capítulos, trechos ou elementos que fundamentam a denúncia"></textarea>
                        </label>
                        <div>
                            <button class="botao-adicionar-livro botao-perigo" type="submit">Enviar denúncia</button>
                        </div>
                    </form>
                </details>
            </section>
        </div>
    </main>

    <script>
        (() => {
            const capitulos = Array.from(document.querySelectorAll('.folha-leitura-capitulo'));
            const btnMenor = document.getElementById('btn-fonte-menor');
            const btnMaior = document.getElementById('btn-fonte-maior');
            const btnFamilia = document.getElementById('btn-fonte-familia');
            const indicadorFonte = document.getElementById('indicador-tamanho-fonte');
            const seletorCap = document.getElementById('seletor-capitulo-leitura');
            const botoesPapel = document.querySelectorAll('.btn-papel-cor');
            const btnModoFoco = document.getElementById('btn-modo-foco-leitura');
            const barraScroll = document.getElementById('barra-progresso-scroll-topo');

            if (!capitulos.length) return;

            let tamanho = Number(localStorage.getItem('folivro_fonte_tamanho')) || 19;
            let serifa = (localStorage.getItem('folivro_fonte_familia') || 'serifa') === 'serifa';
            let papel = localStorage.getItem('folivro_papel_leitura') || 'padrao';
            const idLivroVinc = <?= (int) $idLivroVinculadoObra ?>;
            const idObraAtual = <?= (int) $id ?>;
            const capSalvoDb = <?= (int) $progressoObraAtual ?>;
            const chaveCapLocal = `folivro_obra_cap_${idObraAtual}`;
            const seloProgressoAuto = document.getElementById('selo-progresso-auto-obra');
            let timerProgressoObra = null;
            let ultimoSalvoObra = '';
            let capAtivo = '0';

            // Retoma automaticamente o último capítulo lido (banco ou navegador)
            if (capSalvoDb >= 1 && capSalvoDb <= capitulos.length) {
                capAtivo = String(capSalvoDb - 1);
            } else {
                const capLocal = Number(localStorage.getItem(chaveCapLocal));
                if (!Number.isNaN(capLocal) && capLocal >= 0 && capLocal < capitulos.length) {
                    capAtivo = String(capLocal);
                }
            }

            // Verifica se a URL aponta para um capítulo específico (#capitulo-2)
            if (window.location.hash.startsWith('#capitulo-')) {
                const alvoId = window.location.hash.slice(1);
                const idxHash = capitulos.findIndex((c) => c.id === alvoId);
                if (idxHash >= 0) {
                    capAtivo = String(idxHash);
                }
            }

            function salvarProgressoObraAutomatico(indiceCap) {
                const totalCaps = capitulos.length;
                if (totalCaps <= 0) return;
                const capNum = Math.max(1, Math.min(totalCaps, indiceCap + 1));
                const pctLocal = Math.min(100, Math.max(0, Math.round((capNum / totalCaps) * 100)));

                try {
                    localStorage.setItem(chaveCapLocal, String(capNum - 1));
                } catch (_) {}

                if (seloProgressoAuto) {
                    seloProgressoAuto.textContent = `Cap. ${capNum}/${totalCaps} (${pctLocal}%)`;
                    seloProgressoAuto.classList.remove('salvo');
                }

                if (idLivroVinc <= 0) {
                    if (seloProgressoAuto) {
                        seloProgressoAuto.textContent = `Cap. ${capNum}/${totalCaps} (${pctLocal}%) · Salvo`;
                        seloProgressoAuto.classList.add('salvo');
                    }
                    return;
                }

                const chave = `${capNum}/${totalCaps}`;
                if (chave === ultimoSalvoObra) return;
                if (timerProgressoObra) clearTimeout(timerProgressoObra);

                timerProgressoObra = setTimeout(async () => {
                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    if (!csrfMeta) return;
                    try {
                        const r = await fetch('api/progresso.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': csrfMeta.content
                            },
                            body: JSON.stringify({
                                id_livro: idLivroVinc,
                                pagina_atual: capNum,
                                total_paginas: totalCaps,
                                auto_livraria: true
                            })
                        });
                        const d = await r.json();
                        if (!r.ok || !d.ok) return;
                        ultimoSalvoObra = chave;
                        const pctDb = Math.max(0, Math.min(100, Math.round(Number(d.porcentagem) || pctLocal)));
                        if (seloProgressoAuto) {
                            seloProgressoAuto.textContent = `Progresso: ${pctDb}% · Salvo automaticamente`;
                            seloProgressoAuto.classList.add('salvo');
                        }
                    } catch (_) {}
                }, 450);
            }

            function aplicarConfortoVisual() {
                tamanho = Math.min(28, Math.max(15, tamanho));
                capitulos.forEach((cap) => {
                    const textoBox = cap.querySelector('.conteudo-capitulo-texto') || cap;
                    textoBox.style.fontSize = `${tamanho}px`;
                    textoBox.style.fontFamily = serifa
                        ? 'Georgia, "Newsreader", "Times New Roman", serif'
                        : 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';

                    cap.classList.remove('papel-leitura-padrao', 'papel-leitura-sepia', 'papel-leitura-noturno');
                    cap.classList.add(`papel-leitura-${papel}`);
                });

                if (indicadorFonte) indicadorFonte.textContent = `${tamanho}px`;
                if (btnFamilia) {
                    btnFamilia.classList.toggle('ativo', serifa);
                    btnFamilia.textContent = serifa ? 'Serifa ✓' : 'Sem serifa';
                }
                botoesPapel.forEach((b) => {
                    b.classList.toggle('ativo', b.dataset.papel === papel);
                });

                localStorage.setItem('folivro_fonte_tamanho', String(tamanho));
                localStorage.setItem('folivro_fonte_familia', serifa ? 'serifa' : 'padrao');
                localStorage.setItem('folivro_papel_leitura', papel);
            }

            function exibirCapituloSelecionado(valor, rolarTopo = false) {
                capAtivo = String(valor);
                if (seletorCap) seletorCap.value = capAtivo;

                capitulos.forEach((cap, idx) => {
                    cap.hidden = (capAtivo !== 'todos' && String(idx) !== capAtivo);
                });

                if (rolarTopo) {
                    const palco = document.getElementById('palco-capitulos');
                    if (palco) {
                        palco.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
                if (capAtivo !== 'todos') {
                    const idxNum = Number(capAtivo);
                    if (!Number.isNaN(idxNum)) {
                        salvarProgressoObraAutomatico(idxNum);
                    }
                } else {
                    salvarProgressoObraAutomatico(capitulos.length - 1);
                }
                atualizarProgressoScroll();
            }

            function atualizarProgressoScroll() {
                if (!barraScroll) return;
                const docEl = document.documentElement;
                const scrollTop = window.scrollY || docEl.scrollTop;
                const alturaTotal = Math.max(1, docEl.scrollHeight - window.innerHeight);
                const pct = Math.min(100, Math.max(0, (scrollTop / alturaTotal) * 100));
                barraScroll.style.width = `${pct.toFixed(1)}%`;
            }

            if (btnMenor) btnMenor.addEventListener('click', () => { tamanho -= 2; aplicarConfortoVisual(); });
            if (btnMaior) btnMaior.addEventListener('click', () => { tamanho += 2; aplicarConfortoVisual(); });
            if (btnFamilia) btnFamilia.addEventListener('click', () => { serifa = !serifa; aplicarConfortoVisual(); });

            botoesPapel.forEach((btn) => {
                btn.addEventListener('click', () => {
                    papel = btn.dataset.papel || 'padrao';
                    aplicarConfortoVisual();
                });
            });

            if (seletorCap) {
                seletorCap.addEventListener('change', () => {
                    exibirCapituloSelecionado(seletorCap.value, true);
                });
            }

            document.querySelectorAll('.js-btn-cap-anterior, .js-btn-cap-proximo').forEach((btnNav) => {
                btnNav.addEventListener('click', () => {
                    const alvo = Number(btnNav.dataset.alvo);
                    if (!Number.isNaN(alvo) && alvo >= 0 && alvo < capitulos.length) {
                        exibirCapituloSelecionado(String(alvo), true);
                    }
                });
            });

            if (btnModoFoco) {
                const iconeModoFoco = <?= json_encode(folivro_icone('livro-aberto-seta', 'folivro-icon-xs')) ?>;
                btnModoFoco.addEventListener('click', () => {
                    const focoAtivo = document.body.classList.toggle('modo-leitura-foco');
                    btnModoFoco.classList.toggle('ativo', focoAtivo);
                    btnModoFoco.innerHTML = focoAtivo
                        ? `${iconeModoFoco} Sair do Foco`
                        : `${iconeModoFoco} Modo Foco`;
                });
            }

            window.addEventListener('scroll', atualizarProgressoScroll, { passive: true });
            aplicarConfortoVisual();
            exibirCapituloSelecionado(capAtivo, false);
        })();
    </script>
    <?php
    $apoioAutorNome = (string) ($obra['nome_usuario'] ?? '');
    $apoioAutorLink = (string) ($obra['link_apoio_usuario'] ?? '');
    $apoioAutorPix  = (string) ($obra['chave_pix_usuario'] ?? '');
    $apoioAutorMsg  = (string) ($obra['mensagem_apoio_usuario'] ?? '');
    require __DIR__ . '/includes/apoie-criador-modal.php';
    ?>
</body>

</html>
