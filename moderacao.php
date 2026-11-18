<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = folivro_database();
folivro_require_login();

// Autorização estrita no servidor: apenas administradores acessam a moderação.
if (($_SESSION['nivel_usuario'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Acesso restrito à moderação.');
}

$erro = '';
$sucesso = '';
$idModerador = folivro_user_id();

// Expurgo automático de evidências restritas de chat cujo prazo legal de retenção (180 dias) expirou (LGPD - minimização)
try {
    $pdo->exec(
        "DELETE FROM chat_mensagens
          WHERE status_mensagem = 'removida_moderacao'
            AND id_mensagem IN (
                SELECT item_denuncia FROM denuncias
                 WHERE tipo_denuncias = 'mensagem_chat'
                   AND retencao_evidencia_ate IS NOT NULL
                   AND retencao_evidencia_ate < NOW()
            )"
    );
} catch (Throwable $e) {
    // Silencioso caso não haja registros expirados
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $erro = 'Sessão expirada. Atualize a página.';
    } elseif (($_POST['acao'] ?? '') === 'revisar_publicacao') {
        $idObraRev = filter_input(INPUT_POST, 'id_autor_dos_livros', FILTER_VALIDATE_INT) ?: 0;
        $decisaoRev = (string) ($_POST['decisao_revisao'] ?? '');
        $justificativaRev = trim((string) ($_POST['justificativa_revisao'] ?? ''));

        $stmtObraRev = $pdo->prepare(
            "SELECT a.*, h.origem_obra_historias, h.licenca_historias,
                    (SELECT COUNT(*) FROM capitulos c WHERE c.id_historias = a.id_historias) AS total_capitulos
               FROM autor_dos_livros a
               LEFT JOIN historias h ON h.id_historias = a.id_historias
              WHERE a.id_autor_dos_livros = ?
              LIMIT 1"
        );
        $stmtObraRev->execute([$idObraRev]);
        $obraRev = $stmtObraRev->fetch();

        if (!$obraRev) {
            $erro = 'Obra não encontrada para revisão.';
        } elseif ($justificativaRev === '' || mb_strlen($justificativaRev, 'UTF-8') > 2000) {
            $erro = 'Registre uma justificativa fundamentada (até 2.000 caracteres) para documentar a revisão de publicação.';
        } elseif (!in_array($decisaoRev, ['aprovar_publicacao', 'recusar_publicacao'], true)) {
            $erro = 'Decisão de revisão inválida.';
        } else {
            $origemRev = (string) ($obraRev['origem_obra_historias'] ?? 'autoral_original');
            $modalidadeRev = (string) ($obraRev['modalidade_distribuicao'] ?? 'venda_simulada');
            $temDeclAutoria = (int) ($obraRev['declaracao_autoria'] ?? 0) === 1;
            $temDeclCom = $modalidadeRev === 'gratuita' || (int) ($obraRev['declaracao_comercializacao'] ?? 0) === 1;
            $totalCapRev = (int) ($obraRev['total_capitulos'] ?? 0);

            $stmtSuspAtiva = $pdo->prepare(
                "SELECT COUNT(*) FROM denuncias
                  WHERE tipo_denuncias = 'obra_autoral' AND item_denuncia = ?
                    AND status_denuncias IN ('aberta', 'em_analise') AND suspensao_preventiva = 1"
            );
            $stmtSuspAtiva->execute([$idObraRev]);
            $temSuspensaoAtiva = (int) $stmtSuspAtiva->fetchColumn() > 0;

            if ($decisaoRev === 'aprovar_publicacao') {
                if (in_array($origemRev, ['fanfic_sem_autorizacao', 'dominio_publico_nao_verificado'], true)) {
                    $erro = 'Não é permitido aprovar a publicação ou venda de obras derivadas sem autorização ou de domínio público não verificado.';
                } elseif (!$temDeclAutoria || !$temDeclCom) {
                    $erro = 'Não é possível aprovar a publicação sem os termos obrigatórios de autoria/licença registrados pelo autor.';
                } elseif ($obraRev['status_autor_dos_livros'] === 'removido' || $temSuspensaoAtiva) {
                    $erro = 'Não é possível aprovar obra removida por violação ou sob suspensão preventiva ativa.';
                } elseif ($totalCapRev <= 0) {
                    $erro = 'A obra precisa possuir ao menos um capítulo cadastrado para ser publicada.';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $idHistRev = (int) ($obraRev['id_historias'] ?? 0);
                        $idAutorObraRev = (int) ($obraRev['id_autor'] ?? 0);
                        $capsPendentesObra = 0;
                        if ($idHistRev > 0) {
                            $stmtCountPend = $pdo->prepare("SELECT COUNT(*) FROM capitulos WHERE id_historias = ? AND (status_capitulos = 'rascunho' OR revisao_capitulos = 'pendente')");
                            $stmtCountPend->execute([$idHistRev]);
                            $capsPendentesObra = (int) $stmtCountPend->fetchColumn();
                        }

                        $pdo->prepare(
                            "UPDATE autor_dos_livros
                                SET revisao_publicacao = 'aprovada',
                                    status_autor_dos_livros = 'a_venda',
                                    justificativa_revisao = ?,
                                    data_revisao_publicacao = CURRENT_TIMESTAMP,
                                    id_moderador_revisao = ?,
                                    motivo_bloqueio_autor = NULL,
                                    data_publicacao_autor = COALESCE(data_publicacao_autor, CURRENT_TIMESTAMP)
                              WHERE id_autor_dos_livros = ?"
                        )->execute([$justificativaRev, $idModerador, $idObraRev]);

                        if ($idHistRev > 0) {
                            $pdo->prepare("UPDATE historias SET status_historias = 'publicada' WHERE id_historias = ?")
                                ->execute([$idHistRev]);
                            $pdo->prepare(
                                "UPDATE capitulos
                                    SET status_capitulos = 'publicado',
                                        modo_publicacao_capitulos = 'revisao_humana',
                                        revisao_capitulos = 'aprovada',
                                        justificativa_revisao_capitulos = ?,
                                        id_moderador_capitulos = ?,
                                        data_revisao_capitulos = CURRENT_TIMESTAMP
                                  WHERE id_historias = ? AND revisao_capitulos NOT IN ('recusada', 'oculto_moderacao')"
                            )->execute([$justificativaRev, $idModerador, $idHistRev]);
                        }

                        if ($idAutorObraRev > 0) {
                            if ($capsPendentesObra > 0) {
                                $pdo->prepare("UPDATE usuario SET capitulos_aprovados_usuario = capitulos_aprovados_usuario + ? WHERE id_usuario = ?")
                                    ->execute([$capsPendentesObra, $idAutorObraRev]);
                            }
                            folivro_registrar_historico_moderacao(
                                $pdo,
                                $idAutorObraRev,
                                $idModerador,
                                'aprovacao_obra',
                                $justificativaRev,
                                null,
                                null,
                                $idObraRev,
                                null
                            );
                        }

                        $pdo->commit();
                        require_once __DIR__ . '/config/loja.php';
                        folivro_loja_sincronizar_obra_estudio($pdo, $idObraRev);
                        if ($idAutorObraRev > 0) {
                            folivro_avaliar_qualificacao_autor($pdo, $idAutorObraRev, true);
                        }
                        $sucesso = 'Publicação aprovada após revisão humana. A obra agora está disponível na loja e na comunidade.';
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $erro = 'Não foi possível concluir a aprovação da obra.';
                    }
                }
            } else {
                $pdo->beginTransaction();
                try {
                    $idHistRev = (int) ($obraRev['id_historias'] ?? 0);
                    $idAutorObraRev = (int) ($obraRev['id_autor'] ?? 0);

                    $pdo->prepare(
                        "UPDATE autor_dos_livros
                            SET revisao_publicacao = 'recusada',
                                status_autor_dos_livros = 'rascunho',
                                justificativa_revisao = ?,
                                data_revisao_publicacao = CURRENT_TIMESTAMP,
                                id_moderador_revisao = ?,
                                motivo_bloqueio_autor = ?
                          WHERE id_autor_dos_livros = ?"
                    )->execute([
                        $justificativaRev,
                        $idModerador,
                        'Publicação não aprovada na revisão editorial: ' . mb_substr($justificativaRev, 0, 190, 'UTF-8'),
                        $idObraRev,
                    ]);

                    if ($idHistRev > 0) {
                        $pdo->prepare("UPDATE historias SET status_historias = 'despublicada' WHERE id_historias = ?")
                            ->execute([$idHistRev]);
                        $pdo->prepare(
                            "UPDATE capitulos
                                SET status_capitulos = 'rascunho',
                                    revisao_capitulos = 'recusada',
                                    justificativa_revisao_capitulos = ?,
                                    id_moderador_capitulos = ?,
                                    data_revisao_capitulos = CURRENT_TIMESTAMP
                              WHERE id_historias = ?"
                        )->execute([$justificativaRev, $idModerador, $idHistRev]);
                    }

                    if ($idAutorObraRev > 0) {
                        $pdo->prepare(
                            "UPDATE usuario
                                SET revisoes_recusadas_usuario = revisoes_recusadas_usuario + 1,
                                    modalidade_publicacao_usuario = IF(modalidade_publicacao_usuario = 'automatica', 'suspensa', modalidade_publicacao_usuario),
                                    justificativa_modalidade_usuario = IF(modalidade_publicacao_usuario = 'automatica', ?, justificativa_modalidade_usuario)
                              WHERE id_usuario = ?"
                        )->execute(['Permissão automática suspensa após recusa editorial: ' . mb_substr($justificativaRev, 0, 180, 'UTF-8'), $idAutorObraRev]);

                        folivro_registrar_historico_moderacao(
                            $pdo,
                            $idAutorObraRev,
                            $idModerador,
                            'recusa_obra',
                            $justificativaRev,
                            null,
                            null,
                            $idObraRev,
                            null
                        );
                    }

                    $pdo->commit();
                    require_once __DIR__ . '/config/loja.php';
                    folivro_loja_sincronizar_obra_estudio($pdo, $idObraRev);
                    $sucesso = 'Publicação recusada e mantida como rascunho privado com justificativa visível ao autor.';
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $erro = 'Não foi possível registrar a recusa da publicação.';
                }
            }
        }
    } elseif (($_POST['acao'] ?? '') === 'revisar_capitulo') {
        $idCapRev = filter_input(INPUT_POST, 'id_capitulos', FILTER_VALIDATE_INT) ?: 0;
        $decisaoCap = (string) ($_POST['decisao_capitulo'] ?? '');
        $justificativaCap = trim((string) ($_POST['justificativa_capitulo'] ?? ''));

        $stmtCapRev = $pdo->prepare(
            "SELECT c.*, h.id_usuario AS id_autor_hist, h.titulo_historias, h.origem_obra_historias,
                    a.id_autor_dos_livros, a.revisao_publicacao, a.status_autor_dos_livros,
                    u.modalidade_publicacao_usuario
               FROM capitulos c
               JOIN historias h ON h.id_historias = c.id_historias
               JOIN autor_dos_livros a ON a.id_historias = h.id_historias
               JOIN usuario u ON u.id_usuario = h.id_usuario
              WHERE c.id_capitulos = ?
              LIMIT 1"
        );
        $stmtCapRev->execute([$idCapRev]);
        $capRev = $stmtCapRev->fetch();

        if (!$capRev) {
            $erro = 'Capítulo não encontrado para revisão.';
        } elseif ($justificativaCap === '' || mb_strlen($justificativaCap, 'UTF-8') > 2000) {
            $erro = 'Registre uma justificativa fundamentada (até 2.000 caracteres) para documentar a decisão sobre o capítulo.';
        } elseif (!in_array($decisaoCap, ['aprovar_capitulo', 'recusar_capitulo', 'ocultar_capitulo_automatico', 'confirmar_violacao_capitulo'], true)) {
            $erro = 'Decisão sobre capítulo inválida.';
        } else {
            $idAutorCap = (int) $capRev['id_autor_hist'];
            $idObraCap = (int) $capRev['id_autor_dos_livros'];
            $idHistCap = (int) $capRev['id_historias'];
            $modAntAutor = (string) ($capRev['modalidade_publicacao_usuario'] ?? 'avaliacao');

            $pdo->beginTransaction();
            try {
                if ($decisaoCap === 'aprovar_capitulo') {
                    $eraNaoAprovadoHum = (($capRev['revisao_capitulos'] ?? '') !== 'aprovada');
                    $pdo->prepare(
                        "UPDATE capitulos
                            SET status_capitulos = 'publicado',
                                modo_publicacao_capitulos = 'revisao_humana',
                                revisao_capitulos = 'aprovada',
                                justificativa_revisao_capitulos = ?,
                                id_moderador_capitulos = ?,
                                data_revisao_capitulos = CURRENT_TIMESTAMP
                          WHERE id_capitulos = ?"
                    )->execute([$justificativaCap, $idModerador, $idCapRev]);

                    // Se a obra principal também estava aguardando aprovação e tem base legal válida, aprova a obra para leitura
                    if (($capRev['revisao_publicacao'] ?? '') === 'pendente'
                        && !in_array($capRev['origem_obra_historias'] ?? '', ['fanfic_sem_autorizacao', 'dominio_publico_nao_verificado'], true)) {
                        $pdo->prepare(
                            "UPDATE autor_dos_livros
                                SET revisao_publicacao = 'aprovada',
                                    status_autor_dos_livros = 'a_venda',
                                    justificativa_revisao = COALESCE(justificativa_revisao, ?),
                                    data_revisao_publicacao = COALESCE(data_revisao_publicacao, CURRENT_TIMESTAMP),
                                    id_moderador_revisao = COALESCE(id_moderador_revisao, ?),
                                    motivo_bloqueio_autor = NULL,
                                    data_publicacao_autor = COALESCE(data_publicacao_autor, CURRENT_TIMESTAMP)
                              WHERE id_autor_dos_livros = ?"
                        )->execute([$justificativaCap, $idModerador, $idObraCap]);
                        $pdo->prepare("UPDATE historias SET status_historias = 'publicada' WHERE id_historias = ?")
                            ->execute([$idHistCap]);
                    }

                    if ($eraNaoAprovadoHum && $idAutorCap > 0) {
                        $pdo->prepare("UPDATE usuario SET capitulos_aprovados_usuario = capitulos_aprovados_usuario + 1 WHERE id_usuario = ?")
                            ->execute([$idAutorCap]);
                    }

                    folivro_registrar_historico_moderacao(
                        $pdo,
                        $idAutorCap,
                        $idModerador,
                        'aprovacao_capitulo',
                        $justificativaCap,
                        $modAntAutor,
                        $modAntAutor,
                        $idObraCap,
                        $idCapRev
                    );
                    $pdo->commit();

                    if ($idAutorCap > 0) {
                        folivro_avaliar_qualificacao_autor($pdo, $idAutorCap, true);
                    }
                    $sucesso = 'Capítulo aprovado pela revisão humana e liberado para leitura.';
                } elseif ($decisaoCap === 'recusar_capitulo' || $decisaoCap === 'ocultar_capitulo_automatico') {
                    $novoStatusRevCap = $decisaoCap === 'ocultar_capitulo_automatico' ? 'oculto_moderacao' : 'recusada';
                    $pdo->prepare(
                        "UPDATE capitulos
                            SET status_capitulos = 'rascunho',
                                revisao_capitulos = ?,
                                justificativa_revisao_capitulos = ?,
                                id_moderador_capitulos = ?,
                                data_revisao_capitulos = CURRENT_TIMESTAMP
                          WHERE id_capitulos = ?"
                    )->execute([$novoStatusRevCap, $justificativaCap, $idModerador, $idCapRev]);

                    $pdo->prepare("UPDATE usuario SET revisoes_recusadas_usuario = revisoes_recusadas_usuario + 1 WHERE id_usuario = ?")
                        ->execute([$idAutorCap]);

                    folivro_registrar_historico_moderacao(
                        $pdo,
                        $idAutorCap,
                        $idModerador,
                        $decisaoCap === 'ocultar_capitulo_automatico' ? 'ocultacao_capitulo_pos_mod' : 'recusa_capitulo',
                        $justificativaCap,
                        $modAntAutor,
                        $modAntAutor,
                        $idObraCap,
                        $idCapRev
                    );
                    $pdo->commit();
                    $sucesso = 'Capítulo ocultado/recusado com sucesso. Os demais capítulos aprovados da obra permanecem preservados.';
                } elseif ($decisaoCap === 'confirmar_violacao_capitulo') {
                    $pdo->prepare(
                        "UPDATE capitulos
                            SET status_capitulos = 'rascunho',
                                revisao_capitulos = 'oculto_moderacao',
                                justificativa_revisao_capitulos = ?,
                                id_moderador_capitulos = ?,
                                data_revisao_capitulos = CURRENT_TIMESTAMP
                          WHERE id_capitulos = ?"
                    )->execute([$justificativaCap, $idModerador, $idCapRev]);

                    $motivoSuspAuto = 'Permissão de publicação automática suspensa por violação confirmada no capítulo #' . $idCapRev . ': ' . mb_substr($justificativaCap, 0, 160, 'UTF-8');
                    $pdo->prepare(
                        "UPDATE usuario
                            SET violacoes_confirmadas_usuario = violacoes_confirmadas_usuario + 1,
                                revisoes_recusadas_usuario = revisoes_recusadas_usuario + 1,
                                modalidade_publicacao_usuario = 'suspensa',
                                justificativa_modalidade_usuario = ?
                          WHERE id_usuario = ?"
                    )->execute([$motivoSuspAuto, $idAutorCap]);

                    folivro_registrar_historico_moderacao(
                        $pdo,
                        $idAutorCap,
                        $idModerador,
                        'violacao_confirmada',
                        $justificativaCap,
                        $modAntAutor,
                        'suspensa',
                        $idObraCap,
                        $idCapRev
                    );
                    $pdo->commit();
                    $sucesso = 'Violação confirmada: capítulo ocultado, infração registrada e permissão de publicação automática do autor suspensa (voltou a exigir revisão humana).';
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $erro = 'Não foi possível processar a revisão do capítulo.';
            }
        }
    } elseif (($_POST['acao'] ?? '') === 'gerenciar_qualificacao_autor') {
        $idAutorAlvoMod = filter_input(INPUT_POST, 'id_autor_alvo', FILTER_VALIDATE_INT) ?: 0;
        $novaModalidade = (string) ($_POST['nova_modalidade'] ?? '');
        $justificativaQual = trim((string) ($_POST['justificativa_qualificacao'] ?? ''));

        $stmtAutorMod = $pdo->prepare('SELECT id_usuario, nome_usuario, modalidade_publicacao_usuario FROM usuario WHERE id_usuario = ? LIMIT 1');
        $stmtAutorMod->execute([$idAutorAlvoMod]);
        $autorModRow = $stmtAutorMod->fetch();

        if (!$autorModRow) {
            $erro = 'Autor não encontrado.';
        } elseif (!in_array($novaModalidade, ['avaliacao', 'automatica', 'revisao_obrigatoria', 'suspensa'], true)) {
            $erro = 'Modalidade de publicação inválida.';
        } elseif ($justificativaQual === '' || mb_strlen($justificativaQual, 'UTF-8') > 2000) {
            $erro = 'Registre uma justificativa administrativa (até 2.000 caracteres) para alterar a qualificação do autor.';
        } else {
            $modAnterior = (string) ($autorModRow['modalidade_publicacao_usuario'] ?? 'avaliacao');
            $tipoEv = $novaModalidade === 'automatica' ? 'concessao_manual_confianca' : 'revogacao_confianca';

            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    "UPDATE usuario
                        SET modalidade_publicacao_usuario = ?,
                            justificativa_modalidade_usuario = ?,
                            data_qualificacao_usuario = IF(? = 'automatica', CURRENT_TIMESTAMP, data_qualificacao_usuario)
                      WHERE id_usuario = ?"
                )->execute([$novaModalidade, mb_substr($justificativaQual, 0, 250, 'UTF-8'), $novaModalidade, $idAutorAlvoMod]);

                folivro_registrar_historico_moderacao(
                    $pdo,
                    $idAutorAlvoMod,
                    $idModerador,
                    $tipoEv,
                    $justificativaQual,
                    $modAnterior,
                    $novaModalidade,
                    null,
                    null
                );
                $pdo->commit();
                $sucesso = 'Modalidade de publicação do autor atualizada e registrada no histórico de moderação.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $erro = 'Não foi possível atualizar a modalidade de publicação do autor.';
            }
        }
    } else {
        $idDenuncia = filter_input(INPUT_POST, 'id_denuncias', FILTER_VALIDATE_INT) ?: 0;
        $decisao = (string) ($_POST['decisao'] ?? '');
        $justificativa = trim((string) ($_POST['justificativa_moderacao'] ?? ''));

        $denStmt = $pdo->prepare('SELECT * FROM denuncias WHERE id_denuncias = ? LIMIT 1');
        $denStmt->execute([$idDenuncia]);
        $den = $denStmt->fetch();

        if (!$den) {
            $erro = 'Denúncia não encontrada.';
        } elseif ($justificativa === '' || mb_strlen($justificativa, 'UTF-8') > 2000) {
            $erro = 'Registre uma justificativa fundamentada (até 2.000 caracteres) para documentar a decisão de moderação.';
        } else {
            $pdo->beginTransaction();
            try {
                $tipo = (string) $den['tipo_denuncias'];
                $item = (int) $den['item_denuncia'];

                // Identifica o autor/remetente alvo em denúncias de chat
                $idRemetenteChat = 0;
                if ($tipo === 'mensagem_chat') {
                    $stmtRem = $pdo->prepare('SELECT id_remetente FROM chat_mensagens WHERE id_mensagem = ? LIMIT 1');
                    $stmtRem->execute([$item]);
                    $idRemetenteChat = (int) $stmtRem->fetchColumn();
                } elseif ($tipo === 'conversa_chat') {
                    $stmtConv = $pdo->prepare('SELECT id_usuario_menor, id_usuario_maior FROM chat_conversas WHERE id_conversa = ? LIMIT 1');
                    $stmtConv->execute([$item]);
                    $convRow = $stmtConv->fetch();
                    if ($convRow) {
                        $idDenunciante = (int) $den['id_usuario'];
                        $idRemetenteChat = ((int) $convRow['id_usuario_menor'] === $idDenunciante)
                            ? (int) $convRow['id_usuario_maior']
                            : (int) $convRow['id_usuario_menor'];
                    }
                }

                $idObraParaSincronizar = 0;
                if ($decisao === 'suspender_preventivamente') {
                    // Triagem: coloca em análise e interrompe preventivamente venda, distribuição ou exibição da mensagem.
                    $pdo->prepare(
                        "UPDATE denuncias
                            SET status_denuncias = 'em_analise', suspensao_preventiva = 1,
                                acao_moderacao_aplicada = 'suspender_preventivamente',
                                justificativa_moderacao = ?,
                                retencao_evidencia_ate = COALESCE(retencao_evidencia_ate, DATE_ADD(NOW(), INTERVAL 180 DAY))
                          WHERE id_denuncias = ?"
                    )->execute([$justificativa, $idDenuncia]);

                    if ($tipo === 'obra_autoral') {
                        $obraStmt = $pdo->prepare('SELECT id_historias FROM autor_dos_livros WHERE id_autor_dos_livros = ?');
                        $obraStmt->execute([$item]);
                        $idHist = (int) $obraStmt->fetchColumn();
                        $pdo->prepare(
                            "UPDATE autor_dos_livros
                                SET status_autor_dos_livros = 'suspenso',
                                    motivo_bloqueio_autor = ?
                              WHERE id_autor_dos_livros = ?"
                        )->execute(['Suspensão preventiva pela moderação: ' . mb_substr($justificativa, 0, 200, 'UTF-8'), $item]);
                        if ($idHist > 0) {
                            $pdo->prepare("UPDATE historias SET status_historias = 'despublicada' WHERE id_historias = ?")
                                ->execute([$idHist]);
                        }
                        $pdo->prepare("UPDATE loja_produtos SET status_produto = 'arquivado' WHERE id_autor_dos_livros = ?")
                            ->execute([$item]);
                    } elseif ($tipo === 'mensagem_chat') {
                        $pdo->prepare(
                            "UPDATE chat_mensagens
                                SET status_mensagem = 'oculta_preventivamente',
                                    motivo_ocultacao = ?
                              WHERE id_mensagem = ? AND status_mensagem = 'ativa'"
                        )->execute(['Ocultada preventivamente pela moderação: ' . mb_substr($justificativa, 0, 180, 'UTF-8'), $item]);
                    }
                    $sucesso = 'Item colocado em análise humana com medida preventiva aplicada.';
                } elseif ($decisao === 'improcedente') {
                    $pdo->prepare(
                        "UPDATE denuncias
                            SET status_denuncias = 'improcedente', suspensao_preventiva = 0,
                                acao_moderacao_aplicada = 'improcedente',
                                justificativa_moderacao = ?, data_resposta_denuncia = CURRENT_TIMESTAMP,
                                retencao_evidencia_ate = COALESCE(retencao_evidencia_ate, DATE_ADD(NOW(), INTERVAL 180 DAY))
                          WHERE id_denuncias = ?"
                    )->execute([$justificativa, $idDenuncia]);

                    if ($tipo === 'obra_autoral') {
                        $outrasAtivas = $pdo->prepare(
                            "SELECT COUNT(*) FROM denuncias
                              WHERE tipo_denuncias = 'obra_autoral' AND item_denuncia = ?
                                AND id_denuncias <> ? AND status_denuncias IN ('aberta', 'em_analise') AND suspensao_preventiva = 1"
                        );
                        $outrasAtivas->execute([$item, $idDenuncia]);
                        if ((int) $outrasAtivas->fetchColumn() === 0) {
                            $obraStmt = $pdo->prepare('SELECT id_historias, declaracao_autoria, status_autor_dos_livros, revisao_publicacao FROM autor_dos_livros WHERE id_autor_dos_livros = ?');
                            $obraStmt->execute([$item]);
                            $dadosObra = $obraStmt->fetch();
                            if ($dadosObra && in_array($dadosObra['status_autor_dos_livros'], ['suspenso', 'removido'], true)) {
                                if ((int) $dadosObra['declaracao_autoria'] === 1 && ($dadosObra['revisao_publicacao'] ?? '') === 'aprovada') {
                                    $pdo->prepare("UPDATE autor_dos_livros SET status_autor_dos_livros = 'a_venda', motivo_bloqueio_autor = NULL WHERE id_autor_dos_livros = ?")
                                        ->execute([$item]);
                                    $pdo->prepare("UPDATE historias SET status_historias = 'publicada' WHERE id_historias = ?")
                                        ->execute([(int) $dadosObra['id_historias']]);
                                    $idObraParaSincronizar = $item;
                                } else {
                                    $pdo->prepare("UPDATE autor_dos_livros SET status_autor_dos_livros = 'rascunho', motivo_bloqueio_autor = NULL WHERE id_autor_dos_livros = ?")
                                        ->execute([$item]);
                                }
                            }
                        }
                    } elseif ($tipo === 'mensagem_chat') {
                        $outrasMsgAtivas = $pdo->prepare(
                            "SELECT COUNT(*) FROM denuncias
                              WHERE tipo_denuncias = 'mensagem_chat' AND item_denuncia = ?
                                AND id_denuncias <> ? AND status_denuncias IN ('aberta', 'em_analise')"
                        );
                        $outrasMsgAtivas->execute([$item, $idDenuncia]);
                        if ((int) $outrasMsgAtivas->fetchColumn() === 0) {
                            $pdo->prepare(
                                "UPDATE chat_mensagens
                                    SET status_mensagem = 'ativa', motivo_ocultacao = NULL
                                  WHERE id_mensagem = ? AND status_mensagem = 'oculta_preventivamente'"
                            )->execute([$item]);
                        }
                    }
                    $sucesso = 'Denúncia julgada improcedente / contestação acolhida e decisão fundamentada registrada.';
                } elseif (in_array($decisao, ['aviso_e_remover_mensagem', 'restricao_temporaria_chat', 'remover_item', 'remover_e_banir_reincidente'], true)) {
                    $idAutorAlvo = 0;

                    if ($tipo === 'obra_autoral') {
                        $obraStmt = $pdo->prepare('SELECT id_historias, id_autor FROM autor_dos_livros WHERE id_autor_dos_livros = ?');
                        $obraStmt->execute([$item]);
                        $rowObra = $obraStmt->fetch();
                        $idHist = (int) ($rowObra['id_historias'] ?? 0);
                        $idAutorAlvo = (int) ($rowObra['id_autor'] ?? 0);

                        $pdo->prepare(
                            "UPDATE autor_dos_livros
                                SET status_autor_dos_livros = 'removido',
                                    motivo_bloqueio_autor = ?
                              WHERE id_autor_dos_livros = ?"
                        )->execute(['Removida pela moderação: ' . mb_substr($justificativa, 0, 210, 'UTF-8'), $item]);
                        if ($idHist > 0) {
                            $pdo->prepare("UPDATE historias SET status_historias = 'despublicada' WHERE id_historias = ?")
                                ->execute([$idHist]);
                        }
                        $pdo->prepare("UPDATE loja_produtos SET status_produto = 'arquivado' WHERE id_autor_dos_livros = ?")
                            ->execute([$item]);
                    } elseif ($tipo === 'historia') {
                        $histStmt = $pdo->prepare('SELECT id_usuario FROM historias WHERE id_historias = ?');
                        $histStmt->execute([$item]);
                        $idAutorAlvo = (int) $histStmt->fetchColumn();
                        $pdo->prepare("UPDATE historias SET status_historias = 'despublicada' WHERE id_historias = ?")
                            ->execute([$item]);
                        $pdo->prepare("UPDATE autor_dos_livros SET status_autor_dos_livros = 'removido' WHERE id_historias = ?")
                            ->execute([$item]);
                        $pdo->prepare(
                            "UPDATE loja_produtos
                                SET status_produto = 'arquivado'
                              WHERE id_autor_dos_livros IN (SELECT id_autor_dos_livros FROM autor_dos_livros WHERE id_historias = ?)"
                        )->execute([$item]);
                    } elseif ($tipo === 'comentario') {
                        $comStmt = $pdo->prepare('SELECT id_usuario FROM comentarios WHERE id_comentarios = ?');
                        $comStmt->execute([$item]);
                        $idAutorAlvo = (int) $comStmt->fetchColumn();
                        $pdo->prepare('DELETE FROM comentarios WHERE id_comentarios = ?')->execute([$item]);
                    } elseif ($tipo === 'usuario') {
                        $idAutorAlvo = $item;
                        if ($decisao === 'remover_item' || $decisao === 'remover_e_banir_reincidente') {
                            $pdo->prepare("UPDATE usuario SET status_usuario = 'banido' WHERE id_usuario = ? AND nivel_usuario <> 'admin'")
                                ->execute([$item]);
                            $pdo->prepare("UPDATE loja_produtos SET status_produto = 'arquivado' WHERE id_autor = ?")->execute([$item]);
                            $pdo->prepare("UPDATE autor_dos_livros SET status_autor_dos_livros = 'removido' WHERE id_autor = ?")->execute([$item]);
                            $pdo->prepare("UPDATE historias SET status_historias = 'despublicada' WHERE id_usuario = ?")->execute([$item]);
                        }
                    } elseif ($tipo === 'mensagem_chat') {
                        $idAutorAlvo = $idRemetenteChat;
                        $pdo->prepare(
                            "UPDATE chat_mensagens
                                SET status_mensagem = 'removida_moderacao',
                                    motivo_ocultacao = ?
                              WHERE id_mensagem = ?"
                        )->execute(['Removida pela moderação: ' . mb_substr($justificativa, 0, 190, 'UTF-8'), $item]);
                    } elseif ($tipo === 'conversa_chat') {
                        $idAutorAlvo = $idRemetenteChat;
                    }

                    // Aplica medidas proporcionais na conta infratora e suspende qualificação automática em caso de violação confirmada
                    if ($idAutorAlvo > 0) {
                        $stmtModAnt = $pdo->prepare('SELECT modalidade_publicacao_usuario FROM usuario WHERE id_usuario = ? LIMIT 1');
                        $stmtModAnt->execute([$idAutorAlvo]);
                        $modAntInfrator = (string) ($stmtModAnt->fetchColumn() ?: 'avaliacao');
                        $motivoSuspDen = 'Permissão de publicação automática suspensa por denúncia julgada procedente (#' . $idDenuncia . ').';

                        if ($decisao === 'aviso_e_remover_mensagem') {
                            $pdo->prepare(
                                "UPDATE usuario
                                    SET avisos_moderacao_usuario = avisos_moderacao_usuario + 1,
                                        violacoes_confirmadas_usuario = violacoes_confirmadas_usuario + 1,
                                        modalidade_publicacao_usuario = 'suspensa',
                                        justificativa_modalidade_usuario = ?
                                  WHERE id_usuario = ? AND nivel_usuario <> 'admin'"
                            )->execute([$motivoSuspDen, $idAutorAlvo]);
                        } elseif ($decisao === 'restricao_temporaria_chat') {
                            $pdo->prepare(
                                "UPDATE usuario
                                    SET avisos_moderacao_usuario = avisos_moderacao_usuario + 1,
                                        violacoes_confirmadas_usuario = violacoes_confirmadas_usuario + 1,
                                        modalidade_publicacao_usuario = 'suspensa',
                                        justificativa_modalidade_usuario = ?,
                                        restricao_chat_ate = DATE_ADD(NOW(), INTERVAL 7 DAY)
                                  WHERE id_usuario = ? AND nivel_usuario <> 'admin'"
                            )->execute([$motivoSuspDen, $idAutorAlvo]);
                        } elseif ($decisao === 'remover_item') {
                            $pdo->prepare(
                                "UPDATE usuario
                                    SET violacoes_confirmadas_usuario = violacoes_confirmadas_usuario + 1,
                                        modalidade_publicacao_usuario = 'suspensa',
                                        justificativa_modalidade_usuario = ?
                                  WHERE id_usuario = ? AND nivel_usuario <> 'admin'"
                            )->execute([$motivoSuspDen, $idAutorAlvo]);
                        } elseif ($decisao === 'remover_e_banir_reincidente') {
                            $pdo->prepare(
                                "UPDATE usuario
                                    SET status_usuario = 'banido',
                                        violacoes_confirmadas_usuario = violacoes_confirmadas_usuario + 1,
                                        modalidade_publicacao_usuario = 'suspensa',
                                        justificativa_modalidade_usuario = ?
                                  WHERE id_usuario = ? AND nivel_usuario <> 'admin'"
                            )->execute([$motivoSuspDen, $idAutorAlvo]);
                            $pdo->prepare("UPDATE loja_produtos SET status_produto = 'arquivado' WHERE id_autor = ?")->execute([$idAutorAlvo]);
                            $pdo->prepare("UPDATE autor_dos_livros SET status_autor_dos_livros = 'removido' WHERE id_autor = ?")->execute([$idAutorAlvo]);
                            $pdo->prepare("UPDATE historias SET status_historias = 'despublicada' WHERE id_usuario = ?")->execute([$idAutorAlvo]);
                        }

                        folivro_registrar_historico_moderacao(
                            $pdo,
                            $idAutorAlvo,
                            $idModerador,
                            'violacao_confirmada',
                            "Denúncia #{$idDenuncia} procedente ({$decisao}): {$justificativa}",
                            $modAntInfrator,
                            'suspensa',
                            $tipo === 'obra_autoral' ? $item : null,
                            null
                        );
                    }

                    $pdo->prepare(
                        "UPDATE denuncias
                            SET status_denuncias = 'procedente', suspensao_preventiva = 0,
                                acao_moderacao_aplicada = ?,
                                justificativa_moderacao = ?, data_resposta_denuncia = CURRENT_TIMESTAMP,
                                retencao_evidencia_ate = COALESCE(retencao_evidencia_ate, DATE_ADD(NOW(), INTERVAL 180 DAY))
                          WHERE id_denuncias = ?"
                    )->execute([$decisao, $justificativa, $idDenuncia]);

                    if ($decisao === 'aviso_e_remover_mensagem') {
                        $sucesso = 'Decisão aplicada: aviso formal registrado na conta e mensagem removida do chat.';
                    } elseif ($decisao === 'restricao_temporaria_chat') {
                        $sucesso = 'Decisão aplicada: mensagem removida e restrição temporária do chat por 7 dias imposta à conta infratora.';
                    } elseif ($decisao === 'remover_e_banir_reincidente') {
                        $sucesso = 'Denúncia julgada procedente, item removido e conta reincidente suspensa.';
                    } else {
                        $sucesso = 'Denúncia julgada procedente, item removido e justificativa registrada.';
                    }
                } else {
                    $erro = 'Ação inválida.';
                }
                $pdo->commit();
                if ($idObraParaSincronizar > 0) {
                    require_once __DIR__ . '/config/loja.php';
                    folivro_loja_sincronizar_obra_estudio($pdo, $idObraParaSincronizar);
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $erro = 'Não foi possível processar a decisão.';
            }
        }
    }
}

