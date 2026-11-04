<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = folivro_database();
folivro_require_login();

$idUsuario = (int) $_SESSION['id_usuario'];

// Recarrega informações atualizadas do usuário (incluindo avisos e eventual restrição temporária no chat)
$stmtConta = $pdo->prepare(
    'SELECT id_usuario, nome_usuario, nivel_usuario, status_usuario, restricao_chat_ate, avisos_moderacao_usuario
     FROM usuario WHERE id_usuario = :id LIMIT 1'
);
$stmtConta->execute(['id' => $idUsuario]);
$contaAtual = $stmtConta->fetch();
if (!$contaAtual || ($contaAtual['status_usuario'] ?? 'ativo') !== 'ativo') {
    folivro_redirect_login();
}

$mensagemFeedback = '';
$tipoFeedback = 'sucesso';
$idConversaAtiva = max(0, (int) ($_GET['conversa'] ?? $_POST['id_conversa'] ?? 0));
$idUsuarioPreSelecionado = max(0, (int) ($_GET['usuario'] ?? 0));

/**
 * Busca conversa somente se o usuário autenticado for participante.
 */
function folivro_pagina_chat_conversa(PDO $pdo, int $idConversa, int $idUsuario): ?array
{
    if ($idConversa <= 0) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT c.*,
                u_outro.id_usuario AS outro_id,
                u_outro.nome_usuario AS outro_nome,
                u_outro.nivel_usuario AS outro_nivel,
                u_outro.status_usuario AS outro_status,
                u_outro.biografia_usuario AS outro_bio
         FROM chat_conversas c
         INNER JOIN usuario u_outro
            ON u_outro.id_usuario = CASE
                WHEN c.id_usuario_menor = :uid_case THEN c.id_usuario_maior
                ELSE c.id_usuario_menor
            END
         WHERE c.id_conversa = :id_c
           AND (c.id_usuario_menor = :uid_a OR c.id_usuario_maior = :uid_b)
         LIMIT 1'
    );
    $stmt->execute([
        'uid_case' => $idUsuario,
        'id_c' => $idConversa,
        'uid_a' => $idUsuario,
        'uid_b' => $idUsuario,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Verifica bloqueio mútuo ou unilateral entre dois usuários.
 */
function folivro_pagina_chat_bloqueio(PDO $pdo, int $idUsuario, int $idOutro): array
{
    $stmt = $pdo->prepare(
        'SELECT id_bloqueador, id_bloqueado
         FROM bloqueios_usuario
         WHERE (id_bloqueador = :u1 AND id_bloqueado = :u2)
            OR (id_bloqueador = :u3 AND id_bloqueado = :u4)'
    );
    $stmt->execute([
        'u1' => $idUsuario,
        'u2' => $idOutro,
        'u3' => $idOutro,
        'u4' => $idUsuario,
    ]);
    $euBloqueei = false;
    $outroBloqueou = false;
    foreach ($stmt->fetchAll() as $b) {
        if ((int) $b['id_bloqueador'] === $idUsuario) {
            $euBloqueei = true;
        }
        if ((int) $b['id_bloqueador'] === $idOutro) {
            $outroBloqueou = true;
        }
    }
    return [
        'bloqueado' => ($euBloqueei || $outroBloqueou),
        'eu_bloqueei' => $euBloqueei,
        'outro_bloqueou' => $outroBloqueou,
    ];
}

// Se veio com ?usuario=ID e já existe conversa entre os dois, abre diretamente a conversa existente
if ($idConversaAtiva <= 0 && $idUsuarioPreSelecionado > 0 && $idUsuarioPreSelecionado !== $idUsuario) {
    $menorPre = min($idUsuario, $idUsuarioPreSelecionado);
    $maiorPre = max($idUsuario, $idUsuarioPreSelecionado);
    $stmtPre = $pdo->prepare(
        'SELECT id_conversa FROM chat_conversas WHERE id_usuario_menor = :menor AND id_usuario_maior = :maior LIMIT 1'
    );
    $stmtPre->execute(['menor' => $menorPre, 'maior' => $maiorPre]);
    $convExistente = (int) ($stmtPre->fetchColumn() ?: 0);
    if ($convExistente > 0) {
        $idConversaAtiva = $convExistente;
    }
}

// Processamento de ações POST no servidor (com proteção CSRF e validação estrita)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES)) {
        $mensagemFeedback = 'O envio de arquivos, capas, PDFs ou anexos não é permitido no chat.';
        $tipoFeedback = 'erro';
    } elseif (!folivro_verify_csrf()) {
        $mensagemFeedback = 'Sessão expirada ou token inválido. Tente novamente.';
        $tipoFeedback = 'erro';
    } else {
        $acaoPost = (string) ($_POST['acao'] ?? '');

        try {
            if ($acaoPost === 'iniciar_conversa') {
                $idDestinatario = (int) ($_POST['id_destinatario'] ?? 0);
                if ($idDestinatario <= 0 || $idDestinatario === $idUsuario) {
                    $mensagemFeedback = 'Selecione um leitor ou autor válido diferente da sua própria conta.';
                    $tipoFeedback = 'erro';
                } else {
                    $stmtAlvo = $pdo->prepare(
                        'SELECT id_usuario, nome_usuario, status_usuario FROM usuario WHERE id_usuario = :id LIMIT 1'
                    );
                    $stmtAlvo->execute(['id' => $idDestinatario]);
                    $alvo = $stmtAlvo->fetch();

                    if (!$alvo || ($alvo['status_usuario'] ?? '') !== 'ativo') {
                        $mensagemFeedback = 'O usuário selecionado não está disponível para conversas.';
                        $tipoFeedback = 'erro';
                    } else {
                        $bloq = folivro_pagina_chat_bloqueio($pdo, $idUsuario, $idDestinatario);
                        if ($bloq['bloqueado']) {
                            $mensagemFeedback = $bloq['eu_bloqueei']
                                ? 'Você bloqueou este usuário. Desbloqueie-o caso deseje conversar novamente.'
                                : 'Não é possível iniciar uma conversa com este usuário.';
                            $tipoFeedback = 'erro';
                        } else {
                            $menor = min($idUsuario, $idDestinatario);
                            $maior = max($idUsuario, $idDestinatario);
                            $stmtBusca = $pdo->prepare(
                                'SELECT id_conversa FROM chat_conversas WHERE id_usuario_menor = :menor AND id_usuario_maior = :maior LIMIT 1'
                            );
                            $stmtBusca->execute(['menor' => $menor, 'maior' => $maior]);
                            $idConv = (int) ($stmtBusca->fetchColumn() ?: 0);

                            if ($idConv <= 0) {
                                $stmtTaxaConv = $pdo->prepare(
                                    'SELECT COUNT(*) FROM chat_conversas
                                     WHERE (id_usuario_menor = :u1 OR id_usuario_maior = :u2)
                                       AND data_criacao_conversa >= DATE_SUB(NOW(), INTERVAL 1 DAY)'
                                );
                                $stmtTaxaConv->execute(['u1' => $idUsuario, 'u2' => $idUsuario]);
                                if ((int) $stmtTaxaConv->fetchColumn() >= 15) {
                                    $mensagemFeedback = 'Limite diário de novas conversas atingido para prevenção de spam.';
                                    $tipoFeedback = 'erro';
                                } else {
                                    $stmtIns = $pdo->prepare(
                                        'INSERT INTO chat_conversas (id_usuario_menor, id_usuario_maior) VALUES (:menor, :maior)'
                                    );
                                    $stmtIns->execute(['menor' => $menor, 'maior' => $maior]);
                                    $idConv = (int) $pdo->lastInsertId();
                                }
                            }
                            if ($idConv > 0) {
                                header('Location: chat.php?conversa=' . $idConv);
                                exit;
                            }
                        }
                    }
                }
            } elseif ($acaoPost === 'enviar_mensagem') {
                $idConvPost = (int) ($_POST['id_conversa'] ?? 0);
                $textoBruto = (string) ($_POST['conteudo'] ?? '');
                $textoLimpo = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $textoBruto));
                $idConversaAtiva = $idConvPost;

                $conv = folivro_pagina_chat_conversa($pdo, $idConvPost, $idUsuario);
                if (!$conv) {
                    $mensagemFeedback = 'Acesso negado: você não participa desta conversa.';
                    $tipoFeedback = 'erro';
                } elseif (!empty($contaAtual['restricao_chat_ate']) && strtotime((string) $contaAtual['restricao_chat_ate']) > time()) {
                    $ateFmt = date('d/m/Y \à\s H:i', strtotime((string) $contaAtual['restricao_chat_ate']));
                    $mensagemFeedback = 'Seu envio de mensagens no chat está temporariamente restrito pela moderação até ' . $ateFmt . '.';
                    $tipoFeedback = 'erro';
                } elseif (($conv['outro_status'] ?? '') !== 'ativo') {
                    $mensagemFeedback = 'Não é possível enviar mensagens porque a conta destinatária não está ativa.';
                    $tipoFeedback = 'erro';
                } else {
                    $idOutro = (int) $conv['outro_id'];
                    $bloq = folivro_pagina_chat_bloqueio($pdo, $idUsuario, $idOutro);
                    $tam = mb_strlen($textoLimpo, 'UTF-8');

                    if ($bloq['bloqueado']) {
                        $mensagemFeedback = $bloq['eu_bloqueei']
                            ? 'Envio impedido: você bloqueou este usuário.'
                            : 'Envio impedido: não é possível enviar mensagens para este usuário.';
                        $tipoFeedback = 'erro';
                    } elseif ($tam < 1 || $tam > 800) {
                        $mensagemFeedback = 'A mensagem deve ter entre 1 e 800 caracteres.';
                        $tipoFeedback = 'erro';
                    } else {
                        $stmtRate = $pdo->prepare(
                            'SELECT
                                SUM(CASE WHEN data_envio_mensagem >= DATE_SUB(NOW(), INTERVAL 2 SECOND) THEN 1 ELSE 0 END) AS ultimos_2s,
                                COUNT(*) AS ultimo_minuto
                             FROM chat_mensagens
                             WHERE id_remetente = :uid
                               AND data_envio_mensagem >= DATE_SUB(NOW(), INTERVAL 60 SECOND)'
                        );
                        $stmtRate->execute(['uid' => $idUsuario]);
                        $dadosRate = $stmtRate->fetch();

                        if ((int) ($dadosRate['ultimos_2s'] ?? 0) >= 1) {
                            $mensagemFeedback = 'Aguarde pelo menos 2 segundos antes de enviar outra mensagem.';
                            $tipoFeedback = 'erro';
                        } elseif ((int) ($dadosRate['ultimo_minuto'] ?? 0) >= 12) {
                            $mensagemFeedback = 'Limite de frequência atingido (máximo de 12 mensagens por minuto). Aguarde um instante.';
                            $tipoFeedback = 'erro';
                        } else {
                            $hashMsg = folivro_content_hash($textoLimpo);
                            $stmtHashRem = $pdo->prepare(
                                'SELECT
                                    (SELECT COUNT(*) FROM chat_mensagens WHERE hash_conteudo_mensagem = :h1 AND status_mensagem = \'removida_moderacao\') +
                                    (SELECT COUNT(*) FROM historias h
                                      INNER JOIN autor_dos_livros a ON a.id_historias = h.id_historias
                                      WHERE h.hash_conteudo_historias = :h2 AND a.status_autor_dos_livros = \'removido\')'
                            );
                            $stmtHashRem->execute(['h1' => $hashMsg, 'h2' => $hashMsg]);

                            if ((int) $stmtHashRem->fetchColumn() > 0) {
                                $mensagemFeedback = 'Envio bloqueado: este conteúdo corresponde a material já removido pela moderação por violação de direitos autorais ou das regras da plataforma.';
                                $tipoFeedback = 'erro';
                            } else {
                                $avaliacao = folivro_chat_avaliar_conteudo($textoLimpo);
                                if ($avaliacao['acao'] === 'bloquear') {
                                    $mensagemFeedback = $avaliacao['motivo'];
                                    $tipoFeedback = 'erro';
                                } else {
                                    $statusInicial = ($avaliacao['acao'] === 'reter_para_moderacao') ? 'oculta_preventivamente' : 'ativa';
                                    $motivoOcultacao = ($avaliacao['acao'] === 'reter_para_moderacao') ? $avaliacao['motivo'] : null;

                                    $pdo->beginTransaction();
                                    $stmtInsMsg = $pdo->prepare(
                                        'INSERT INTO chat_mensagens
                                            (id_conversa, id_remetente, conteudo_mensagem, hash_conteudo_mensagem, status_mensagem, motivo_ocultacao)
                                         VALUES (:id_c, :uid, :conteudo, :hash_c, :status_m, :motivo_o)'
                                    );
                                    $stmtInsMsg->execute([
                                        'id_c' => $idConvPost,
                                        'uid' => $idUsuario,
                                        'conteudo' => $textoLimpo,
                                        'hash_c' => $hashMsg,
                                        'status_m' => $statusInicial,
                                        'motivo_o' => $motivoOcultacao,
                                    ]);
                                    $idNovaMsg = (int) $pdo->lastInsertId();

                                    $stmtUpConv = $pdo->prepare(
                                        'UPDATE chat_conversas SET data_ultima_mensagem = CURRENT_TIMESTAMP WHERE id_conversa = :id_c'
                                    );
                                    $stmtUpConv->execute(['id_c' => $idConvPost]);

                                    if ($statusInicial === 'oculta_preventivamente') {
                                        $stmtTriagem = $pdo->prepare(
                                            'INSERT INTO denuncias
                                                (id_usuario, tipo_denuncias, item_denuncia, motivo_denuncia, descricao_denuncia, prioridade_denuncia, suspensao_preventiva, status_denuncias, retencao_evidencia_ate)
                                             VALUES (:uid, \'mensagem_chat\', :id_msg, \'Violação de direitos autorais\', :desc, \'alta\', 1, \'em_analise\', DATE_ADD(NOW(), INTERVAL 180 DAY))'
                                        );
                                        $stmtTriagem->execute([
                                            'uid' => $idUsuario,
                                            'id_msg' => $idNovaMsg,
                                            'desc' => '[Triagem preventiva automática do chat] ' . $motivoOcultacao,
                                        ]);
                                    }
                                    $pdo->commit();

                                    if ($statusInicial === 'oculta_preventivamente') {
                                        $mensagemFeedback = 'Sua mensagem contém link externo de compartilhamento ou referência suspeita e foi retida preventivamente para revisão humana da moderação.';
                                        $tipoFeedback = 'erro';
                                    } else {
                                        $mensagemFeedback = 'Mensagem enviada com sucesso.';
                                    }
                                }
                            }
                        }
                    }
                }
            } elseif ($acaoPost === 'apagar_mensagem') {
                $idMensagem = (int) ($_POST['id_mensagem'] ?? 0);
                $stmtMsg = $pdo->prepare(
                    'SELECT m.id_mensagem, m.id_conversa, m.id_remetente, m.status_mensagem
                     FROM chat_mensagens m
                     INNER JOIN chat_conversas c ON c.id_conversa = m.id_conversa
                     WHERE m.id_mensagem = :id_m
                       AND (c.id_usuario_menor = :uid_a OR c.id_usuario_maior = :uid_b)
                     LIMIT 1'
                );
                $stmtMsg->execute([
                    'id_m' => $idMensagem,
                    'uid_a' => $idUsuario,
                    'uid_b' => $idUsuario,
                ]);
                $msgRow = $stmtMsg->fetch();
                if (!$msgRow || (int) $msgRow['id_remetente'] !== $idUsuario) {
                    $mensagemFeedback = 'Você só pode apagar mensagens enviadas por você mesmo.';
                    $tipoFeedback = 'erro';
                } elseif ($msgRow['status_mensagem'] !== 'ativa') {
                    $mensagemFeedback = 'Esta mensagem não pode ser apagada porque já foi removida ou está sob custódia da moderação.';
                    $tipoFeedback = 'erro';
                } else {
                    $stmtDel = $pdo->prepare(
                        'UPDATE chat_mensagens SET status_mensagem = \'apagada_pelo_autor\' WHERE id_mensagem = :id_m AND id_remetente = :uid'
                    );
                    $stmtDel->execute(['id_m' => $idMensagem, 'uid' => $idUsuario]);
                    $idConversaAtiva = (int) $msgRow['id_conversa'];
                    $mensagemFeedback = 'Sua mensagem foi apagada.';
                }
            } elseif ($acaoPost === 'bloquear_usuario') {
                $idAlvo = (int) ($_POST['id_usuario_alvo'] ?? 0);
                $motivo = mb_substr(trim((string) ($_POST['motivo'] ?? '')), 0, 150, 'UTF-8');
                if ($idAlvo <= 0 || $idAlvo === $idUsuario) {
                    $mensagemFeedback = 'Usuário inválido para bloqueio.';
                    $tipoFeedback = 'erro';
                } else {
                    $stmtBloq = $pdo->prepare(
                        'INSERT INTO bloqueios_usuario (id_bloqueador, id_bloqueado, motivo_bloqueio)
                         VALUES (:b1, :b2, :motivo)
                         ON DUPLICATE KEY UPDATE motivo_bloqueio = VALUES(motivo_bloqueio)'
                    );
                    $stmtBloq->execute([
                        'b1' => $idUsuario,
                        'b2' => $idAlvo,
                        'motivo' => $motivo !== '' ? $motivo : null,
                    ]);
                    $mensagemFeedback = 'Usuário bloqueado. Novas mensagens entre vocês foram bloqueadas no servidor.';
                }
            } elseif ($acaoPost === 'desbloquear_usuario') {
                $idAlvo = (int) ($_POST['id_usuario_alvo'] ?? 0);
                $stmtDes = $pdo->prepare(
                    'DELETE FROM bloqueios_usuario WHERE id_bloqueador = :b1 AND id_bloqueado = :b2'
                );
                $stmtDes->execute(['b1' => $idUsuario, 'b2' => $idAlvo]);
                $mensagemFeedback = 'Usuário desbloqueado com sucesso.';
            } elseif ($acaoPost === 'denunciar_chat') {
                $tipoAlvo = (string) ($_POST['tipo_alvo'] ?? '');
                $idAlvo = (int) ($_POST['id_alvo'] ?? 0);
                $categoria = (string) ($_POST['categoria'] ?? '');
                $obraReferencia = trim((string) ($_POST['obra_referencia'] ?? ''));
                $descricao = trim((string) ($_POST['descricao'] ?? ''));
                $mapaMotivos = [
                    'assedio' => 'Assédio ou comportamento abusivo',
                    'ameaca' => 'Ameaça ou intimidação',
                    'spam' => 'Spam ou propaganda indesejada',
                    'direitos_autorais' => 'Violação de direitos autorais',
                    'outro' => 'Outro motivo de segurança',
                ];

                if (!in_array($tipoAlvo, ['mensagem_chat', 'conversa_chat'], true) || $idAlvo <= 0) {
                    $mensagemFeedback = 'Item de denúncia inválido.';
                    $tipoFeedback = 'erro';
                } elseif (!isset($mapaMotivos[$categoria])) {
                    $mensagemFeedback = 'Selecione uma categoria válida para a denúncia.';
                    $tipoFeedback = 'erro';
                } elseif (mb_strlen($descricao, 'UTF-8') < 10 || mb_strlen($descricao, 'UTF-8') > 1000) {
                    $mensagemFeedback = 'Descreva o ocorrido com entre 10 e 1000 caracteres para análise da moderação.';
                    $tipoFeedback = 'erro';
                } else {
                    $autorizado = false;
                    if ($tipoAlvo === 'mensagem_chat') {
                        $stmtVer = $pdo->prepare(
                            'SELECT m.id_mensagem, m.id_conversa, m.id_remetente
                             FROM chat_mensagens m
                             INNER JOIN chat_conversas c ON c.id_conversa = m.id_conversa
                             WHERE m.id_mensagem = :id_m
                               AND (c.id_usuario_menor = :uid_a OR c.id_usuario_maior = :uid_b)
                             LIMIT 1'
                        );
                        $stmtVer->execute(['id_m' => $idAlvo, 'uid_a' => $idUsuario, 'uid_b' => $idUsuario]);
                        $alvoMsg = $stmtVer->fetch();
                        if ($alvoMsg && (int) $alvoMsg['id_remetente'] !== $idUsuario) {
                            $autorizado = true;
                            $idConversaAtiva = (int) $alvoMsg['id_conversa'];
                        }
                    } else {
                        $convVer = folivro_pagina_chat_conversa($pdo, $idAlvo, $idUsuario);
                        if ($convVer) {
                            $autorizado = true;
                            $idConversaAtiva = $idAlvo;
                        }
                    }

                    if (!$autorizado) {
                        $mensagemFeedback = 'Acesso negado: você só pode denunciar conversas ou mensagens de outros participantes em conversas das quais faz parte.';
                        $tipoFeedback = 'erro';
                    } else {
                        $stmtDup = $pdo->prepare(
                            'SELECT id_denuncias FROM denuncias
                             WHERE id_usuario = :uid AND tipo_denuncias = :tipo AND item_denuncia = :alvo
                               AND status_denuncias IN (\'aberta\', \'em_analise\') LIMIT 1'
                        );
                        $stmtDup->execute(['uid' => $idUsuario, 'tipo' => $tipoAlvo, 'alvo' => $idAlvo]);
                        if ($stmtDup->fetch()) {
                            $mensagemFeedback = 'Você já possui uma denúncia em aberto para este item.';
                            $tipoFeedback = 'erro';
                        } else {
                            $pdo->beginTransaction();
                            $ocultou = false;
                            $statusInicialDen = 'aberta';
                            $prioridade = in_array($categoria, ['direitos_autorais', 'ameaca', 'assedio'], true) ? 'alta' : 'media';

                            if ($tipoAlvo === 'mensagem_chat' && $categoria === 'direitos_autorais' && mb_strlen($descricao, 'UTF-8') >= 15) {
                                $stmtOc = $pdo->prepare(
                                    'UPDATE chat_mensagens
                                     SET status_mensagem = \'oculta_preventivamente\',
                                         motivo_ocultacao = \'Mensagem ocultada preventivamente devido a denúncia de possível violação de direitos autorais.\'
                                     WHERE id_mensagem = :id_m AND status_mensagem = \'ativa\''
                                );
                                $stmtOc->execute(['id_m' => $idAlvo]);
                                $ocultou = ($stmtOc->rowCount() > 0);
                                $statusInicialDen = 'em_analise';
                            }

                            $stmtInsDen = $pdo->prepare(
                                'INSERT INTO denuncias
                                    (id_usuario, tipo_denuncias, item_denuncia, motivo_denuncia, obra_referencia_denuncia, descricao_denuncia, prioridade_denuncia, suspensao_preventiva, status_denuncias, retencao_evidencia_ate)
                                 VALUES (:uid, :tipo, :alvo, :motivo, :obra_ref, :desc, :prio, :susp, :status_d, DATE_ADD(NOW(), INTERVAL 180 DAY))'
                            );
                            $stmtInsDen->execute([
                                'uid' => $idUsuario,
                                'tipo' => $tipoAlvo,
                                'alvo' => $idAlvo,
                                'motivo' => $mapaMotivos[$categoria],
                                'obra_ref' => $obraReferencia !== '' ? mb_substr($obraReferencia, 0, 255, 'UTF-8') : null,
                                'desc' => $descricao,
                                'prio' => $prioridade,
                                'susp' => $ocultou ? 1 : 0,
                                'status_d' => $statusInicialDen,
                            ]);
                            $pdo->commit();

                            $mensagemFeedback = $ocultou
                                ? 'Denúncia de direitos autorais registrada. A mensagem denunciada foi ocultada preventivamente até decisão da moderação.'
                                : 'Denúncia registrada com sucesso. A equipe de moderação analisará o caso com sigilo.';
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensagemFeedback = 'Não foi possível concluir a operação solicitada no momento.';
            $tipoFeedback = 'erro';
        }
    }
}

// Carrega lista de conversas do usuário logado
$stmtConversas = $pdo->prepare(
    'SELECT c.id_conversa, c.id_usuario_menor, c.id_usuario_maior, c.data_ultima_mensagem,
            u_outro.id_usuario AS outro_id,
            u_outro.nome_usuario AS outro_nome,
            u_outro.nivel_usuario AS outro_nivel,
            u_outro.status_usuario AS outro_status,
            (SELECT cm2.conteudo_mensagem FROM chat_mensagens cm2
              WHERE cm2.id_conversa = c.id_conversa AND cm2.status_mensagem = \'ativa\'
              ORDER BY cm2.id_mensagem DESC LIMIT 1) AS ultima_mensagem
     FROM chat_conversas c
     INNER JOIN usuario u_outro
        ON u_outro.id_usuario = CASE
            WHEN c.id_usuario_menor = :uid_case THEN c.id_usuario_maior
            ELSE c.id_usuario_menor
        END
     WHERE c.id_usuario_menor = :uid_a OR c.id_usuario_maior = :uid_b
     ORDER BY c.data_ultima_mensagem DESC'
);
$stmtConversas->execute([
    'uid_case' => $idUsuario,
    'uid_a' => $idUsuario,
    'uid_b' => $idUsuario,
]);
$listaConversas = $stmtConversas->fetchAll();

if ($idConversaAtiva <= 0 && $idUsuarioPreSelecionado <= 0 && !empty($listaConversas)) {
    $idConversaAtiva = (int) $listaConversas[0]['id_conversa'];
}

$conversaSelecionada = $idConversaAtiva > 0
    ? folivro_pagina_chat_conversa($pdo, $idConversaAtiva, $idUsuario)
    : null;

$mensagensConversa = [];
$estadoBloqueioAtivo = ['bloqueado' => false, 'eu_bloqueei' => false, 'outro_bloqueou' => false];

if ($conversaSelecionada) {
    $idOutroAtivo = (int) $conversaSelecionada['outro_id'];
    $estadoBloqueioAtivo = folivro_pagina_chat_bloqueio($pdo, $idUsuario, $idOutroAtivo);

    $stmtMsgs = $pdo->prepare(
        'SELECT m.*, u.nome_usuario AS nome_remetente
         FROM chat_mensagens m
         INNER JOIN usuario u ON u.id_usuario = m.id_remetente
         WHERE m.id_conversa = :id_c
         ORDER BY m.id_mensagem ASC
         LIMIT 150'
    );
    $stmtMsgs->execute(['id_c' => (int) $conversaSelecionada['id_conversa']]);
    $mensagensConversa = $stmtMsgs->fetchAll();
}

// Lista de leitores e autores ativos para iniciar conversa (exibe apenas id, nome e perfil público, sem e-mail)
$stmtUsuariosComunidade = $pdo->prepare(
    'SELECT id_usuario, nome_usuario, nivel_usuario
     FROM usuario
     WHERE id_usuario <> :uid AND status_usuario = \'ativo\'
     ORDER BY nome_usuario ASC
     LIMIT 100'
);
$stmtUsuariosComunidade->execute(['uid' => $idUsuario]);
$usuariosComunidade = $stmtUsuariosComunidade->fetchAll();

// Lista de usuários bloqueados pelo usuário logado
$stmtMeusBloqueios = $pdo->prepare(
    'SELECT b.id_bloqueado, b.motivo_bloqueio, b.data_bloqueio, u.nome_usuario, u.nivel_usuario
     FROM bloqueios_usuario b
     INNER JOIN usuario u ON u.id_usuario = b.id_bloqueado
     WHERE b.id_bloqueador = :uid
     ORDER BY b.data_bloqueio DESC'
);
$stmtMeusBloqueios->execute(['uid' => $idUsuario]);
$meusBloqueios = $stmtMeusBloqueios->fetchAll();

// Histórico resumido das denúncias feitas pelo próprio usuário no chat (sem expor dados privados de terceiros)
$stmtMinhasDenuncias = $pdo->prepare(
    'SELECT id_denuncias, tipo_denuncias, motivo_denuncia, status_denuncias, acao_moderacao_aplicada, data_denuncia, retencao_evidencia_ate
     FROM denuncias
     WHERE id_usuario = :uid
       AND tipo_denuncias IN (\'mensagem_chat\', \'conversa_chat\')
     ORDER BY data_denuncia DESC
     LIMIT 15'
);
$stmtMinhasDenuncias->execute(['uid' => $idUsuario]);
$minhasDenunciasChat = $stmtMinhasDenuncias->fetchAll();

$statusDenunciaLabels = [
    'aberta' => 'Recebida (aguardando triagem)',
    'em_analise' => 'Em análise pela moderação (medida preventiva ativa)',
    'procedente' => 'Concluída com medida aplicada pela moderação',
    'improcedente' => 'Analisada e arquivada pela moderação',
];
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Literário | FOLIVRO</title>
    <link rel="stylesheet" href="home.css?v=20261022_11">
    <link rel="stylesheet" href="layout.css?v=20261022_11">
    <meta name="csrf-token" content="<?= folivro_escape(folivro_csrf_token()) ?>">
</head>

<body>

    <?php $menuAtivo = 'chat'; require __DIR__ . '/includes/menu-lateral.php'; ?>

    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">
        <header class="chat-cabecalho-pagina">
            <div>
                <span class="selo-status">Comunidade literária · Segurança, privacidade e direitos autorais</span>
                <h1>Chat Literário do FOLIVRO</h1>
                <p>Converse sobre leituras, processos criativos e obras autorais com proteção contra assédio, spam e compartilhamento não autorizado de obras protegidas.</p>
            </div>
            <div class="acoes-obra">
                <a href="termos.php" class="botao-mais">Termos, Privacidade e Direitos Autorais</a>
            </div>
        </header>

        <!-- Diretrizes obrigatórias de Direitos Autorais e Privacidade no Chat -->
        <section class="aviso-direitos-chat" aria-label="Regras de direitos autorais e privacidade do chat">
            <div class="aviso-direitos-chat-coluna">
                <h2>Proteção de Direitos Autorais no Chat</h2>
                <p>
                    <strong>Não envie nem solicite cópias não autorizadas</strong> de livros, capítulos, arquivos PDF/EPUB, traduções, capas, ilustrações ou outros materiais protegidos.
                    O envio de arquivos ou anexos é bloqueado nesta etapa.
                </p>
                <p class="texto-apoio-pequeno">
                    <strong>Regra fundamental:</strong> nem a declaração de um usuário, nem o caráter privado da conversa, nem a finalidade acadêmica autorizam reproduzir ou distribuir obras protegidas sem permissão documentada do(a) titular ou base legal verificada. Links de download de livros são bloqueados e mensagens denunciadas por violação autoral são ocultadas preventivamente até revisão humana.
                </p>
            </div>
            <div class="aviso-direitos-chat-coluna">
                <h2>Privacidade, Acesso Restrito e Retenção (LGPD)</h2>
                <p>
                    Suas mensagens são privadas e acessíveis apenas aos dois participantes da conversa.
                    <strong>Quem pode acessar mensagens denunciadas:</strong> exclusivamente moderadores/administradores autenticados do FOLIVRO.
                </p>
                <p class="texto-apoio-pequeno">
                    <strong>Finalidade e retenção:</strong> o acesso ocorre estritamente para analisar denúncias de segurança (assédio, ameaça, spam) ou violação de direitos autorais. Registros e evidências vinculados a denúncias são retidos sob sigilo pelo prazo de <strong>180 dias (6 meses)</strong> para auditoria e cumprimento legal (Marco Civil da Internet e LGPD).
                </p>
            </div>
        </section>

        <?php if (!empty($contaAtual['restricao_chat_ate']) && strtotime((string) $contaAtual['restricao_chat_ate']) > time()): ?>
            <p class="estado-vazio estado-erro" role="alert">
                <strong>Restrição temporária ativa no chat:</strong> sua conta está impedida de enviar novas mensagens até
                <strong><?= folivro_escape(date('d/m/Y \à\s H:i', strtotime((string) $contaAtual['restricao_chat_ate']))) ?></strong>
                por decisão fundamentada da moderação.
            </p>
        <?php endif; ?>

        <?php if ((int) ($contaAtual['avisos_moderacao_usuario'] ?? 0) > 0): ?>
            <p class="estado-vazio" role="status">
                <strong>Aviso da moderação:</strong> sua conta possui <strong><?= (int) $contaAtual['avisos_moderacao_usuario'] ?> aviso(s) formal(is)</strong> registrado(s). Reincidências em spam, assédio ou compartilhamento de obras protegidas resultam em restrição ou suspensão.
            </p>
        <?php endif; ?>

        <?php if ($mensagemFeedback !== ''): ?>
            <p class="estado-vazio <?= $tipoFeedback === 'erro' ? 'estado-erro' : 'estado-sucesso' ?>" role="status">
                <?= folivro_escape($mensagemFeedback) ?>
            </p>
        <?php endif; ?>

        <div class="grade-chat-literario">
            <!-- Coluna Esquerda: Iniciar conversa + Lista de Conversas -->
            <aside class="painel-conversas-chat" aria-label="Suas conversas">
                <div class="bloco-iniciar-conversa">
                    <h2>Nova Conversa</h2>
                    <form method="post" action="chat.php" class="form-iniciar-conversa">
                        <input type="hidden" name="csrf_token" value="<?= folivro_escape(folivro_csrf_token()) ?>">
                        <input type="hidden" name="acao" value="iniciar_conversa">
                        <label for="select-destinatario">Escolher leitor(a) ou autor(a) da comunidade</label>
                        <select id="select-destinatario" name="id_destinatario" required>
                            <option value="">Selecione um(a) participante...</option>
                            <?php foreach ($usuariosComunidade as $uCom): ?>
                                <option value="<?= (int) $uCom['id_usuario'] ?>" <?= $idUsuarioPreSelecionado === (int) $uCom['id_usuario'] ? 'selected' : '' ?>>
                                    <?= folivro_escape($uCom['nome_usuario']) ?> (<?= folivro_escape(ucfirst((string) $uCom['nivel_usuario'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="botao-adicionar-livro">Abrir conversa</button>
                    </form>
                </div>

                <div class="cabecalho-lista-conversas">
                    <h2>Conversas Participantes (<?= count($listaConversas) ?>)</h2>
                </div>

                <?php if (empty($listaConversas)): ?>
                    <p class="estado-vazio">Você ainda não iniciou nenhuma conversa literária. Escolha um(a) leitor(a) ou autor(a) acima para começar.</p>
                <?php else: ?>
                    <ul class="lista-conversas-itens" id="lista-conversas-ul">
                        <?php foreach ($listaConversas as $convItem): ?>
                            <?php
                            $idC = (int) $convItem['id_conversa'];
                            $ativa = ($conversaSelecionada && (int) $conversaSelecionada['id_conversa'] === $idC);
                            $bloqItem = folivro_pagina_chat_bloqueio($pdo, $idUsuario, (int) $convItem['outro_id']);
                            $resumoUltima = trim((string) ($convItem['ultima_mensagem'] ?? ''));
                            if ($resumoUltima === '') {
                                $resumoUltima = 'Sem mensagens ativas ainda.';
                            } elseif (mb_strlen($resumoUltima, 'UTF-8') > 52) {
                                $resumoUltima = mb_substr($resumoUltima, 0, 52, 'UTF-8') . '...';
                            }
                            ?>
                            <li>
                                <a href="chat.php?conversa=<?= $idC ?>" class="item-conversa-link <?= $ativa ? 'ativa' : '' ?>">
                                    <span class="avatar-chat-letra"><?= folivro_escape(mb_strtoupper(mb_substr((string) $convItem['outro_nome'], 0, 1))) ?></span>
                                    <div class="item-conversa-info">
                                        <div class="item-conversa-topo">
                                            <strong><?= folivro_escape((string) $convItem['outro_nome']) ?></strong>
                                            <small><?= folivro_escape(date('d/m H:i', strtotime((string) $convItem['data_ultima_mensagem']))) ?></small>
                                        </div>
                                        <p class="item-conversa-previa">
                                            <?php if ($bloqItem['bloqueado']): ?>
                                                <span class="selo-bloqueio-chat">Bloqueio ativo</span>
                                            <?php else: ?>
                                                <?= folivro_escape($resumoUltima) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </aside>

            <!-- Coluna Direita: Janela da Conversa Ativa -->
            <section class="painel-mensagens-chat" aria-label="Mensagens da conversa selecionada">
                <?php if (!$conversaSelecionada): ?>
                    <div class="estado-vazio-chat-principal">
                        <h2>Selecione ou inicie uma conversa</h2>
                        <p>Troque impressões sobre capítulos originais, clubes de leitura e processos criativos com respeito aos direitos autorais e às normas de convivência.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $idConvAtual = (int) $conversaSelecionada['id_conversa'];
                    $idOutroUsuario = (int) $conversaSelecionada['outro_id'];
                    $nomeOutroUsuario = (string) $conversaSelecionada['outro_nome'];
                    $nivelOutroUsuario = ucfirst((string) $conversaSelecionada['outro_nivel']);
                    ?>
                    <header class="cabecalho-conversa-ativa">
                        <div class="participante-conversa-dados">
                            <span class="avatar-chat-letra"><?= folivro_escape(mb_strtoupper(mb_substr($nomeOutroUsuario, 0, 1))) ?></span>
                            <div>
                                <h2><?= folivro_escape($nomeOutroUsuario) ?></h2>
                                <span class="subtitulo-participante">
                                    Perfil: <?= folivro_escape($nivelOutroUsuario) ?>
                                    <?php if ($estadoBloqueioAtivo['eu_bloqueei']): ?>
                                        · <strong class="texto-perigo">Bloqueado por você</strong>
                                    <?php elseif ($estadoBloqueioAtivo['outro_bloqueou']): ?>
                                        · <strong class="texto-perigo">Indisponível para novas mensagens</strong>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <div class="acoes-conversa-topo">
                            <?php if ($estadoBloqueioAtivo['eu_bloqueei']): ?>
                                <form method="post" action="chat.php?conversa=<?= $idConvAtual ?>" class="form-inline-chat">
                                    <input type="hidden" name="csrf_token" value="<?= folivro_escape(folivro_csrf_token()) ?>">
                                    <input type="hidden" name="acao" value="desbloquear_usuario">
                                    <input type="hidden" name="id_usuario_alvo" value="<?= $idOutroUsuario ?>">
                                    <button type="submit" class="filtro-pilula">Desbloquear usuário</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="chat.php?conversa=<?= $idConvAtual ?>" class="form-inline-chat" onsubmit="return confirm('Deseja bloquear este usuário? Novas mensagens entre vocês serão impedidas no servidor.');">
                                    <input type="hidden" name="csrf_token" value="<?= folivro_escape(folivro_csrf_token()) ?>">
                                    <input type="hidden" name="acao" value="bloquear_usuario">
                                    <input type="hidden" name="id_usuario_alvo" value="<?= $idOutroUsuario ?>">
                                    <input type="hidden" name="motivo" value="Bloqueio acionado pelo participante no chat">
                                    <button type="submit" class="filtro-pilula botao-perigo-suave">Bloquear usuário</button>
                                </form>
                            <?php endif; ?>

                            <button type="button" class="filtro-pilula" onclick="abrirFormularioDenunciaChat('conversa_chat', <?= $idConvAtual ?>, 'Conversa com <?= folivro_escape(addslashes($nomeOutroUsuario)) ?>')">
                                Denunciar conversa
                            </button>
                        </div>
                    </header>

                    <div class="historico-mensagens-chat" id="historico-mensagens-chat" data-id-conversa="<?= $idConvAtual ?>">
                        <?php if (empty($mensagensConversa)): ?>
                            <p class="estado-vazio" id="aviso-sem-mensagens">
                                Nenhuma mensagem enviada ainda nesta conversa. Envie uma saudação literária respeitando as regras da comunidade.
                            </p>
                        <?php else: ?>
                            <?php foreach ($mensagensConversa as $m): ?>
                                <?php
                                $idMsg = (int) $m['id_mensagem'];
                                $souEu = ((int) $m['id_remetente'] === $idUsuario);
                                $statusMsg = (string) ($m['status_mensagem'] ?? 'ativa');
                                ?>
                                <article class="balao-mensagem <?= $souEu ? 'minha-mensagem' : 'outra-mensagem' ?> <?= $statusMsg !== 'ativa' ? 'mensagem-sistema-status' : '' ?>" data-id-mensagem="<?= $idMsg ?>">
                                    <header class="balao-cabecalho">
                                        <strong><?= $souEu ? 'Você' : folivro_escape((string) $m['nome_remetente']) ?></strong>
                                        <time datetime="<?= folivro_escape((string) $m['data_envio_mensagem']) ?>">
                                            <?= folivro_escape(date('d/m/Y H:i', strtotime((string) $m['data_envio_mensagem']))) ?>
                                        </time>
                                    </header>

                                    <div class="balao-corpo">
                                        <?php if ($statusMsg === 'apagada_pelo_autor'): ?>
                                            <p class="texto-mensagem-removida">Mensagem apagada pelo(a) remetente.</p>
                                        <?php elseif ($statusMsg === 'oculta_preventivamente'): ?>
                                            <p class="texto-mensagem-removida">
                                                <?= $souEu
                                                    ? 'Sua mensagem foi retida preventivamente para revisão humana da moderação (segurança e direitos autorais).'
                                                    : 'Mensagem ocultada preventivamente enquanto aguarda revisão da moderação.' ?>
                                            </p>
                                        <?php elseif ($statusMsg === 'removida_moderacao'): ?>
                                            <p class="texto-mensagem-removida">
                                                Mensagem removida pela moderação por violação das regras de convivência ou de direitos autorais.
                                            </p>
                                        <?php else: ?>
                                            <p class="texto-mensagem-ativa"><?= nl2br(folivro_escape((string) $m['conteudo_mensagem'])) ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($statusMsg === 'ativa'): ?>
                                        <footer class="balao-acoes">
                                            <?php if ($souEu): ?>
                                                <form method="post" action="chat.php?conversa=<?= $idConvAtual ?>" class="form-inline-chat" onsubmit="return confirm('Deseja apagar esta mensagem?');">
                                                    <input type="hidden" name="csrf_token" value="<?= folivro_escape(folivro_csrf_token()) ?>">
                                                    <input type="hidden" name="acao" value="apagar_mensagem">
                                                    <input type="hidden" name="id_mensagem" value="<?= $idMsg ?>">
                                                    <button type="submit" class="link-acao-mensagem" title="Apagar minha mensagem">Apagar</button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="link-acao-mensagem denunciar" onclick="abrirFormularioDenunciaChat('mensagem_chat', <?= $idMsg ?>, 'Mensagem #<?= $idMsg ?> de <?= folivro_escape(addslashes($nomeOutroUsuario)) ?>')">
                                                    Denunciar mensagem
                                                </button>
                                            <?php endif; ?>
                                        </footer>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Caixa de Envio ou Aviso de Bloqueio -->
                    <footer class="rodape-envio-chat">
                        <?php if ($estadoBloqueioAtivo['bloqueado']): ?>
                            <div class="aviso-conversa-bloqueada">
                                <strong>Envio bloqueado:</strong>
                                <?= $estadoBloqueioAtivo['eu_bloqueei']
                                    ? 'Você bloqueou este usuário. Enquanto o bloqueio estiver ativo, novas mensagens não podem ser enviadas entre vocês.'
                                    : 'O envio de novas mensagens nesta conversa não está disponível.' ?>
                            </div>
                        <?php else: ?>
                            <form method="post" action="chat.php?conversa=<?= $idConvAtual ?>" id="form-enviar-mensagem-chat" class="form-enviar-chat">
                                <input type="hidden" name="csrf_token" value="<?= folivro_escape(folivro_csrf_token()) ?>">
                                <input type="hidden" name="acao" value="enviar_mensagem">
                                <input type="hidden" name="id_conversa" value="<?= $idConvAtual ?>">

                                <div class="campo-mensagem-wrapper">
                                    <label for="campo-texto-chat" class="sr-only">Escreva sua mensagem literária</label>
                                    <textarea
                                        id="campo-texto-chat"
                                        name="conteudo"
                                        rows="2"
                                        maxlength="800"
                                        required
                                        placeholder="Converse sobre literatura e obras autorais (máx. 800 caracteres). Não envie links de PDFs, e-books ou cópias não autorizadas."></textarea>
                                    <div class="barra-meta-envio">
                                        <span class="contador-caracteres-chat" id="contador-caracteres-chat">0 / 800 caracteres · Sem anexos</span>
                                        <button type="submit" class="botao-adicionar-livro" id="botao-enviar-chat">Enviar mensagem</button>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </footer>
                <?php endif; ?>
            </section>
        </div>

        <!-- Painel de Formulário de Denúncia Contextual -->
        <section class="card-interacao secao-denuncia-chat" id="secao-denuncia-chat" hidden aria-labelledby="titulo-form-denuncia-chat">
            <div class="cabecalho-secao-denuncia">
                <h2 id="titulo-form-denuncia-chat">Registrar Denúncia para a Moderação</h2>
                <button type="button" class="filtro-pilula" onclick="fecharFormularioDenunciaChat()">Cancelar</button>
            </div>
            <p class="texto-apoio-pequeno">
                Sua denúncia será analisada exclusivamente pela equipe de moderação. Denúncias plausíveis de violação de direitos autorais ocultam preventivamente a mensagem denunciada até a revisão humana.
            </p>
            <form method="post" action="chat.php<?= $idConversaAtiva > 0 ? '?conversa=' . $idConversaAtiva : '' ?>" class="form-denuncia-chat">
                <input type="hidden" name="csrf_token" value="<?= folivro_escape(folivro_csrf_token()) ?>">
                <input type="hidden" name="acao" value="denunciar_chat">
                <input type="hidden" name="tipo_alvo" id="denuncia-tipo-alvo" value="mensagem_chat">
                <input type="hidden" name="id_alvo" id="denuncia-id-alvo" value="0">

                <div class="campo-form-chat">
                    <label for="denuncia-rotulo-alvo">Item selecionado para análise</label>
                    <input type="text" id="denuncia-rotulo-alvo" readonly value="">
                </div>

                <div class="campo-form-chat">
                    <label for="denuncia-categoria">Categoria da denúncia *</label>
                    <select id="denuncia-categoria" name="categoria" required>
                        <option value="assedio">Assédio ou comportamento abusivo</option>
                        <option value="ameaca">Ameaça ou intimidação</option>
                        <option value="spam">Spam ou propaganda indesejada</option>
                        <option value="direitos_autorais">Possível violação de direitos autorais (envio/solicitação de cópia não autorizada)</option>
                        <option value="outro">Outro motivo de segurança</option>
                    </select>
                </div>

                <div class="campo-form-chat">
                    <label for="denuncia-obra-ref">Obra ou autor de referência (opcional — útil em casos de direitos autorais)</label>
                    <input type="text" id="denuncia-obra-ref" name="obra_referencia" maxlength="255" placeholder="Ex.: Título do livro, autor(a) ou editora afetada">
                </div>

                <div class="campo-form-chat">
                    <label for="denuncia-descricao">Descrição objetiva do ocorrido (10 a 1000 caracteres) *</label>
                    <textarea id="denuncia-descricao" name="descricao" rows="3" minlength="10" maxlength="1000" required placeholder="Explique brevemente por que esta mensagem ou conversa viola as regras de convivência ou direitos autorais."></textarea>
                </div>

                <div>
                    <button type="submit" class="botao-adicionar-livro">Enviar denúncia à moderação</button>
                </div>
            </form>
        </section>

        <!-- Seção Inferior: Gestão de Bloqueios e Acompanhamento de Denúncias -->
        <div class="grade-apoio-chat">
            <section class="card-interacao" aria-labelledby="titulo-bloqueios">
                <h2 id="titulo-bloqueios">Usuários Bloqueados por Você</h2>
                <p class="texto-apoio-pequeno">Usuários bloqueados ficam impedidos de enviar ou receber novas mensagens suas tanto na interface quanto por chamadas diretas à API.</p>
                <?php if (empty($meusBloqueios)): ?>
                    <p class="estado-vazio">Você não possui usuários bloqueados no momento.</p>
                <?php else: ?>
                    <ul class="lista-bloqueios-simples">
                        <?php foreach ($meusBloqueios as $bloqRow): ?>
                            <li>
                                <div>
                                    <strong><?= folivro_escape((string) $bloqRow['nome_usuario']) ?></strong>
                                    <span class="texto-apoio-pequeno">(<?= folivro_escape(ucfirst((string) $bloqRow['nivel_usuario'])) ?>) · bloqueado em <?= folivro_escape(date('d/m/Y', strtotime((string) $bloqRow['data_bloqueio']))) ?></span>
                                </div>
                                <form method="post" action="chat.php<?= $idConversaAtiva > 0 ? '?conversa=' . $idConversaAtiva : '' ?>">
                                    <input type="hidden" name="csrf_token" value="<?= folivro_escape(folivro_csrf_token()) ?>">
                                    <input type="hidden" name="acao" value="desbloquear_usuario">
                                    <input type="hidden" name="id_usuario_alvo" value="<?= (int) $bloqRow['id_bloqueado'] ?>">
                                    <button type="submit" class="filtro-pilula">Desbloquear</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="card-interacao" aria-labelledby="titulo-status-denuncias">
                <h2 id="titulo-status-denuncias">Estado das Suas Denúncias no Chat</h2>
                <p class="texto-apoio-pequeno">Acompanhe o andamento das denúncias que você enviou, preservando o sigilo e os dados privados das partes.</p>
                <?php if (empty($minhasDenunciasChat)): ?>
                    <p class="estado-vazio">Você não registrou denúncias no chat até o momento.</p>
                <?php else: ?>
                    <div class="tabela-responsiva-chat">
                        <table class="tabela-simples-chat">
                            <thead>
                                <tr>
                                    <th>Protocolo</th>
                                    <th>Alvo / Categoria</th>
                                    <th>Estado da Análise</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($minhasDenunciasChat as $denRow): ?>
                                    <?php $stKey = (string) ($denRow['status_denuncias'] ?? 'aberta'); ?>
                                    <tr>
                                        <td>#<?= (int) $denRow['id_denuncias'] ?></td>
                                        <td>
                                            <strong><?= $denRow['tipo_denuncias'] === 'mensagem_chat' ? 'Mensagem' : 'Conversa' ?></strong><br>
                                            <small><?= folivro_escape((string) $denRow['motivo_denuncia']) ?></small>
                                        </td>
                                        <td>
                                            <span class="selo-status"><?= folivro_escape($statusDenunciaLabels[$stKey] ?? ucfirst($stKey)) ?></span>
                                            <?php if (!empty($denRow['acao_moderacao_aplicada'])): ?>
                                                <br><small>Medida: <?= folivro_escape(folivro_status_label((string) $denRow['acao_moderacao_aplicada'])) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= folivro_escape(date('d/m/Y H:i', strtotime((string) $denRow['data_denuncia']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <script>
        (function () {
            const historico = document.getElementById('historico-mensagens-chat');
            if (historico) {
                historico.scrollTop = historico.scrollHeight;
            }

            const campoTexto = document.getElementById('campo-texto-chat');
            const contador = document.getElementById('contador-caracteres-chat');
            if (campoTexto && contador) {
                const atualizarContador = function () {
                    const len = Array.from(campoTexto.value).length;
                    contador.textContent = len + ' / 800 caracteres · Sem anexos';
                };
                campoTexto.addEventListener('input', atualizarContador);
                atualizarContador();
            }
        })();

        function abrirFormularioDenunciaChat(tipoAlvo, idAlvo, rotulo) {
            const secao = document.getElementById('secao-denuncia-chat');
            const inputTipo = document.getElementById('denuncia-tipo-alvo');
            const inputId = document.getElementById('denuncia-id-alvo');
            const inputRotulo = document.getElementById('denuncia-rotulo-alvo');
            const campoDesc = document.getElementById('denuncia-descricao');

            if (!secao || !inputTipo || !inputId || !inputRotulo) return;
            inputTipo.value = tipoAlvo;
            inputId.value = String(idAlvo);
            inputRotulo.value = rotulo;
            secao.hidden = false;
            secao.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (campoDesc) {
                campoDesc.focus();
            }
        }

        function fecharFormularioDenunciaChat() {
            const secao = document.getElementById('secao-denuncia-chat');
            if (secao) {
                secao.hidden = true;
            }
        }
    </script>
</body>

</html>
