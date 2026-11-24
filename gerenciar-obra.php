<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/loja.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

// Autorização no servidor: só o autor dono da obra a encontra.
$q = $pdo->prepare(
    'SELECT a.id_autor_dos_livros, a.id_historias, a.titulo_autor_dos_livros, a.preco_dos_livros_autor,
            a.modalidade_distribuicao, a.status_autor_dos_livros, a.declaracao_autoria,
            a.declaracao_comercializacao, a.revisao_publicacao, a.justificativa_revisao,
            a.data_revisao_publicacao, a.motivo_bloqueio_autor,
            h.sinopse_historias, h.capa_historias, h.generos_historias, h.status_historias,
            h.licenca_historias, h.aviso_conteudo_historias, h.origem_obra_historias, h.hash_conteudo_historias
       FROM autor_dos_livros a
       JOIN historias h ON h.id_historias = a.id_historias
      WHERE a.id_autor_dos_livros = ? AND a.id_autor = ?'
);
$q->execute([$id, $uid]);
$work = $q->fetch();
if (!$work) {
    http_response_code(404);
    exit('Obra não encontrada.');
}

$error = '';
$success = '';
$idHist = (int) $work['id_historias'];

// Verifica se há suspensão preventiva ativa por denúncia de direitos autorais em análise.
$suspStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM denuncias
      WHERE tipo_denuncias = 'obra_autoral' AND item_denuncia = ?
        AND status_denuncias IN ('aberta', 'em_analise') AND suspensao_preventiva = 1"
);
$suspStmt->execute([$id]);
$temSuspensaoPreventiva = (int) $suspStmt->fetchColumn() > 0;

