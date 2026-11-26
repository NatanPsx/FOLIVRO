<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/loja.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];
$maiorDeIdadeAutor = folivro_usuario_maior_de_idade($pdo, $uid);
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $erro = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $title            = trim((string) ($_POST['titulo'] ?? ''));
        $synopsis         = trim((string) ($_POST['sinopse'] ?? ''));
        $modalidade       = (!folivro_pagamentos_ativos() || ($_POST['modalidade_distribuicao'] ?? 'venda_simulada') === 'gratuita') ? 'gratuita' : 'venda_simulada';
        $price            = $modalidade === 'gratuita' ? 0.00 : filter_var($_POST['preco'] ?? null, FILTER_VALIDATE_FLOAT);
        $origemObra       = (string) ($_POST['origem_obra'] ?? 'autoral_original');
        $licenca          = (string) ($_POST['licenca_historias'] ?? 'direitos_reservados');
        $avisoConteudo    = folivro_normalizar_classificacao((string) ($_POST['aviso_conteudo_historias'] ?? 'livre'));
        $generosSel       = $_POST['generos_historias'] ?? [];
        $generosExtra     = trim((string) ($_POST['generos_personalizados'] ?? ''));
        $generosString    = folivro_generos_para_string($generosSel, $generosExtra, 5);
        $declareAutoria   = isset($_POST['declaracao_autoria']);
        $declareComercial = isset($_POST['declaracao_comercializacao']);
        $publish          = isset($_POST['publicar']);

        // Monta lista de capítulos (suporta múltiplos capítulos via abas ou envio único legado)
        $capitulosLista = [];
        $titulosPost = $_POST['capitulos_titulos'] ?? null;
        $conteudosPost = $_POST['capitulos_conteudos'] ?? null;
        if (is_array($titulosPost) && is_array($conteudosPost) && count($titulosPost) > 0) {
            $totalPost = min(50, max(count($titulosPost), count($conteudosPost)));
            for ($i = 0; $i < $totalPost; $i++) {
                $tCap = trim((string) ($titulosPost[$i] ?? ''));
                $cCap = trim((string) ($conteudosPost[$i] ?? ''));
                // Ignora abas completamente vazias além da primeira, se o autor abriu e não preencheu
                if ($i > 0 && $tCap === '' && trim(strip_tags($cCap)) === '') {
                    continue;
                }
                $capitulosLista[] = ['titulo' => $tCap, 'conteudo' => $cCap];
            }
        }
        if (!$capitulosLista) {
            $capitulosLista[] = [
                'titulo' => trim((string) ($_POST['titulo_capitulo'] ?? '')),
                'conteudo' => trim((string) ($_POST['capitulo'] ?? '')),
            ];
        }

        $chapterTitle = $capitulosLista[0]['titulo'] ?? '';
        $chapter      = $capitulosLista[0]['conteudo'] ?? '';

        $origensValidas  = ['autoral_original', 'autorizacao_documentada', 'fanfic_sem_autorizacao', 'dominio_publico_nao_verificado'];
        $licencasValidas = ['direitos_reservados', 'cc_by_nc_nd', 'autorizacao_especifica'];
        $avisosValidos   = ['livre', '10', '12', '14', '16', '18'];

        if (!in_array($origemObra, $origensValidas, true)) {
            $origemObra = 'autoral_original';
        }
        if (!in_array($licenca, $licencasValidas, true)) {
            $licenca = 'direitos_reservados';
        }
        if (!in_array($avisoConteudo, $avisosValidos, true)) {
            $avisoConteudo = 'livre';
        }

        $erroCapitulo = '';
        foreach ($capitulosLista as $idxCapVal => $capVal) {
            $numCapVal = $idxCapVal + 1;
            $textoPuroVal = trim(strip_tags($capVal['conteudo']));
            if ($capVal['titulo'] === '' || mb_strlen($capVal['titulo'], 'UTF-8') > 120) {
                $erroCapitulo = "Preencha um título válido (até 120 caracteres) no Capítulo {$numCapVal}.";
                break;
            }
            if ($textoPuroVal === '' || strlen($capVal['conteudo']) > 500000) {
                $erroCapitulo = "Escreva o conteúdo do Capítulo {$numCapVal} (máx. 500 KB).";
                break;
            }
        }

        if ($title === '' || mb_strlen($title, 'UTF-8') > 120
            || mb_strlen($synopsis, 'UTF-8') > 5000
            || !is_float($price) || $price < 0 || $price > 99999999) {
            $erro = 'Preencha título (até 120 caracteres), sinopse válida e preço válido.';
        } elseif ($erroCapitulo !== '') {
            $erro = $erroCapitulo;
        } elseif (!$maiorDeIdadeAutor && folivro_classificacao_eh_maior18($avisoConteudo)) {
            $erro = 'Restrição etária: autores menores de 18 anos não podem escrever ou publicar obras com classificação indicativa +18.';
        } elseif ($generosString === '') {
            $erro = 'Selecione pelo menos um gênero literário para a sua obra (ou informe um gênero personalizado).';
        } elseif ($publish && !$declareAutoria) {
            $erro = 'Confirmação obrigatória: para disponibilizar uma obra ao público, marque a declaração autoral de titularidade para reprodução e publicação.';
        } elseif ($publish && $modalidade === 'venda_simulada' && !$declareComercial) {
            $erro = 'Confirmação obrigatória: para comercializar na loja, marque também a declaração específica de comercialização.';
        } elseif ($publish && $modalidade === 'venda_simulada' && $licenca === 'cc_by_nc_nd' && $price > 0) {
            $erro = 'A licença Creative Commons BY-NC-ND 4.0 é restrita a uso não comercial. Selecione outra licença ou publique gratuitamente.';
        } else {
            // Verifica se algum capítulo coincide com material anteriormente removido pela moderação.
            $hashConteudo = folivro_content_hash($title . ' ' . $chapter);
            foreach ($capitulosLista as $idxCapChk => $capChk) {
                if (folivro_conteudo_foi_removido($pdo, $title, $capChk['conteudo'], 0)) {
                    $numCapChk = $idxCapChk + 1;
                    $erro = "Bloqueio de segurança: o conteúdo do Capítulo {$numCapChk} corresponde a material anteriormente removido pela moderação por violação de direitos autorais. Não é permitida a republicação automática; utilize o recurso de contestação no painel da obra original.";
                    break;
                }
            }

            $caminhoCapa = null;
            if ($erro === '' && !empty($_FILES['capa_historias']['name'])) {
                if (!$declareAutoria) {
                    $erro = 'Confirmação obrigatória: para enviar imagem de capa, marque a declaração autoral confirmando que a arte é de sua autoria ou possui licença/autorização verificada.';
                } else {
                    $arqCapa = $_FILES['capa_historias'];
                    $tiposPermitidos = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                    ];
                    if (($arqCapa['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        $erro = 'Falha no envio da imagem de capa. Tente um arquivo menor.';
                    } elseif (($arqCapa['size'] ?? 0) > 2 * 1024 * 1024) {
                        $erro = 'A capa da obra deve ter no máximo 2 MB.';
                    } else {
                        $infoCapa = @getimagesize($arqCapa['tmp_name']);
                        $mimeCapa = is_array($infoCapa) ? ($infoCapa['mime'] ?? '') : '';
                        if (!isset($tiposPermitidos[$mimeCapa])) {
                            $erro = 'Formato de capa inválido. Envie apenas JPG, PNG ou WebP.';
                        } else {
                            $dirCapas = __DIR__ . '/uploads/capas';
                            if (!is_dir($dirCapas)) {
                                mkdir($dirCapas, 0755, true);
                                file_put_contents($dirCapas . '/.htaccess', "Options -Indexes\nphp_flag engine off\n");
                            }
                            $nomeCapa = 'capa_' . $uid . '_' . bin2hex(random_bytes(8)) . '.' . $tiposPermitidos[$mimeCapa];
                            if (move_uploaded_file($arqCapa['tmp_name'], $dirCapas . '/' . $nomeCapa)) {
                                $caminhoCapa = 'uploads/capas/' . $nomeCapa;
                            } else {
                                $erro = 'Não foi possível salvar a imagem de capa.';
                            }
                        }
                    }
                }
            }

            if ($erro === '') {
                $qualificacaoAutor = folivro_avaliar_qualificacao_autor($pdo, $uid, true);
                $aprovAutoGlobal = folivro_aprovacao_automatica_obras_ativa($pdo);
                $todosCapsSeguros = true;
                foreach ($capitulosLista as $capSeg) {
                    $avalSegCap = folivro_avaliar_seguranca_capitulo($pdo, $title, $capSeg['titulo'], $capSeg['conteudo'], $origemObra, 0);
                    if (($avalSegCap['acao'] ?? '') !== 'permitir') {
                        $todosCapsSeguros = false;
                        break;
                    }
                }
                $baseLegalIncerta = in_array($origemObra, ['fanfic_sem_autorizacao', 'dominio_publico_nao_verificado'], true);
                $publicarAutomatico = ($publish && !$baseLegalIncerta && ($qualificacaoAutor['qualificado_automatico'] || $aprovAutoGlobal) && $todosCapsSeguros);

                $revisaoPublicacao = 'nao_solicitada';
                $motivoBloqueio = null;
                $statusHistNovo = 'rascunho';
                $statusAdlNovo = 'rascunho';
                $statusCapNovo = 'rascunho';
                $modoPubCapNovo = 'revisao_humana';
                $revCapNovo = 'nao_solicitada';

                if ($publish && $baseLegalIncerta) {
                    $revisaoPublicacao = 'recusada';
                    $revCapNovo = 'recusada';
                    $motivoBloqueio = 'Publicação bloqueada preventivamente: fanfic, tradução, adaptação ou domínio público não verificado não concedem autorização automática (Lei nº 9.610/1998). Encaminhe comprovação de titularidade/jurisdição para revisão humana.';
                } elseif ($publicarAutomatico) {
                    $revisaoPublicacao = 'aprovada';
                    $statusHistNovo = 'publicada';
                    $statusAdlNovo = 'a_venda';
                    $statusCapNovo = 'publicado';
                    $modoPubCapNovo = 'automatica';
                    $revCapNovo = 'publicado_automatico';
                } elseif ($publish) {
                    $revisaoPublicacao = 'pendente';
                    $revCapNovo = 'pendente';
                    $motivoBloqueio = 'Sua publicação foi encaminhada para revisão humana.';
                }

                try {
                    $pdo->beginTransaction();

                    $story = $pdo->prepare(
                        'INSERT INTO historias (id_usuario, titulo_historias, sinopse_historias, capa_historias,
                                                generos_historias, licenca_historias, aviso_conteudo_historias, origem_obra_historias,
                                                hash_conteudo_historias, status_historias)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $story->execute([
                        $uid, $title, $synopsis, $caminhoCapa,
                        $generosString, $licenca, $avisoConteudo, $origemObra,
                        $hashConteudo, $statusHistNovo,
                    ]);
                    $storyId = (int) $pdo->lastInsertId();

                    $ch = $pdo->prepare(
                        'INSERT INTO capitulos
                            (id_historias, titulo_capitulos, conteudo_capitulos, hash_conteudo_capitulos,
                             ordem_capitulos, status_capitulos, modo_publicacao_capitulos, revisao_capitulos,
                             data_revisao_capitulos)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, IF(?, CURRENT_TIMESTAMP, NULL))'
                    );
                    $newCapId = 0;
                    foreach ($capitulosLista as $idxIns => $capIns) {
                        $ordemCap = $idxIns + 1;
                        $hashCapItem = folivro_content_hash($capIns['conteudo']);
                        $ch->execute([
                            $storyId, $capIns['titulo'], $capIns['conteudo'], $hashCapItem,
                            $ordemCap, $statusCapNovo, $modoPubCapNovo, $revCapNovo,
                            $publicarAutomatico ? 1 : 0,
                        ]);
                        if ($idxIns === 0) {
                            $newCapId = (int) $pdo->lastInsertId();
                        }
                    }
                    $qtdCapsSalvos = count($capitulosLista);

                    $book = $pdo->prepare(
                        'INSERT INTO autor_dos_livros (id_historias, id_autor, titulo_autor_dos_livros, preco_dos_livros_autor,
                                                       modalidade_distribuicao, declaracao_autoria, data_declaracao_autoria,
                                                       declaracao_comercializacao, data_declaracao_comercializacao,
                                                       revisao_publicacao, motivo_bloqueio_autor, status_autor_dos_livros, data_publicacao_autor)
                         VALUES (?, ?, ?, ?, ?, ?, IF(?, CURRENT_TIMESTAMP, NULL), ?, IF(?, CURRENT_TIMESTAMP, NULL), ?, ?, ?, IF(?, CURRENT_TIMESTAMP, NULL))'
                    );
                    $book->execute([
                        $storyId, $uid, $title, $price,
                        $modalidade,
                        $declareAutoria ? 1 : 0, $declareAutoria ? 1 : 0,
                        $declareComercial ? 1 : 0, $declareComercial ? 1 : 0,
                        $revisaoPublicacao,
                        $motivoBloqueio,
                        $statusAdlNovo,
                        $publicarAutomatico ? 1 : 0,
                    ]);
                    $bookId = (int) $pdo->lastInsertId();

                    if ($publicarAutomatico) {
                        $rotuloCaps = $qtdCapsSalvos > 1 ? "{$qtdCapsSalvos} capítulos" : "Capítulo 1 ({$chapterTitle})";
                        folivro_registrar_historico_moderacao(
                            $pdo,
                            $uid,
                            null,
                            'publicacao_automatica_capitulo',
                            "Obra '{$title}' e {$rotuloCaps} publicados automaticamente (sujeitos à moderação posterior).",
                            $qualificacaoAutor['modalidade'],
                            $qualificacaoAutor['modalidade'],
                            $bookId,
                            $newCapId
                        );
                    }

                    if ($declareAutoria) {
                        $term = $pdo->prepare("INSERT INTO termos_aceitos (id_usuario, tipo_termos, versao_termos, id_autor_dos_livros) VALUES (?, 'declaracao_autoria', ?, ?)");
                        $term->execute([$uid, FOLIVRO_TERMOS_VERSAO, $bookId]);
                    }
                    if ($declareComercial) {
                        $termCom = $pdo->prepare("INSERT INTO termos_aceitos (id_usuario, tipo_termos, versao_termos, id_autor_dos_livros) VALUES (?, 'declaracao_comercializacao', ?, ?)");
                        $termCom->execute([$uid, FOLIVRO_TERMOS_VERSAO, $bookId]);
                    }

                    $pdo->prepare("UPDATE usuario SET nivel_usuario = 'autor' WHERE id_usuario = ? AND nivel_usuario = 'leitor'")->execute([$uid]);
                    $pdo->commit();

                    if ($publicarAutomatico && $bookId > 0) {
                        require_once __DIR__ . '/config/loja.php';
                        folivro_loja_sincronizar_obra_estudio($pdo, $bookId);
                    }

                    $txtQtd = $qtdCapsSalvos > 1 ? "{$qtdCapsSalvos} capítulos" : "1 capítulo";
                    if ($publish && $baseLegalIncerta) {
                        $erro = $motivoBloqueio . " A obra ({$txtQtd}) foi salva apenas como rascunho privado.";
                    } elseif ($publicarAutomatico) {
                        $sucesso = "Você está qualificado para publicação automática. Obra publicada com {$txtQtd} (sujeita à moderação posterior).";
                    } elseif ($publish) {
                        $sucesso = "Sua publicação com {$txtQtd} foi encaminhada para revisão humana.";
                    } else {
                        $sucesso = "Rascunho privado salvo com {$txtQtd} em Livros escritos.";
                    }
                    $_POST = [];
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $erro = 'Não foi possível salvar a obra. Revise os campos e tente novamente.';
                }
            }
        }
    }
}