$filaRevisao = $pdo->query(
    "SELECT a.*, u.nome_usuario AS nome_autor, u.email_usuario AS email_autor,
            u.modalidade_publicacao_usuario,
            h.origem_obra_historias, h.licenca_historias, h.status_historias,
            (SELECT COUNT(*) FROM capitulos c WHERE c.id_historias = a.id_historias) AS total_capitulos
       FROM autor_dos_livros a
       JOIN usuario u ON u.id_usuario = a.id_autor
       LEFT JOIN historias h ON h.id_historias = a.id_historias
      WHERE a.revisao_publicacao IN ('pendente', 'recusada', 'aprovada')
         OR a.status_autor_dos_livros <> 'removido'
      ORDER BY FIELD(a.revisao_publicacao, 'pendente', 'recusada', 'aprovada', 'nao_solicitada'),
               a.data_publicacao_autor DESC
      LIMIT 80"
)->fetchAll();

// Fila de capítulos: pendentes de revisão humana, publicados automaticamente (moderação posterior) e recusados/ocultados
$filaCapitulos = $pdo->query(
    "SELECT c.*, h.titulo_historias, h.id_usuario AS id_autor_obra,
            a.id_autor_dos_livros, a.status_autor_dos_livros,
            u.nome_usuario AS nome_autor, u.modalidade_publicacao_usuario
       FROM capitulos c
       JOIN historias h ON h.id_historias = c.id_historias
       JOIN autor_dos_livros a ON a.id_historias = h.id_historias
       JOIN usuario u ON u.id_usuario = h.id_usuario
      WHERE a.status_autor_dos_livros <> 'removido'
      ORDER BY FIELD(c.revisao_capitulos, 'pendente', 'publicado_automatico', 'oculto_moderacao', 'recusada', 'aprovada', 'nao_solicitada'),
               c.data_criacao_capitulos DESC
      LIMIT 80"
)->fetchAll();