$qualificacaoAutor = folivro_avaliar_qualificacao_autor($pdo, $uid, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $error = 'Sessão expirada. Atualize a página.';
    } else {
        $action = (string) ($_POST['acao'] ?? '');
        try {
            if ($action === 'editar_obra') {
                // RF11 / RF16 / RNF07 — Atualizar título, sinopse, preço, origem, licença, aviso de conteúdo e capa da obra.
                $novoTitulo     = trim((string) ($_POST['titulo_obra'] ?? ''));
                $novaSinopse    = trim((string) ($_POST['sinopse_obra'] ?? ''));
                $novaModalidade = (!folivro_pagamentos_ativos() || ($_POST['modalidade_distribuicao'] ?? 'venda_simulada') === 'gratuita') ? 'gratuita' : 'venda_simulada';
                $novoPreco      = $novaModalidade === 'gratuita' ? 0.00 : filter_var($_POST['preco_obra'] ?? null, FILTER_VALIDATE_FLOAT);
                $novaOrigem     = (string) ($_POST['origem_obra_historias'] ?? ($work['origem_obra_historias'] ?? 'autoral_original'));
                $novaLicenca    = (string) ($_POST['licenca_historias'] ?? 'direitos_reservados');
                $novoAviso      = folivro_normalizar_classificacao((string) ($_POST['aviso_conteudo_historias'] ?? 'livre'));
                $generosSel     = $_POST['generos_historias'] ?? [];
                $generosExtra   = trim((string) ($_POST['generos_personalizados'] ?? ''));
                $novosGeneros   = folivro_generos_para_string($generosSel, $generosExtra, 5);

                if (!in_array($novaOrigem, ['autoral_original', 'autorizacao_documentada', 'fanfic_sem_autorizacao', 'dominio_publico_nao_verificado'], true)) {
                    $novaOrigem = 'autoral_original';
                }
                if (!in_array($novaLicenca, ['direitos_reservados', 'cc_by_nc_nd', 'autorizacao_especifica'], true)) {
                    $novaLicenca = 'direitos_reservados';
                }
                if (!in_array($novoAviso, ['livre', '10', '12', '14', '16', '18'], true)) {
                    $novoAviso = 'livre';
                }

                if ($novoTitulo === '' || mb_strlen($novoTitulo, 'UTF-8') > 120
                    || mb_strlen($novaSinopse, 'UTF-8') > 5000
                    || !is_float($novoPreco) || $novoPreco < 0 || $novoPreco > 99999999) {
                    throw new RuntimeException('Informe título válido (até 120 caracteres), sinopse (até 5.000 caracteres) e preço não negativo.');
                }
                if ($novosGeneros === '') {
                    throw new RuntimeException('Selecione pelo menos um gênero literário para a obra (ou informe um gênero personalizado).');
                }
                if ($novaModalidade === 'venda_simulada' && $novaLicenca === 'cc_by_nc_nd' && $novoPreco > 0) {
                    throw new RuntimeException('A licença Creative Commons BY-NC-ND 4.0 não permite uso comercial. Altere a licença ou use publicação gratuita.');
                }

                // Verifica se o título combinado ao primeiro capítulo coincide com material removido pela moderação.
                $cap1Stmt = $pdo->prepare('SELECT conteudo_capitulos FROM capitulos WHERE id_historias = ? ORDER BY ordem_capitulos ASC LIMIT 1');
                $cap1Stmt->execute([$idHist]);
                $textoCap1 = (string) ($cap1Stmt->fetchColumn() ?: '');
                $novoHash = folivro_content_hash($novoTitulo . ' ' . $textoCap1);
                if (folivro_conteudo_foi_removido($pdo, $novoTitulo, $textoCap1, $id)) {
                    throw new RuntimeException('Bloqueio de segurança: este conteúdo corresponde a material anteriormente removido pela moderação por violação de direitos autorais.');
                }

                $novaCapa = $work['capa_historias'];
                if (!empty($_FILES['capa_historias']['name'])) {
                    $arqCapa = $_FILES['capa_historias'];
                    $tiposPermitidos = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                    ];
                    if (($arqCapa['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('Falha no envio da imagem de capa. Tente um arquivo menor.');
                    }
                    if (($arqCapa['size'] ?? 0) > 2 * 1024 * 1024) {
                        throw new RuntimeException('A capa da obra deve ter no máximo 2 MB.');
                    }
                    $infoCapa = @getimagesize($arqCapa['tmp_name']);
                    $mimeCapa = is_array($infoCapa) ? ($infoCapa['mime'] ?? '') : '';
                    if (!isset($tiposPermitidos[$mimeCapa])) {
                        throw new RuntimeException('Formato de capa inválido. Envie apenas JPG, PNG ou WebP.');
                    }
                    $dirCapas = __DIR__ . '/uploads/capas';
                    if (!is_dir($dirCapas)) {
                        mkdir($dirCapas, 0755, true);
                        file_put_contents($dirCapas . '/.htaccess', "Options -Indexes\nphp_flag engine off\n");
                    }
                    $nomeCapa = 'capa_' . $uid . '_' . bin2hex(random_bytes(8)) . '.' . $tiposPermitidos[$mimeCapa];
                    if (!move_uploaded_file($arqCapa['tmp_name'], $dirCapas . '/' . $nomeCapa)) {
                        throw new RuntimeException('Não foi possível salvar a imagem de capa.');
                    }
                    $novaCapa = 'uploads/capas/' . $nomeCapa;
                }

                // Alterações em obras já publicadas suspendem sua exibição e exigem nova revisão humana.
                $estavaPublicada = ($work['status_autor_dos_livros'] === 'a_venda' || $work['status_historias'] === 'publicada');
                $baseIncerta = in_array($novaOrigem, ['fanfic_sem_autorizacao', 'dominio_publico_nao_verificado'], true);
                $exigeNovaDeclaracaoVenda = ($novaModalidade === 'venda_simulada' && empty($work['declaracao_comercializacao']));

                $novoStatusObra = $work['status_autor_dos_livros'] === 'removido' ? 'removido' : 'rascunho';
                $novoStatusHist = $estavaPublicada ? 'despublicada' : ($work['status_historias'] === 'publicada' ? 'despublicada' : $work['status_historias']);
                $novaRevisao = $work['revisao_publicacao'] ?? 'nao_solicitada';
                $novoMotivoBloqueio = $work['motivo_bloqueio_autor'];

                if ($baseIncerta) {
                    $novaRevisao = 'recusada';
                    $novoMotivoBloqueio = 'Publicação bloqueada preventivamente: fanfic, tradução, adaptação ou domínio público não verificado não concedem autorização automática (Lei nº 9.610/1998).';
                } elseif ($estavaPublicada) {
                    $novaRevisao = $exigeNovaDeclaracaoVenda ? 'nao_solicitada' : 'pendente';
                    $novoMotivoBloqueio = $exigeNovaDeclaracaoVenda
                        ? 'Obra despublicada: confirme a declaração específica de comercialização para solicitar nova revisão humana.'
                        : 'Obra editada após publicação: exibição pública suspensa preventivamente até nova revisão humana da moderação.';
                }

                $pdo->beginTransaction();
                $pdo->prepare(
                    'UPDATE historias
                        SET titulo_historias = ?, sinopse_historias = ?, capa_historias = ?,
                            generos_historias = ?, origem_obra_historias = ?, licenca_historias = ?, aviso_conteudo_historias = ?,
                            hash_conteudo_historias = ?, status_historias = ?
                      WHERE id_historias = ? AND id_usuario = ?'
                )->execute([$novoTitulo, $novaSinopse !== '' ? $novaSinopse : null, $novaCapa, $novosGeneros, $novaOrigem, $novaLicenca, $novoAviso, $novoHash, $novoStatusHist, $idHist, $uid]);
                $pdo->prepare(
                    'UPDATE autor_dos_livros
                        SET titulo_autor_dos_livros = ?, preco_dos_livros_autor = ?,
                            modalidade_distribuicao = ?, status_autor_dos_livros = ?,
                            revisao_publicacao = ?, motivo_bloqueio_autor = ?
                      WHERE id_autor_dos_livros = ? AND id_autor = ?'
                )->execute([$novoTitulo, $novoPreco, $novaModalidade, $novoStatusObra, $novaRevisao, $novoMotivoBloqueio, $id, $uid]);
                $pdo->commit();

                $work['titulo_autor_dos_livros'] = $novoTitulo;
                $work['sinopse_historias'] = $novaSinopse;
                $work['generos_historias'] = $novosGeneros;
                $work['preco_dos_livros_autor'] = $novoPreco;
                $work['modalidade_distribuicao'] = $novaModalidade;
                $work['origem_obra_historias'] = $novaOrigem;
                $work['licenca_historias'] = $novaLicenca;
                $work['aviso_conteudo_historias'] = $novoAviso;
                $work['capa_historias'] = $novaCapa;
                $work['status_autor_dos_livros'] = $novoStatusObra;
                $work['status_historias'] = $novoStatusHist;
                $work['revisao_publicacao'] = $novaRevisao;
                $work['motivo_bloqueio_autor'] = $novoMotivoBloqueio;
                $success = $estavaPublicada
                    ? 'Dados atualizados. Por segurança autoral, alterações em obra publicada suspendem sua exibição e encaminham a obra para nova revisão humana.'
                    : 'Dados, origem, licença e classificação da obra atualizados com sucesso.';
            } elseif ($action === 'capitulo') {
                $title = trim((string) ($_POST['titulo'] ?? ''));
                $body  = trim((string) ($_POST['conteudo'] ?? ''));
                if ($title === '' || $body === '' || mb_strlen($title, 'UTF-8') > 120 || strlen($body) > 500000) {
                    throw new RuntimeException('Preencha o título (até 120 caracteres) e o conteúdo do capítulo.');
                }
                $avalSeg = folivro_avaliar_seguranca_capitulo(
                    $pdo,
                    (string) $work['titulo_autor_dos_livros'],
                    $title,
                    $body,
                    (string) ($work['origem_obra_historias'] ?? 'autoral_original'),
                    $id
                );
                if ($avalSeg['acao'] === 'bloquear') {
                    throw new RuntimeException($avalSeg['motivo']);
                }

                $hashCapitulo = folivro_content_hash($body);
                $qualificacaoAutor = folivro_avaliar_qualificacao_autor($pdo, $uid, true);
                $obraJaPublicada = ($work['status_autor_dos_livros'] === 'a_venda' && ($work['revisao_publicacao'] ?? '') === 'aprovada' && !$temSuspensaoPreventiva);
                $publicarAutomatico = (($qualificacaoAutor['qualificado_automatico'] || folivro_aprovacao_automatica_obras_ativa($pdo)) && $obraJaPublicada && $avalSeg['acao'] === 'permitir');

                $statusNovoCap = $publicarAutomatico ? 'publicado' : 'rascunho';
                $modoPubCap    = $publicarAutomatico ? 'automatica' : 'revisao_humana';
                $revisaoCap    = $publicarAutomatico
                    ? 'publicado_automatico'
                    : (($obraJaPublicada || ($work['revisao_publicacao'] ?? '') === 'pendente') ? 'pendente' : 'nao_solicitada');

                $pdo->beginTransaction();
                $next = $pdo->prepare('SELECT COALESCE(MAX(ordem_capitulos), 0) + 1 FROM capitulos WHERE id_historias = ? FOR UPDATE');
                $next->execute([$idHist]);
                $ordemNovo = (int) $next->fetchColumn();

                $insert = $pdo->prepare(
                    'INSERT INTO capitulos
                        (id_historias, titulo_capitulos, conteudo_capitulos, hash_conteudo_capitulos,
                         ordem_capitulos, status_capitulos, modo_publicacao_capitulos, revisao_capitulos,
                         data_revisao_capitulos)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, IF(?, CURRENT_TIMESTAMP, NULL))'
                );
                $insert->execute([
                    $idHist, $title, $body, $hashCapitulo,
                    $ordemNovo, $statusNovoCap, $modoPubCap, $revisaoCap,
                    $publicarAutomatico ? 1 : 0,
                ]);
                $idNovoCap = (int) $pdo->lastInsertId();

                if ($publicarAutomatico) {
                    folivro_registrar_historico_moderacao(
                        $pdo,
                        $uid,
                        null,
                        'publicacao_automatica_capitulo',
                        "Capítulo {$ordemNovo} ({$title}) publicado automaticamente e sujeito à moderação posterior.",
                        $qualificacaoAutor['modalidade'],
                        $qualificacaoAutor['modalidade'],
                        $id,
                        $idNovoCap
                    );
                }

                // Nota: Os capítulos anteriores já publicados permanecem disponíveis em obra.php (não despublica a obra).
                $pdo->commit();

                if ($publicarAutomatico) {
                    $success = 'Este capítulo foi publicado e está sujeito à moderação posterior.';
                } elseif ($avalSeg['acao'] === 'reter_revisao_humana') {
                    $success = $avalSeg['motivo'];
                } elseif ($obraJaPublicada) {
                    $success = 'Sua publicação foi encaminhada para revisão humana. Os capítulos anteriores já aprovados permanecem disponíveis para os leitores.';
                } else {
                    $success = 'Capítulo adicionado ao rascunho. Sua obra está sujeita à revisão humana.';
                }
            } elseif ($action === 'editar_capitulo') {
                // RF12 — Editar capítulo existente com moderação híbrida por capítulo.
                $idCap = filter_input(INPUT_POST, 'id_capitulos', FILTER_VALIDATE_INT) ?: 0;
                $title = trim((string) ($_POST['titulo'] ?? ''));
                $body  = trim((string) ($_POST['conteudo'] ?? ''));
                if ($idCap < 1 || $title === '' || $body === '' || mb_strlen($title, 'UTF-8') > 120 || strlen($body) > 500000) {
                    throw new RuntimeException('Informe título e conteúdo válidos para o capítulo.');
                }
                $avalSeg = folivro_avaliar_seguranca_capitulo(
                    $pdo,
                    (string) $work['titulo_autor_dos_livros'],
                    $title,
                    $body,
                    (string) ($work['origem_obra_historias'] ?? 'autoral_original'),
                    $id
                );
                if ($avalSeg['acao'] === 'bloquear') {
                    throw new RuntimeException($avalSeg['motivo']);
                }

                $hashEdit = folivro_content_hash($work['titulo_autor_dos_livros'] . ' ' . $body);
                $hashCapitulo = folivro_content_hash($body);
                $qualificacaoAutor = folivro_avaliar_qualificacao_autor($pdo, $uid, true);
                $obraJaPublicada = ($work['status_autor_dos_livros'] === 'a_venda' && ($work['revisao_publicacao'] ?? '') === 'aprovada' && !$temSuspensaoPreventiva);
                $publicarAutomatico = (($qualificacaoAutor['qualificado_automatico'] || folivro_aprovacao_automatica_obras_ativa($pdo)) && $obraJaPublicada && $avalSeg['acao'] === 'permitir');

                $statusCapEdit = $publicarAutomatico ? 'publicado' : 'rascunho';
                $modoPubCap    = $publicarAutomatico ? 'automatica' : 'revisao_humana';
                $revisaoCap    = $publicarAutomatico
                    ? 'publicado_automatico'
                    : (($obraJaPublicada || ($work['revisao_publicacao'] ?? '') === 'pendente') ? 'pendente' : 'nao_solicitada');

                $pdo->beginTransaction();
                $updCap = $pdo->prepare(
                    'UPDATE capitulos
                        SET titulo_capitulos = ?,
                            conteudo_capitulos = ?,
                            hash_conteudo_capitulos = ?,
                            status_capitulos = ?,
                            modo_publicacao_capitulos = ?,
                            revisao_capitulos = ?,
                            data_revisao_capitulos = IF(?, CURRENT_TIMESTAMP, data_revisao_capitulos)
                      WHERE id_capitulos = ? AND id_historias = ?'
                );
                $updCap->execute([
                    $title, $body, $hashCapitulo,
                    $statusCapEdit, $modoPubCap, $revisaoCap,
                    $publicarAutomatico ? 1 : 0,
                    $idCap, $idHist,
                ]);

                $ordStmt = $pdo->prepare('SELECT ordem_capitulos FROM capitulos WHERE id_capitulos = ? AND id_historias = ?');
                $ordStmt->execute([$idCap, $idHist]);
                $ordemEditada = (int) $ordStmt->fetchColumn();
                if ($ordemEditada === 1) {
                    $pdo->prepare('UPDATE historias SET hash_conteudo_historias = ? WHERE id_historias = ?')->execute([$hashEdit, $idHist]);
                }

                if ($publicarAutomatico) {
                    folivro_registrar_historico_moderacao(
                        $pdo,
                        $uid,
                        null,
                        'publicacao_automatica_capitulo',
                        "Edição do Capítulo {$ordemEditada} ({$title}) publicada automaticamente e sujeita à moderação posterior.",
                        $qualificacaoAutor['modalidade'],
                        $qualificacaoAutor['modalidade'],
                        $id,
                        $idCap
                    );
                }

                $pdo->commit();
                if ($publicarAutomatico) {
                    $success = 'Este capítulo foi publicado e está sujeito à moderação posterior.';
                } elseif ($avalSeg['acao'] === 'reter_revisao_humana') {
                    $success = $avalSeg['motivo'];
                } else {
                    $success = 'Capítulo atualizado. Sua publicação foi encaminhada para revisão humana.';
                }
            } elseif ($action === 'excluir_capitulo') {
                // RF12 — Excluir capítulo (mantendo ao menos 1 capítulo na obra).
                $idCap = filter_input(INPUT_POST, 'id_capitulos', FILTER_VALIDATE_INT) ?: 0;
                $totalCapStmt = $pdo->prepare('SELECT COUNT(*) FROM capitulos WHERE id_historias = ?');
                $totalCapStmt->execute([$idHist]);
                if ((int) $totalCapStmt->fetchColumn() <= 1) {
                    throw new RuntimeException('A obra deve manter pelo menos um capítulo.');
                }
                $pdo->prepare('DELETE FROM capitulos WHERE id_capitulos = ? AND id_historias = ?')->execute([$idCap, $idHist]);
                $success = 'Capítulo excluído.';
            } elseif ($action === 'publicar') {
                if (!isset($_POST['declaracao'])) {
                    throw new RuntimeException('Confirme a declaração de autoria e titularidade para solicitar a disponibilização da obra ao público.');
                }
                if (($work['modalidade_distribuicao'] ?? 'venda_simulada') === 'venda_simulada' && !isset($_POST['declaracao_comercializacao']) && empty($work['declaracao_comercializacao'])) {
                    throw new RuntimeException('A permissão para publicar gratuitamente é separada da permissão para vender. Confirme também a autorização específica para comercialização.');
                }
                if (($work['modalidade_distribuicao'] ?? 'venda_simulada') === 'venda_simulada' && ($work['licenca_historias'] ?? '') === 'cc_by_nc_nd' && (float) $work['preco_dos_livros_autor'] > 0) {
                    throw new RuntimeException('A licença Creative Commons BY-NC-ND 4.0 não permite uso comercial. Altere a licença ou publique gratuitamente.');
                }
                if ($work['status_autor_dos_livros'] === 'removido') {
                    throw new RuntimeException('Esta obra foi removida pela moderação e não pode ser republicada automaticamente. Utilize o formulário de contestação abaixo.');
                }
                if ($temSuspensaoPreventiva) {
                    throw new RuntimeException('Esta obra encontra-se sob suspensão preventiva devido a denúncia de direitos autorais em análise. Aguarde a decisão da moderação ou envie uma contestação.');
                }
                if (in_array($work['origem_obra_historias'] ?? '', ['fanfic_sem_autorizacao', 'dominio_publico_nao_verificado'], true)) {
                    $motivoBloqueio = 'Publicação bloqueada preventivamente: obras marcadas como fanfic/adaptação sem autorização documentada ou domínio público não verificado não podem ser aprovadas enquanto a base de direitos permanecer incerta.';
                    $pdo->prepare("UPDATE autor_dos_livros SET revisao_publicacao = 'recusada', status_autor_dos_livros = 'rascunho', motivo_bloqueio_autor = ? WHERE id_autor_dos_livros = ? AND id_autor = ?")
                        ->execute([$motivoBloqueio, $id, $uid]);
                    $work['revisao_publicacao'] = 'recusada';
                    $work['motivo_bloqueio_autor'] = $motivoBloqueio;
                    throw new RuntimeException($motivoBloqueio);
                }

                // Se o autor está qualificado para publicação automática e a obra cumpre as regras de direitos/segurança, libera com moderação posterior; caso contrário, encaminha para revisão humana.
                $qualificacaoAutor = folivro_avaliar_qualificacao_autor($pdo, $uid, true);
                $comercialOk = (($work['modalidade_distribuicao'] ?? 'venda_simulada') === 'venda_simulada') ? 1 : (int) $work['declaracao_comercializacao'];

                // Verifica segurança de todos os capítulos da obra antes de eventual publicação automática
                $todosCapsSeguros = true;
                $stmtCapsCheck = $pdo->prepare('SELECT titulo_capitulos, conteudo_capitulos FROM capitulos WHERE id_historias = ?');
                $stmtCapsCheck->execute([$idHist]);
                foreach ($stmtCapsCheck->fetchAll() as $capCheckRow) {
                    $avCap = folivro_avaliar_seguranca_capitulo(
                        $pdo,
                        (string) $work['titulo_autor_dos_livros'],
                        (string) $capCheckRow['titulo_capitulos'],
                        (string) $capCheckRow['conteudo_capitulos'],
                        (string) ($work['origem_obra_historias'] ?? 'autoral_original'),
                        $id
                    );
                    if ($avCap['acao'] !== 'permitir') {
                        $todosCapsSeguros = false;
                        break;
                    }
                }

                $liberarAutomaticoObra = (($qualificacaoAutor['qualificado_automatico'] || folivro_aprovacao_automatica_obras_ativa($pdo)) && $todosCapsSeguros);

                $pdo->beginTransaction();
                if ($liberarAutomaticoObra) {
                    $pdo->prepare(
                        "UPDATE autor_dos_livros
                            SET declaracao_autoria = 1, data_declaracao_autoria = CURRENT_TIMESTAMP,
                                declaracao_comercializacao = ?, data_declaracao_comercializacao = IF(?, CURRENT_TIMESTAMP, data_declaracao_comercializacao),
                                revisao_publicacao = 'aprovada',
                                motivo_bloqueio_autor = NULL,
                                status_autor_dos_livros = 'a_venda',
                                data_publicacao_autor = COALESCE(data_publicacao_autor, CURRENT_TIMESTAMP)
                          WHERE id_autor_dos_livros = ? AND id_autor = ?"
                    )->execute([$comercialOk, $comercialOk, $id, $uid]);
                    $pdo->prepare("UPDATE historias SET status_historias = 'publicada' WHERE id_historias = ? AND id_usuario = ?")
                        ->execute([$idHist, $uid]);
                    $pdo->prepare(
                        "UPDATE capitulos
                            SET status_capitulos = 'publicado',
                                modo_publicacao_capitulos = IF(revisao_capitulos = 'aprovada', modo_publicacao_capitulos, 'automatica'),
                                revisao_capitulos = IF(revisao_capitulos = 'aprovada', 'aprovada', 'publicado_automatico'),
                                data_revisao_capitulos = COALESCE(data_revisao_capitulos, CURRENT_TIMESTAMP)
                          WHERE id_historias = ? AND revisao_capitulos NOT IN ('recusada', 'oculto_moderacao')"
                    )->execute([$idHist]);

                    folivro_registrar_historico_moderacao(
                        $pdo,
                        $uid,
                        null,
                        'publicacao_automatica_capitulo',
                        "Obra '{$work['titulo_autor_dos_livros']}' e capítulos disponibilizados por autor qualificado (sujeitos à moderação posterior).",
                        $qualificacaoAutor['modalidade'],
                        $qualificacaoAutor['modalidade'],
                        $id,
                        null
                    );
                } else {
                    $motivoPendencia = 'Sua publicação foi encaminhada para revisão humana.';
                    $pdo->prepare(
                        "UPDATE autor_dos_livros
                            SET declaracao_autoria = 1, data_declaracao_autoria = CURRENT_TIMESTAMP,
                                declaracao_comercializacao = ?, data_declaracao_comercializacao = IF(?, CURRENT_TIMESTAMP, data_declaracao_comercializacao),
                                revisao_publicacao = 'pendente',
                                motivo_bloqueio_autor = ?,
                                status_autor_dos_livros = 'rascunho'
                          WHERE id_autor_dos_livros = ? AND id_autor = ?"
                    )->execute([$comercialOk, $comercialOk, $motivoPendencia, $id, $uid]);
                    $pdo->prepare("UPDATE capitulos SET revisao_capitulos = 'pendente' WHERE id_historias = ? AND status_capitulos = 'rascunho'")
                        ->execute([$idHist]);
                }

                $pdo->prepare("INSERT INTO termos_aceitos (id_usuario, tipo_termos, versao_termos, id_autor_dos_livros) VALUES (?, 'declaracao_autoria', ?, ?)")
                    ->execute([$uid, FOLIVRO_TERMOS_VERSAO, $id]);
                if ($comercialOk) {
                    $pdo->prepare("INSERT INTO termos_aceitos (id_usuario, tipo_termos, versao_termos, id_autor_dos_livros) VALUES (?, 'declaracao_comercializacao', ?, ?)")
                        ->execute([$uid, FOLIVRO_TERMOS_VERSAO, $id]);
                }
                $pdo->commit();

                if ($liberarAutomaticoObra) {
                    $work['status_autor_dos_livros'] = 'a_venda';
                    $work['status_historias'] = 'publicada';
                    $work['revisao_publicacao'] = 'aprovada';
                    $work['declaracao_comercializacao'] = $comercialOk;
                    $work['motivo_bloqueio_autor'] = null;
                    $success = 'Você está qualificado para publicação automática. Este capítulo foi publicado e está sujeito à moderação posterior.';
                } else {
                    $work['status_autor_dos_livros'] = 'rascunho';
                    $work['revisao_publicacao'] = 'pendente';
                    $work['declaracao_comercializacao'] = $comercialOk;
                    $work['motivo_bloqueio_autor'] = 'Sua publicação foi encaminhada para revisão humana.';
                    $success = 'Sua publicação foi encaminhada para revisão humana.';
                }
            } elseif ($action === 'pausar') {
                // RF13 / RF18 — Pausar venda e despublicar história da vitrine.
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE autor_dos_livros SET status_autor_dos_livros = 'suspenso' WHERE id_autor_dos_livros = ? AND id_autor = ? AND status_autor_dos_livros = 'a_venda'")
                    ->execute([$id, $uid]);
                $pdo->prepare("UPDATE historias SET status_historias = 'despublicada' WHERE id_historias = ? AND id_usuario = ?")
                    ->execute([$idHist, $uid]);
                $pdo->commit();
                $work['status_autor_dos_livros'] = 'suspenso';
                $work['status_historias'] = 'despublicada';
                $success = 'Venda pausada e história despublicada da loja. Quem já adquiriu continua com acesso, salvo em caso de suspensão por violação de direitos autorais.';
            } elseif ($action === 'contestar_denuncia') {
                $idDen = filter_input(INPUT_POST, 'id_denuncias', FILTER_VALIDATE_INT) ?: 0;
                $textoContestacao = trim((string) ($_POST['contestacao_autor'] ?? ''));
                if ($idDen < 1 || $textoContestacao === '' || mb_strlen($textoContestacao, 'UTF-8') > 3000) {
                    throw new RuntimeException('Informe os argumentos e referências de comprovação da sua contestação (até 3.000 caracteres).');
                }
                $updCont = $pdo->prepare(
                    "UPDATE denuncias
                        SET contestacao_autor = ?, data_contestacao = CURRENT_TIMESTAMP
                      WHERE id_denuncias = ? AND tipo_denuncias = 'obra_autoral' AND item_denuncia = ?"
                );
                $updCont->execute([$textoContestacao, $idDen, $id]);
                $success = 'Contestação registrada com sucesso. O caso será reavaliado por um moderador humano.';
            } else {
                $error = 'Ação inválida.';
            }
            if ($success !== '' && $error === '') {
                require_once __DIR__ . '/config/loja.php';
                folivro_loja_sincronizar_obra_estudio($pdo, $id);
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível atualizar a obra.';
        }
    }
}