$old = static fn(string $k): string => folivro_escape((string) ($_POST[$k] ?? ''));
$oldRaw = static fn(string $k): string => (string) ($_POST[$k] ?? '');
$capitulosIniciais = [];
if (is_array($_POST['capitulos_titulos'] ?? null) && is_array($_POST['capitulos_conteudos'] ?? null)) {
    $totInit = min(50, max(count($_POST['capitulos_titulos']), count($_POST['capitulos_conteudos'])));
    for ($i = 0; $i < $totInit; $i++) {
        $capitulosIniciais[] = [
            'titulo' => (string) ($_POST['capitulos_titulos'][$i] ?? ''),
            'conteudo' => folivro_sanitizar_html_capitulo((string) ($_POST['capitulos_conteudos'][$i] ?? '')),
        ];
    }
}
if (!$capitulosIniciais) {
    $capitulosIniciais[] = [
        'titulo' => $oldRaw('titulo_capitulo'),
        'conteudo' => $oldRaw('capitulo') !== '' ? folivro_sanitizar_html_capitulo($oldRaw('capitulo')) : '',
    ];
}
$qualificacaoEstudio = folivro_avaliar_qualificacao_autor($pdo, $uid, false);
$aprovAutoEstudio = folivro_aprovacao_automatica_obras_ativa($pdo);
$taxaLojaEstudio = 10.0;
if (is_file(__DIR__ . '/config/loja.php')) {
    require_once __DIR__ . '/config/loja.php';
    $taxaLojaEstudio = (float) (folivro_loja_config()['taxa_percentual'] ?? 10.0);
}
$pubImediataAtiva = ($aprovAutoEstudio || !empty($qualificacaoEstudio['qualificado_automatico']));
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escrever | FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261126_01">
    <link rel="stylesheet" href="home.css?v=20261123_01">
