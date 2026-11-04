<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$pdo = folivro_database();
folivro_require_login();

$idUsuario = (int) $_SESSION['id_usuario'];

// Proibição estrita de anexos ou envio de arquivos nesta etapa
if (!empty($_FILES)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'mensagem' => 'O envio de arquivos, PDFs, e-books, imagens ou anexos não é permitido no chat do FOLIVRO.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$entradaJson = [];
if ($metodo === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decodificado = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decodificado)) {
            $entradaJson = $decodificado;
        }
    }
    if (!empty($entradaJson['csrf_token']) && empty($_POST['csrf_token'])) {
        $_POST['csrf_token'] = (string) $entradaJson['csrf_token'];
    }
    if (!folivro_verify_csrf()) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'mensagem' => 'Token de segurança inválido ou expirado. Recarregue a página e tente novamente.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$acao = (string) ($entradaJson['acao'] ?? $_POST['acao'] ?? $_GET['acao'] ?? '');

$stmtConta = $pdo->prepare(
    'SELECT id_usuario, nome_usuario, nivel_usuario, status_usuario, restricao_chat_ate, avisos_moderacao_usuario
     FROM usuario WHERE id_usuario = :id LIMIT 1'
);
$stmtConta->execute(['id' => $idUsuario]);
$contaAtual = $stmtConta->fetch();