$ch = $pdo->prepare(
    'SELECT id_capitulos, titulo_capitulos, conteudo_capitulos, ordem_capitulos,
            status_capitulos, modo_publicacao_capitulos, revisao_capitulos,
            justificativa_revisao_capitulos, data_revisao_capitulos
       FROM capitulos
      WHERE id_historias = ?
   ORDER BY ordem_capitulos'
);
$ch->execute([$idHist]);
$chapters = $ch->fetchAll();

// Reavalia a modalidade atual do autor para exibição fiel na interface
$qualificacaoAutor = folivro_avaliar_qualificacao_autor($pdo, $uid, false);

// Histórico de termos aceitos pelo autor para esta obra (transparência e auditoria).
$termosObraStmt = $pdo->prepare(
    'SELECT tipo_termos, versao_termos, data_aceite_termos
       FROM termos_aceitos
      WHERE id_usuario = ? AND id_autor_dos_livros = ?
   ORDER BY data_aceite_termos DESC'
);
$termosObraStmt->execute([$uid, $id]);
$termosObra = $termosObraStmt->fetchAll();

// Denúncias vinculadas à obra — NUNCA seleciona id_usuario ou dados pessoais do denunciante (privacidade RNF).
$denObraStmt = $pdo->prepare(
    "SELECT id_denuncias, motivo_denuncia, obra_referencia_denuncia, status_denuncias,
            suspensao_preventiva, justificativa_moderacao, contestacao_autor, data_contestacao,
            data_denuncia, data_resposta_denuncia
       FROM denuncias
      WHERE tipo_denuncias = 'obra_autoral' AND item_denuncia = ?
   ORDER BY data_denuncia DESC"
);
$denObraStmt->execute([$id]);
$denunciasObra = $denObraStmt->fetchAll();