// Lista de autores para gestão de qualificação de confiança
$autoresRows = $pdo->query(
    "SELECT DISTINCT u.id_usuario, u.nome_usuario, u.email_usuario, u.nivel_usuario,
            u.modalidade_publicacao_usuario, u.justificativa_modalidade_usuario, u.data_qualificacao_usuario
       FROM usuario u
      WHERE u.nivel_usuario = 'autor'
         OR EXISTS (SELECT 1 FROM autor_dos_livros a WHERE a.id_autor = u.id_usuario)
      ORDER BY u.id_usuario ASC"
)->fetchAll();

$autoresConfianca = [];
foreach ($autoresRows as $ar) {
    $avalAutor = folivro_avaliar_qualificacao_autor($pdo, (int) $ar['id_usuario'], true);
    $autoresConfianca[] = array_merge($ar, ['avaliacao_confianca' => $avalAutor]);
}

// Histórico auditável de decisões administrativas e alterações de qualificação
$historicoModLogs = $pdo->query(
    "SELECT hm.*, u_aut.nome_usuario AS nome_autor, u_mod.nome_usuario AS nome_moderador,
            a.titulo_autor_dos_livros AS titulo_obra, c.titulo_capitulos AS titulo_capitulo
       FROM historico_moderacao_autor hm
       JOIN usuario u_aut ON u_aut.id_usuario = hm.id_autor
  LEFT JOIN usuario u_mod ON u_mod.id_usuario = hm.id_moderador
  LEFT JOIN autor_dos_livros a ON a.id_autor_dos_livros = hm.id_autor_dos_livros
  LEFT JOIN capitulos c ON c.id_capitulos = hm.id_capitulos
   ORDER BY hm.data_evento DESC, hm.id_historico_mod DESC
      LIMIT 50"
)->fetchAll();