if (!$contaAtual || ($contaAtual['status_usuario'] ?? '') !== 'ativo') {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'mensagem' => 'Sua conta não está ativa para utilizar o chat.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Busca conversa garantindo no servidor que o usuário autenticado faz parte dela.
 */
function folivro_chat_obter_conversa_autorizada(PDO $pdo, int $idConversa, int $idUsuario): ?array
{
    if ($idConversa <= 0) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT c.*,
                u1.nome_usuario AS nome_menor, u1.nivel_usuario AS nivel_menor, u1.status_usuario AS status_menor,
                u2.nome_usuario AS nome_maior, u2.nivel_usuario AS nivel_maior, u2.status_usuario AS status_maior
         FROM chat_conversas c
         INNER JOIN usuario u1 ON u1.id_usuario = c.id_usuario_menor
         INNER JOIN usuario u2 ON u2.id_usuario = c.id_usuario_maior
         WHERE c.id_conversa = :id_c
           AND (c.id_usuario_menor = :uid_a OR c.id_usuario_maior = :uid_b)
         LIMIT 1'
    );
    $stmt->execute([
        'id_c' => $idConversa,
        'uid_a' => $idUsuario,
        'uid_b' => $idUsuario,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Verifica estado de bloqueio entre dois usuários em ambas as direções.
 *
 * @return array{bloqueado: bool, eu_bloqueei: bool, outro_bloqueou: bool}
 */
function folivro_chat_estado_bloqueio(PDO $pdo, int $idUsuario, int $idOutro): array
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

/**
 * Formata uma mensagem para retorno seguro na API (sem expor conteúdo apagado ou oculto).
 */
function folivro_chat_formatar_mensagem(array $msg, int $idUsuario): array
{
    $status = (string) ($msg['status_mensagem'] ?? 'ativa');
    $souRemetente = ((int) $msg['id_remetente'] === $idUsuario);
    $textoExibicao = (string) $msg['conteudo_mensagem'];
    $avisoSistema = null;

    if ($status === 'apagada_pelo_autor') {
        $textoExibicao = '';
        $avisoSistema = 'Mensagem apagada pelo(a) remetente.';
    } elseif ($status === 'oculta_preventivamente') {
        $textoExibicao = '';
        $avisoSistema = $souRemetente
            ? 'Sua mensagem foi retida preventivamente para análise humana da moderação (segurança e direitos autorais).'
            : 'Mensagem ocultada preventivamente enquanto aguarda revisão da moderação.';
    } elseif ($status === 'removida_moderacao') {
        $textoExibicao = '';
        $avisoSistema = 'Mensagem removida pela moderação por violação das regras de convivência ou direitos autorais.';
    }

    return [
        'id_mensagem' => (int) $msg['id_mensagem'],
        'id_conversa' => (int) $msg['id_conversa'],
        'id_remetente' => (int) $msg['id_remetente'],
        'nome_remetente' => (string) ($msg['nome_remetente'] ?? 'Leitor'),
        'sou_remetente' => $souRemetente,
        'status_mensagem' => $status,
        'conteudo' => $textoExibicao,
        'aviso_sistema' => $avisoSistema,
        'criado_em' => (string) $msg['data_envio_mensagem'],
        'horario_curto' => date('d/m H:i', strtotime((string) $msg['data_envio_mensagem'])),
        'pode_apagar' => ($souRemetente && $status === 'ativa'),
        'pode_denunciar' => (!$souRemetente && $status === 'ativa'),
    ];
}

try {
    if ($acao === 'listar_conversas') {
        $stmt = $pdo->prepare(
            'SELECT c.id_conversa, c.id_usuario_menor, c.id_usuario_maior, c.data_ultima_mensagem,
                    u_outro.id_usuario AS outro_id,
                    u_outro.nome_usuario AS outro_nome,
                    u_outro.nivel_usuario AS outro_nivel,
                    u_outro.status_usuario AS outro_status
             FROM chat_conversas c
             INNER JOIN usuario u_outro
                ON u_outro.id_usuario = CASE
                    WHEN c.id_usuario_menor = :uid_case THEN c.id_usuario_maior
                    ELSE c.id_usuario_menor
                END
             WHERE c.id_usuario_menor = :uid_a OR c.id_usuario_maior = :uid_b
             ORDER BY c.data_ultima_mensagem DESC'
        );
        $stmt->execute([
            'uid_case' => $idUsuario,
            'uid_a' => $idUsuario,
            'uid_b' => $idUsuario,
        ]);
        $conversas = [];
        foreach ($stmt->fetchAll() as $row) {
            $idOutro = (int) $row['outro_id'];
            $bloq = folivro_chat_estado_bloqueio($pdo, $idUsuario, $idOutro);
            $conversas[] = [
                'id_conversa' => (int) $row['id_conversa'],
                'outro_id' => $idOutro,
                'outro_nome' => (string) $row['outro_nome'],
                'outro_nivel' => (string) $row['outro_nivel'],
                'outro_status' => (string) $row['outro_status'],
                'atualizado_em' => date('d/m H:i', strtotime((string) $row['data_ultima_mensagem'])),
                'bloqueado' => $bloq['bloqueado'],
                'eu_bloqueei' => $bloq['eu_bloqueei'],
            ];
        }

        echo json_encode([
            'ok' => true,
            'conversas' => $conversas,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($acao === 'ler_mensagens') {
        $idConversa = (int) ($entradaJson['id_conversa'] ?? $_GET['id_conversa'] ?? $_POST['id_conversa'] ?? 0);
        $aposId = max(0, (int) ($entradaJson['apos_id'] ?? $_GET['apos_id'] ?? $_POST['apos_id'] ?? 0));

        $conversa = folivro_chat_obter_conversa_autorizada($pdo, $idConversa, $idUsuario);
        if (!$conversa) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Acesso negado: você não participa desta conversa ou ela não existe.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $idOutro = ((int) $conversa['id_usuario_menor'] === $idUsuario)
            ? (int) $conversa['id_usuario_maior']
            : (int) $conversa['id_usuario_menor'];

        $sqlMsg = 'SELECT m.*, u.nome_usuario AS nome_remetente
                   FROM chat_mensagens m
                   INNER JOIN usuario u ON u.id_usuario = m.id_remetente
                   WHERE m.id_conversa = :id_c';
        $paramsMsg = ['id_c' => $idConversa];
        if ($aposId > 0) {
            $sqlMsg .= ' AND m.id_mensagem > :apos_id';
            $paramsMsg['apos_id'] = $aposId;
        }
        $sqlMsg .= ' ORDER BY m.id_mensagem ASC LIMIT 150';

        $stmtMsg = $pdo->prepare($sqlMsg);
        $stmtMsg->execute($paramsMsg);
        $mensagensFormatadas = [];
        foreach ($stmtMsg->fetchAll() as $m) {
            $mensagensFormatadas[] = folivro_chat_formatar_mensagem($m, $idUsuario);
        }

        $bloq = folivro_chat_estado_bloqueio($pdo, $idUsuario, $idOutro);

        echo json_encode([
            'ok' => true,
            'id_conversa' => $idConversa,
            'bloqueio' => $bloq,
            'mensagens' => $mensagensFormatadas,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($metodo !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'ok' => false,
            'mensagem' => 'Método HTTP não permitido para esta operação.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($acao === 'iniciar_conversa') {
        $idDestinatario = (int) ($entradaJson['id_destinatario'] ?? $_POST['id_destinatario'] ?? 0);
        if ($idDestinatario <= 0 || $idDestinatario === $idUsuario) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Selecione um leitor ou autor válido diferente da sua própria conta.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtAlvo = $pdo->prepare(
            'SELECT id_usuario, nome_usuario, status_usuario FROM usuario WHERE id_usuario = :id LIMIT 1'
        );
        $stmtAlvo->execute(['id' => $idDestinatario]);
        $alvo = $stmtAlvo->fetch();
        if (!$alvo || ($alvo['status_usuario'] ?? '') !== 'ativo') {
            http_response_code(404);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'O usuário selecionado não está disponível para receber mensagens.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $bloq = folivro_chat_estado_bloqueio($pdo, $idUsuario, $idDestinatario);
        if ($bloq['bloqueado']) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'mensagem' => $bloq['eu_bloqueei']
                    ? 'Você bloqueou este usuário. Desbloqueie-o caso queira iniciar uma conversa.'
                    : 'Não é possível iniciar uma conversa com este usuário no momento.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $menor = min($idUsuario, $idDestinatario);
        $maior = max($idUsuario, $idDestinatario);

        $stmtBusca = $pdo->prepare(
            'SELECT id_conversa FROM chat_conversas
             WHERE id_usuario_menor = :menor AND id_usuario_maior = :maior LIMIT 1'
        );
        $stmtBusca->execute(['menor' => $menor, 'maior' => $maior]);
        $idConversaExistente = (int) ($stmtBusca->fetchColumn() ?: 0);

        if ($idConversaExistente <= 0) {
            $stmtTaxaConv = $pdo->prepare(
                'SELECT COUNT(*) FROM chat_conversas
                 WHERE (id_usuario_menor = :u1 OR id_usuario_maior = :u2)
                   AND data_criacao_conversa >= DATE_SUB(NOW(), INTERVAL 1 DAY)'
            );
            $stmtTaxaConv->execute(['u1' => $idUsuario, 'u2' => $idUsuario]);
            if ((int) $stmtTaxaConv->fetchColumn() >= 15) {
                http_response_code(429);
                echo json_encode([
                    'ok' => false,
                    'mensagem' => 'Limite diário de novas conversas atingido para prevenção de spam.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmtIns = $pdo->prepare(
                'INSERT INTO chat_conversas (id_usuario_menor, id_usuario_maior) VALUES (:menor, :maior)'
            );
            $stmtIns->execute(['menor' => $menor, 'maior' => $maior]);
            $idConversaExistente = (int) $pdo->lastInsertId();
        }

        echo json_encode([
            'ok' => true,
            'id_conversa' => $idConversaExistente,
            'mensagem' => 'Conversa pronta.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($acao === 'enviar_mensagem') {
        $idConversa = (int) ($entradaJson['id_conversa'] ?? $_POST['id_conversa'] ?? 0);
        $textoBruto = (string) ($entradaJson['conteudo'] ?? $_POST['conteudo'] ?? '');
        $textoLimpo = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $textoBruto));

        $conversa = folivro_chat_obter_conversa_autorizada($pdo, $idConversa, $idUsuario);
        if (!$conversa) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Acesso negado: você não faz parte desta conversa.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!empty($contaAtual['restricao_chat_ate']) && strtotime((string) $contaAtual['restricao_chat_ate']) > time()) {
            $ateFormatado = date('d/m/Y \à\s H:i', strtotime((string) $contaAtual['restricao_chat_ate']));
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Seu envio de mensagens no chat está temporariamente restrito pela moderação até ' . $ateFormatado . '.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $idOutro = ((int) $conversa['id_usuario_menor'] === $idUsuario)
            ? (int) $conversa['id_usuario_maior']
            : (int) $conversa['id_usuario_menor'];
        $statusOutro = ((int) $conversa['id_usuario_menor'] === $idUsuario)
            ? (string) $conversa['status_maior']
            : (string) $conversa['status_menor'];

        if ($statusOutro !== 'ativo') {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Não é possível enviar mensagens porque a conta destinatária não está ativa.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Regra de bloqueio em ambas as direções (inclusive contra chamadas diretas à API)
        $bloq = folivro_chat_estado_bloqueio($pdo, $idUsuario, $idOutro);
        if ($bloq['bloqueado']) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'mensagem' => $bloq['eu_bloqueei']
                    ? 'Envio impedido: você bloqueou este usuário. Desbloqueie-o para voltar a enviar mensagens.'
                    : 'Envio impedido: não é possível enviar mensagens para este usuário.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Validação de tamanho no servidor (1 a 800 caracteres)
        $tamanho = mb_strlen($textoLimpo, 'UTF-8');
        if ($tamanho < 1) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Digite uma mensagem antes de enviar.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($tamanho > 800) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'A mensagem excede o limite máximo de 800 caracteres permitido no chat.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Validação de frequência (Rate Limiting no servidor):
        // 1) Intervalo mínimo de 2 segundos desde a última mensagem enviada
        // 2) Máximo de 12 mensagens nos últimos 60 segundos
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
            http_response_code(429);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Você está enviando mensagens muito rápido. Aguarde pelo menos 2 segundos entre cada envio.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ((int) ($dadosRate['ultimo_minuto'] ?? 0) >= 12) {
            http_response_code(429);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Limite de frequência atingido (máximo de 12 mensagens por minuto). Aguarde um instante.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Proteção de direitos autorais: verificação de hash SHA-256 contra republicação de conteúdo já removido pela moderação
        $hashMsg = folivro_content_hash($textoLimpo);
        $stmtHashRemovido = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM chat_mensagens WHERE hash_conteudo_mensagem = :h1 AND status_mensagem = \'removida_moderacao\') +
                (SELECT COUNT(*) FROM historias h
                  INNER JOIN autor_dos_livros a ON a.id_historias = h.id_historias
                  WHERE h.hash_conteudo_historias = :h2 AND a.status_autor_dos_livros = \'removido\') AS total_bloqueado'
        );
        $stmtHashRemovido->execute(['h1' => $hashMsg, 'h2' => $hashMsg]);
        if ((int) $stmtHashRemovido->fetchColumn() > 0) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Envio bloqueado: este conteúdo corresponde a material já removido pela moderação por violação de direitos autorais ou das regras da plataforma.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Proteção contra links de arquivos/downloads de e-books ou compartilhamento externo suspeito
        $avaliacaoConteudo = folivro_chat_avaliar_conteudo($textoLimpo);
        if ($avaliacaoConteudo['acao'] === 'bloquear') {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'mensagem' => $avaliacaoConteudo['motivo'],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $statusInicial = 'ativa';
        $motivoOcultacao = null;
        if ($avaliacaoConteudo['acao'] === 'reter_para_moderacao') {
            $statusInicial = 'oculta_preventivamente';
            $motivoOcultacao = $avaliacaoConteudo['motivo'];
        }

        $pdo->beginTransaction();
        $stmtInsert = $pdo->prepare(
            'INSERT INTO chat_mensagens
                (id_conversa, id_remetente, conteudo_mensagem, hash_conteudo_mensagem, status_mensagem, motivo_ocultacao)
             VALUES (:id_c, :uid, :conteudo, :hash_c, :status_m, :motivo_o)'
        );
        $stmtInsert->execute([
            'id_c' => $idConversa,
            'uid' => $idUsuario,
            'conteudo' => $textoLimpo,
            'hash_c' => $hashMsg,
            'status_m' => $statusInicial,
            'motivo_o' => $motivoOcultacao,
        ]);
        $idNovaMensagem = (int) $pdo->lastInsertId();

        $stmtUpConv = $pdo->prepare(
            'UPDATE chat_conversas SET data_ultima_mensagem = CURRENT_TIMESTAMP WHERE id_conversa = :id_c'
        );
        $stmtUpConv->execute(['id_c' => $idConversa]);

        if ($statusInicial === 'oculta_preventivamente') {
            $stmtTriagem = $pdo->prepare(
                'INSERT INTO denuncias
                    (id_usuario, tipo_denuncias, item_denuncia, motivo_denuncia, descricao_denuncia, prioridade_denuncia, suspensao_preventiva, status_denuncias, retencao_evidencia_ate)
                 VALUES (:uid, \'mensagem_chat\', :id_msg, \'Violação de direitos autorais\', :desc, \'alta\', 1, \'em_analise\', DATE_ADD(NOW(), INTERVAL 180 DAY))'
            );
            $stmtTriagem->execute([
                'uid' => $idUsuario,
                'id_msg' => $idNovaMensagem,
                'desc' => '[Triagem preventiva automática do chat] ' . $motivoOcultacao,
            ]);
        }
        $pdo->commit();

        $stmtNova = $pdo->prepare(
            'SELECT m.*, u.nome_usuario AS nome_remetente
             FROM chat_mensagens m
             INNER JOIN usuario u ON u.id_usuario = m.id_remetente
             WHERE m.id_mensagem = :id_m LIMIT 1'
        );
        $stmtNova->execute(['id_m' => $idNovaMensagem]);
        $msgInserida = $stmtNova->fetch();

        echo json_encode([
            'ok' => true,
            'retida_moderacao' => ($statusInicial === 'oculta_preventivamente'),
            'mensagem' => ($statusInicial === 'oculta_preventivamente')
                ? 'Sua mensagem contém link ou referência externa de compartilhamento e foi retida preventivamente para revisão humana da moderação.'
                : 'Mensagem enviada.',
            'item' => $msgInserida ? folivro_chat_formatar_mensagem($msgInserida, $idUsuario) : null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($acao === 'apagar_mensagem') {
        $idMensagem = (int) ($entradaJson['id_mensagem'] ?? $_POST['id_mensagem'] ?? 0);
        if ($idMensagem <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensagem' => 'Mensagem inválida.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

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
        $msg = $stmtMsg->fetch();

        if (!$msg || (int) $msg['id_remetente'] !== $idUsuario) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Você só pode apagar mensagens enviadas por você mesmo.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($msg['status_mensagem'] !== 'ativa') {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Esta mensagem já foi apagada ou encontra-se sob custódia da moderação.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtDel = $pdo->prepare(
            'UPDATE chat_mensagens
             SET status_mensagem = \'apagada_pelo_autor\'
             WHERE id_mensagem = :id_m AND id_remetente = :uid'
        );
        $stmtDel->execute([
            'id_m' => $idMensagem,
            'uid' => $idUsuario,
        ]);

        echo json_encode([
            'ok' => true,
            'id_mensagem' => $idMensagem,
            'mensagem' => 'Mensagem apagada com sucesso.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($acao === 'bloquear_usuario') {
        $idAlvo = (int) ($entradaJson['id_usuario_alvo'] ?? $_POST['id_usuario_alvo'] ?? 0);
        $motivo = trim((string) ($entradaJson['motivo'] ?? $_POST['motivo'] ?? ''));
        $motivo = mb_substr($motivo, 0, 150, 'UTF-8');

        if ($idAlvo <= 0 || $idAlvo === $idUsuario) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensagem' => 'Usuário inválido para bloqueio.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtCheckAlvo = $pdo->prepare('SELECT id_usuario FROM usuario WHERE id_usuario = :id LIMIT 1');
        $stmtCheckAlvo->execute(['id' => $idAlvo]);
        if (!$stmtCheckAlvo->fetch()) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'mensagem' => 'Usuário não encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtBloq = $pdo->prepare(
            'INSERT INTO bloqueios_usuario (id_bloqueador, id_bloqueado, motivo_bloqueio)
             VALUES (:bloqueador, :bloqueado, :motivo)
             ON DUPLICATE KEY UPDATE motivo_bloqueio = VALUES(motivo_bloqueio)'
        );
        $stmtBloq->execute([
            'bloqueador' => $idUsuario,
            'bloqueado' => $idAlvo,
            'motivo' => $motivo !== '' ? $motivo : null,
        ]);

        echo json_encode([
            'ok' => true,
            'mensagem' => 'Usuário bloqueado. Novas mensagens entre vocês estão impedidas.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($acao === 'desbloquear_usuario') {
        $idAlvo = (int) ($entradaJson['id_usuario_alvo'] ?? $_POST['id_usuario_alvo'] ?? 0);
        if ($idAlvo <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensagem' => 'Usuário inválido para desbloqueio.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtDesbloq = $pdo->prepare(
            'DELETE FROM bloqueios_usuario WHERE id_bloqueador = :bloqueador AND id_bloqueado = :bloqueado'
        );
        $stmtDesbloq->execute([
            'bloqueador' => $idUsuario,
            'bloqueado' => $idAlvo,
        ]);

        echo json_encode([
            'ok' => true,
            'mensagem' => 'Usuário desbloqueado com sucesso.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($acao === 'denunciar_chat') {
        $tipoAlvo = (string) ($entradaJson['tipo_alvo'] ?? $_POST['tipo_alvo'] ?? '');
        $idAlvo = (int) ($entradaJson['id_alvo'] ?? $_POST['id_alvo'] ?? 0);
        $categoria = (string) ($entradaJson['categoria'] ?? $_POST['categoria'] ?? '');
        $obraReferencia = trim((string) ($entradaJson['obra_referencia'] ?? $_POST['obra_referencia'] ?? ''));
        $descricao = trim((string) ($entradaJson['descricao'] ?? $_POST['descricao'] ?? ''));

        $mapaMotivos = [
            'assedio' => 'Assédio ou comportamento abusivo',
            'ameaca' => 'Ameaça ou intimidação',
            'spam' => 'Spam ou propaganda indesejada',
            'direitos_autorais' => 'Violação de direitos autorais',
            'outro' => 'Outro motivo de segurança',
        ];
        if (!in_array($tipoAlvo, ['mensagem_chat', 'conversa_chat'], true) || $idAlvo <= 0) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Selecione uma mensagem ou conversa válida para denunciar.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!isset($mapaMotivos[$categoria])) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Selecione uma categoria válida para a denúncia (assédio, ameaça, spam, direitos autorais ou outro).',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $tamDesc = mb_strlen($descricao, 'UTF-8');
        if ($tamDesc < 10 || $tamDesc > 1000) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Descreva o motivo da denúncia com entre 10 e 1000 caracteres para análise da moderação.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($tipoAlvo === 'mensagem_chat') {
            $stmtVerif = $pdo->prepare(
                'SELECT m.id_mensagem, m.id_conversa, m.id_remetente, m.status_mensagem
                 FROM chat_mensagens m
                 INNER JOIN chat_conversas c ON c.id_conversa = m.id_conversa
                 WHERE m.id_mensagem = :id_m
                   AND (c.id_usuario_menor = :uid_a OR c.id_usuario_maior = :uid_b)
                 LIMIT 1'
            );
            $stmtVerif->execute([
                'id_m' => $idAlvo,
                'uid_a' => $idUsuario,
                'uid_b' => $idUsuario,
            ]);
            $alvoMsg = $stmtVerif->fetch();
            if (!$alvoMsg) {
                http_response_code(403);
                echo json_encode([
                    'ok' => false,
                    'mensagem' => 'Acesso negado: você só pode denunciar mensagens de conversas das quais participa.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ((int) $alvoMsg['id_remetente'] === $idUsuario) {
                http_response_code(422);
                echo json_encode([
                    'ok' => false,
                    'mensagem' => 'Você não pode denunciar sua própria mensagem.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            $conversaAlvo = folivro_chat_obter_conversa_autorizada($pdo, $idAlvo, $idUsuario);
            if (!$conversaAlvo) {
                http_response_code(403);
                echo json_encode([
                    'ok' => false,
                    'mensagem' => 'Acesso negado: você só pode denunciar conversas das quais participa.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $stmtDup = $pdo->prepare(
            'SELECT id_denuncias FROM denuncias
             WHERE id_usuario = :uid
               AND tipo_denuncias = :tipo
               AND item_denuncia = :alvo
               AND status_denuncias IN (\'aberta\', \'em_analise\')
             LIMIT 1'
        );
        $stmtDup->execute([
            'uid' => $idUsuario,
            'tipo' => $tipoAlvo,
            'alvo' => $idAlvo,
        ]);
        if ($stmtDup->fetch()) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'mensagem' => 'Você já possui uma denúncia em análise para este item.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $pdo->beginTransaction();

        $ocultadaPreventivamente = false;
        $statusDenunciaInicial = 'aberta';
        $prioridade = in_array($categoria, ['direitos_autorais', 'ameaca', 'assedio'], true) ? 'alta' : 'media';

        if ($tipoAlvo === 'mensagem_chat' && $categoria === 'direitos_autorais' && $tamDesc >= 15) {
            $stmtOcultar = $pdo->prepare(
                'UPDATE chat_mensagens
                 SET status_mensagem = \'oculta_preventivamente\',
                     motivo_ocultacao = \'Mensagem ocultada preventivamente devido a denúncia de possível violação de direitos autorais.\'
                 WHERE id_mensagem = :id_m AND status_mensagem = \'ativa\''
            );
            $stmtOcultar->execute(['id_m' => $idAlvo]);
            $ocultadaPreventivamente = ($stmtOcultar->rowCount() > 0);
            $statusDenunciaInicial = 'em_analise';
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
            'susp' => $ocultadaPreventivamente ? 1 : 0,
            'status_d' => $statusDenunciaInicial,
        ]);

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'ocultada_preventivamente' => $ocultadaPreventivamente,
            'mensagem' => $ocultadaPreventivamente
                ? 'Denúncia de direitos autorais registrada. A mensagem foi ocultada preventivamente e encaminhada para análise humana da moderação.'
                : 'Denúncia registrada com sucesso e encaminhada de forma confidencial à equipe de moderação.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'mensagem' => 'Ação desconhecida ou não informada.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'mensagem' => 'Não foi possível concluir a operação no chat no momento.',
    ], JSON_UNESCAPED_UNICODE);
}