</head>

<body>

    <?php $menuAtivo = 'escrever'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main id="conteudo-principal" class="conteudo">
        <div class="cabecalho-estudio">
            <h1 class="titulo-com-icone-folivro"><?= folivro_icone('notebook-livros', 'folivro-icon-lg') ?> Estúdio de Escrita &amp; Publicação</h1>
            <p>Escreva seus capítulos com foco, personalize a capa e publique diretamente na loja e na comunidade.</p>
        </div>

        <div class="banner-modalidade-publicacao modal-pub-<?= $pubImediataAtiva ? 'automatica' : folivro_escape($qualificacaoEstudio['modalidade']) ?>" role="status">
            <strong>Status de publicação:</strong>
            <span>
                <?= $pubImediataAtiva
                    ? 'Publicação imediata ativa — obras originais publicadas vão direto para a loja.'
                    : 'Revisão editorial — novas publicações passam por aprovação da moderação antes de aparecer na loja.' ?>
            </span>
        </div>

        <?php if ($erro): ?>
            <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape($erro) ?></p>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <p class="estado-vazio estado-sucesso" role="status"><?= folivro_escape($sucesso) ?> <a href="biblioteca-autor.php">Abrir Livros escritos</a></p>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="estudio-escrita-form" id="form-escrita">
            <input type="hidden" name="csrf_token" value="<?= folivro_escape(folivro_csrf_token()) ?>">

            <div class="estudio-escrita-layout" id="estudio-layout">
                <!-- ÁREA PRINCIPAL: O MANUSCRITO RELAXANTE -->
                <section class="estudio-manuscrito" aria-label="Área de escrita do manuscrito">
                    <header class="estudio-manuscrito-topo">
                        <input id="titulo" name="titulo" class="input-titulo-manuscrito" maxlength="120" required placeholder="Título do seu livro (máx. 120 caracteres)..." value="<?= $old('titulo') ?>" aria-label="Título da obra" autocomplete="off">

                        <!-- Barra de abas de capítulos + botão "Escrever mais" -->
                        <div class="estudio-abas-capitulos-barra">
                            <div class="estudio-abas-capitulos" id="abas-capitulos-lista" role="tablist" aria-label="Capítulos da obra"></div>
                            <button type="button" id="btn-escrever-mais" class="btn-escrever-mais" title="Abrir mais uma aba de capítulo para escrita">
                                + Escrever mais
                            </button>
                        </div>

                        <input id="titulo_capitulo" name="titulo_capitulo" class="input-capitulo-manuscrito" maxlength="120" required placeholder="Capítulo 1: Título do capítulo (máx. 120 caracteres)..." value="<?= folivro_escape($capitulosIniciais[0]['titulo'] ?? '') ?>" aria-label="Título do capítulo atual" autocomplete="off">
                    </header>

                    <div class="estudio-canvas-wrapper">
                        <!-- BARRA DE FORMATAÇÃO DE TEXTO DO ESTÚDIO -->
                        <div class="estudio-toolbar-formatacao" role="toolbar" aria-label="Ferramentas de formatação de texto">
                            <!-- Tamanho / Hierarquia -->
                            <div class="toolbar-grupo">
                                <select id="select-tamanho-fonte" class="toolbar-select" title="Tamanho da letra">
                                    <option value="">Tamanho da letra</option>
                                    <option value="1">Pequeno (14px)</option>
                                    <option value="3" selected>Normal (18px)</option>
                                    <option value="5">Médio (22px)</option>
                                    <option value="6">Grande (28px)</option>
                                    <option value="7">Destaque (36px)</option>
                                </select>
                                <select id="select-bloco-estilo" class="toolbar-select" title="Estilo de parágrafo / título">
                                    <option value="p">Parágrafo normal</option>
                                    <option value="h2">Título Principal (H2)</option>
                                    <option value="h3">Subtítulo (H3)</option>
                                    <option value="blockquote">Citação em bloco</option>
                                </select>
                            </div>

                            <div class="toolbar-separador"></div>

                            <!-- Estilo de Caractere: B, I, U, S -->
                            <div class="toolbar-grupo">
                                <button type="button" class="toolbar-btn" data-cmd="bold" title="Negrito (Ctrl+B)">
                                    <strong>B</strong>
                                </button>
                                <button type="button" class="toolbar-btn" data-cmd="italic" title="Itálico (Ctrl+I)">
                                    <em>I</em>
                                </button>
                                <button type="button" class="toolbar-btn" data-cmd="underline" title="Sublinhado (Ctrl+U)">
                                    <u>U</u>
                                </button>
                                <button type="button" class="toolbar-btn" data-cmd="strikeThrough" title="Tachado">
                                    <s>S</s>
                                </button>
                            </div>

                            <div class="toolbar-separador"></div>

                            <!-- Alinhamento de Texto -->
                            <div class="toolbar-grupo">
                                <button type="button" class="toolbar-btn" data-cmd="justifyLeft" title="Alinhar à esquerda">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M3 5h18v2H3V5zm0 4h12v2H3V9zm0 4h18v2H3v-2zm0 4h12v2H3v-2z"/></svg>
                                </button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyCenter" title="Centralizar">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M3 5h18v2H3V5zm3 4h12v2H6V9zm-3 4h18v2H3v-2zm3 4h12v2H6v-2z"/></svg>
                                </button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyRight" title="Alinhar à direita">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M3 5h18v2H3V5zm6 4h12v2H9V9zm-6 4h18v2H3v-2zm6 4h12v2H9v-2z"/></svg>
                                </button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyFull" title="Justificar">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M3 5h18v2H3V5zm0 4h18v2H3V9zm0 4h18v2H3v-2zm0 4h18v2H3v-2z"/></svg>
                                </button>
                            </div>

                            <div class="toolbar-separador"></div>

                            <!-- Listas -->
                            <div class="toolbar-grupo">
                                <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList" title="Lista com marcadores">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M4 6h2v2H4V6zm0 5h2v2H4v-2zm0 5h2v2H4v-2zm5-10h12v2H9V6zm0 5h12v2H9v-2zm0 5h12v2H9v-2z"/></svg>
                                </button>
                                <button type="button" class="toolbar-btn" data-cmd="insertOrderedList" title="Lista numerada">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M3 5h2v3H4V6H3V5zm0 6h3v1H4v1h2v1H3v-1h2v-1H3v-1zm0 6h2.5L3 19.5V21h3v-1H4.2l1.8-1.5V17H3v1zm6-11h12v2H9V6zm0 6h12v2H9v-2zm0 6h12v2H9v-2z"/></svg>
                                </button>
                            </div>

                            <div class="toolbar-separador"></div>

                            <!-- Desfazer, Refazer, Limpar -->
                            <div class="toolbar-grupo">
                                <button type="button" class="toolbar-btn" data-cmd="undo" title="Desfazer (Ctrl+Z)">↶</button>
                                <button type="button" class="toolbar-btn" data-cmd="redo" title="Refazer (Ctrl+Y)">↷</button>
                                <button type="button" class="toolbar-btn" data-cmd="removeFormat" title="Limpar formatação">T⃠</button>
                            </div>
                        </div>

                        <!-- Editor Visual com Formatação Rica -->
                        <div id="editor-capitulo" 
                             class="canvas-escrita-serena canvas-editor-rico" 
                             contenteditable="true" 
                             role="textbox" 
                             aria-multiline="true" 
                             aria-label="Conteúdo do capítulo" 
                             data-placeholder="Era uma vez... comece a escrever sua história aqui com tranquilidade e inspiração."><?= !empty($oldRaw('capitulo')) ? folivro_sanitizar_html_capitulo($oldRaw('capitulo')) : '' ?></div>

                        <!-- Textarea Sincronizado para envio no POST do form -->
                        <textarea id="capitulo" name="capitulo" style="display:none;"><?= folivro_escape($capitulosIniciais[0]['conteudo'] ?? '') ?></textarea>
                        <div id="capitulos-hidden-inputs" hidden></div>
                    </div>

                    <footer class="estudio-barra-zen">
                        <div class="estudio-metricas">
                            <span id="contador-capitulos" class="selo-metrica selo-metrica-caps">1 capítulo</span>
                            <span id="contador-palavras" class="selo-metrica">0 palavras</span>
                            <span id="contador-caracteres" class="selo-metrica">0 caracteres</span>
                            <span id="tempo-leitura" class="selo-metrica">~0 min de leitura</span>
                            <span class="selo-status-rascunho">● Rascunho privado seguro</span>
                        </div>
                        <button type="button" id="btn-zen" class="botao-zen-toggle" title="Alternar modo silencioso/foco"><?= folivro_icone('livro-aberto-seta', 'folivro-icon-xs') ?> Modo Silencioso</button>
                    </footer>
                </section>

                <!-- PAINEL LATERAL: METADADOS, CAPA E PUBLICAÇÃO (DESKTOP) -->
                <aside class="estudio-painel-lateral" id="estudio-painel">
                    <div class="card-estudio">
                        <h3>Detalhes da Obra</h3>

                        <label for="sinopse">Sinopse da história
                            <textarea id="sinopse" name="sinopse" maxlength="5000" rows="3" placeholder="Apresente brevemente o universo e a trama..."><?= $old('sinopse') ?></textarea>
                        </label>

                        <?php
                        $generosDisponiveis = folivro_generos_disponiveis();
                        $generosPostados = is_array($_POST['generos_historias'] ?? null) ? $_POST['generos_historias'] : [];
                        ?>
                        <div class="bloco-seletor-generos" role="group" aria-labelledby="rotulo-generos-estudio">
                            <div class="topo-seletor-generos">
                                <span id="rotulo-generos-estudio" class="rotulo-campo-estudio">Gêneros literários da obra</span>
                                <span class="contador-generos-badge" id="contador-generos-estudio">Até 5 gêneros</span>
                            </div>
                            <div class="seletor-generos-grade" id="grade-generos-estudio">
                                <?php foreach ($generosDisponiveis as $genItem): ?>
                                    <?php $genMarcado = in_array($genItem, $generosPostados, true); ?>
                                    <label class="chip-genero-escolha <?= $genMarcado ? 'ativo' : '' ?>">
                                        <input type="checkbox" name="generos_historias[]" value="<?= folivro_escape($genItem) ?>" <?= $genMarcado ? 'checked' : '' ?>>
                                        <span><?= folivro_escape($genItem) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="generos_personalizados" id="generos_personalizados" class="input-genero-personalizado" maxlength="120" value="<?= $old('generos_personalizados') ?>" placeholder="Outro gênero (opcional, separe por vírgula)...">
                        </div>

                        <label for="capa_historias">Imagem de capa
                            <input id="capa_historias" type="file" name="capa_historias" accept="image/jpeg,image/png,image/webp">
                            <small style="color:var(--texto-apoio); font-size:12px;">JPG, PNG ou WebP até 2 MB</small>
                            <div id="preview-capa-estudio" style="display:none; margin-top:8px;">
                                <img id="img-preview-capa-estudio" src="" alt="Prévia da capa" style="width:110px; aspect-ratio:2/3; object-fit:cover; border-radius:8px; border:1px solid var(--borda-forte);">
                            </div>
                        </label>

                        <?php $classifSelecionada = folivro_normalizar_classificacao($oldRaw('aviso_conteudo_historias') ?: 'livre'); ?>
                        <label for="aviso_conteudo_historias">Classificação indicativa e aviso de conteúdo sensível
                            <select id="aviso_conteudo_historias" name="aviso_conteudo_historias" required>
                                <option value="livre" <?= $classifSelecionada === 'livre' ? 'selected' : '' ?>>Livre (L) — Sem conteúdo sensível</option>
                                <option value="10" <?= $classifSelecionada === '10' ? 'selected' : '' ?>>+10 anos — Violência fantasiosa ou linguagem leve</option>
                                <option value="12" <?= $classifSelecionada === '12' ? 'selected' : '' ?>>+12 anos — Agressões moderadas ou temas dramáticos sensíveis</option>
                                <option value="14" <?= $classifSelecionada === '14' ? 'selected' : '' ?>>+14 anos — Violência mais intensa, drogas lícitas ou temas sensíveis</option>
                                <option value="16" <?= $classifSelecionada === '16' ? 'selected' : '' ?>>+16 anos — Violência gráfica, drogas ou temas psicológicos intensos</option>
                                <?php if ($maiorDeIdadeAutor): ?>
                                    <option value="18" <?= $classifSelecionada === '18' ? 'selected' : '' ?>>+18 anos — Conteúdo adulto/sensível (sexo explícito, violência extrema)</option>
                                <?php endif; ?>
                            </select>
                            <?php if (!$maiorDeIdadeAutor): ?>
                                <small style="color:var(--texto-apoio); font-size:12px;">Autores menores de 18 anos não podem publicar obras com classificação indicativa +18.</small>
                            <?php endif; ?>
                            <div class="resumo-classificacao-seletor" id="resumo-classificacao-estudio" aria-live="polite">
                                <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($classifSelecionada) ?>" id="badge-classificacao-estudio">
                                    <?= folivro_escape(folivro_classificacao_sigla($classifSelecionada)) ?>
                                </span>
                                <span class="texto-classificacao-estudio" id="texto-classificacao-estudio">
                                    <?= $classifSelecionada === '18'
                                        ? 'Aviso de conteúdo sensível (+18): conteúdo adulto exclusivo para maiores de 18 anos (violência extrema, sexo explícito, drogas ou temas altamente sensíveis). Para menores de 18 anos, a capa fica embaçada com olhinho bloqueado e o título oculto.'
                                        : 'Livre para todos os públicos: sem violência, linguagem imprópria, drogas ou conteúdo sexual.' ?>
                                </span>
                            </div>
                        </label>
                    </div>

                    <div class="card-estudio">
                        <h3>Publicação &amp; Loja</h3>

                        <label for="origem_obra">Origem da obra
                            <select id="origem_obra" name="origem_obra" required>
                                <option value="autoral_original">Obra original escrita por mim</option>
                                <option value="autorizacao_documentada">Obra com autorização do titular</option>
                                <option value="fanfic_sem_autorizacao">Fanfic ou adaptação (sujeita à revisão)</option>
                                <option value="dominio_publico_nao_verificado">Domínio público (sujeita à revisão)</option>
                            </select>
                        </label>

                        <label for="licenca_historias">Licença
                            <select id="licenca_historias" name="licenca_historias" required>
                                <option value="direitos_reservados">Todos os direitos reservados</option>
                                <option value="cc_by_nc_nd">Creative Commons (não comercial)</option>
                                <option value="autorizacao_especifica">Licença informada na sinopse</option>
                            </select>
                        </label>

                        <?php if (folivro_pagamentos_ativos()): ?>
                        <label for="modalidade_distribuicao">Modelo na loja
                            <select id="modalidade_distribuicao" name="modalidade_distribuicao">
                                <option value="venda_simulada">Venda na loja (e-book)</option>
                                <option value="gratuita">Gratuito para leitores (R$ 0,00)</option>
                            </select>
                        </label>

                        <label for="preco">Preço na loja (R$)
                            <input id="preco" type="number" name="preco" min="0" max="99999999" step="0.01" value="<?= $old('preco') ?: '0.00' ?>" required>
                            <small id="resumo-liquido-autor" style="color:var(--texto-apoio); font-size:12px;"></small>
                        </label>
                        <?php else: ?>
                            <input type="hidden" name="modalidade_distribuicao" value="gratuita">
                            <input type="hidden" name="preco" value="0.00">
                        <?php endif; ?>

                        <div class="estudio-termos">
                            <label class="termo-check" id="bloco-declaracao-autoria">
                                <input type="checkbox" name="declaracao_autoria" id="declaracao_autoria" value="1" <?= isset($_POST['declaracao_autoria']) ? 'checked' : '' ?>>
                                <span><strong>Declaração autoral obrigatória:</strong> Declaro ser titular dos direitos autorais da obra e da capa (Lei 9.610/98). <strong style="color:#8b2635;">(Obrigatório para publicar ou enviar capa)</strong></span>
                            </label>
                            <?php if (folivro_pagamentos_ativos()): ?>
                            <label class="termo-check" id="bloco-declaracao-comercial">
                                <input type="checkbox" name="declaracao_comercializacao" id="declaracao_comercializacao" value="1" <?= isset($_POST['declaracao_comercializacao']) ? 'checked' : '' ?>>
                                <span>Autorizo a comercialização desta obra na loja. <strong style="color:#8b2635;">(Obrigatório para venda)</strong></span>
                            </label>
                            <?php endif; ?>
                            <label class="termo-check termo-publicar">
                                <input type="checkbox" name="publicar" id="checkbox-publicar-estudio" value="1" <?= isset($_POST['publicar']) ? 'checked' : '' ?>>
                                <span><strong>Publicar na loja ao salvar</strong> (desmarcado salva como rascunho)</span>
                            </label>
                        </div>

                        <button class="botao-adicionar-livro botao-salvar-obra" id="btn-submit-estudio" type="submit">Salvar como rascunho</button>
                    </div>
                </aside>
            </div>
        </form>
    </main>

    <script>
        (() => {
            const selectModalidade = document.getElementById('modalidade_distribuicao');
            const inputPreco = document.getElementById('preco');
            const blocoComercial = document.getElementById('bloco-declaracao-comercial');
            const checkComercial = document.getElementById('declaracao_comercializacao');
            const textareaCapitulo = document.getElementById('capitulo');
            const contadorPalavras = document.getElementById('contador-palavras');
            const contadorCaracteres = document.getElementById('contador-caracteres');
            const tempoLeitura = document.getElementById('tempo-leitura');
            const btnZen = document.getElementById('btn-zen');
            const estudioLayout = document.getElementById('estudio-layout');

            // Sincronização entre modalidade gratuita e preço
            if (selectModalidade && inputPreco) {
                function sincronizarModalidade() {
                    const gratuita = selectModalidade.value === 'gratuita';
                    if (gratuita) {
                        inputPreco.value = '0.00';
                        inputPreco.readOnly = true;
                    } else {
                        inputPreco.readOnly = false;
                    }
                    if (blocoComercial && checkComercial) {
                        blocoComercial.style.opacity = gratuita ? '0.5' : '1';
                    }
                }
                selectModalidade.addEventListener('change', sincronizarModalidade);
                sincronizarModalidade();
            }

            const editorCapitulo = document.getElementById('editor-capitulo');
            const toolbarFormatacao = document.querySelector('.estudio-toolbar-formatacao');
            const selectTamanho = document.getElementById('select-tamanho-fonte');
            const selectBloco = document.getElementById('select-bloco-estilo');
            const formEscrita = document.getElementById('form-escrita');
            const inputTitObra = document.getElementById('titulo');
            const inputTitCap = document.getElementById('titulo_capitulo');
            const abasLista = document.getElementById('abas-capitulos-lista');
            const btnEscreverMais = document.getElementById('btn-escrever-mais');
            const hiddenCapsBox = document.getElementById('capitulos-hidden-inputs');
            const contadorCapitulos = document.getElementById('contador-capitulos');

            // Gerenciamento de múltiplos capítulos em abas
            const chaveLocal = 'folivro_rascunho_estudio_v2_' + <?= (int) $uid ?>;
            let capitulos = <?= json_encode($capitulosIniciais, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
            if (!Array.isArray(capitulos) || capitulos.length === 0) {
                capitulos = [{ titulo: '', conteudo: '' }];
            }
            let indiceCapAtivo = 0;

            function salvarEstadoCapituloAtual() {
                if (!capitulos[indiceCapAtivo]) return;
                if (inputTitCap) {
                    capitulos[indiceCapAtivo].titulo = inputTitCap.value;
                }
                if (editorCapitulo) {
                    const textoPuro = editorCapitulo.innerText.trim();
                    capitulos[indiceCapAtivo].conteudo = textoPuro === '' ? '' : editorCapitulo.innerHTML;
                }
            }

            function renderizarAbasCapitulos() {
                if (!abasLista) return;
                abasLista.innerHTML = '';
                capitulos.forEach((cap, idx) => {
                    const wrap = document.createElement('div');
                    wrap.className = 'aba-capitulo-item' + (idx === indiceCapAtivo ? ' ativa' : '');

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'aba-capitulo-tab';
                    btn.setAttribute('role', 'tab');
                    btn.setAttribute('aria-selected', idx === indiceCapAtivo ? 'true' : 'false');
                    const tituloCurto = (cap.titulo || '').trim();
                    btn.textContent = tituloCurto ? `Cap. ${idx + 1}: ${tituloCurto.slice(0, 18)}${tituloCurto.length > 18 ? '…' : ''}` : `Capítulo ${idx + 1}`;
                    btn.title = tituloCurto ? `Capítulo ${idx + 1} — ${tituloCurto}` : `Capítulo ${idx + 1}`;
                    btn.addEventListener('click', () => selecionarCapitulo(idx));
                    wrap.appendChild(btn);

                    if (capitulos.length > 1) {
                        const btnRem = document.createElement('button');
                        btnRem.type = 'button';
                        btnRem.className = 'btn-remover-aba-cap';
                        btnRem.setAttribute('aria-label', `Remover Capítulo ${idx + 1}`);
                        btnRem.title = `Remover Capítulo ${idx + 1}`;
                        btnRem.textContent = '×';
                        btnRem.addEventListener('click', (ev) => {
                            ev.stopPropagation();
                            removerCapitulo(idx);
                        });
                        wrap.appendChild(btnRem);
                    }
                    abasLista.appendChild(wrap);
                });

                if (contadorCapitulos) {
                    contadorCapitulos.textContent = `${capitulos.length} capítulo${capitulos.length === 1 ? '' : 's'}`;
                }
            }

            function carregarCapituloNoEditor(idx) {
                indiceCapAtivo = Math.max(0, Math.min(capitulos.length - 1, idx));
                const cap = capitulos[indiceCapAtivo] || { titulo: '', conteudo: '' };
                if (inputTitCap) {
                    inputTitCap.value = cap.titulo || '';
                    inputTitCap.placeholder = `Capítulo ${indiceCapAtivo + 1}: Título do capítulo (máx. 120 caracteres)...`;
                    inputTitCap.setAttribute('aria-label', `Título do Capítulo ${indiceCapAtivo + 1}`);
                }
                if (editorCapitulo) {
                    editorCapitulo.innerHTML = cap.conteudo || '';
                }
                sincronizarEditor();
                renderizarAbasCapitulos();
            }

            function selecionarCapitulo(idx) {
                if (idx === indiceCapAtivo) return;
                salvarEstadoCapituloAtual();
                carregarCapituloNoEditor(idx);
                if (inputTitCap) inputTitCap.focus();
            }

            function adicionarNovoCapitulo() {
                if (capitulos.length >= 50) {
                    alert('Você pode escrever até 50 capítulos por vez.');
                    return;
                }
                salvarEstadoCapituloAtual();
                capitulos.push({ titulo: '', conteudo: '' });
                carregarCapituloNoEditor(capitulos.length - 1);
                salvarRascunhoLocal();
                if (inputTitCap) {
                    inputTitCap.focus();
                }
            }

            function removerCapitulo(idx) {
                if (capitulos.length <= 1) return;
                salvarEstadoCapituloAtual();
                const alvo = capitulos[idx];
                const temConteudo = (alvo.titulo && alvo.titulo.trim() !== '') || (alvo.conteudo && alvo.conteudo.replace(/<[^>]*>/g, '').trim() !== '');
                if (temConteudo && !confirm(`Deseja remover a aba do Capítulo ${idx + 1}? O texto escrito nela será descartado.`)) {
                    return;
                }
                capitulos.splice(idx, 1);
                const novoIdx = Math.min(indiceCapAtivo, capitulos.length - 1);
                carregarCapituloNoEditor(novoIdx);
                salvarRascunhoLocal();
            }

            if (btnEscreverMais) {
                btnEscreverMais.addEventListener('click', adicionarNovoCapitulo);
            }

            function salvarRascunhoLocal() {
                if (!inputTitObra) return;
                try {
                    localStorage.setItem(chaveLocal, JSON.stringify({
                        t: inputTitObra.value,
                        caps: capitulos,
                        idx: indiceCapAtivo
                    }));
                } catch (e) {}
            }

            // Sincronização entre o editor WYSIWYG e o textarea do formulário
            function sincronizarEditor() {
                if (!editorCapitulo || !textareaCapitulo) return;
                const textoPuro = editorCapitulo.innerText.trim();
                textareaCapitulo.value = textoPuro === '' ? '' : editorCapitulo.innerHTML;
                salvarEstadoCapituloAtual();
                atualizarMetricas();
                salvarRascunhoLocal();
            }

            // Restaura rascunho local se o formulário estiver vazio
            if (inputTitObra && inputTitCap && editorCapitulo) {
                const estaVazio = !inputTitObra.value && capitulos.length === 1 && !capitulos[0].titulo && !capitulos[0].conteudo;
                if (estaVazio) {
                    try {
                        const salvo = JSON.parse(localStorage.getItem(chaveLocal) || 'null');
                        if (salvo && Array.isArray(salvo.caps) && salvo.caps.length > 0) {
                            const temAlgo = (salvo.t || '').trim() !== '' || salvo.caps.some((c) => (c.titulo || '').trim() !== '' || (c.conteudo || '').trim() !== '');
                            if (temAlgo) {
                                inputTitObra.value = salvo.t || '';
                                capitulos = salvo.caps.slice(0, 50).map((c) => ({
                                    titulo: String(c.titulo || ''),
                                    conteudo: String(c.conteudo || '')
                                }));
                                indiceCapAtivo = Math.max(0, Math.min(capitulos.length - 1, Number(salvo.idx) || 0));
                            }
                        }
                    } catch (e) {}
                }

                carregarCapituloNoEditor(indiceCapAtivo);

                inputTitObra.addEventListener('input', salvarRascunhoLocal);
                inputTitCap.addEventListener('input', () => {
                    salvarEstadoCapituloAtual();
                    renderizarAbasCapitulos();
                    salvarRascunhoLocal();
                });
                editorCapitulo.addEventListener('input', sincronizarEditor);
                editorCapitulo.addEventListener('keyup', sincronizarEditor);
                editorCapitulo.addEventListener('blur', sincronizarEditor);
                editorCapitulo.addEventListener('paste', () => setTimeout(sincronizarEditor, 20));
            }

            // Ações dos botões da barra de formatação
            if (toolbarFormatacao && editorCapitulo) {
                toolbarFormatacao.addEventListener('click', (e) => {
                    const btn = e.target.closest('.toolbar-btn');
                    if (!btn) return;
                    e.preventDefault();
                    const cmd = btn.getAttribute('data-cmd');
                    const val = btn.getAttribute('data-val') || null;
                    if (cmd) {
                        editorCapitulo.focus();
                        document.execCommand(cmd, false, val);
                        sincronizarEditor();
                    }
                });

                if (selectTamanho) {
                    selectTamanho.addEventListener('change', () => {
                        const val = selectTamanho.value;
                        if (val) {
                            editorCapitulo.focus();
                            document.execCommand('fontSize', false, val);
                            selectTamanho.value = '';
                            sincronizarEditor();
                        }
                    });
                }

                if (selectBloco) {
                    selectBloco.addEventListener('change', () => {
                        const val = selectBloco.value;
                        if (val) {
                            editorCapitulo.focus();
                            document.execCommand('formatBlock', false, val === 'blockquote' ? 'blockquote' : `<${val}>`);
                            selectBloco.value = 'p';
                            sincronizarEditor();
                        }
                    });
                }
            }

            if (formEscrita && editorCapitulo && textareaCapitulo) {
                formEscrita.addEventListener('submit', (ev) => {
                    salvarEstadoCapituloAtual();

                    // Valida que cada capítulo preenchido (e o capítulo 1) tenha título e texto
                    for (let i = 0; i < capitulos.length; i++) {
                        const t = (capitulos[i].titulo || '').trim();
                        const textoLimpo = (capitulos[i].conteudo || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
                        if (i > 0 && t === '' && textoLimpo === '') {
                            continue; // aba extra vazia será ignorada no servidor
                        }
                        if (t === '') {
                            ev.preventDefault();
                            carregarCapituloNoEditor(i);
                            if (inputTitCap) inputTitCap.focus();
                            alert(`Preencha o título do Capítulo ${i + 1} antes de salvar.`);
                            return;
                        }
                        if (textoLimpo === '') {
                            ev.preventDefault();
                            carregarCapituloNoEditor(i);
                            editorCapitulo.focus();
                            alert(`Escreva o conteúdo do Capítulo ${i + 1} antes de salvar.`);
                            return;
                        }
                    }

                    // Sincroniza campos ocultos de todos os capítulos para o POST
                    if (hiddenCapsBox) {
                        hiddenCapsBox.innerHTML = '';
                        capitulos.forEach((cap) => {
                            const inpT = document.createElement('input');
                            inpT.type = 'hidden';
                            inpT.name = 'capitulos_titulos[]';
                            inpT.value = cap.titulo || '';
                            hiddenCapsBox.appendChild(inpT);

                            const inpC = document.createElement('textarea');
                            inpC.name = 'capitulos_conteudos[]';
                            inpC.value = cap.conteudo || '';
                            hiddenCapsBox.appendChild(inpC);
                        });
                    }

                    // Mantém campos legados com o Capítulo 1
                    if (textareaCapitulo && capitulos[0]) {
                        textareaCapitulo.value = capitulos[0].conteudo || '';
                    }
                    try { localStorage.removeItem(chaveLocal); } catch (e) {}
                });
            }

            // Contador dinâmico de palavras e estimativa de leitura baseado no texto visível
            function atualizarMetricas() {
                const texto = editorCapitulo ? editorCapitulo.innerText.trim() : (textareaCapitulo ? textareaCapitulo.value.trim() : '');
                const caracteres = texto.length;
                const palavras = texto === '' ? 0 : texto.split(/\s+/).length;
                const minutos = Math.max(1, Math.ceil(palavras / 200));

                if (contadorPalavras) contadorPalavras.textContent = `${palavras} palavra${palavras === 1 ? '' : 's'}`;
                if (contadorCaracteres) contadorCaracteres.textContent = `${caracteres} caractere${caracteres === 1 ? '' : 's'}`;
                if (tempoLeitura) tempoLeitura.textContent = palavras > 0 ? `~${minutos} min de leitura` : '~0 min de leitura';
            }

            atualizarMetricas();

            // Alternância suave do Modo Silencioso / Foco
            if (btnZen && estudioLayout) {
                const iconeZenEstudio = <?= json_encode(folivro_icone('livro-aberto-seta', 'folivro-icon-xs')) ?>;
                btnZen.addEventListener('click', () => {
                    const modoZen = estudioLayout.classList.toggle('modo-zen');
                    btnZen.innerHTML = modoZen
                        ? `${iconeZenEstudio} Ver Painel`
                        : `${iconeZenEstudio} Modo Silencioso`;
                });
            }

            // Atualização dinâmica do selo de classificação indicativa e aviso de conteúdo sensível no estúdio
            const selectClassif = document.getElementById('aviso_conteudo_historias');
            const badgeClassif = document.getElementById('badge-classificacao-estudio');
            const textoClassif = document.getElementById('texto-classificacao-estudio');
            if (selectClassif && badgeClassif && textoClassif) {
                const descricoesIdade = {
                    livre: 'Livre para todos os públicos: sem violência, linguagem imprópria, drogas ou conteúdo sexual.',
                    '10': 'Aviso de conteúdo (+10): pode conter violência fantasiosa leve, linguagem levemente imprópria ou conflitos suaves.',
                    '12': 'Aviso de conteúdo (+12): pode conter agressões moderadas, linguagem depreciativa, insinuações leves ou temas dramáticos sensíveis.',
                    '14': 'Aviso de conteúdo (+14): pode conter violência mais intensa, uso de álcool/drogas lícitas, linguagem chula ou temas sensíveis (luto, traumas).',
                    '16': 'Aviso de conteúdo (+16): pode conter violência gráfica moderada, drogas ilícitas, conteúdo sexual não explícito ou temas psicológicos intensos.',
                    '18': 'Aviso de conteúdo sensível (+18): conteúdo adulto exclusivo para maiores de 18 anos (violência extrema, sexo explícito, drogas ou temas altamente sensíveis). Para menores de 18 anos, a capa fica embaçada com olhinho bloqueado e o título fica oculto.'
                };
                const atualizarClassifUI = () => {
                    const val = selectClassif.value || 'livre';
                    badgeClassif.className = `selo-classificacao-idade selo-idade-${val}`;
                    badgeClassif.textContent = val === 'livre' ? 'L' : `+${val}`;
                    textoClassif.textContent = descricoesIdade[val] || descricoesIdade.livre;
                };
                selectClassif.addEventListener('change', atualizarClassifUI);
                atualizarClassifUI();
            }

            // Controle interativo de seleção de gêneros literários (máx. 5)
            const gradeGeneros = document.getElementById('grade-generos-estudio');
            const contadorGeneros = document.getElementById('contador-generos-estudio');
            if (gradeGeneros) {
                const checksGeneros = Array.from(gradeGeneros.querySelectorAll('input[type="checkbox"]'));
                const atualizarGenerosUI = (ultimoClicado = null) => {
                    let selecionados = checksGeneros.filter((c) => c.checked);
                    if (selecionados.length > 5 && ultimoClicado) {
                        ultimoClicado.checked = false;
                        selecionados = checksGeneros.filter((c) => c.checked);
                    }
                    checksGeneros.forEach((chk) => {
                        const chip = chk.closest('.chip-genero-escolha');
                        if (chip) chip.classList.toggle('ativo', chk.checked);
                    });
                    if (contadorGeneros) {
                        contadorGeneros.textContent = `${selecionados.length}/5 selecionado${selecionados.length === 1 ? '' : 's'}`;
                    }
                };
                checksGeneros.forEach((chk) => {
                    chk.addEventListener('change', () => atualizarGenerosUI(chk));
                });
                atualizarGenerosUI();
            }

            // Prévia da capa ao selecionar arquivo
            const inputCapa = document.getElementById('capa_historias');
            const boxPrevCapa = document.getElementById('preview-capa-estudio');
            const imgPrevCapa = document.getElementById('img-preview-capa-estudio');
            if (inputCapa && boxPrevCapa && imgPrevCapa) {
                inputCapa.addEventListener('change', () => {
                    const arq = inputCapa.files && inputCapa.files[0];
                    if (!arq) {
                        boxPrevCapa.style.display = 'none';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        imgPrevCapa.src = String(e.target.result || '');
                        boxPrevCapa.style.display = 'block';
                    };
                    reader.readAsDataURL(arq);
                });
            }

            // Cálculo em tempo real da receita líquida do autor e texto dinâmico do botão
            const inputPrecoEstudio = document.getElementById('preco');
            const resumoLiquido = document.getElementById('resumo-liquido-autor');
            const chkPublicar = document.getElementById('checkbox-publicar-estudio');
            const btnSubmitEstudio = document.getElementById('btn-submit-estudio');
            const taxaPctEstudio = <?= json_encode($taxaLojaEstudio) ?>;
            const pubImediata = <?= json_encode($pubImediataAtiva) ?>;

            function atualizarResumoPreco() {
                if (!inputPrecoEstudio || !resumoLiquido) return;
                const val = parseFloat(inputPrecoEstudio.value || '0');
                if (Number.isNaN(val) || val <= 0) {
                    resumoLiquido.textContent = 'Obra gratuita na loja (sem cobrança).';
                } else {
                    const liq = val * (1 - (taxaPctEstudio / 100));
                    resumoLiquido.textContent = `Você recebe R$ ${liq.toFixed(2).replace('.', ',')} por venda (taxa de ${taxaPctEstudio}%).`;
                }
            }
            if (inputPrecoEstudio) {
                inputPrecoEstudio.addEventListener('input', atualizarResumoPreco);
                atualizarResumoPreco();
            }

            const chkAutoria = document.getElementById('declaracao_autoria');
            const atualizarObrigatoriedadeAutoria = () => {
                if (!chkAutoria) return;
                const temCapa = Boolean(inputCapa && inputCapa.files && inputCapa.files.length > 0);
                const vaiPublicar = Boolean(chkPublicar && chkPublicar.checked);
                chkAutoria.required = vaiPublicar || temCapa;
            };
            if (inputCapa) {
                inputCapa.addEventListener('change', atualizarObrigatoriedadeAutoria);
            }
            if (chkPublicar && btnSubmitEstudio) {
                const atualizarTextoBotao = () => {
                    if (!chkPublicar.checked) {
                        btnSubmitEstudio.textContent = 'Salvar como rascunho';
                    } else {
                        btnSubmitEstudio.textContent = pubImediata ? 'Publicar na loja' : 'Enviar para aprovação';
                    }
                    atualizarObrigatoriedadeAutoria();
                };
                chkPublicar.addEventListener('change', atualizarTextoBotao);
                atualizarTextoBotao();
            } else {
                atualizarObrigatoriedadeAutoria();
            }
        })();
    </script>
</body>

</html>