$lista = $pdo->query(
    "SELECT d.*, u.nome_usuario AS denunciante,
            a.id_autor AS id_autor_obra, a.titulo_autor_dos_livros AS titulo_obra,
            cm.conteudo_mensagem AS texto_mensagem_chat,
            cm.status_mensagem AS status_mensagem_chat,
            cm.id_conversa AS id_conversa_da_mensagem,
            u_msg.nome_usuario AS autor_mensagem_chat,
            u_msg.avisos_moderacao_usuario AS avisos_autor_msg,
            c_com.comentarios_comentario AS texto_comentario_denunciado,
            u_com.nome_usuario AS autor_comentario_denunciado,
            (SELECT COUNT(*)
               FROM denuncias d2
               LEFT JOIN autor_dos_livros a2 ON d2.tipo_denuncias = 'obra_autoral' AND a2.id_autor_dos_livros = d2.item_denuncia
              WHERE d2.status_denuncias = 'procedente'
                AND a2.id_autor IS NOT NULL AND a2.id_autor = a.id_autor) AS reincidencias_autor
       FROM denuncias d
       JOIN usuario u ON u.id_usuario = d.id_usuario
  LEFT JOIN autor_dos_livros a ON d.tipo_denuncias = 'obra_autoral' AND a.id_autor_dos_livros = d.item_denuncia
  LEFT JOIN chat_mensagens cm ON d.tipo_denuncias = 'mensagem_chat' AND cm.id_mensagem = d.item_denuncia
  LEFT JOIN usuario u_msg ON u_msg.id_usuario = cm.id_remetente
  LEFT JOIN comentarios c_com ON d.tipo_denuncias = 'comentario' AND c_com.id_comentarios = d.item_denuncia
  LEFT JOIN usuario u_com ON u_com.id_usuario = c_com.id_usuario
   ORDER BY FIELD(d.status_denuncias, 'aberta', 'em_analise', 'procedente', 'improcedente'),
            FIELD(d.prioridade_denuncia, 'alta', 'media', 'baixa'),
            d.data_denuncia DESC
      LIMIT 100"
)->fetchAll();