$csrf = folivro_escape(folivro_csrf_token());
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gerenciar obra | FOLIVRO</title>
    <link rel="stylesheet" href="home.css?v=20261123_01">
    <link rel="stylesheet" href="layout.css?v=20261123_03">
</head>

<body>

    <?php $menuAtivo = 'biblioteca-autor'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main id="conteudo-principal" class="conteudo">
        <div class="gerenciar-obra-container">
            <nav class="migalhas-navegacao" aria-label="Navegação do autor">
                <a href="biblioteca-autor.php">← Voltar a Livros escritos</a>
                <span>·</span>
                <a href="obra.php?id=<?= (int) $id ?>">Visualizar página de leitura da obra ↗</a>
            </nav>

            <header class="cabecalho-gerenciar-obra">
                <div class="cabecalho-gerenciar-info">
                    <h1 class="titulo-com-icone-folivro"><?= folivro_icone('livro-indice-f', 'folivro-icon-lg') ?> <?= folivro_escape($work['titulo_autor_dos_livros']) ?></h1>
                    <?php $classifAtualWork = folivro_normalizar_classificacao($work['aviso_conteudo_historias'] ?? 'livre'); ?>
                    <div class="grade-selos-obra-gerenciar">
                        <span class="selo-status <?= $work['status_autor_dos_livros'] === 'a_venda' ? 'selo-comprado' : '' ?>">
                            Status: <?= folivro_escape(folivro_status_label($work['status_autor_dos_livros'])) ?>
                        </span>
                        <span class="selo-status <?= ($work['revisao_publicacao'] ?? '') === 'aprovada' ? 'selo-lido' : '' ?>">
                            Revisão: <?= folivro_escape(folivro_status_label($work['revisao_publicacao'] ?? 'nao_solicitada')) ?>
                        </span>
                        <span class="selo-status">
                            Modalidade: <?= folivro_escape(folivro_status_label($work['modalidade_distribuicao'] ?? 'venda_simulada')) ?>
                        </span>
                        <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($classifAtualWork) ?>" title="<?= folivro_escape(folivro_aviso_conteudo_label($classifAtualWork)) ?>">
                            <?= folivro_escape(folivro_classificacao_sigla($classifAtualWork)) ?>
                        </span>
                        <span class="selo-status">
                            <?= folivro_escape(folivro_aviso_conteudo_label($classifAtualWork)) ?>
                        </span>
                        <span class="selo-status">
                            <?= folivro_escape(folivro_licenca_label($work['licenca_historias'] ?? 'direitos_reservados')) ?>
                        </span>
                        <?php foreach (folivro_generos_lista((string) ($work['generos_historias'] ?? '')) as $gBadge): ?>
                            <span class="tag-genero-card">
                                <?= folivro_escape($gBadge) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </header>

            <div class="banner-modalidade-publicacao modal-pub-<?= folivro_escape($qualificacaoAutor['modalidade']) ?>" role="status">
                <strong>Modalidade de publicação:</strong>
                <span><?= folivro_escape($qualificacaoAutor['mensagem_interface']) ?></span>
            </div>

            <?php if (!empty($work['motivo_bloqueio_autor'])): ?>
                <p class="estado-vazio estado-erro" role="alert">
                    <strong>Situação de conformidade / moderação:</strong> <?= folivro_escape($work['motivo_bloqueio_autor']) ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($work['justificativa_revisao'])): ?>
                <p class="estado-vazio <?= ($work['revisao_publicacao'] ?? '') === 'aprovada' ? 'estado-sucesso' : 'estado-erro' ?>" role="status">
                    <strong>Parecer da revisão humana<?= !empty($work['data_revisao_publicacao']) ? ' (' . date('d/m/Y H:i', strtotime((string) $work['data_revisao_publicacao'])) . ')' : '' ?>:</strong>
                    <?= folivro_escape((string) $work['justificativa_revisao']) ?>
                </p>
            <?php endif; ?>

            <?php if ($error): ?>
                <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape($error) ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="estado-vazio estado-sucesso" role="status"><?= folivro_escape($success) ?></p>
            <?php endif; ?>

            <details class="configurar-meta painel-edicao-metadados-obra">
                <summary>Editar dados, gêneros literários, licença, classificação indicativa, preço e capa da obra</summary>
                <form method="post" enctype="multipart/form-data" class="progresso-form form-largura-total">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="acao" value="editar_obra">
                    <label for="titulo_obra">Título da obra (máx. 120 caracteres)
                        <input id="titulo_obra" name="titulo_obra" maxlength="120" required value="<?= folivro_escape($work['titulo_autor_dos_livros']) ?>">
                    </label>
                    <label for="sinopse_obra">Sinopse
                        <textarea id="sinopse_obra" name="sinopse_obra" maxlength="5000" rows="3"><?= folivro_escape((string) ($work['sinopse_historias'] ?? '')) ?></textarea>
                    </label>
                    <?php
                    $generosPadrao = folivro_generos_disponiveis();
                    $generosAtuaisObra = folivro_generos_lista((string) ($work['generos_historias'] ?? ''));
                    $generosExtrasObra = array_values(array_filter(
                        $generosAtuaisObra,
                        static fn(string $g): bool => !in_array($g, $generosPadrao, true)
                    ));
                    ?>
                    <div class="bloco-seletor-generos" role="group" aria-labelledby="rotulo-generos-gerenciar">
                        <div class="topo-seletor-generos">
                            <span id="rotulo-generos-gerenciar" class="rotulo-campo-estudio">Gêneros literários da obra</span>
                            <span class="contador-generos-badge" id="contador-generos-gerenciar">Até 5 gêneros</span>
                        </div>
                        <div class="seletor-generos-grade" id="grade-generos-gerenciar">
                            <?php foreach ($generosPadrao as $genOpt): ?>
                                <?php $genAtivo = in_array($genOpt, $generosAtuaisObra, true); ?>
                                <label class="chip-genero-escolha <?= $genAtivo ? 'ativo' : '' ?>">
                                    <input type="checkbox" name="generos_historias[]" value="<?= folivro_escape($genOpt) ?>" <?= $genAtivo ? 'checked' : '' ?>>
                                    <span><?= folivro_escape($genOpt) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" name="generos_personalizados" class="input-genero-personalizado" maxlength="120" value="<?= folivro_escape(implode(', ', $generosExtrasObra)) ?>" placeholder="Outro gênero personalizado (opcional, separe por vírgula)...">
                    </div>
                    <div class="grade-campos-dupla">
                        <label for="origem_obra_historias">Origem e base de direitos da obra
                            <select id="origem_obra_historias" name="origem_obra_historias">
                                <?php foreach ([
                                    'autoral_original' => 'Obra 100% autoral e inédita escrita por mim',
                                    'autorizacao_documentada' => 'Possuo autorização expressa e documentada do(a) titular para este uso',
                                    'fanfic_sem_autorizacao' => 'Fanfic, adaptação ou tradução sem autorização documentada (requer bloqueio/revisão humana)',
                                    'dominio_publico_nao_verificado' => 'Obra antiga / domínio público não verificado na jurisdição aplicável (requer revisão humana)',
                                ] as $kOrig => $vOrig): ?>
                                    <option value="<?= $kOrig ?>" <?= ($work['origem_obra_historias'] ?? 'autoral_original') === $kOrig ? 'selected' : '' ?>><?= $vOrig ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label for="licenca_historias">Licença e permissões declaradas
                            <select id="licenca_historias" name="licenca_historias">
                                <?php foreach ([
                                    'direitos_reservados' => 'Todos os direitos reservados pelo(a) autor(a)',
                                    'cc_by_nc_nd' => 'Creative Commons BY-NC-ND 4.0 (uso não comercial, sem derivadas)',
                                    'autorizacao_especifica' => 'Autorização documentada do(a) titular para uso específico',
                                ] as $kLic => $vLic): ?>
                                    <option value="<?= $kLic ?>" <?= ($work['licenca_historias'] ?? 'direitos_reservados') === $kLic ? 'selected' : '' ?>><?= $vLic ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="grade-campos-dupla">
                        <label for="aviso_conteudo_historias">Classificação indicativa e aviso de conteúdo sensível
                            <select id="aviso_conteudo_historias" name="aviso_conteudo_historias">
                                <?php foreach ([
                                    'livre' => 'Livre (L) — Sem conteúdo sensível',
                                    '10'    => '+10 anos — Violência fantasiosa ou linguagem leve',
                                    '12'    => '+12 anos — Agressões moderadas ou temas dramáticos sensíveis',
                                    '14'    => '+14 anos — Violência mais intensa, drogas lícitas ou temas sensíveis',
                                    '16'    => '+16 anos — Violência gráfica, drogas ou temas psicológicos intensos',
                                    '18'    => '+18 anos — Conteúdo adulto/sensível (sexo explícito, violência extrema; capa e título ocultos para menores)',
                                ] as $kAv => $vAv): ?>
                                    <option value="<?= $kAv ?>" <?= $classifAtualWork === $kAv ? 'selected' : '' ?>><?= $vAv ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php if (folivro_pagamentos_ativos()): ?>
                        <label for="modalidade_distribuicao">Modalidade de disponibilização
                            <select id="modalidade_distribuicao" name="modalidade_distribuicao">
                                <option value="venda_simulada" <?= ($work['modalidade_distribuicao'] ?? 'venda_simulada') === 'venda_simulada' ? 'selected' : '' ?>>Comercialização na loja (venda simulada)</option>
                                <option value="gratuita" <?= ($work['modalidade_distribuicao'] ?? '') === 'gratuita' ? 'selected' : '' ?>>Publicação gratuita (R$ 0,00)</option>
                            </select>
                        </label>
                        <?php else: ?>
                            <input type="hidden" name="modalidade_distribuicao" value="gratuita">
                        <?php endif; ?>
                    </div>
                    <div class="grade-campos-dupla">
                        <?php if (folivro_pagamentos_ativos()): ?>
                        <label for="preco_obra">Preço em reais (simulação acadêmica)
                            <input id="preco_obra" type="number" name="preco_obra" min="0" max="99999999" step="0.01" required value="<?= number_format((float) $work['preco_dos_livros_autor'], 2, '.', '') ?>">
                        </label>
                        <?php else: ?>
                            <input type="hidden" name="preco_obra" value="0.00">
                        <?php endif; ?>
                        <label for="capa_historias">Substituir imagem de capa original (JPG, PNG ou WebP até 2 MB)
                            <input id="capa_historias" type="file" name="capa_historias" accept="image/jpeg,image/png,image/webp">
                        </label>
                    </div>
                    <div>
                        <button class="botao-adicionar-livro" type="submit">Salvar dados da obra</button>
                    </div>
                </form>
            </details>

            <?php
            // Função auxiliar local para renderizar a barra de ferramentas do Estúdio de Escrita
            $renderizarToolbarEstudio = static function (): void {
                ?>
                <div class="estudio-toolbar-formatacao" role="toolbar" aria-label="Ferramentas de formatação de texto">
                    <div class="toolbar-grupo">
                        <select class="toolbar-select js-select-tamanho" title="Tamanho da letra">
                            <option value="">Tamanho da letra</option>
                            <option value="1">Pequeno (14px)</option>
                            <option value="3">Normal (18px)</option>
                            <option value="5">Médio (22px)</option>
                            <option value="6">Grande (28px)</option>
                            <option value="7">Destaque (36px)</option>
                        </select>
                        <select class="toolbar-select js-select-bloco" title="Estilo de parágrafo / título">
                            <option value="p">Parágrafo normal</option>
                            <option value="h2">Título Principal (H2)</option>
                            <option value="h3">Subtítulo (H3)</option>
                            <option value="blockquote">Citação em bloco</option>
                        </select>
                    </div>

                    <div class="toolbar-separador"></div>

                    <div class="toolbar-grupo">
                        <button type="button" class="toolbar-btn" data-cmd="bold" title="Negrito (Ctrl+B)"><strong>B</strong></button>
                        <button type="button" class="toolbar-btn" data-cmd="italic" title="Itálico (Ctrl+I)"><em>I</em></button>
                        <button type="button" class="toolbar-btn" data-cmd="underline" title="Sublinhado (Ctrl+U)"><u>U</u></button>
                        <button type="button" class="toolbar-btn" data-cmd="strikeThrough" title="Tachado"><s>S</s></button>
                    </div>

                    <div class="toolbar-separador"></div>

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

                    <div class="toolbar-grupo">
                        <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList" title="Lista com marcadores">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M4 6h2v2H4V6zm0 5h2v2H4v-2zm0 5h2v2H4v-2zm5-10h12v2H9V6zm0 5h12v2H9v-2zm0 5h12v2H9v-2z"/></svg>
                        </button>
                        <button type="button" class="toolbar-btn" data-cmd="insertOrderedList" title="Lista numerada">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M3 5h2v3H4V6H3V5zm0 6h3v1H4v1h2v1H3v-1h2v-1H3v-1zm0 6h2.5L3 19.5V21h3v-1H4.2l1.8-1.5V17H3v1zm6-11h12v2H9V6zm0 6h12v2H9v-2zm0 6h12v2H9v-2z"/></svg>
                        </button>
                    </div>

                    <div class="toolbar-separador"></div>

                    <div class="toolbar-grupo">
                        <button type="button" class="toolbar-btn" data-cmd="undo" title="Desfazer (Ctrl+Z)">↶</button>
                        <button type="button" class="toolbar-btn" data-cmd="redo" title="Refazer (Ctrl+Y)">↷</button>
                        <button type="button" class="toolbar-btn" data-cmd="removeFormat" title="Limpar formatação">T⃠</button>
                    </div>
                </div>
                <?php
            };
            ?>

            <section class="secao-capitulos-gerenciar" aria-labelledby="titulo-secao-capitulos">
                <div class="cabecalho-secao-capitulos">
                    <div>
                        <h2 id="titulo-secao-capitulos">Capítulos publicados e rascunhos (<?= count($chapters) ?>)</h2>
                        <p>Edite qualquer capítulo existente no Estúdio de Escrita rico ou continue sua história adicionando novos capítulos abaixo.</p>
                    </div>
                    <a href="#novo-capitulo-estudio" class="botao-adicionar-livro">+ Escrever novo capítulo</a>
                </div>

                <div class="lista-interacoes">
                    <?php foreach ($chapters as $c): ?>
                        <?php
                        $idCapItem = (int) $c['id_capitulos'];
                        $ordemCap = (int) $c['ordem_capitulos'];
                        $textoLimpoCap = trim(strip_tags((string) $c['conteudo_capitulos']));
                        $palavrasCap = $textoLimpoCap === '' ? 0 : count(preg_split('/\s+/u', $textoLimpoCap));
                        $minLeituraCap = max(1, (int) ceil($palavrasCap / 200));
                        $revCapItem = (string) ($c['revisao_capitulos'] ?? ($c['status_capitulos'] === 'publicado' ? 'aprovada' : 'pendente'));
                        ?>
                        <article class="card-interacao card-capitulo-gerenciar">
                            <header class="cabecalho-card-capitulo">
                                <div class="titulo-card-capitulo-bloco">
                                    <span class="selo-numero-capitulo">Capítulo <?= $ordemCap ?></span>
                                    <strong><?= folivro_escape($c['titulo_capitulos']) ?></strong>
                                    <span class="selo-status <?= $c['status_capitulos'] === 'publicado' ? 'selo-lido' : '' ?>">
                                        <?= folivro_escape(folivro_status_label($c['status_capitulos'])) ?>
                                    </span>
                                    <?php if ($revCapItem === 'publicado_automatico' && $c['status_capitulos'] === 'publicado'): ?>
                                        <span class="selo-status selo-comprado">Este capítulo foi publicado e está sujeito à moderação posterior.</span>
                                    <?php elseif ($revCapItem === 'pendente' && $c['status_capitulos'] !== 'publicado'): ?>
                                        <span class="selo-status">Sua publicação foi encaminhada para revisão humana.</span>
                                    <?php elseif (in_array($revCapItem, ['recusada', 'oculto_moderacao'], true)): ?>
                                        <span class="selo-status estado-erro"><?= folivro_escape(folivro_status_label($revCapItem)) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="meta-card-capitulo">
                                    <span class="selo-metrica"><?= $palavrasCap ?> palavras · ~<?= $minLeituraCap ?> min</span>
                                    <a href="obra.php?id=<?= (int) $id ?>#capitulo-<?= $ordemCap ?>" class="link-ver-detalhes" style="margin:0;">Ler capítulo &rarr;</a>
                                </div>
                            </header>
                            <?php if (!empty($c['justificativa_revisao_capitulos'])): ?>
                                <p class="aviso-direitos" style="margin: 6px 0 0;">
                                    <small><strong>Nota da moderação neste capítulo:</strong> <?= folivro_escape((string) $c['justificativa_revisao_capitulos']) ?></small>
                                </p>
                            <?php endif; ?>

                            <details class="configurar-meta details-estudio-capitulo">
                                <summary>Editar ou excluir capítulo</summary>
                                <div class="estudio-manuscrito estudio-capitulo-inline js-bloco-editor-rico">
                                    <form method="post" class="js-form-editor-rico">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="acao" value="editar_capitulo">
                                        <input type="hidden" name="id_capitulos" value="<?= $idCapItem ?>">

                                        <header class="estudio-manuscrito-topo">
                                            <span class="etiqueta-manuscrito-topo">Editando Capítulo <?= $ordemCap ?></span>
                                            <input name="titulo" class="input-capitulo-manuscrito" maxlength="120" required value="<?= folivro_escape($c['titulo_capitulos']) ?>" placeholder="Título do capítulo (máx. 120 caracteres)..." aria-label="Título do capítulo">
                                        </header>

                                        <div class="estudio-canvas-wrapper">
                                            <?php $renderizarToolbarEstudio(); ?>

                                            <div class="canvas-escrita-serena canvas-editor-rico js-editor-contenteditable"
                                                 contenteditable="true"
                                                 role="textbox"
                                                 aria-multiline="true"
                                                 aria-label="Conteúdo do capítulo <?= $ordemCap ?>"
                                                 data-placeholder="Escreva ou edite o conteúdo deste capítulo..."><?= folivro_sanitizar_html_capitulo((string) $c['conteudo_capitulos']) ?></div>

                                            <textarea name="conteudo" class="js-textarea-sincronizado" style="display:none;" required><?= folivro_escape((string) $c['conteudo_capitulos']) ?></textarea>
                                        </div>

                                        <footer class="estudio-barra-zen">
                                            <div class="estudio-metricas">
                                                <span class="selo-metrica js-contador-palavras">0 palavras</span>
                                                <span class="selo-metrica js-contador-caracteres">0 caracteres</span>
                                                <span class="selo-metrica js-tempo-leitura">~0 min de leitura</span>
                                            </div>
                                            <div class="acoes-rodape-estudio-cap">
                                                <button class="botao-adicionar-livro" type="submit">Salvar alterações do capítulo</button>
                                            </div>
                                        </footer>
                                    </form>

                                    <?php if (count($chapters) > 1): ?>
                                        <form method="post" class="form-excluir-capitulo-inline" onsubmit="return confirm('Tem certeza que deseja excluir este capítulo permanentemente?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="acao" value="excluir_capitulo">
                                            <input type="hidden" name="id_capitulos" value="<?= $idCapItem ?>">
                                            <button type="submit" class="link-acao">Excluir este capítulo</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- CONTINUAÇÃO DE POSTAGEM DE CAPÍTULOS NO ESTÚDIO MODERNO -->
            <?php $proximoNumeroCap = count($chapters) + 1; ?>
            <section id="novo-capitulo-estudio" class="secao-novo-capitulo-estudio" aria-labelledby="titulo-novo-capitulo">
                <div class="estudio-manuscrito js-bloco-editor-rico" id="estudio-novo-capitulo-box">
                    <form method="post" class="js-form-editor-rico">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="acao" value="capitulo">

                        <header class="estudio-manuscrito-topo">
                            <div class="topo-continuacao-flex">
                                <span class="etiqueta-manuscrito-topo">Continuação da obra · Capítulo <?= $proximoNumeroCap ?></span>
                                <span class="selo-status-rascunho">● Estúdio de escrita formatada</span>
                            </div>
                            <h2 id="titulo-novo-capitulo" class="titulo-estudio-continuacao">Adicionar novo capítulo</h2>
                            <input id="cap-titulo" name="titulo" class="input-capitulo-manuscrito" maxlength="120" required placeholder="Capítulo <?= $proximoNumeroCap ?>: Digite o título do novo capítulo (máx. 120 caracteres)..." autocomplete="off">
                        </header>

                        <div class="estudio-canvas-wrapper">
                            <?php $renderizarToolbarEstudio(); ?>

                            <div id="editor-novo-capitulo"
                                 class="canvas-escrita-serena canvas-editor-rico js-editor-contenteditable"
                                 contenteditable="true"
                                 role="textbox"
                                 aria-multiline="true"
                                 aria-label="Conteúdo do novo capítulo"
                                 data-placeholder="Continue sua história aqui com tranquilidade, usando títulos, citações, negrito ou itálico quando desejar..."></div>

                            <textarea id="cap-conteudo" name="conteudo" class="js-textarea-sincronizado" style="display:none;" required></textarea>
                        </div>

                        <footer class="estudio-barra-zen">
                            <div class="estudio-metricas">
                                <span class="selo-metrica js-contador-palavras">0 palavras</span>
                                <span class="selo-metrica js-contador-caracteres">0 caracteres</span>
                                <span class="selo-metrica js-tempo-leitura">~0 min de leitura</span>
                            </div>
                            <div class="acoes-rodape-estudio-cap">
                                <button type="button" id="btn-zen-continuacao" class="botao-zen-toggle" title="Expandir área de escrita">
                                    <?= folivro_icone('livro-aberto-seta', 'folivro-icon-xs') ?> Modo Silencioso
                                </button>
                                <button class="botao-adicionar-livro botao-salvar-obra" type="submit" style="width:auto; padding: 11px 26px;">
                                    Salvar e publicar capítulo <?= $proximoNumeroCap ?>
                                </button>
                            </div>
                        </footer>
                    </form>
                </div>
            </section>

            <?php if ($work['status_autor_dos_livros'] === 'removido'): ?>
                <p class="estado-vazio estado-erro">Obra removida pela moderação. A republicação automática está bloqueada; utilize a seção de transparência e contestação abaixo caso deseje apresentar comprovação de titularidade.</p>
            <?php elseif ($temSuspensaoPreventiva): ?>
                <p class="estado-vazio estado-erro">Venda e distribuição suspensas preventivamente enquanto uma denúncia de direitos autorais é analisada pela moderação humana. Envie seus esclarecimentos abaixo.</p>
            <?php elseif (($work['revisao_publicacao'] ?? '') === 'pendente' && $work['status_autor_dos_livros'] !== 'a_venda'): ?>
                <div class="card-interacao">
                    <h2>Solicitação de publicação em análise humana</h2>
                    <p>Sua declaração foi registrada e esta obra aguarda decisão humana da equipe de moderação antes de ser disponibilizada na loja. Enquanto estiver em revisão, o conteúdo permanece privado.</p>
                </div>
            <?php elseif ($work['status_autor_dos_livros'] !== 'a_venda'): ?>
                <form method="post" class="progresso-form form-largura-total">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="acao" value="publicar">
                    <h2>Solicitar revisão humana para publicação</h2>
                    <p><small>Declarações de autoria ou comercialização não liberam a obra automaticamente. A obra permanecerá privada até decisão humana registrada na moderação.</small></p>
                    <label>
                        <input type="checkbox" name="declaracao" value="1" required>
                        <strong>Declaração autoral obrigatória:</strong> Declaro ser titular dos direitos autorais desta obra e de sua capa (ou possuir autorização válida para reprodução e disponibilização ao público), ciente de que a declaração não substitui comprovação legal. <strong style="color:#8b2635;">(Obrigatório)</strong>
                    </label>
                    <?php if (($work['modalidade_distribuicao'] ?? 'venda_simulada') === 'venda_simulada'): ?>
                        <label>
                            <input type="checkbox" name="declaracao_comercializacao" value="1" required>
                            <strong>Autorização comercial obrigatória:</strong> Declaro possuir autorização específica para comercializar esta obra na loja (permissão de venda separada da permissão de publicação gratuita). <strong style="color:#8b2635;">(Obrigatório)</strong>
                        </label>
                    <?php endif; ?>
                    <div>
                        <button class="botao-adicionar-livro" type="submit">Solicitar aprovação para publicar</button>
                    </div>
                </form>
            <?php else: ?>
                <form method="post" class="progresso-form form-largura-total">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="acao" value="pausar">
                    <div>
                        <button class="botao-adicionar-livro" type="submit">Pausar venda / Despublicar</button>
                    </div>
                </form>
            <?php endif; ?>

            <section class="secao-interacao secao-largura-total" aria-labelledby="titulo-transparencia-autor">
                <h2 id="titulo-transparencia-autor">Transparência do autor: declarações registradas e situação de denúncias</h2>

                <div class="card-interacao">
                    <h3>Registro de declarações e termos aceitos nesta obra</h3>
                    <?php if (!$termosObra): ?>
                        <p>Nenhuma declaração de publicação ou comercialização registrada ainda para esta obra.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($termosObra as $to): ?>
                                <li>
                                    <strong><?= $to['tipo_termos'] === 'declaracao_comercializacao' ? 'Autorização específica para comercialização' : 'Declaração de autoria / publicação' ?></strong>
                                    — Versão <?= folivro_escape($to['versao_termos']) ?> registrada em <?= date('d/m/Y H:i', strtotime($to['data_aceite_termos'])) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="lista-interacoes">
                    <h3>Notificações de moderação e contestações (dados do denunciante preservados em sigilo)</h3>
                    <?php if (!$denunciasObra): ?>
                        <p class="estado-vazio">Nenhuma denúncia registrada para esta obra.</p>
                    <?php else: ?>
                        <?php foreach ($denunciasObra as $do): ?>
                            <article class="card-interacao">
                                <header>
                                    <strong>Notificação #<?= (int) $do['id_denuncias'] ?> · Status: <?= folivro_escape(folivro_status_label($do['status_denuncias'])) ?></strong>
                                    <?php if (!empty($do['suspensao_preventiva'])): ?>
                                        <span class="selo-status">Suspensão preventiva ativa</span>
                                    <?php endif; ?>
                                    <small>Registrada em <?= date('d/m/Y H:i', strtotime($do['data_denuncia'])) ?></small>
                                </header>
                                <p><strong>Motivo informado:</strong> <?= folivro_escape($do['motivo_denuncia']) ?></p>
                                <?php if (!empty($do['obra_referencia_denuncia'])): ?>
                                    <p><strong>Obra/fonte apontada na denúncia:</strong> <?= folivro_escape($do['obra_referencia_denuncia']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($do['justificativa_moderacao'])): ?>
                                    <p><strong>Fundamentação da moderação:</strong> <?= folivro_escape($do['justificativa_moderacao']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($do['contestacao_autor'])): ?>
                                    <p><strong>Sua contestação enviada (<?= !empty($do['data_contestacao']) ? date('d/m/Y H:i', strtotime($do['data_contestacao'])) : '' ?>):</strong> <?= folivro_escape($do['contestacao_autor']) ?></p>
                                <?php endif; ?>

                                <?php if (in_array($do['status_denuncias'], ['aberta', 'em_analise', 'procedente'], true)): ?>
                                    <details class="configurar-meta">
                                        <summary>Apresentar ou atualizar contestação / esclarecimento à moderação</summary>
                                        <form method="post" class="progresso-form form-largura-total">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="acao" value="contestar_denuncia">
                                            <input type="hidden" name="id_denuncias" value="<?= (int) $do['id_denuncias'] ?>">
                                            <label>Esclarecimentos e referências de titularidade/autorização (não inclua documentos pessoais sensíveis desnecessários)
                                                <textarea name="contestacao_autor" rows="3" maxlength="3000" required><?= folivro_escape((string) ($do['contestacao_autor'] ?? '')) ?></textarea>
                                            </label>
                                            <div>
                                                <button class="botao-adicionar-livro" type="submit">Enviar contestação para análise humana</button>
                                            </div>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <script>
        (() => {
            const selectMod = document.getElementById('modalidade_distribuicao');
            const inputPreco = document.getElementById('preco_obra');
            if (selectMod && inputPreco) {
                function sincronizarPreco() {
                    if (selectMod.value === 'gratuita') {
                        inputPreco.value = '0.00';
                        inputPreco.readOnly = true;
                    } else {
                        inputPreco.readOnly = false;
                    }
                }
                selectMod.addEventListener('change', sincronizarPreco);
                sincronizarPreco();
            }

            // Inicializa todos os blocos do Estúdio de Escrita Rico (edição de capítulos e adição de novo capítulo)
            document.querySelectorAll('.js-bloco-editor-rico').forEach((bloco) => {
                const editor = bloco.querySelector('.js-editor-contenteditable');
                const textarea = bloco.querySelector('.js-textarea-sincronizado');
                const form = bloco.querySelector('.js-form-editor-rico');
                const toolbar = bloco.querySelector('.estudio-toolbar-formatacao');
                const selectTamanho = bloco.querySelector('.js-select-tamanho');
                const selectBloco = bloco.querySelector('.js-select-bloco');
                const elPalavras = bloco.querySelector('.js-contador-palavras');
                const elCaracteres = bloco.querySelector('.js-contador-caracteres');
                const elTempo = bloco.querySelector('.js-tempo-leitura');

                if (!editor || !textarea) return;

                function atualizarMetricas() {
                    const texto = (editor.innerText || '').trim();
                    const caracteres = texto.length;
                    const palavras = texto === '' ? 0 : texto.split(/\s+/).length;
                    const minutos = Math.max(1, Math.ceil(palavras / 200));

                    if (elPalavras) elPalavras.textContent = `${palavras} palavra${palavras === 1 ? '' : 's'}`;
                    if (elCaracteres) elCaracteres.textContent = `${caracteres} caractere${caracteres === 1 ? '' : 's'}`;
                    if (elTempo) elTempo.textContent = palavras > 0 ? `~${minutos} min de leitura` : '~0 min de leitura';
                }

                function sincronizar() {
                    const textoPuro = (editor.innerText || '').trim();
                    textarea.value = textoPuro === '' ? '' : editor.innerHTML;
                    atualizarMetricas();
                }

                editor.addEventListener('input', sincronizar);
                editor.addEventListener('keyup', sincronizar);
                editor.addEventListener('blur', sincronizar);
                editor.addEventListener('paste', () => setTimeout(sincronizar, 20));

                if (toolbar) {
                    toolbar.addEventListener('click', (e) => {
                        const btn = e.target.closest('.toolbar-btn');
                        if (!btn) return;
                        e.preventDefault();
                        const cmd = btn.getAttribute('data-cmd');
                        const val = btn.getAttribute('data-val') || null;
                        if (cmd) {
                            editor.focus();
                            document.execCommand(cmd, false, val);
                            sincronizar();
                        }
                    });
                }

                if (selectTamanho) {
                    selectTamanho.addEventListener('change', () => {
                        const val = selectTamanho.value;
                        if (val) {
                            editor.focus();
                            document.execCommand('fontSize', false, val);
                            selectTamanho.value = '';
                            sincronizar();
                        }
                    });
                }

                if (selectBloco) {
                    selectBloco.addEventListener('change', () => {
                        const val = selectBloco.value;
                        if (val) {
                            editor.focus();
                            document.execCommand('formatBlock', false, val === 'blockquote' ? 'blockquote' : `<${val}>`);
                            selectBloco.value = 'p';
                            sincronizar();
                        }
                    });
                }

                if (form) {
                    form.addEventListener('submit', (e) => {
                        sincronizar();
                        if (textarea.value.trim() === '') {
                            e.preventDefault();
                            editor.focus();
                            alert('Por favor, escreva o conteúdo do capítulo antes de salvar.');
                        }
                    });
                }

                sincronizar();
            });

            // Botão Modo Silencioso na adição de novo capítulo
            const btnZenCont = document.getElementById('btn-zen-continuacao');
            const boxNovoCap = document.getElementById('estudio-novo-capitulo-box');
            if (btnZenCont && boxNovoCap) {
                const iconeZenCont = <?= json_encode(folivro_icone('livro-aberto-seta', 'folivro-icon-xs')) ?>;
                btnZenCont.addEventListener('click', () => {
                    const ativo = boxNovoCap.classList.toggle('estudio-tela-cheia');
                    btnZenCont.innerHTML = ativo
                        ? `${iconeZenCont} Sair do Modo Silencioso`
                        : `${iconeZenCont} Modo Silencioso`;
                });
            }

            // Controle interativo dos chips de gêneros na edição da obra (máx. 5)
            const gradeGenGer = document.getElementById('grade-generos-gerenciar');
            const contGenGer = document.getElementById('contador-generos-gerenciar');
            if (gradeGenGer) {
                const checksGen = Array.from(gradeGenGer.querySelectorAll('input[type="checkbox"]'));
                const atualizarGenUI = (ultimo = null) => {
                    let marcados = checksGen.filter((c) => c.checked);
                    if (marcados.length > 5 && ultimo) {
                        ultimo.checked = false;
                        marcados = checksGen.filter((c) => c.checked);
                    }
                    checksGen.forEach((c) => {
                        const chip = c.closest('.chip-genero-escolha');
                        if (chip) chip.classList.toggle('ativo', c.checked);
                    });
                    if (contGenGer) {
                        contGenGer.textContent = `${marcados.length}/5 selecionado${marcados.length === 1 ? '' : 's'}`;
                    }
                };
                checksGen.forEach((c) => c.addEventListener('change', () => atualizarGenUI(c)));
                atualizarGenUI();
            }
        })();
    </script>
</body>

</html>
