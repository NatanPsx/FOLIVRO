<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $erro = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $title            = trim((string) ($_POST['titulo'] ?? ''));
        $synopsis         = trim((string) ($_POST['sinopse'] ?? ''));
        $chapter          = trim((string) ($_POST['capitulo'] ?? ''));
        $chapterTitle     = trim((string) ($_POST['titulo_capitulo'] ?? ''));
        $modalidade       = ($_POST['modalidade_distribuicao'] ?? 'venda_simulada') === 'gratuita' ? 'gratuita' : 'venda_simulada';
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

        if ($title === '' || $chapter === '' || $chapterTitle === ''
            || mb_strlen($title, 'UTF-8') > 200 || mb_strlen($chapterTitle, 'UTF-8') > 200
            || mb_strlen($synopsis, 'UTF-8') > 5000 || strlen($chapter) > 500000
            || !is_float($price) || $price < 0 || $price > 99999999) {
            $erro = 'Preencha título, primeiro capítulo e preço válido.';
        } elseif ($generosString === '') {
            $erro = 'Selecione pelo menos um gênero literário para a sua obra (ou informe um gênero personalizado).';
        } elseif ($publish && !$declareAutoria) {
            $erro = 'Para disponibilizar uma obra ao público, confirme a declaração de titularidade/autoria para reprodução e publicação.';
        } elseif ($publish && $modalidade === 'venda_simulada' && !$declareComercial) {
            $erro = 'A permissão para publicar gratuitamente é separada da permissão para vender. Para comercializar na loja, confirme também a declaração específica de comercialização.';
        } elseif ($publish && $modalidade === 'venda_simulada' && $licenca === 'cc_by_nc_nd' && $price > 0) {
            $erro = 'A licença Creative Commons BY-NC-ND 4.0 é restrita a uso não comercial. Selecione outra licença ou publique gratuitamente.';
        } else {
            // Verifica se o mesmo material (capítulo isolado ou título+capítulo) já foi removido pela moderação.
            $hashConteudo = folivro_content_hash($title . ' ' . $chapter);
            $hashCapitulo = folivro_content_hash($chapter);
            if (folivro_conteudo_foi_removido($pdo, $title, $chapter, 0)) {
                $erro = 'Bloqueio de segurança: este conteúdo corresponde a material anteriormente removido pela moderação por violação de direitos autorais. Não é permitida a republicação automática; utilize o recurso de contestação no painel da obra original.';
            }

            $caminhoCapa = null;
            if ($erro === '' && !empty($_FILES['capa_historias']['name'])) {
                if (!$declareAutoria) {
                    $erro = 'Para enviar imagem de capa, confirme que a arte é de sua autoria ou possui licença/autorização verificada.';
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
                $avalSegCap1 = folivro_avaliar_seguranca_capitulo($pdo, $title, $chapterTitle, $chapter, $origemObra, 0);
                $baseLegalIncerta = in_array($origemObra, ['fanfic_sem_autorizacao', 'dominio_publico_nao_verificado'], true);
                $publicarAutomatico = ($publish && !$baseLegalIncerta && ($qualificacaoAutor['qualificado_automatico'] || $aprovAutoGlobal) && $avalSegCap1['acao'] === 'permitir');

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
                    $ch->execute([
                        $storyId, $chapterTitle, $chapter, $hashCapitulo,
                        1, $statusCapNovo, $modoPubCapNovo, $revCapNovo,
                        $publicarAutomatico ? 1 : 0,
                    ]);
                    $newCapId = (int) $pdo->lastInsertId();

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
                        folivro_registrar_historico_moderacao(
                            $pdo,
                            $uid,
                            null,
                            'publicacao_automatica_capitulo',
                            "Obra '{$title}' e Capítulo 1 ({$chapterTitle}) publicados automaticamente (sujeitos à moderação posterior).",
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

                    if ($publish && $baseLegalIncerta) {
                        $erro = $motivoBloqueio . ' A obra foi salva apenas como rascunho privado.';
                    } elseif ($publicarAutomatico) {
                        $sucesso = 'Você está qualificado para publicação automática. Este capítulo foi publicado e está sujeito à moderação posterior.';
                    } elseif ($publish) {
                        $sucesso = 'Sua publicação foi encaminhada para revisão humana.';
                    } else {
                        $sucesso = 'Rascunho privado salvo em Livros escritos.';
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
    <link rel="stylesheet" href="layout.css?v=20261104_20">
    <link rel="stylesheet" href="home.css?v=20261104_20">
</head>

<body>

    <?php $menuAtivo = 'escrever'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main id="conteudo-principal" class="conteudo">
        <div class="cabecalho-estudio">
            <h1>Estúdio de Escrita &amp; Publicação</h1>
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
                        <input id="titulo" name="titulo" class="input-titulo-manuscrito" maxlength="200" required placeholder="Título do seu livro..." value="<?= $old('titulo') ?>" aria-label="Título da obra" autocomplete="off">
                        <input id="titulo_capitulo" name="titulo_capitulo" class="input-capitulo-manuscrito" maxlength="200" required placeholder="Capítulo 1: Título do primeiro capítulo..." value="<?= $old('titulo_capitulo') ?>" aria-label="Título do primeiro capítulo" autocomplete="off">
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
                        <textarea id="capitulo" name="capitulo" style="display:none;" required><?= $old('capitulo') ?></textarea>
                    </div>

                    <footer class="estudio-barra-zen">
                        <div class="estudio-metricas">
                            <span id="contador-palavras" class="selo-metrica">0 palavras</span>
                            <span id="contador-caracteres" class="selo-metrica">0 caracteres</span>
                            <span id="tempo-leitura" class="selo-metrica">~0 min de leitura</span>
                            <span class="selo-status-rascunho">● Rascunho privado seguro</span>
                        </div>
                        <button type="button" id="btn-zen" class="botao-zen-toggle" title="Alternar modo silencioso/foco"><img src="img/icones/leaf.png" alt="" class="icone-btn-png"> Modo Silencioso</button>
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
                        <label for="aviso_conteudo_historias">Classificação indicativa (idade)
                            <select id="aviso_conteudo_historias" name="aviso_conteudo_historias" required>
                                <option value="livre" <?= $classifSelecionada === 'livre' ? 'selected' : '' ?>>Livre — Para todos os públicos (L)</option>
                                <option value="10" <?= $classifSelecionada === '10' ? 'selected' : '' ?>>10 anos — Não recomendado para menores de 10 anos (+10)</option>
                                <option value="12" <?= $classifSelecionada === '12' ? 'selected' : '' ?>>12 anos — Não recomendado para menores de 12 anos (+12)</option>
                                <option value="14" <?= $classifSelecionada === '14' ? 'selected' : '' ?>>14 anos — Não recomendado para menores de 14 anos (+14)</option>
                                <option value="16" <?= $classifSelecionada === '16' ? 'selected' : '' ?>>16 anos — Não recomendado para menores de 16 anos (+16)</option>
                                <option value="18" <?= $classifSelecionada === '18' ? 'selected' : '' ?>>18 anos — Proibido para menores de 18 anos (+18)</option>
                            </select>
                            <div class="resumo-classificacao-seletor" id="resumo-classificacao-estudio" aria-live="polite">
                                <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($classifSelecionada) ?>" id="badge-classificacao-estudio">
                                    <?= folivro_escape(folivro_classificacao_sigla($classifSelecionada)) ?>
                                </span>
                                <span class="texto-classificacao-estudio" id="texto-classificacao-estudio">
                                    <?= $classifSelecionada === '18'
                                        ? 'Obra +18: para leitores menores de 18 anos, a capa ficará embaçada com olhinho bloqueado e o título oculto.'
                                        : 'Exibida de acordo com a faixa etária padrão do Ministério da Justiça (ClassInd).' ?>
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

                        <div class="estudio-termos">
                            <label class="termo-check">
                                <input type="checkbox" name="declaracao_autoria" value="1">
                                <span>Declaro ser titular dos direitos autorais da obra e da capa (Lei 9.610/98).</span>
                            </label>
                            <label class="termo-check" id="bloco-declaracao-comercial">
                                <input type="checkbox" name="declaracao_comercializacao" id="declaracao_comercializacao" value="1">
                                <span>Autorizo a comercialização desta obra na loja.</span>
                            </label>
                            <label class="termo-check termo-publicar">
                                <input type="checkbox" name="publicar" id="checkbox-publicar-estudio" value="1">
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

            // Sincronização entre o editor WYSIWYG e o textarea do formulário
            function sincronizarEditor() {
                if (!editorCapitulo || !textareaCapitulo) return;
                const textoPuro = editorCapitulo.innerText.trim();
                if (textoPuro === '') {
                    textareaCapitulo.value = '';
                } else {
                    textareaCapitulo.value = editorCapitulo.innerHTML;
                }
                atualizarMetricas();
            }

            // Inicialização do conteúdo se houver valor prévio
            if (editorCapitulo && textareaCapitulo) {
                if (editorCapitulo.innerHTML.trim() === '' && textareaCapitulo.value.trim() !== '') {
                    editorCapitulo.innerHTML = textareaCapitulo.value;
                }
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
                formEscrita.addEventListener('submit', () => {
                    sincronizarEditor();
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
                btnZen.addEventListener('click', () => {
                    const modoZen = estudioLayout.classList.toggle('modo-zen');
                    btnZen.innerHTML = modoZen
                        ? '<img src="img/icones/book.png" alt="" class="icone-btn-png"> Ver Painel'
                        : '<img src="img/icones/leaf.png" alt="" class="icone-btn-png"> Modo Silencioso';
                });
            }

            // Atualização dinâmica do selo de classificação indicativa no estúdio
            const selectClassif = document.getElementById('aviso_conteudo_historias');
            const badgeClassif = document.getElementById('badge-classificacao-estudio');
            const textoClassif = document.getElementById('texto-classificacao-estudio');
            if (selectClassif && badgeClassif && textoClassif) {
                const descricoesIdade = {
                    livre: 'Livre para todas as idades (sem restrição etária).',
                    '10': 'Não recomendado para menores de 10 anos (linguagem ou temas leves).',
                    '12': 'Não recomendado para menores de 12 anos (conflitos moderados ou drama).',
                    '14': 'Não recomendado para menores de 14 anos (temas mais intensos).',
                    '16': 'Não recomendado para menores de 16 anos (temas maduros ou violência).',
                    '18': 'Exclusivo para maiores de 18 anos: para menores de 18 anos, a capa fica embaçada com olhinho bloqueado e o título fica oculto.'
                };
                selectClassif.addEventListener('change', () => {
                    const val = selectClassif.value || 'livre';
                    badgeClassif.className = `selo-classificacao-idade selo-idade-${val}`;
                    badgeClassif.textContent = val === 'livre' ? 'L' : `+${val}`;
                    textoClassif.textContent = descricoesIdade[val] || descricoesIdade.livre;
                });
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

            if (chkPublicar && btnSubmitEstudio) {
                const atualizarTextoBotao = () => {
                    if (!chkPublicar.checked) {
                        btnSubmitEstudio.textContent = 'Salvar como rascunho';
                    } else {
                        btnSubmitEstudio.textContent = pubImediata ? 'Publicar na loja' : 'Enviar para aprovação';
                    }
                };
                chkPublicar.addEventListener('change', atualizarTextoBotao);
                atualizarTextoBotao();
            }

            // Auto-save local no navegador (evita perda acidental do texto)
            const chaveLocal = 'folivro_rascunho_estudio_v1_' + <?= (int) $uid ?>;
            const inputTit = document.getElementById('titulo');
            const inputCap = document.getElementById('titulo_capitulo');
            if (inputTit && inputCap && editor && form) {
                if (!inputTit.value && !inputCap.value && editor.innerHTML.trim() === '') {
                    try {
                        const salvo = JSON.parse(localStorage.getItem(chaveLocal) || 'null');
                        if (salvo && (salvo.t || salvo.c || salvo.b)) {
                            inputTit.value = salvo.t || '';
                            inputCap.value = salvo.c || '';
                            editor.innerHTML = salvo.b || '';
                            sincronizarConteudo();
                            atualizarMetricas();
                        }
                    } catch (e) {}
                }
                const salvarLocal = () => {
                    try {
                        localStorage.setItem(chaveLocal, JSON.stringify({
                            t: inputTit.value,
                            c: inputCap.value,
                            b: editor.innerHTML
                        }));
                    } catch (e) {}
                };
                inputTit.addEventListener('input', salvarLocal);
                inputCap.addEventListener('input', salvarLocal);
                editor.addEventListener('input', salvarLocal);
                form.addEventListener('submit', () => {
                    try { localStorage.removeItem(chaveLocal); } catch (e) {}
                });
            }
        })();
    </script>
</body>

</html>