$csrf = folivro_escape(folivro_csrf_token());
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moderação de denúncias | FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261022_11">
    <link rel="stylesheet" href="home.css?v=20261022_11">
</head>

<body>

    <?php $menuAtivo = 'moderacao'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <p><a href="usuario.php">← Voltar ao perfil</a></p>
        <h1>Painel de moderação, triagem e direitos autorais</h1>
        <p>Gerencie a fila de denúncias com prioridade para suspeitas de violação de direitos autorais e segurança no chat, aplique suspensão preventiva, analise contestações de autores e registre decisões proporcionais e fundamentadas.</p>
        <p><small><strong>Aviso institucional e privacidade:</strong> As ferramentas deste painel apoiam a governança da comunidade (<em>Notice and Takedown</em>), mas não substituem assessoria jurídica habilitada. O acesso a trechos denunciados do chat é restrito à moderação e retido por 180 dias apenas para auditoria de segurança e direitos autorais.</small></p>

        <?php if ($erro): ?>
            <p class="estado-vazio estado-erro" role="alert"><?= folivro_escape($erro) ?></p>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <p class="estado-vazio estado-sucesso" role="status"><?= folivro_escape($sucesso) ?></p>
        <?php endif; ?>

        <!-- SEÇÃO 1: MODERAÇÃO HÍBRIDA DE CAPÍTULOS (REVISÃO HUMANA PRÉVIA E AUDITORIA DE PUBLICAÇÃO AUTOMÁTICA) -->
        <section class="secao-perfil" aria-labelledby="titulo-fila-capitulos-hibrida">
            <h2 id="titulo-fila-capitulos-hibrida">Moderação híbrida de capítulos (Revisão prévia e publicações automáticas)</h2>
            <p>Aprove capítulos enviados por autores em período de avaliação ou audite capítulos disponibilizados automaticamente por autores qualificados. Capítulos anteriores já aprovados permanecem visíveis aos leitores mesmo quando um novo capítulo está pendente.</p>

            <?php if ($filaCapitulos): ?>
                <div class="filtros-biblioteca" role="toolbar" aria-label="Filtrar capítulos da moderação híbrida">
                    <button type="button" class="filtro-pilula ativo" data-filtro-cap="todos">Todos os capítulos (<?= count($filaCapitulos) ?>)</button>
                    <button type="button" class="filtro-pilula" data-filtro-cap="pendente">Aguardando revisão humana</button>
                    <button type="button" class="filtro-pilula" data-filtro-cap="automatico">Publicados automaticamente</button>
                    <button type="button" class="filtro-pilula" data-filtro-cap="oculto">Recusados / Ocultados</button>
                </div>
                <div class="lista-interacoes" id="lista-moderacao-capitulos">
                    <?php foreach ($filaCapitulos as $fc): ?>
                        <?php
                        $revCapStatus = (string) ($fc['revisao_capitulos'] ?? 'pendente');
                        $modoPubCap = (string) ($fc['modo_publicacao_capitulos'] ?? 'revisao_humana');
                        $ehAutoCap = ($modoPubCap === 'automatica' || $revCapStatus === 'publicado_automatico');
                        $ehPendCap = ($revCapStatus === 'pendente');
                        $ehOcultoCap = in_array($revCapStatus, ['recusada', 'oculto_moderacao'], true);
                        $resumoTextoCap = mb_substr(trim(strip_tags((string) $fc['conteudo_capitulos'])), 0, 260, 'UTF-8');
                        ?>
                        <article class="card-interacao item-moderacao"
                                 data-cap-pendente="<?= $ehPendCap ? '1' : '0' ?>"
                                 data-cap-automatico="<?= $ehAutoCap ? '1' : '0' ?>"
                                 data-cap-oculto="<?= $ehOcultoCap ? '1' : '0' ?>">
                            <header>
                                <strong>Capítulo #<?= (int) $fc['id_capitulos'] ?> (Cap. <?= (int) $fc['ordem_capitulos'] ?>: <?= folivro_escape($fc['titulo_capitulos']) ?>)</strong>
                                <span class="selo-status <?= $fc['status_capitulos'] === 'publicado' ? 'selo-lido' : '' ?>">
                                    <?= folivro_escape(folivro_status_label((string) $fc['status_capitulos'])) ?>
                                </span>
                                <span class="selo-status <?= $ehAutoCap ? 'selo-comprado' : '' ?>">
                                    <?= folivro_escape(folivro_status_label($revCapStatus)) ?>
                                </span>
                                <small>Obra: <strong><?= folivro_escape($fc['titulo_historias']) ?></strong> · Autor: <?= folivro_escape($fc['nome_autor']) ?></small>
                            </header>
                            <p><small><strong>Prévia do texto:</strong> <?= folivro_escape($resumoTextoCap) ?><?= mb_strlen(strip_tags((string) $fc['conteudo_capitulos']), 'UTF-8') > 260 ? '…' : '' ?></small></p>
                            <?php if (!empty($fc['justificativa_revisao_capitulos'])): ?>
                                <p><strong>Parecer anterior no capítulo:</strong> <?= folivro_escape((string) $fc['justificativa_revisao_capitulos']) ?></p>
                            <?php endif; ?>
                            <p><a href="obra.php?id=<?= (int) $fc['id_autor_dos_livros'] ?>#capitulo-<?= (int) $fc['ordem_capitulos'] ?>">Inspecionar capítulo na página da obra</a></p>
                            <details class="configurar-meta" <?= $ehPendCap ? 'open' : '' ?>>
                                <summary><?= $ehPendCap ? 'Aprovar ou recusar capítulo pendente' : ($ehAutoCap ? 'Moderação posterior de capítulo publicado automaticamente' : 'Atualizar decisão sobre o capítulo') ?></summary>
                                <form method="post" class="progresso-form">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="acao" value="revisar_capitulo">
                                    <input type="hidden" name="id_capitulos" value="<?= (int) $fc['id_capitulos'] ?>">
                                    <label>Justificativa fundamentada da moderação
                                        <textarea name="justificativa_capitulo" rows="2" maxlength="2000" required placeholder="Registre a análise do capítulo..."><?= folivro_escape((string) ($fc['justificativa_revisao_capitulos'] ?? '')) ?></textarea>
                                    </label>
                                    <label>Decisão sobre o capítulo
                                        <select name="decisao_capitulo" required>
                                            <option value="aprovar_capitulo">Aprovar capítulo por revisão humana (contabiliza no histórico positivo)</option>
                                            <option value="recusar_capitulo">Recusar capítulo e manter em rascunho para ajustes</option>
                                            <option value="ocultar_capitulo_automatico">Ocultar capítulo em moderação posterior (sem suspender qualificação)</option>
                                            <option value="confirmar_violacao_capitulo">Confirmar violação grave: ocultar capítulo e suspender publicação automática do autor</option>
                                        </select>
                                    </label>
                                    <button class="botao-adicionar-livro" type="submit">Confirmar decisão no capítulo</button>
                                </form>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="estado-vazio">Nenhum capítulo cadastrado no momento.</p>
            <?php endif; ?>
        </section>

        <!-- SEÇÃO 2: GESTÃO DE CONFIANÇA E QUALIFICAÇÃO DE AUTORES + HISTÓRICO AUDITÁVEL -->
        <section class="secao-perfil" aria-labelledby="titulo-qualificacao-autores">
            <h2 id="titulo-qualificacao-autores">Qualificação de confiança de autores e histórico de moderação</h2>
            <p>Consulte os indicadores objetivos de cada autor (capítulos aprovados por revisão humana, obras aprovadas, publicações automáticas, denúncias procedentes e violações confirmadas) e gerencie a permissão de publicação automática com justificativa registrada.</p>

            <?php if (!$autoresConfianca): ?>
                <p class="estado-vazio">Nenhum autor registrado com obras até o momento.</p>
            <?php else: ?>
                <div class="lista-interacoes">
                    <?php foreach ($autoresConfianca as $aut): ?>
                        <?php
                        $av = $aut['avaliacao_confianca'];
                        $met = $av['metricas'];
                        $modAut = (string) $av['modalidade'];
                        ?>
                        <article class="card-interacao item-moderacao">
                            <header>
                                <strong>Autor #<?= (int) $aut['id_usuario'] ?> · <?= folivro_escape($aut['nome_usuario']) ?> (<?= folivro_escape($aut['email_usuario']) ?>)</strong>
                                <span class="selo-status <?= $modAut === 'automatica' ? 'selo-lido' : ($modAut === 'suspensa' ? 'estado-erro' : 'selo-comprado') ?>">
                                    <?= folivro_escape(folivro_status_label($modAut)) ?>
                                </span>
                            </header>
                            <p>
                                <strong>Capítulos aprovados (revisão humana):</strong> <?= (int) $met['capitulos_aprovados_humano'] ?>
                                · <strong>Capítulos automáticos:</strong> <?= (int) $met['capitulos_automaticos'] ?>
                                · <strong>Obras aprovadas:</strong> <?= (int) $met['obras_aprovadas'] ?>
                                · <strong>Violações confirmadas:</strong> <?= (int) $met['violacoes_confirmadas'] ?>
                                · <strong>Denúncias procedentes:</strong> <?= (int) $met['denuncias_procedentes'] ?>
                                · <strong>Recusas editoriais:</strong> <?= (int) $met['revisoes_recusadas'] ?>
                            </p>
                            <?php if (!empty($av['justificativa_admin'])): ?>
                                <p><small><strong>Última justificativa registrada:</strong> <?= folivro_escape($av['justificativa_admin']) ?></small></p>
                            <?php endif; ?>
                            <details class="configurar-meta">
                                <summary>Alterar qualificação / modalidade de publicação do autor</summary>
                                <form method="post" class="progresso-form">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="acao" value="gerenciar_qualificacao_autor">
                                    <input type="hidden" name="id_autor_alvo" value="<?= (int) $aut['id_usuario'] ?>">
                                    <label>Nova modalidade de publicação do autor
                                        <select name="nova_modalidade" required>
                                            <option value="avaliacao" <?= $modAut === 'avaliacao' ? 'selected' : '' ?>>Em período de avaliação (qualifica automaticamente ao cumprir critérios)</option>
                                            <option value="automatica" <?= $modAut === 'automatica' ? 'selected' : '' ?>>Qualificado para publicação automática (histórico positivo)</option>
                                            <option value="revisao_obrigatoria" <?= $modAut === 'revisao_obrigatoria' ? 'selected' : '' ?>>Exigir revisão humana obrigatória (trava administrativa)</option>
                                            <option value="suspensa" <?= $modAut === 'suspensa' ? 'selected' : '' ?>>Suspender publicação automática por violação (exigir revisão humana)</option>
                                        </select>
                                    </label>
                                    <label>Justificativa administrativa obrigatória
                                        <textarea name="justificativa_qualificacao" rows="2" maxlength="2000" required placeholder="Fundamente a concessão, revogação ou exigência de revisão humana..."></textarea>
                                    </label>
                                    <button class="botao-adicionar-livro" type="submit">Salvar permissão do autor</button>
                                </form>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <details class="configurar-meta" style="margin-top: 16px;">
                <summary>Consultar histórico auditável de decisões e permissões de moderação (<?= count($historicoModLogs) ?> registros recentes)</summary>
                <?php if (!$historicoModLogs): ?>
                    <p class="estado-vazio">Nenhum evento registrado no histórico de moderação de autores até o momento.</p>
                <?php else: ?>
                    <div class="lista-interacoes" style="margin-top: 10px;">
                        <?php foreach ($historicoModLogs as $hlog): ?>
                            <article class="card-interacao" style="padding: 12px 16px;">
                                <header>
                                    <strong>#<?= (int) $hlog['id_historico_mod'] ?> · <?= folivro_escape($hlog['tipo_evento']) ?></strong>
                                    <span class="selo-status">Autor: <?= folivro_escape($hlog['nome_autor']) ?></span>
                                    <?php if (!empty($hlog['modalidade_nova'])): ?>
                                        <span class="selo-status"><?= folivro_escape((string) ($hlog['modalidade_anterior'] ?? '—')) ?> → <?= folivro_escape((string) $hlog['modalidade_nova']) ?></span>
                                    <?php endif; ?>
                                    <small><?= date('d/m/Y H:i', strtotime((string) $hlog['data_evento'])) ?> · Moderador: <?= folivro_escape((string) ($hlog['nome_moderador'] ?? 'Sistema automático')) ?></small>
                                </header>
                                <?php if (!empty($hlog['titulo_obra']) || !empty($hlog['titulo_capitulo'])): ?>
                                    <p style="margin:4px 0;"><small>Obra: <?= folivro_escape((string) ($hlog['titulo_obra'] ?? '—')) ?><?= !empty($hlog['titulo_capitulo']) ? ' · Capítulo: ' . folivro_escape((string) $hlog['titulo_capitulo']) : '' ?></small></p>
                                <?php endif; ?>
                                <p style="margin:4px 0 0;"><?= folivro_escape((string) $hlog['justificativa']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </details>
        </section>

        <section class="secao-perfil" aria-labelledby="titulo-fila-revisao-publicacao">
            <h2 id="titulo-fila-revisao-publicacao">Fila de revisão humana de publicação de obras autorais</h2>
            <p>A declaração de autoria, finalidade acadêmica ou domínio público informado pelo autor não autoriza publicação automática. Verifique a base legal, os metadados e o conteúdo dos capítulos antes de aprovar qualquer disponibilização na loja ou na comunidade.</p>
            <?php if (!$filaRevisao): ?>
                <p class="estado-vazio">Nenhuma obra autoral cadastrada para revisão de publicação no momento.</p>
            <?php else: ?>
                <div class="lista-interacoes">
                    <?php foreach ($filaRevisao as $rev): ?>
                        <?php
                        $statusRev = (string) ($rev['revisao_publicacao'] ?? 'nao_solicitada');
                        $origemRev = (string) ($rev['origem_obra_historias'] ?? 'autoral_original');
                        $bloqueadaPorOrigem = in_array($origemRev, ['fanfic_sem_autorizacao', 'dominio_publico_nao_verificado'], true);
                        $modDist = (string) ($rev['modalidade_distribuicao'] ?? 'venda_simulada');
                        ?>
                        <article class="card-interacao item-moderacao">
                            <header>
                                <strong>Obra #<?= (int) $rev['id_autor_dos_livros'] ?> · <?= folivro_escape($rev['titulo_autor_dos_livros']) ?></strong>
                                <span class="selo-status <?= $statusRev === 'aprovada' ? 'selo-lido' : ($statusRev === 'pendente' ? 'selo-comprado' : '') ?>">
                                    <?= folivro_escape(folivro_status_label($statusRev)) ?>
                                </span>
                                <span class="selo-status">Status atual: <?= folivro_escape(folivro_status_label((string) $rev['status_autor_dos_livros'])) ?></span>
                                <small>Autor: <?= folivro_escape($rev['nome_autor']) ?> · Capítulos: <?= (int) ($rev['total_capitulos'] ?? 0) ?></small>
                            </header>
                            <p>
                                <strong>Origem/base legal informada:</strong> <?= folivro_escape(folivro_origem_obra_label($origemRev)) ?>
                                · <strong>Licença:</strong> <?= folivro_escape((string) ($rev['licenca_historias'] ?? 'todos_os_direitos_reservados')) ?>
                                · <strong>Modalidade:</strong> <?= $modDist === 'gratuita' ? 'Distribuição gratuita' : 'Venda simulada (R$ ' . number_format((float) $rev['preco_dos_livros_autor'], 2, ',', '.') . ')' ?>
                            </p>
                            <?php if ($bloqueadaPorOrigem): ?>
                                <p class="aviso-direitos" role="alert"><strong>Bloqueio normativo:</strong> Esta obra foi declarada como derivada sem autorização ou domínio público não verificado. A aprovação para publicação ou venda está bloqueada até comprovação formal de titularidade/base legal.</p>
                            <?php endif; ?>
                            <?php if (!empty($rev['justificativa_revisao'])): ?>
                                <p><strong>Parecer editorial anterior:</strong> <?= folivro_escape($rev['justificativa_revisao']) ?></p>
                            <?php endif; ?>
                            <p><a href="obra.php?id=<?= (int) $rev['id_autor_dos_livros'] ?>">Inspecionar obra completa e capítulos</a></p>
                            <details class="configurar-meta" <?= $statusRev === 'pendente' ? 'open' : '' ?>>
                                <summary><?= $statusRev === 'pendente' ? 'Avaliar solicitação de publicação' : 'Atualizar revisão editorial de publicação' ?></summary>
                                <form method="post" class="progresso-form">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="acao" value="revisar_publicacao">
                                    <input type="hidden" name="id_autor_dos_livros" value="<?= (int) $rev['id_autor_dos_livros'] ?>">
                                    <label>Parecer fundamentado da revisão humana
                                        <textarea name="justificativa_revisao" rows="2" maxlength="2000" required placeholder="Registre a verificação realizada sobre autoria, licença e conformidade do conteúdo..."><?= folivro_escape((string) ($rev['justificativa_revisao'] ?? '')) ?></textarea>
                                    </label>
                                    <label>Decisão editorial
                                        <select name="decisao_revisao" required>
                                            <?php if (!$bloqueadaPorOrigem): ?>
                                                <option value="aprovar_publicacao">Aprovar publicação na loja e comunidade</option>
                                            <?php endif; ?>
                                            <option value="recusar_publicacao" <?= $bloqueadaPorOrigem ? 'selected' : '' ?>>Recusar / manter como rascunho privado</option>
                                        </select>
                                    </label>
                                    <button class="botao-adicionar-livro" type="submit">Confirmar decisão editorial</button>
                                </form>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <h2>Fila de denúncias e contestações (Notice and Takedown)</h2>

        <?php if ($lista): ?>
            <div class="filtros-biblioteca" role="toolbar" aria-label="Filtrar fila de moderação">
                <button type="button" class="filtro-pilula ativo" data-filtro-mod="todas">Todas (<?= count($lista) ?>)</button>
                <button type="button" class="filtro-pilula" data-filtro-mod="alta">Alta prioridade / Direitos autorais</button>
                <button type="button" class="filtro-pilula" data-filtro-mod="chat">Denúncias do Chat</button>
                <button type="button" class="filtro-pilula" data-filtro-mod="pendentes">Pendentes de decisão</button>
                <button type="button" class="filtro-pilula" data-filtro-mod="contestadas">Com contestação do autor</button>
                <button type="button" class="filtro-pilula" data-filtro-mod="concluidas">Concluídas</button>
            </div>
        <?php endif; ?>

        <div class="lista-interacoes" id="fila-moderacao">
            <?php if (!$lista): ?>
                <p class="estado-vazio">Nenhuma denúncia registrada até o momento.</p>
            <?php else: ?>
                <?php foreach ($lista as $d): ?>
                    <?php
                    $pendente = in_array($d['status_denuncias'], ['aberta', 'em_analise'], true);
                    $temContestacao = !empty($d['contestacao_autor']);
                    $prioridade = (string) ($d['prioridade_denuncia'] ?? 'media');
                    $reincidencias = (int) ($d['reincidencias_autor'] ?? 0);
                    $ehChat = in_array($d['tipo_denuncias'], ['mensagem_chat', 'conversa_chat'], true);
                    ?>
                    <article class="card-interacao item-moderacao"
                             data-prioridade="<?= folivro_escape($prioridade) ?>"
                             data-chat="<?= $ehChat ? '1' : '0' ?>"
                             data-pendente="<?= $pendente ? '1' : '0' ?>"
                             data-contestada="<?= $temContestacao ? '1' : '0' ?>">
                        <header>
                            <strong>#<?= (int) $d['id_denuncias'] ?> · <?= folivro_escape(folivro_status_label($d['tipo_denuncias'])) ?> (ID <?= (int) $d['item_denuncia'] ?><?= !empty($d['titulo_obra']) ? ' — ' . folivro_escape($d['titulo_obra']) : '' ?>)</strong>
                            <span class="selo-status <?= $prioridade === 'alta' ? 'selo-comprado' : '' ?>"><?= folivro_escape(folivro_status_label($prioridade)) ?></span>
                            <span class="selo-status <?= $d['status_denuncias'] === 'procedente' ? 'selo-lido' : '' ?>">Status: <?= folivro_escape(folivro_status_label($d['status_denuncias'])) ?></span>
                            <?php if (!empty($d['suspensao_preventiva'])): ?>
                                <span class="selo-status">Medida preventiva ativa</span>
                            <?php endif; ?>
                            <?php if (!empty($d['acao_moderacao_aplicada'])): ?>
                                <span class="selo-status">Medida: <?= folivro_escape(folivro_status_label((string) $d['acao_moderacao_aplicada'])) ?></span>
                            <?php endif; ?>
                            <?php if ($reincidencias > 0): ?>
                                <span class="selo-status">Reincidência do autor: <?= $reincidencias ?> caso(s) procedente(s)</span>
                            <?php endif; ?>
                            <small>Denunciante (restrito à moderação): <?= folivro_escape($d['denunciante']) ?> em <?= date('d/m/Y H:i', strtotime($d['data_denuncia'])) ?></small>
                        </header>

                        <p><strong>Categoria / Motivo:</strong> <?= folivro_escape($d['motivo_denuncia']) ?></p>
                        <?php if (!empty($d['obra_referencia_denuncia'])): ?>
                            <p><strong>Obra/autor original indicado:</strong> <?= folivro_escape($d['obra_referencia_denuncia']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($d['descricao_denuncia'])): ?>
                            <p><strong>Evidências relatadas:</strong> <?= folivro_escape($d['descricao_denuncia']) ?></p>
                        <?php endif; ?>

                        <?php if ($d['tipo_denuncias'] === 'mensagem_chat'): ?>
                            <div class="evidencia-chat-moderacao">
                                <p>
                                    <strong>Evidência restrita da mensagem denunciada</strong>
                                    (Remetente: <?= folivro_escape((string) ($d['autor_mensagem_chat'] ?? 'Desconhecido')) ?>
                                    · Avisos anteriores: <?= (int) ($d['avisos_autor_msg'] ?? 0) ?>
                                    · Estado atual: <?= folivro_escape(folivro_status_label((string) ($d['status_mensagem_chat'] ?? ''))) ?>):
                                </p>
                                <blockquote><?= nl2br(folivro_escape((string) ($d['texto_mensagem_chat'] ?? '[Mensagem não encontrada]'))) ?></blockquote>
                                <?php if (!empty($d['retencao_evidencia_ate'])): ?>
                                    <small>Retenção legal da evidência até: <?= folivro_escape(date('d/m/Y', strtotime((string) $d['retencao_evidencia_ate']))) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($d['tipo_denuncias'] === 'conversa_chat'): ?>
                            <div class="evidencia-chat-moderacao">
                                <?php
                                $stmtUltimas = $pdo->prepare(
                                    "SELECT cm.conteudo_mensagem, cm.data_envio, u.nome_usuario
                                       FROM chat_mensagens cm
                                       JOIN usuario u ON u.id_usuario = cm.id_remetente
                                      WHERE cm.id_conversa = ?
                                      ORDER BY cm.id_mensagem DESC
                                      LIMIT 5"
                                );
                                $stmtUltimas->execute([(int) $d['item_denuncia']]);
                                $ultimasMsgs = array_reverse($stmtUltimas->fetchAll());
                                ?>
                                <p><strong>Evidência restrita das últimas mensagens da conversa denunciada:</strong></p>
                                <?php if (!$ultimasMsgs): ?>
                                    <p><small>[Nenhuma mensagem encontrada nesta conversa]</small></p>
                                <?php else: ?>
                                    <?php foreach ($ultimasMsgs as $um): ?>
                                        <blockquote><strong><?= folivro_escape($um['nome_usuario']) ?> (<?= date('d/m H:i', strtotime((string) $um['data_envio'])) ?>):</strong> <?= nl2br(folivro_escape($um['conteudo_mensagem'])) ?></blockquote>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (!empty($d['retencao_evidencia_ate'])): ?>
                                    <small>Retenção legal do registro de denúncia da conversa até: <?= folivro_escape(date('d/m/Y', strtotime((string) $d['retencao_evidencia_ate']))) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($d['tipo_denuncias'] === 'comentario'): ?>
                            <div class="evidencia-chat-moderacao">
                                <p><strong>Comentário denunciado (Autor: <?= folivro_escape((string) ($d['autor_comentario_denunciado'] ?? 'Removido')) ?>):</strong></p>
                                <blockquote><?= nl2br(folivro_escape((string) ($d['texto_comentario_denunciado'] ?? '[Comentário já removido]'))) ?></blockquote>
                            </div>
                        <?php endif; ?>

                        <?php if ($temContestacao): ?>
                            <p><strong>Contestação / defesa do autor (<?= !empty($d['data_contestacao']) ? date('d/m/Y H:i', strtotime($d['data_contestacao'])) : '' ?>):</strong> <?= folivro_escape($d['contestacao_autor']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($d['justificativa_moderacao'])): ?>
                            <p><strong>Decisão / justificativa registrada:</strong> <?= folivro_escape($d['justificativa_moderacao']) ?></p>
                        <?php endif; ?>

                        <?php if ($d['tipo_denuncias'] === 'obra_autoral'): ?>
                            <p><a href="obra.php?id=<?= (int) $d['item_denuncia'] ?>">Inspecionar obra denunciada</a></p>
                        <?php endif; ?>

                        <details class="configurar-meta" <?= $pendente ? 'open' : '' ?>>
                            <summary><?= $pendente ? 'Registrar triagem ou decisão fundamentada' : 'Revisar decisão (após contestação ou nova análise)' ?></summary>
                            <form method="post" class="progresso-form">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="id_denuncias" value="<?= (int) $d['id_denuncias'] ?>">
                                <label>Justificativa fundamentada da decisão
                                    <textarea name="justificativa_moderacao" rows="2" maxlength="2000" required placeholder="Descreva os elementos verificados e o fundamento da decisão..."><?= folivro_escape((string) ($d['justificativa_moderacao'] ?? '')) ?></textarea>
                                </label>
                                <label>Medida proporcional a aplicar
                                    <select name="decisao" required>
                                        <option value="suspender_preventivamente">Triagem: Em análise + Ocultar/suspender preventivamente</option>
                                        <option value="aviso_e_remover_mensagem">Procedente (Leve): Emitir aviso formal ao usuário + Remover mensagem/item</option>
                                        <option value="restricao_temporaria_chat">Procedente (Moderada): Remover mensagem + Restringir chat do infrator por 7 dias</option>
                                        <option value="remover_item">Procedente: Remover definitivamente obra/mensagem e bloquear republicação por hash</option>
                                        <option value="remover_e_banir_reincidente">Procedente (Grave / Reincidente): Remover item e suspender conta infratora</option>
                                        <option value="improcedente">Improcedente / Acolher contestação: Arquivar denúncia e restaurar item</option>
                                    </select>
                                </label>
                                <button class="botao-adicionar-livro" type="submit">Confirmar decisão de moderação</button>
                            </form>
                        </details>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.querySelectorAll('[data-filtro-mod]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const f = btn.dataset.filtroMod;
                document.querySelectorAll('[data-filtro-mod]').forEach((b) => b.classList.toggle('ativo', b === btn));
                document.querySelectorAll('#fila-moderacao .item-moderacao').forEach((card) => {
                    if (f === 'todas') card.hidden = false;
                    else if (f === 'alta') card.hidden = card.dataset.prioridade !== 'alta';
                    else if (f === 'chat') card.hidden = card.dataset.chat !== '1';
                    else if (f === 'pendentes') card.hidden = card.dataset.pendente !== '1';
                    else if (f === 'contestadas') card.hidden = card.dataset.contestada !== '1';
                    else if (f === 'concluidas') card.hidden = card.dataset.pendente === '1';
                });
            });
        });

        document.querySelectorAll('[data-filtro-cap]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const f = btn.dataset.filtroCap;
                document.querySelectorAll('[data-filtro-cap]').forEach((b) => b.classList.toggle('ativo', b === btn));
                document.querySelectorAll('#lista-moderacao-capitulos .item-moderacao').forEach((card) => {
                    if (f === 'todos') card.hidden = false;
                    else if (f === 'pendente') card.hidden = card.dataset.capPendente !== '1';
                    else if (f === 'automatico') card.hidden = card.dataset.capAutomatico !== '1';
                    else if (f === 'oculto') card.hidden = card.dataset.capOculto !== '1';
                });
            });
        });
    </script>

</body>

</html>
