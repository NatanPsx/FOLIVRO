<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/dominio_publico.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];
$idPerfilParam = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$idAlvo = ($idPerfilParam > 0) ? $idPerfilParam : $uid;
$ehMeuPerfil = ($idAlvo === $uid);

$msgPerfilErro = '';
$msgPerfilSucesso = '';

// Edição de perfil (RF03) e validação segura de upload de foto (RNF07) — somente o próprio dono.
if (!$ehMeuPerfil && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'alternar_seguir') {
    if (!folivro_verify_csrf()) {
        $msgPerfilErro = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $chkSeg = $pdo->prepare('SELECT 1 FROM seguidores WHERE id_seguidor = ? AND id_seguido = ? LIMIT 1');
        $chkSeg->execute([$uid, $idAlvo]);
        if ($chkSeg->fetchColumn()) {
            $pdo->prepare('DELETE FROM seguidores WHERE id_seguidor = ? AND id_seguido = ?')->execute([$uid, $idAlvo]);
            $msgPerfilSucesso = 'Você deixou de seguir este usuário.';
        } else {
            $pdo->prepare('INSERT IGNORE INTO seguidores (id_seguidor, id_seguido) VALUES (?, ?)')->execute([$uid, $idAlvo]);
            $msgPerfilSucesso = 'Agora você está seguindo este usuário!';
        }
    }
} elseif ($ehMeuPerfil && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'editar_perfil') {
    if (!folivro_verify_csrf()) {
        $msgPerfilErro = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $novoNome = trim((string) ($_POST['nome_usuario'] ?? ''));
        $novaBio  = trim((string) ($_POST['biografia_usuario'] ?? ''));
        $novaDataNasc = trim((string) ($_POST['data_nascimento_usuario'] ?? ''));
        $atualizarApoio = isset($_POST['salvar_apoio_criador']);
        $linkApoioVal = '';
        $chavePixVal = '';
        $msgApoioVal = '';
        if ($atualizarApoio) {
            $linkApoioRes = folivro_validar_link_apoio((string) ($_POST['link_apoio_usuario'] ?? ''));
            $chavePixRes  = folivro_validar_chave_pix((string) ($_POST['chave_pix_usuario'] ?? ''));
            $msgApoioVal  = trim((string) ($_POST['mensagem_apoio_usuario'] ?? ''));
            if ($linkApoioRes === null) {
                $msgPerfilErro = 'Informe um link de apoio válido iniciando com https:// (ex.: https://apoia.se/seuperfil).';
            } elseif ($chavePixRes === null) {
                $msgPerfilErro = 'Chave Pix inválida. Use chave aleatória, e-mail, telefone ou CPF/CNPJ.';
            } elseif (mb_strlen($msgApoioVal, 'UTF-8') > 200) {
                $msgPerfilErro = 'A mensagem de apoio deve ter no máximo 200 caracteres.';
            } else {
                $linkApoioVal = $linkApoioRes;
                $chavePixVal = $chavePixRes;
            }
        }
        $tamNome  = mb_strlen($novoNome, 'UTF-8');
        $idadeValida = $novaDataNasc === '' ? true : (($idCalc = folivro_calcular_idade($novaDataNasc)) !== null && $idCalc >= 5 && $idCalc <= 120);

        if ($msgPerfilErro !== '') {
            // Erro já definido na validação dos campos de apoio
        } elseif ($tamNome < 2 || $tamNome > 120 || mb_strlen($novaBio, 'UTF-8') > 1000) {
            $msgPerfilErro = 'Informe um nome entre 2 e 120 caracteres e biografia de até 1.000 caracteres.';
        } elseif (!$idadeValida) {
            $msgPerfilErro = 'Informe uma data de nascimento válida.';
        } else {
            $caminhoFoto = null;

            if (!empty($_FILES['foto_usuario']['name'])) {
                $arq = $_FILES['foto_usuario'];
                $tiposPermitidos = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                ];

                if (($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $msgPerfilErro = 'Falha no envio da imagem. Tente um arquivo menor.';
                } elseif (($arq['size'] ?? 0) > 2 * 1024 * 1024) {
                    $msgPerfilErro = 'A foto deve ter no máximo 2 MB.';
                } else {
                    $infoImg = @getimagesize($arq['tmp_name']);
                    $mimeReal = is_array($infoImg) ? ($infoImg['mime'] ?? '') : '';
                    if (!isset($tiposPermitidos[$mimeReal])) {
                        $msgPerfilErro = 'Formato de imagem inválido. Envie apenas JPG, PNG ou WebP.';
                    } else {
                        $dirUpload = __DIR__ . '/uploads/perfis';
                        if (!is_dir($dirUpload)) {
                            mkdir($dirUpload, 0755, true);
                            file_put_contents($dirUpload . '/.htaccess', "Options -Indexes\nphp_flag engine off\n");
                        }
                        $nomeSeguro = 'perfil_' . $uid . '_' . bin2hex(random_bytes(8)) . '.' . $tiposPermitidos[$mimeReal];
                        $destino = $dirUpload . '/' . $nomeSeguro;
                        if (move_uploaded_file($arq['tmp_name'], $destino)) {
                            $caminhoFoto = 'uploads/perfis/' . $nomeSeguro;
                        } else {
                            $msgPerfilErro = 'Não foi possível salvar a foto enviada.';
                        }
                    }
                }
            }

            if ($msgPerfilErro === '') {
                $dtNascFinal = $novaDataNasc !== '' ? $novaDataNasc : null;
                if ($caminhoFoto !== null) {
                    $upd = $pdo->prepare('UPDATE usuario SET nome_usuario = ?, data_nascimento_usuario = COALESCE(?, data_nascimento_usuario), biografia_usuario = ?, foto_usuario = ? WHERE id_usuario = ?');
                    $upd->execute([$novoNome, $dtNascFinal, $novaBio !== '' ? $novaBio : null, $caminhoFoto, $uid]);
                } else {
                    $upd = $pdo->prepare('UPDATE usuario SET nome_usuario = ?, data_nascimento_usuario = COALESCE(?, data_nascimento_usuario), biografia_usuario = ? WHERE id_usuario = ?');
                    $upd->execute([$novoNome, $dtNascFinal, $novaBio !== '' ? $novaBio : null, $uid]);
                }
                if ($atualizarApoio) {
                    $updApoio = $pdo->prepare('UPDATE usuario SET link_apoio_usuario = ?, chave_pix_usuario = ?, mensagem_apoio_usuario = ? WHERE id_usuario = ?');
                    $updApoio->execute([
                        $linkApoioVal !== '' ? $linkApoioVal : null,
                        $chavePixVal !== '' ? $chavePixVal : null,
                        $msgApoioVal !== '' ? $msgApoioVal : null,
                        $uid,
                    ]);
                }
                $_SESSION['nome_usuario'] = $novoNome;
                if ($dtNascFinal !== null) {
                    $_SESSION['data_nascimento_usuario'] = $dtNascFinal;
                }
                $msgPerfilSucesso = 'Perfil atualizado com sucesso.';
            }
        }
    }
} elseif ($ehMeuPerfil && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'alterar_senha') {
    if (!folivro_verify_csrf()) {
        $msgPerfilErro = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
        $novaSenha = (string) ($_POST['nova_senha'] ?? '');
        $confNovaSenha = (string) ($_POST['confirmacao_nova_senha'] ?? '');

        $pwdStmt = $pdo->prepare('SELECT senha_usuario FROM usuario WHERE id_usuario = ? LIMIT 1');
        $pwdStmt->execute([$uid]);
        $senhaHash = (string) $pwdStmt->fetchColumn();

        if (!password_verify($senhaAtual, $senhaHash)) {
            $msgPerfilErro = 'A senha atual informada está incorreta.';
        } elseif (strlen($novaSenha) < 8 || strlen($novaSenha) > 255) {
            $msgPerfilErro = 'A nova senha deve ter no mínimo 8 caracteres.';
        } elseif ($novaSenha !== $confNovaSenha) {
            $msgPerfilErro = 'A confirmação da nova senha não confere.';
        } else {
            $updPwd = $pdo->prepare('UPDATE usuario SET senha_usuario = ? WHERE id_usuario = ?');
            $updPwd->execute([password_hash($novaSenha, PASSWORD_DEFAULT), $uid]);
            $msgPerfilSucesso = 'Senha alterada com sucesso.';
        }
    }
}

$stmt = $pdo->prepare('SELECT id_usuario, nome_usuario, email_usuario, data_nascimento_usuario, biografia_usuario, link_apoio_usuario, chave_pix_usuario, mensagem_apoio_usuario, foto_usuario, nivel_usuario, status_usuario, data_cadastro_usuario FROM usuario WHERE id_usuario = ?');
$stmt->execute([$idAlvo]);
$user = $stmt->fetch();
if (!$user || (!$ehMeuPerfil && ($user['status_usuario'] ?? '') !== 'ativo')) {
    http_response_code(404);
    $user = null;
}
$idadeUsuarioPerfil = $ehMeuPerfil && $user ? folivro_calcular_idade($user['data_nascimento_usuario'] ?? null) : null;
$maiorDeIdadePerfil = folivro_usuario_maior_de_idade($pdo, $uid);

// Estado da conversa no chat quando visualizando o perfil de outro usuário
$estadoChatPerfil = null;
if (!$ehMeuPerfil && $user) {
    $menorP = min($uid, $idAlvo);
    $maiorP = max($uid, $idAlvo);
    $stChatP = $pdo->prepare('SELECT id_conversa, status_conversa, id_solicitante FROM chat_conversas WHERE id_usuario_menor = ? AND id_usuario_maior = ? LIMIT 1');
    $stChatP->execute([$menorP, $maiorP]);
    $estadoChatPerfil = $stChatP->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Contadores reais do perfil (inclui seguidores, seguindo e pontos).
$contStmt = $pdo->prepare(
    'SELECT
        (SELECT COUNT(*) FROM livraria l0 LEFT JOIN loja_produtos lp0 ON lp0.id_livro = l0.id_livro LEFT JOIN autor_dos_livros adl0 ON adl0.id_autor_dos_livros = lp0.id_autor_dos_livros WHERE l0.id_usuario = ? AND (adl0.id_autor_dos_livros IS NULL OR adl0.status_autor_dos_livros <> "removido")) +
        (SELECT COUNT(*) FROM livraria_digital ld JOIN autor_dos_livros adl2 ON adl2.id_autor_dos_livros = ld.id_autor_dos_livros WHERE ld.id_usuario = ? AND adl2.status_autor_dos_livros <> "removido" AND NOT EXISTS (SELECT 1 FROM livraria l2 JOIN loja_produtos lp2 ON lp2.id_livro = l2.id_livro WHERE l2.id_usuario = ld.id_usuario AND lp2.id_autor_dos_livros = adl2.id_autor_dos_livros)) AS livros_salvos,
        (SELECT COUNT(*) FROM resenha WHERE id_usuario = ?) AS livros_avaliados,
        (SELECT COUNT(*) FROM curtidas WHERE id_usuario = ?) AS favoritos,
        (SELECT COALESCE(pontos_contador_de_pontos, 0) FROM contador_pontos WHERE id_usuario = ?) AS pontos,
        (SELECT COUNT(*) FROM seguidores WHERE id_seguido = ?) AS seguidores,
        (SELECT COUNT(*) FROM seguidores WHERE id_seguidor = ?) AS seguindo'
);
$contStmt->execute([$idAlvo, $idAlvo, $idAlvo, $idAlvo, $idAlvo, $idAlvo, $idAlvo]);
$contadores = $contStmt->fetch() ?: ['livros_salvos' => 0, 'livros_avaliados' => 0, 'favoritos' => 0, 'pontos' => 0, 'seguidores' => 0, 'seguindo' => 0];

$jaSeguindo = false;
if (!$ehMeuPerfil && $user) {
    $stSeg = $pdo->prepare('SELECT 1 FROM seguidores WHERE id_seguidor = ? AND id_seguido = ? LIMIT 1');
    $stSeg->execute([$uid, $idAlvo]);
    $jaSeguindo = (bool) $stSeg->fetchColumn();
}

// Obras publicadas na loja pelo usuário (se for autor)
$obrasLojaPerfil = [];
if ($user) {
    $pagamentosAtivosPerfil = function_exists('folivro_pagamentos_ativos') && folivro_pagamentos_ativos();
    $filtroPagosPerfil = $pagamentosAtivosPerfil ? '' : 'AND lp.preco_centavos = 0';
    $stObrasLoja = $pdo->prepare(
        "SELECT lp.id_produto, lp.titulo_produto, lp.capa_produto, lp.preco_centavos, lp.arquivo_tipo, lp.classificacao_produto
           FROM loja_produtos lp
           JOIN usuario u ON u.id_usuario = lp.id_autor
      LEFT JOIN autor_dos_livros a ON a.id_autor_dos_livros = lp.id_autor_dos_livros
      LEFT JOIN historias h ON h.id_historias = a.id_historias
          WHERE lp.id_autor = ? AND lp.status_produto = 'publicado' AND u.status_usuario = 'ativo'
            {$filtroPagosPerfil}
            AND (lp.id_autor_dos_livros IS NULL OR (a.status_autor_dos_livros = 'a_venda' AND a.revisao_publicacao = 'aprovada' AND h.status_historias = 'publicada'))
            AND (lp.id_autor_dos_livros IS NULL OR NOT EXISTS (SELECT 1 FROM denuncias d WHERE d.tipo_denuncias = 'obra_autoral' AND d.item_denuncia = lp.id_autor_dos_livros AND d.status_denuncias IN ('aberta', 'em_analise') AND d.suspensao_preventiva = 1))
       ORDER BY lp.data_publicacao DESC
          LIMIT 6"
    );
    $stObrasLoja->execute([$idAlvo]);
    $obrasLojaPerfil = $stObrasLoja->fetchAll(PDO::FETCH_ASSOC);
}

// Últimas resenhas escritas pelo usuário
$resenhasPerfil = [];
if ($user) {
    $stRevP = $pdo->prepare(
        'SELECT r.id_livro, r.nota_resenha, r.titulo_resenha, r.resenha_resenha, r.data_avaliacao_resenha, r.compra_verificada_resenha, l.titulo_livro
           FROM resenha r
           JOIN livro l ON l.id_livro = r.id_livro
          WHERE r.id_usuario = ?
       ORDER BY r.data_avaliacao_resenha DESC
          LIMIT 3'
    );
    $stRevP->execute([$idAlvo]);
    $resenhasPerfil = $stRevP->fetchAll(PDO::FETCH_ASSOC);
}

// Prévia visual de livros salvos e obras compradas para a estante do perfil (como no Figma).
$prevLivrosStmt = $pdo->prepare(
    'SELECT b.id_livro, b.titulo_livro, b.autor_livro, b.capa_livro, b.classificacao_livro,
            COALESCE(b.fonte_livro, "catalogo") AS fonte_livro, COALESCE(b.id_externo_livro, "") AS id_externo_livro,
            l.status_livraria, COALESCE(p.porcentagem_progressao, 0) AS porcentagem
       FROM livraria l
       JOIN livro b ON b.id_livro = l.id_livro
  LEFT JOIN progressao_leitura p ON p.id_usuario = l.id_usuario AND p.id_livro = l.id_livro
      WHERE l.id_usuario = ?
   ORDER BY l.data_solicitacao_livraria DESC, l.id_livraria DESC
      LIMIT 6'
);
$prevLivrosStmt->execute([$idAlvo]);
$prevLivros = $prevLivrosStmt->fetchAll();

$csrf = folivro_escape(folivro_csrf_token());
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meu perfil | FOLIVRO</title>
    <link rel="stylesheet" href="usuario.css?v=20261126_03">
    <link rel="stylesheet" href="layout.css?v=20261126_01">
    <link rel="stylesheet" href="estante.css?v=20261124_01">
</head>

<body>

    <?php $menuAtivo = 'perfil'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <section class="perfil">
            <div class="perfil-hero">
                <div class="perfil-banner" aria-hidden="true"></div>
                <div class="cabecalho-perfil">
                    <div class="avatar" aria-hidden="true">
                        <?php if (!empty($user['foto_usuario'])): ?>
                            <img src="<?= folivro_escape($user['foto_usuario']) ?>" alt="Foto de perfil">
                        <?php else: ?>
                            <?= folivro_icone('perfil-folivro', 'folivro-icon-xl') ?>
                        <?php endif; ?>
                    </div>

                    <div class="dados-usuario">
                        <div class="perfil-nome-linha">
                            <h1><?= folivro_escape($user['nome_usuario'] ?? 'Perfil não encontrado') ?></h1>
                            <?php if ($user): ?>
                                <span class="selo-papel-perfil"><?= folivro_escape(ucfirst((string) ($user['nivel_usuario'] ?? 'leitor'))) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($user): ?>
                            <?php if ($ehMeuPerfil): ?>
                                <p class="email"><?= folivro_escape($user['email_usuario'] ?? '') ?></p>
                            <?php endif; ?>
                            <?php if (!empty($user['biografia_usuario'])): ?>
                                <p class="biografia"><?= folivro_escape($user['biografia_usuario']) ?></p>
                            <?php endif; ?>
                            <p class="membro">
                                Membro desde <?= isset($user['data_cadastro_usuario']) ? date('d/m/Y', strtotime($user['data_cadastro_usuario'])) : '' ?>
                                <?php if ($ehMeuPerfil): ?>
                                    <?php if ($idadeUsuarioPerfil !== null): ?>
                                        · Nascimento: <?= date('d/m/Y', strtotime((string) $user['data_nascimento_usuario'])) ?> (<?= $idadeUsuarioPerfil ?> anos — <?= $maiorDeIdadePerfil ? 'Maior de 18 anos' : 'Menor de 18 anos · Obras +18 ocultas' ?>)
                                    <?php else: ?>
                                        · Data de nascimento não informada (Obras +18 ocultas por proteção etária)
                                    <?php endif; ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($user): ?>
                    <div class="perfil-acoes-topo">
                        <?php if ($ehMeuPerfil): ?>
                            <a class="filtro-pilula" href="minha-biblioteca.php">Minha estante</a>
                            <?php if ($pagamentosAtivosPerfil): ?>
                                <a class="filtro-pilula" href="minhas-compras.php">Minhas compras</a>
                            <?php endif; ?>
                            <a class="filtro-pilula" href="estatisticas.php">Metas e conquistas</a>
                            <?php if ($pagamentosAtivosPerfil && in_array($user['nivel_usuario'] ?? '', ['autor', 'admin'], true)): ?>
                                <a class="filtro-pilula" href="painel-vendas.php">Painel de vendas</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="acao" value="alternar_seguir">
                                <button type="submit" class="<?= $jaSeguindo ? 'filtro-pilula' : 'botao-adicionar-livro' ?>">
                                    <?= $jaSeguindo ? 'Seguindo ✓' : '+ Seguir' ?>
                                </button>
                            </form>
                            <?php if (!empty($user['link_apoio_usuario']) || !empty($user['chave_pix_usuario'])): ?>
                                <button type="button" class="btn-apoie-criador js-abrir-apoie-criador">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true">
                                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                    </svg>
                                    <span>Apoie o criador</span>
                                </button>
                            <?php endif; ?>
                            <?php if ($estadoChatPerfil && ($estadoChatPerfil['status_conversa'] ?? 'aceita') === 'pendente'): ?>
                                <?php if ((int) ($estadoChatPerfil['id_solicitante'] ?? 0) === $uid): ?>
                                    <span class="selo-status selo-comprado">Solicitação de mensagem pendente</span>
                                    <a class="filtro-pilula" href="chat.php?conversa=<?= (int) $estadoChatPerfil['id_conversa'] ?>">Ver no chat</a>
                                <?php else: ?>
                                    <span class="selo-status selo-comprado">Solicitou conversar com você</span>
                                    <a class="botao-adicionar-livro" href="chat.php?conversa=<?= (int) $estadoChatPerfil['id_conversa'] ?>">Responder no chat</a>
                                <?php endif; ?>
                            <?php elseif ($estadoChatPerfil && ($estadoChatPerfil['status_conversa'] ?? 'aceita') === 'aceita'): ?>
                                <a class="filtro-pilula" href="chat.php?conversa=<?= (int) $estadoChatPerfil['id_conversa'] ?>">Abrir conversa no chat</a>
                            <?php else: ?>
                                <form method="post" action="chat.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="acao" value="iniciar_conversa">
                                    <input type="hidden" name="id_destinatario" value="<?= (int) $idAlvo ?>">
                                    <button type="submit" class="filtro-pilula">Solicitar conversa</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$user): ?>
                <p class="estado-vazio estado-erro">Usuário não encontrado ou inativo.</p>
            <?php endif; ?>

            <?php if ($user && $ehMeuPerfil): ?>
            <?php if ($msgPerfilErro): ?>
                <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape($msgPerfilErro) ?></p>
            <?php endif; ?>
            <?php if ($msgPerfilSucesso): ?>
                <p class="estado-vazio estado-sucesso" role="status"><?= folivro_escape($msgPerfilSucesso) ?></p>
            <?php endif; ?>

            <details class="configurar-meta card-editar-perfil">
                <summary>Editar perfil, foto e apoio ao criador</summary>
                <form method="post" enctype="multipart/form-data" class="progresso-form form-editar-perfil-moderno">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="acao" value="editar_perfil">

                    <div class="layout-duas-colunas-perfil">
                        <!-- Coluna Esquerda: Dados do perfil -->
                        <div class="coluna-dados-perfil">
                            <h3 class="titulo-subsecao-perfil">Dados pessoais e apresentação</h3>

                            <div class="grade-campos-perfil">
                                <label class="campo-perfil-moderno" for="nome_usuario">
                                    <span class="rotulo-campo-perfil">Nome de exibição</span>
                                    <input id="nome_usuario" type="text" name="nome_usuario" maxlength="120" required value="<?= folivro_escape($user['nome_usuario'] ?? '') ?>">
                                </label>

                                <label class="campo-perfil-moderno" for="data_nascimento_usuario">
                                    <span class="rotulo-campo-perfil">Data de nascimento</span>
                                    <input id="data_nascimento_usuario" name="data_nascimento_usuario" type="date" min="1905-01-01" max="<?= date('Y-m-d') ?>" value="<?= folivro_escape((string) ($user['data_nascimento_usuario'] ?? '')) ?>">
                                    <span class="dica-campo-perfil">Define a exibição de obras +18</span>
                                </label>
                            </div>

                            <label class="campo-perfil-moderno" for="biografia_usuario">
                                <span class="rotulo-campo-perfil">Biografia <span class="dica-campo-perfil">Opcional · até 1.000 caracteres</span></span>
                                <textarea id="biografia_usuario" name="biografia_usuario" rows="4" maxlength="1000" placeholder="Conte um pouco sobre seus gostos literários e projetos..."><?= folivro_escape((string) ($user['biografia_usuario'] ?? '')) ?></textarea>
                            </label>

                            <label class="campo-perfil-moderno" for="foto_usuario">
                                <span class="rotulo-campo-perfil">Foto de perfil <span class="dica-campo-perfil">JPG, PNG ou WebP · máx. 2 MB</span></span>
                                <input id="foto_usuario" type="file" name="foto_usuario" accept="image/jpeg,image/png,image/webp">
                            </label>
                        </div>

                        <!-- Coluna Direita: Apoio ao criador -->
                        <div class="bloco-apoio-perfil-config">
                            <input type="hidden" name="salvar_apoio_criador" value="1">
                            <div class="topo-apoio-perfil">
                                <h3>
                                    <svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor" style="color:#d9463e;" aria-hidden="true">
                                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                    </svg>
                                    <span>Apoio ao criador</span>
                                </h3>
                                <span class="selo-opcional-apoio">Opcional</span>
                            </div>
                            <p class="desc-apoio-perfil">
                                Preencha seu link de apoio ou chave Pix para exibir o botão <strong>"Apoie o criador"</strong> nas suas obras autorais. O FOLIVRO não processa pagamentos nem cobra taxas.
                            </p>

                            <div class="grade-campos-apoio">
                                <label class="campo-perfil-moderno" for="link_apoio_usuario">
                                    <span class="rotulo-campo-perfil">Link externo de apoio</span>
                                    <input id="link_apoio_usuario" name="link_apoio_usuario" type="url" maxlength="255"
                                           placeholder="https://apoia.se/seuperfil"
                                           value="<?= folivro_escape((string) ($user['link_apoio_usuario'] ?? '')) ?>">
                                    <span class="dica-campo-perfil">Apoia.se, Catarse, Ko-fi, LivePix, Patreon...</span>
                                </label>

                                <label class="campo-perfil-moderno" for="chave_pix_usuario">
                                    <span class="rotulo-campo-perfil">Chave Pix</span>
                                    <input id="chave_pix_usuario" name="chave_pix_usuario" type="text" maxlength="140"
                                           placeholder="Ex.: chave aleatória ou autor@email.com"
                                           value="<?= folivro_escape((string) ($user['chave_pix_usuario'] ?? '')) ?>">
                                    <span class="dica-campo-perfil">Recomendamos chave aleatória ou e-mail profissional</span>
                                </label>

                                <label class="campo-perfil-moderno" for="mensagem_apoio_usuario">
                                    <span class="rotulo-campo-perfil">Mensagem para os leitores <span class="dica-campo-perfil">Até 200 caracteres</span></span>
                                    <input id="mensagem_apoio_usuario" name="mensagem_apoio_usuario" type="text" maxlength="200"
                                           placeholder="Ex.: Curtiu a história? Seu apoio ajuda a manter novos capítulos!"
                                           value="<?= folivro_escape((string) ($user['mensagem_apoio_usuario'] ?? '')) ?>">
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="rodape-form-perfil">
                        <button class="botao-adicionar-livro" type="submit">Salvar alterações</button>
                    </div>
                </form>
            </details>
            <?php endif; ?>

            <section class="estatisticas" aria-label="Resumo do perfil">
                <div class="estatistica">
                    <strong><?= (int) $contadores['livros_salvos'] ?></strong>
                    <span>Livros na estante</span>
                </div>
                <div class="estatistica">
                    <strong><?= (int) $contadores['livros_avaliados'] ?></strong>
                    <span>Resenhas escritas</span>
                </div>
                <div class="estatistica">
                    <strong><?= (int) $contadores['pontos'] ?></strong>
                    <span>Pontos de leitura</span>
                </div>
                <div class="estatistica">
                    <strong><?= (int) $contadores['seguidores'] ?></strong>
                    <span>Seguidores</span>
                </div>
                <div class="estatistica">
                    <strong><?= (int) $contadores['seguindo'] ?></strong>
                    <span>Seguindo</span>
                </div>
            </section>

            <section class="area-livros">
                <h2><?= $ehMeuPerfil ? 'Estante recente' : 'Estante de ' . folivro_escape($user['nome_usuario'] ?? 'leitor') ?></h2>
                <?php if ($ehMeuPerfil): ?>
                    <p>Consulte os livros salvos e as obras independentes que você adquiriu.</p>
                <?php endif; ?>

                <?php if ($prevLivros): ?>
                    <div class="biblioteca-lista estante-biblioteca estante-perfil">
                        <?php foreach ($prevLivros as $pl): ?>
                            <?php
                            $pctPrev = max(0, min(100, (int) round((float) $pl['porcentagem'])));
                            $classifPl = folivro_normalizar_classificacao($pl['classificacao_livro'] ?? 'livre');
                            $bloqueadoPl = (!$maiorDeIdadePerfil && folivro_classificacao_eh_maior18($classifPl));
                            $capaUrlPl = folivro_capa_url($pl['capa_livro'] ?? '', (string) $pl['titulo_livro'], (string) $pl['autor_livro']);
                            ?>
                            <article class="livro-card <?= $bloqueadoPl ? 'livro-card-bloqueado-idade' : '' ?>">
                                <div class="livro-capa <?= $bloqueadoPl ? 'capa-bloqueada-18' : '' ?>">
                                    <img src="<?= folivro_escape($capaUrlPl) ?>" alt="<?= $bloqueadoPl ? 'Capa bloqueada (+18)' : 'Capa de ' . folivro_escape($pl['titulo_livro']) ?>" loading="lazy">
                                    <?php if ($bloqueadoPl): ?>
                                        <div class="capa-bloqueio-idade-overlay" role="img" aria-label="Conteúdo bloqueado para menores de 18 anos">
                                            <div class="icone-olho-bloqueado-circulo">
                                                <?= folivro_icone_olho_bloqueado_svg(30) ?>
                                            </div>
                                            <strong class="titulo-bloqueio-capa">Classificação +18</strong>
                                            <span class="motivo-bloqueio-capa">Bloqueado: conteúdo exclusivo para maiores de 18 anos</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="livro-info">
                                    <div class="linha-selos-card">
                                        <span class="selo-status"><?= folivro_escape(folivro_status_label((string) $pl['status_livraria'])) ?></span>
                                        <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($classifPl) ?>" title="<?= folivro_escape(folivro_aviso_conteudo_label($classifPl)) ?>">
                                            <?= folivro_escape(folivro_classificacao_sigla($classifPl)) ?>
                                        </span>
                                    </div>
                                    <?php if ($bloqueadoPl): ?>
                                        <h3><span class="titulo-oculto-idade" title="Título oculto para menores de 18 anos">Título oculto (+18)</span></h3>
                                        <p class="autor-card-livro"><?= folivro_escape($pl['autor_livro']) ?></p>
                                        <div class="rodape-card-leitura">
                                            <p class="texto-motivo-bloqueio-card">Obra restrita para maiores de 18 anos conforme sua data de nascimento.</p>
                                            <button class="botao-adicionar-livro botao-bloqueado-idade btn-card-principal" type="button" disabled>Bloqueado (+18)</button>
                                        </div>
                                    <?php else: ?>
                                        <h3><a href="livro.php?id=<?= (int) $pl['id_livro'] ?>" class="link-titulo-livro"><?= folivro_escape($pl['titulo_livro']) ?></a></h3>
                                        <p class="autor-card-livro"><?= folivro_escape($pl['autor_livro']) ?></p>
                                        <div class="rodape-card-leitura">
                                            <div class="bloco-progresso-card">
                                                <div class="topo-progresso-card">
                                                    <span>Progresso</span>
                                                    <strong><?= $pctPrev ?>%</strong>
                                                </div>
                                                <div class="mini-progresso" title="<?= $pctPrev ?>% concluído">
                                                    <span style="width: <?= $pctPrev ?>%"></span>
                                                </div>
                                            </div>
                                            <?php
                                            $ehOlPerfil = (($pl['fonte_livro'] ?? '') === 'openlibrary' || preg_match('/^OL[0-9]+W$/', (string) ($pl['id_externo_livro'] ?? '')) === 1)
                                                && folivro_dp_encontrar_obra((string) ($pl['id_externo_livro'] ?? ''), (string) ($pl['titulo_livro'] ?? '')) === null;
                                            ?>
                                            <?php if ($ehMeuPerfil && !$ehOlPerfil): ?>
                                                <a class="botao-adicionar-livro btn-card-principal" href="leitor.php?id=<?= (int) $pl['id_livro'] ?>">Continuar leitura</a>
                                            <?php elseif ($ehMeuPerfil): ?>
                                                <a class="botao-adicionar-livro btn-card-principal" href="livro.php?id=<?= (int) $pl['id_livro'] ?>">Ver ficha crítica</a>
                                            <?php else: ?>
                                                <a class="botao-adicionar-livro btn-card-principal" href="livro.php?id=<?= (int) $pl['id_livro'] ?>">Ver livro</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($obrasLojaPerfil): ?>
                <section class="area-livros">
                    <h2>Obras publicadas na Loja</h2>
                    <div class="biblioteca-lista estante-biblioteca">
                        <?php foreach ($obrasLojaPerfil as $olp): ?>
                            <?php
                            $classifOlp = folivro_normalizar_classificacao((string) ($olp['classificacao_produto'] ?? 'livre'));
                            $bloqueadoOlp = (!$maiorDeIdadePerfil && folivro_classificacao_eh_maior18($classifOlp));
                            ?>
                            <article class="livro-card <?= $bloqueadoOlp ? 'livro-card-bloqueado-idade' : '' ?>">
                                <div class="livro-capa <?= $bloqueadoOlp ? 'capa-bloqueada-18' : '' ?>">
                                    <?php if (!empty($olp['capa_produto'])): ?>
                                        <img src="<?= folivro_escape($olp['capa_produto']) ?>" alt="<?= $bloqueadoOlp ? 'Capa bloqueada (+18)' : 'Capa de ' . folivro_escape($olp['titulo_produto']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="capa-fallback"><?= $bloqueadoOlp ? 'Conteúdo Restrito +18' : folivro_escape($olp['titulo_produto']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($bloqueadoOlp): ?>
                                        <div class="capa-bloqueio-idade-overlay" role="img" aria-label="Conteúdo bloqueado para menores de 18 anos">
                                            <div class="icone-olho-bloqueado-circulo">
                                                <?= folivro_icone_olho_bloqueado_svg(30) ?>
                                            </div>
                                            <strong class="titulo-bloqueio-capa">Classificação +18</strong>
                                            <span class="motivo-bloqueio-capa">Bloqueado: conteúdo exclusivo para maiores de 18 anos</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="livro-info">
                                    <div class="linha-selos-card">
                                        <span class="selo-status selo-comprado">
                                            <?= (!$pagamentosAtivosPerfil || (int) $olp['preco_centavos'] === 0) ? 'Gratuita' : 'R$ ' . number_format(((int) $olp['preco_centavos']) / 100, 2, ',', '.') ?>
                                        </span>
                                        <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($classifOlp) ?>" title="<?= folivro_escape(folivro_aviso_conteudo_label($classifOlp)) ?>">
                                            <?= folivro_escape(folivro_classificacao_sigla($classifOlp)) ?>
                                        </span>
                                    </div>
                                    <?php if ($bloqueadoOlp): ?>
                                        <h3><span class="titulo-oculto-idade" title="Título oculto para menores de 18 anos">Título oculto (+18)</span></h3>
                                        <button class="botao-adicionar-livro botao-bloqueado-idade btn-card-principal" type="button" disabled>Bloqueado (+18)</button>
                                    <?php else: ?>
                                        <h3><a href="loja-obra.php?id=<?= (int) $olp['id_produto'] ?>" class="link-titulo-livro"><?= folivro_escape($olp['titulo_produto']) ?></a></h3>
                                        <a class="botao-adicionar-livro btn-card-principal" href="loja-obra.php?id=<?= (int) $olp['id_produto'] ?>">Ver na loja</a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($resenhasPerfil): ?>
                <section class="area-livros">
                    <h2>Últimas avaliações escritas</h2>
                    <div class="perfil-resenhas-lista">
                        <?php foreach ($resenhasPerfil as $rp): ?>
                            <article class="perfil-resenha-card">
                                <div style="display:flex; justify-content:space-between; gap:8px; align-items:center;">
                                    <a href="livro.php?id=<?= (int) $rp['id_livro'] ?>"><strong><?= folivro_escape($rp['titulo_livro']) ?></strong></a>
                                    <span><?= str_repeat('★', (int) ($rp['nota_resenha'] ?? 0)) ?></span>
                                </div>
                                <?php if (!empty($rp['titulo_resenha'])): ?>
                                    <h3><?= folivro_escape($rp['titulo_resenha']) ?></h3>
                                <?php endif; ?>
                                <p><?= folivro_escape(mb_strimwidth((string) $rp['resenha_resenha'], 0, 160, '...', 'UTF-8')) ?></p>
                                <small style="display:block; margin-top:8px; color:var(--texto-apoio);">
                                    <?= date('d/m/Y', strtotime((string) $rp['data_avaliacao_resenha'])) ?>
                                    <?= (int) ($rp['compra_verificada_resenha'] ?? 0) === 1 ? ' · Compra verificada' : '' ?>
                                </small>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($user && $ehMeuPerfil): ?>
            <section class="privacidade-conta" aria-labelledby="titulo-privacidade">
                <h2 id="titulo-privacidade">Privacidade e conta</h2>
                <p>Veja os <a href="termos.php">termos de uso e a política de privacidade</a>.</p>
                <?php $conta = $_GET['conta'] ?? ''; ?>
                <?php if ($conta === 'senha'): ?>
                    <p class="estado-vazio estado-erro" role="alert">Senha incorreta ou confirmação não marcada. A conta não foi excluída.</p>
                <?php elseif ($conta === 'erro'): ?>
                    <p class="estado-vazio estado-erro" role="alert">Não foi possível excluir a conta agora. Tente novamente.</p>
                <?php endif; ?>
                <details class="configurar-meta" style="margin-bottom:14px;">
                    <summary>Alterar senha de acesso</summary>
                    <form class="progresso-form" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="acao" value="alterar_senha">
                        <label for="senha_atual">Senha atual
                            <input id="senha_atual" type="password" name="senha_atual" maxlength="255" autocomplete="current-password" required>
                        </label>
                        <label for="nova_senha">Nova senha (mínimo 8 caracteres)
                            <input id="nova_senha" type="password" name="nova_senha" maxlength="255" autocomplete="new-password" minlength="8" required>
                        </label>
                        <label for="confirmacao_nova_senha">Confirmar nova senha
                            <input id="confirmacao_nova_senha" type="password" name="confirmacao_nova_senha" maxlength="255" autocomplete="new-password" minlength="8" required>
                        </label>
                        <button class="botao-adicionar-livro" type="submit">Salvar nova senha</button>
                    </form>
                </details>

                <details>
                    <summary>Excluir minha conta</summary>
                    <form class="progresso-form" method="post" action="excluir-conta.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <p>Seus dados pessoais serão anonimizados, a biblioteca e o progresso serão apagados e suas obras sairão da loja. Esta ação não pode ser desfeita.</p>
                        <label for="senha_confirmacao">Confirme sua senha
                            <input id="senha_confirmacao" type="password" name="senha_confirmacao" maxlength="255" autocomplete="current-password" required>
                        </label>
                        <label><input type="checkbox" name="confirmo_exclusao" value="1" required> Entendo que a exclusão é definitiva.</label>
                        <button class="botao-adicionar-livro botao-perigo" type="submit">Excluir conta</button>
                    </form>
                </details>
            </section>
            <?php endif; ?>
        </section>

        <footer class="rodape">
            <button type="button" class="botao-sair" id="abrirModalSair">Sair</button>
        </footer>
    </main>

    <!-- Modal de confirmação de saída conforme o Figma -->
    <div class="modal-sair-fundo" id="modalSair" hidden role="dialog" aria-modal="true" aria-labelledby="tituloModalSair">
        <div class="modal-sair-caixa">
            <p id="tituloModalSair">Tem certeza que deseja sair?</p>
            <div class="modal-sair-botoes">
                <button type="button" class="botao-cancelar-sair" id="cancelarSair">cancelar</button>
                <form method="post" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="botao-sair">sair</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const modalSair = document.getElementById('modalSair');
        const fecharModalSair = () => {
            modalSair.hidden = true;
            document.getElementById('abrirModalSair').focus();
        };
        document.getElementById('abrirModalSair').addEventListener('click', () => {
            modalSair.hidden = false;
            document.getElementById('cancelarSair').focus();
        });
        document.getElementById('cancelarSair').addEventListener('click', fecharModalSair);
        modalSair.addEventListener('click', (ev) => {
            if (ev.target === modalSair) fecharModalSair();
        });
        document.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape' && !modalSair.hidden) fecharModalSair();
        });
    </script>

    <?php
    if ($user) {
        $apoioAutorNome = (string) ($user['nome_usuario'] ?? '');
        $apoioAutorLink = (string) ($user['link_apoio_usuario'] ?? '');
        $apoioAutorPix  = (string) ($user['chave_pix_usuario'] ?? '');
        $apoioAutorMsg  = (string) ($user['mensagem_apoio_usuario'] ?? '');
        require __DIR__ . '/includes/apoie-criador-modal.php';
    }
    ?>

</body>

</html>
