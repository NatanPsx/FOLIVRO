<?php
declare(strict_types=1);

/** Versão vigente dos termos de uso/privacidade (registrada em termos_aceitos.versao_termos). */
const FOLIVRO_TERMOS_VERSAO = '1.0';

// Evita exposição de stack traces, caminhos internos, senhas ou tokens em mensagens de erro.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_exception_handler(static function (Throwable $e): void {
    error_log('[FOLIVRO] Exceção não tratada: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        if (str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(['error' => 'Ocorreu um erro interno ao processar a solicitação.'], JSON_UNESCAPED_UNICODE));
        }
        header('Content-Type: text/html; charset=utf-8');
    }
    exit('Não foi possível concluir a solicitação no momento. Tente novamente em instantes.');
});

function folivro_start_session(): void
{
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function folivro_require_login(): void
{
    folivro_start_session();
    if (empty($_SESSION['id_usuario'])) {
        folivro_redirect_login();
    }

    // Revalida no servidor: conta banida, inativa ou anonimizada perde a sessão imediatamente.
    if (function_exists('folivro_database')) {
        $check = folivro_database()->prepare('SELECT status_usuario, nivel_usuario, data_nascimento_usuario FROM usuario WHERE id_usuario = ? LIMIT 1');
        $check->execute([(int) $_SESSION['id_usuario']]);
        $user = $check->fetch();
        if (!$user || $user['status_usuario'] !== 'ativo') {
            $_SESSION = [];
            session_destroy();
            folivro_redirect_login();
        }
        $_SESSION['nivel_usuario'] = $user['nivel_usuario'];
        $_SESSION['data_nascimento_usuario'] = $user['data_nascimento_usuario'] ?? null;
    }
}

function folivro_redirect_login(): void
{
    // Endpoints da pasta /api respondem JSON em vez de redirecionar.
    if (str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/')) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['error' => 'Sessão expirada. Entre novamente.'], JSON_UNESCAPED_UNICODE));
    }
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    header('Location: ' . $base . '/login.php');
    exit;
}

function folivro_user_id(): int
{
    folivro_start_session();
    return (int) ($_SESSION['id_usuario'] ?? 0);
}

/** Converte valores ENUM do banco em rótulos legíveis para a interface. */
function folivro_status_label(?string $status): string
{
    $labels = [
        'quero_ler' => 'Quero ler', 'lendo' => 'Lendo', 'lido' => 'Lido', 'abandonado' => 'Abandonado',
        'rascunho' => 'Rascunho', 'publicada' => 'Publicada', 'publicado' => 'Publicado',
        'despublicada' => 'Despublicada', 'a_venda' => 'À venda', 'suspenso' => 'Venda/distribuição pausada',
        'removido' => 'Removido pela moderação', 'aberta' => 'Aberta (triagem)', 'em_analise' => 'Em análise humana',
        'procedente' => 'Procedente', 'improcedente' => 'Improcedente',
        'gratuita' => 'Publicação gratuita', 'venda_simulada' => 'Venda simulada na loja',
        'baixa' => 'Prioridade baixa', 'media' => 'Prioridade média', 'alta' => 'Prioridade alta (Direitos Autorais / Segurança)',
        'mensagem_chat' => 'Mensagem de chat', 'conversa_chat' => 'Conversa de chat',
        'ativa' => 'Ativa', 'apagada_pelo_autor' => 'Apagada pelo remetente',
        'oculta_preventivamente' => 'Ocultada preventivamente (em análise)',
        'removida_moderacao' => 'Removida pela moderação',
        'aviso_e_remover_mensagem' => 'Aviso formal + Remoção da mensagem',
        'restricao_temporaria_chat' => 'Remoção + Restrição do chat por 7 dias',
        'remover_item' => 'Remoção definitiva do conteúdo',
        'remover_e_banir_reincidente' => 'Remoção + Suspensão da conta infratora',
        'suspender_preventivamente' => 'Suspensão / Ocultação preventiva para triagem',
        'improcedente' => 'Improcedente / Arquivada',
        'nao_solicitada' => 'Não solicitada (rascunho privado)',
        'pendente' => 'Aguardando revisão humana da moderação',
        'aprovada' => 'Aprovada pela revisão humana',
        'recusada' => 'Recusada / Bloqueada na revisão de direitos',
        'comprado' => 'Na sua biblioteca digital',
        'gratuito_salvo' => 'Obra gratuita salva',
        'avaliacao' => 'Em período de avaliação (revisão humana)',
        'automatica' => 'Qualificado para publicação automática',
        'revisao_obrigatoria' => 'Revisão humana obrigatória',
        'suspensa' => 'Permissão de publicação automática suspensa',
        'publicado_automatico' => 'Publicado automaticamente (sujeito à moderação posterior)',
        'oculto_moderacao' => 'Ocultado pela moderação posterior',
        'revisao_humana' => 'Revisão humana prévia',
    ];
    return $labels[$status ?? ''] ?? (string) $status;
}

/** Rótulos legíveis para a origem e base de direitos declarada pelo autor. */
function folivro_origem_obra_label(?string $origem): string
{
    $labels = [
        'autoral_original' => 'Obra 100% autoral e inédita do(a) autor(a)',
        'autorizacao_documentada' => 'Autorização expressa e documentada do(a) titular',
        'fanfic_sem_autorizacao' => 'Fanfic, adaptação ou tradução sem autorização documentada (bloqueada)',
        'dominio_publico_nao_verificado' => 'Obra antiga / domínio público não verificado na jurisdição (bloqueada)',
    ];
    return $labels[$origem ?? ''] ?? 'Origem não verificada';
}

/** Rótulos legíveis para a licença/base de direitos declarada pelo autor. */
function folivro_licenca_label(?string $licenca): string
{
    $labels = [
        'direitos_reservados' => 'Todos os direitos reservados pelo(a) autor(a)',
        'cc_by_nc_nd' => 'Creative Commons BY-NC-ND 4.0 (uso não comercial, sem derivadas)',
        'autorizacao_especifica' => 'Autorização documentada do(a) titular para uso específico',
    ];
    return $labels[$licenca ?? ''] ?? 'Todos os direitos reservados pelo(a) autor(a)';
}

/** Mapa oficial de Classificação Indicativa de idade padrão (ClassInd). */
function folivro_classificacoes_idade(): array
{
    return [
        'livre' => 'Classificação Livre (L)',
        '10'    => 'Não recomendado para menores de 10 anos (+10)',
        '12'    => 'Não recomendado para menores de 12 anos (+12)',
        '14'    => 'Não recomendado para menores de 14 anos (+14)',
        '16'    => 'Não recomendado para menores de 16 anos (+16)',
        '18'    => 'Proibido para menores de 18 anos (+18)',
    ];
}

/** Normaliza valores antigos ou atuais para o código de classificação indicativa padrão. */
function folivro_normalizar_classificacao(?string $aviso): string
{
    $v = strtolower(trim((string) ($aviso ?? 'livre')));
    $mapaLegado = [
        'livre'               => 'livre',
        'l'                   => 'livre',
        '10'                  => '10',
        '+10'                 => '10',
        '12'                  => '12',
        '+12'                 => '12',
        'temas_sensiveis'     => '12',
        '14'                  => '14',
        '+14'                 => '14',
        '16'                  => '16',
        '+16'                 => '16',
        'violencia_moderada'  => '16',
        '18'                  => '18',
        '+18'                 => '18',
        'linguagem_impropria' => '18',
    ];
    return $mapaLegado[$v] ?? 'livre';
}

/** Rótulos legíveis para a classificação indicativa de idade da obra. */
function folivro_aviso_conteudo_label(?string $aviso): string
{
    $norm = folivro_normalizar_classificacao($aviso);
    $labels = folivro_classificacoes_idade();
    return $labels[$norm] ?? $labels['livre'];
}

/** Sigla curta da classificação indicativa (L, +10, +12, +14, +16, +18). */
function folivro_classificacao_sigla(?string $aviso): string
{
    $norm = folivro_normalizar_classificacao($aviso);
    return $norm === 'livre' ? 'L' : '+' . $norm;
}

/** Verifica se a classificação da obra é restrita para maiores de 18 anos (+18). */
function folivro_classificacao_eh_maior18(?string $aviso): bool
{
    return folivro_normalizar_classificacao($aviso) === '18';
}

/** Calcula a idade em anos completos a partir da data de nascimento (YYYY-MM-DD). */
function folivro_calcular_idade(?string $dataNascimento): ?int
{
    if ($dataNascimento === null || trim($dataNascimento) === '') {
        return null;
    }
    try {
        $dtNasc = DateTimeImmutable::createFromFormat('!Y-m-d', trim($dataNascimento));
        if (!$dtNasc || $dtNasc->format('Y-m-d') !== trim($dataNascimento)) {
            return null;
        }
        $hoje = new DateTimeImmutable('today');
        if ($dtNasc > $hoje) {
            return null;
        }
        return $dtNasc->diff($hoje)->y;
    } catch (Throwable $e) {
        return null;
    }
}

/** Retorna a idade do usuário autenticado (ou null se não tiver data de nascimento cadastrada). */
function folivro_usuario_idade(?PDO $pdo = null, ?int $uid = null): ?int
{
    folivro_start_session();
    $userId = $uid ?? (int) ($_SESSION['id_usuario'] ?? 0);
    if ($userId <= 0) {
        return null;
    }
    $dataNasc = $_SESSION['data_nascimento_usuario'] ?? null;
    if ($dataNasc === null && ($pdo instanceof PDO || function_exists('folivro_database'))) {
        try {
            $db = $pdo ?? folivro_database();
            $stmt = $db->prepare('SELECT data_nascimento_usuario FROM usuario WHERE id_usuario = ? LIMIT 1');
            $stmt->execute([$userId]);
            $val = $stmt->fetchColumn();
            $dataNasc = is_string($val) && $val !== '' ? $val : null;
            $_SESSION['data_nascimento_usuario'] = $dataNasc;
        } catch (Throwable $e) {
            $dataNasc = null;
        }
    }
    return folivro_calcular_idade(is_string($dataNasc) ? $dataNasc : null);
}

/**
 * Verifica se o usuário autenticado possui 18 anos ou mais com base na data de nascimento do cadastro.
 * Caso não tenha 18 anos completos (ou não tenha data de nascimento informada), retorna false.
 */
function folivro_usuario_maior_de_idade(?PDO $pdo = null, ?int $uid = null): bool
{
    $idade = folivro_usuario_idade($pdo, $uid);
    return $idade !== null && $idade >= 18;
}

/** Ícone SVG de olhinho bloqueado (eye-off) para capas e títulos restritos a maiores de 18 anos. */
function folivro_icone_olho_bloqueado_svg(int $size = 34): string
{
    return '<svg class="icone-olho-bloqueado-svg" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>'
        . '<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>'
        . '<path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>'
        . '<line x1="1" y1="1" x2="23" y2="23"/>'
        . '</svg>';
}

/**
 * Determina a classificação indicativa padrão (livre, 10, 12, 14, 16, 18) para obras do catálogo
 * com base em mapeamento literário e temas conhecidos.
 */
function folivro_estimar_classificacao_catalogo(string $titulo, string $workId = ''): string
{
    $t = mb_strtolower(trim($titulo), 'UTF-8');

    // Obras explicitamente Livre / Infantil / Juvenil
    if (preg_match('/(pequeno pr[íi]ncipe|little prince|alice no pa[íi]s|alice in wonderland|m[áa]gico de oz|wizard of oz|peter pan|cr[ôo]nicas de n[áa]rnia|narnia|hobbit|anne de green gables|heid[ií]|pinoquio|pinocchio)/iu', $t)) {
        return 'livre';
    }
    if (preg_match('/(harry potter|percy jackson|ilha do tesouro|treasure island|viagem ao centro da terra|vinte mil l[ée]guas|volta ao mundo em 80|sherlock holmes|aventuras de tom sawyer)/iu', $t)) {
        return '10';
    }
    if (preg_match('/(orgulho e preconceito|pride and prejudice|raz[ãa]o e sensibilidade|sense and sensibility|mulherzinhas|little women|senhor dos an[ée]is|lord of the rings|dom quixote|don quixote|romeu e julieta|romeo and juliet|moreninha|guarani)/iu', $t)) {
        return '12';
    }
    if (preg_match('/(dom casmurro|mem[óo]rias p[óo]stumas|quincas borba|alienista|o alquimista|alchemist|grande gatsby|great gatsby|morro dos ventos uivantes|wuthering heights|jane eyre|conde de monte cristo|monte cristo|miser[áa]veis|metamorfose|metamorphosis|fahrenheit 451|revolu[çc][ãa]o dos bichos|animal farm)/iu', $t)) {
        return '14';
    }
    if (preg_match('/(1984|admir[áa]vel mundo novo|brave new world|frankenstein|hamlet|macbeth|rei lear|otelo|vidas secas|capit[ãa]es da areia|hora da estrela)/iu', $t)) {
        return '16';
    }
    // Obras +18 (temas adultos, violência intensa, horror gótico adulto ou drama adulto)
    if (preg_match('/(assim que acaba|it ends with us|crime e castigo|crime and punishment|retrato de dorian gray|picture of dorian gray|dr[áa]cula|corti[çc]o|madame bovary|anna kari[êe]nina|anna karenina|lolita|laranja mec[âa]nica|clockwork orange|cinquenta tons|fifty shades|iluminado|shining|it: a coisa|exorcista|psicose|american psycho|bela da tarde|amante de lady chatterley| marqu[êe]s de sade|bukowski|irm[ãa]os karam[áa]zov|brothers karamazov)/iu', $t)) {
        return '18';
    }

    // Distribuição determinística consistente para demais títulos do catálogo aberto
    $seed = abs((int) crc32($workId !== '' ? $workId : $t));
    $faixas = ['livre', '10', '12', '14', '16', '18'];
    return $faixas[$seed % count($faixas)];
}

/** Gera impressão digital SHA-256 normalizada para impedir republicação automática de texto removido. */
function folivro_content_hash(string $texto): string
{
    $normalizado = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $texto)), 'UTF-8');
    return hash('sha256', $normalizado);
}

/**
 * Verifica se o texto do capítulo ou a combinação título+capítulo coincide com material
 * já removido pela moderação (em obras autorais, capítulos ou mensagens do chat).
 */
function folivro_conteudo_foi_removido(PDO $pdo, string $titulo, string $textoCapitulo, int $ignorarIdObra = 0): bool
{
    $hashCombinado = folivro_content_hash($titulo . ' ' . $textoCapitulo);
    $hashCapitulo = folivro_content_hash($textoCapitulo);

    $stmtObra = $pdo->prepare(
        "SELECT 1
           FROM historias h
           JOIN autor_dos_livros a ON a.id_historias = h.id_historias
      LEFT JOIN capitulos c ON c.id_historias = h.id_historias
          WHERE a.status_autor_dos_livros = 'removido'
            AND a.id_autor_dos_livros <> ?
            AND (
                h.hash_conteudo_historias IN (?, ?)
                OR c.hash_conteudo_capitulos IN (?, ?)
            )
          LIMIT 1"
    );
    $stmtObra->execute([$ignorarIdObra, $hashCombinado, $hashCapitulo, $hashCombinado, $hashCapitulo]);
    if ($stmtObra->fetchColumn()) {
        return true;
    }

    $stmtMsg = $pdo->prepare(
        "SELECT 1 FROM chat_mensagens
          WHERE status_mensagem = 'removida_moderacao'
            AND hash_conteudo_mensagem IN (?, ?)
          LIMIT 1"
    );
    $stmtMsg->execute([$hashCombinado, $hashCapitulo]);
    return (bool) $stmtMsg->fetchColumn();
}

/**
 * Avalia preventivamente se o texto do chat contém links de download de arquivos/e-books
 * ou indícios de compartilhamento de cópias não verificadas que exigem retenção para análise humana.
 *
 * @return array{acao: 'permitir'|'bloquear'|'reter_para_moderacao', motivo: string}
 */
function folivro_chat_avaliar_conteudo(string $texto): array
{
    // 1) Bloqueio direto de links para arquivos de livros/pacotes (.pdf, .epub, .mobi, .azw3, .cbr, .cbz, .torrent, magnet:)
    if (preg_match('/(magnet:\?xt=|\bhttps?:\/\/\S+\.(pdf|epub|mobi|azw3|djvu|cbr|cbz|torrent|zip|rar)\b)/iu', $texto)) {
        return [
            'acao' => 'bloquear',
            'motivo' => 'Envio bloqueado: o chat não permite compartilhar links de arquivos (.pdf, .epub, .mobi, .zip, torrent) ou downloads de obras.',
        ];
    }

    // 2) Retenção preventiva para análise humana quando houver links de repositórios de compartilhamento/drives externos
    //    ou oferta/solicitação explícita de cópias piratas/integrais sem verificação de licença.
    if (preg_match('/\b(drive\.google\.com|mega\.nz|mediafire\.com|dropbox\.com|t\.me\/|onedrive\.live\.com|4shared\.com|z-lib|libgen|annas-archive)\b/iu', $texto)
        || preg_match('/\b(baixe\s+o\s+pdf|livro\s+completo\s+em\s+pdf|envio\s+o\s+pdf|mando\s+o\s+epub|download\s+do\s+livro\s+completo|c[oó]pia\s+pirata)\b/iu', $texto)) {
        return [
            'acao' => 'reter_para_moderacao',
            'motivo' => 'Conteúdo retido preventivamente para análise humana: links externos de compartilhamento ou possível distribuição de obra sem licença verificada.',
        ];
    }

    return ['acao' => 'permitir', 'motivo' => ''];
}

function folivro_csrf_token(): string
{
    folivro_start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function folivro_verify_csrf(): bool
{
    folivro_start_session();
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $formToken = $_POST['csrf_token'] ?? '';
    $token = is_string($headerToken) && $headerToken !== '' ? $headerToken : $formToken;
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function folivro_escape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitiza o conteúdo formatado do capítulo para renderização segura,
 * preservando formatações ricas (negrito, itálico, subtítulos, listas, citações e tamanhos).
 */
function folivro_sanitizar_html_capitulo(?string $html): string
{
    if ($html === null || $html === '') {
        return '';
    }
    // Se for texto simples sem nenhuma tag HTML, converte quebras de linha com segurança
    if ($html === strip_tags($html)) {
        return nl2br(folivro_escape($html));
    }
    $permitidas = '<p><br><b><strong><i><em><u><s><strike><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><span><hr><font><div>';
    $sanitizado = strip_tags($html, $permitidas);
    // Remove eventos de script perigosos (onerror, onclick, etc.) e links javascript:
    $sanitizado = preg_replace('/\s*(on[a-z]+|javascript:|data:)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $sanitizado);
    return $sanitizado;
}

/**
 * Lista padrão de gêneros literários disponíveis para seleção nas obras.
 *
 * @return string[]
 */
function folivro_generos_disponiveis(): array
{
    return [
        'Romance',
        'Fantasia',
        'Ficção Científica',
        'Suspense',
        'Terror',
        'Mistério',
        'Drama',
        'Aventura',
        'Ficção',
        'Poesia',
        'Crônica',
        'Contos',
        'Histórico',
        'Distopia',
        'Jovem Adulto (YA)',
        'Humor',
        'Biografia / Memórias',
        'Não Ficção',
        'Literatura Nacional',
    ];
}

/**
 * Normaliza e higieniza uma entrada de gêneros (array de checkboxes + string separada por vírgula),
 * retornando um array de gêneros únicos (máx. $max itens).
 *
 * @param array|string|null $selecionados
 * @param string|null $personalizados
 * @return string[]
 */
function folivro_normalizar_generos($selecionados, ?string $personalizados = null, int $max = 5): array
{
    $brutos = [];
    if (is_array($selecionados)) {
        foreach ($selecionados as $item) {
            if (is_string($item)) {
                $brutos[] = $item;
            }
        }
    } elseif (is_string($selecionados) && trim($selecionados) !== '') {
        foreach (preg_split('/[,;|]+/u', $selecionados) ?: [] as $parte) {
            $brutos[] = $parte;
        }
    }

    if ($personalizados !== null && trim($personalizados) !== '') {
        foreach (preg_split('/[,;|]+/u', $personalizados) ?: [] as $parte) {
            $brutos[] = $parte;
        }
    }

    $limpos = [];
    $vistos = [];
    foreach ($brutos as $g) {
        $g = trim((string) preg_replace('/\s+/u', ' ', $g));
        $g = trim($g, " \t\n\r\0\x0B.,;|-_#/\\");
        if ($g === '' || mb_strlen($g, 'UTF-8') > 45) {
            continue;
        }
        $chave = mb_strtolower($g, 'UTF-8');
        if (isset($vistos[$chave])) {
            continue;
        }
        $vistos[$chave] = true;
        $limpos[] = $g;
        if (count($limpos) >= $max) {
            break;
        }
    }

    return $limpos;
}

/**
 * Converte a seleção de gêneros para string formatada ("Romance, Fantasia") pronta para persistir no banco.
 *
 * @param array|string|null $selecionados
 */
function folivro_generos_para_string($selecionados, ?string $personalizados = null, int $max = 5): string
{
    $lista = folivro_normalizar_generos($selecionados, $personalizados, $max);
    return implode(', ', $lista);
}

/**
 * Extrai a lista de gêneros a partir da coluna `generos_historias` salva no banco.
 *
 * @return string[]
 */
function folivro_generos_lista(?string $generosSalvos): array
{
    if ($generosSalvos === null || trim($generosSalvos) === '') {
        return [];
    }
    return folivro_normalizar_generos($generosSalvos, null, 8);
}

/**
 * Critérios objetivos e centralizados de qualificação de autores para publicação automática.
 * A qualificação exige histórico positivo comprovado por revisão humana e ausência total de violações.
 *
 * @return array<string, int|bool>
 */
function folivro_criterios_confianca_autor(): array
{
    return [
        'min_capitulos_aprovados_humano' => 3,
        'min_obras_aprovadas'            => 1,
        'max_violacoes_confirmadas'      => 0,
        'max_denuncias_procedentes'      => 0,
        'max_revisoes_recusadas'         => 0,
        'max_avisos_moderacao'           => 0,
        'exigir_zero_suspensao_ativa'    => true,
    ];
}

/**
 * Registra um evento auditável no histórico de moderação e qualificação de autores.
 */
function folivro_registrar_historico_moderacao(
    PDO $pdo,
    int $idAutor,
    ?int $idModerador,
    string $tipoEvento,
    string $justificativa,
    ?string $modAnterior = null,
    ?string $modNova = null,
    ?int $idObra = null,
    ?int $idCap = null
): void {
    if ($idAutor <= 0) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO historico_moderacao_autor
            (id_autor, id_moderador, id_autor_dos_livros, id_capitulos, tipo_evento, modalidade_anterior, modalidade_nova, justificativa)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $idAutor,
        $idModerador && $idModerador > 0 ? $idModerador : null,
        $idObra && $idObra > 0 ? $idObra : null,
        $idCap && $idCap > 0 ? $idCap : null,
        mb_substr($tipoEvento, 0, 60, 'UTF-8'),
        $modAnterior,
        $modNova,
        mb_substr(trim($justificativa), 0, 2000, 'UTF-8'),
    ]);
}

/**
 * Avalia os critérios objetivos de confiança de um autor, atualizando automaticamente sua modalidade
 * quando cabível (promoção de 'avaliacao' para 'automatica' ou suspensão em caso de violação confirmada).
 *
 * @return array{
 *   modalidade: string,
 *   qualificado_automatico: bool,
 *   mensagem_interface: string,
 *   justificativa_admin: string,
 *   metricas: array<string, int>
 * }
 */
function folivro_avaliar_qualificacao_autor(PDO $pdo, int $idAutor, bool $persistirMudanca = true): array
{
    $criterios = folivro_criterios_confianca_autor();

    $stmtUser = $pdo->prepare(
        'SELECT id_usuario, nivel_usuario, status_usuario,
                modalidade_publicacao_usuario, capitulos_aprovados_usuario,
                violacoes_confirmadas_usuario, revisoes_recusadas_usuario,
                avisos_moderacao_usuario, justificativa_modalidade_usuario,
                data_qualificacao_usuario
           FROM usuario
          WHERE id_usuario = ?
          LIMIT 1'
    );
    $stmtUser->execute([$idAutor]);
    $u = $stmtUser->fetch();

    if (!$u) {
        return [
            'modalidade' => 'avaliacao',
            'qualificado_automatico' => false,
            'mensagem_interface' => 'Sua obra está sujeita à revisão humana.',
            'justificativa_admin' => '',
            'metricas' => [
                'capitulos_aprovados_humano' => 0,
                'capitulos_automaticos' => 0,
                'obras_aprovadas' => 0,
                'violacoes_confirmadas' => 0,
                'denuncias_procedentes' => 0,
                'revisoes_recusadas' => 0,
                'avisos_moderacao' => 0,
                'suspensoes_ativas' => 0,
            ],
        ];
    }

    // 1. Capítulos aprovados especificamente por revisão humana (não conta publicação automática como aprovação prévia)
    $stmtCapsHum = $pdo->prepare(
        "SELECT COUNT(*)
           FROM capitulos c
           JOIN historias h ON h.id_historias = c.id_historias
          WHERE h.id_usuario = ?
            AND c.status_capitulos = 'publicado'
            AND c.revisao_capitulos = 'aprovada'
            AND c.modo_publicacao_capitulos = 'revisao_humana'"
    );
    $stmtCapsHum->execute([$idAutor]);
    $capsHumDb = (int) $stmtCapsHum->fetchColumn();
    $capsAprovadosHumano = max((int) ($u['capitulos_aprovados_usuario'] ?? 0), $capsHumDb);

    // 2. Capítulos publicados automaticamente pelo autor
    $stmtCapsAuto = $pdo->prepare(
        "SELECT COUNT(*)
           FROM capitulos c
           JOIN historias h ON h.id_historias = c.id_historias
          WHERE h.id_usuario = ?
            AND c.modo_publicacao_capitulos = 'automatica'"
    );
    $stmtCapsAuto->execute([$idAutor]);
    $capsAutomaticos = (int) $stmtCapsAuto->fetchColumn();

    // 3. Obras aprovadas na revisão humana e ativas
    $stmtObrasAp = $pdo->prepare(
        "SELECT COUNT(*)
           FROM autor_dos_livros
          WHERE id_autor = ?
            AND revisao_publicacao = 'aprovada'
            AND status_autor_dos_livros <> 'removido'"
    );
    $stmtObrasAp->execute([$idAutor]);
    $obrasAprovadas = (int) $stmtObrasAp->fetchColumn();

    // 4. Obras removidas ou recusadas na revisão humana
    $stmtObrasRem = $pdo->prepare(
        "SELECT COUNT(*) FROM autor_dos_livros WHERE id_autor = ? AND status_autor_dos_livros = 'removido'"
    );
    $stmtObrasRem->execute([$idAutor]);
    $obrasRemovidas = (int) $stmtObrasRem->fetchColumn();

    $stmtRecusasDb = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM autor_dos_livros WHERE id_autor = ? AND revisao_publicacao = 'recusada')
          + (SELECT COUNT(*) FROM capitulos c JOIN historias h ON h.id_historias = c.id_historias WHERE h.id_usuario = ? AND c.revisao_capitulos IN ('recusada', 'oculto_moderacao'))"
    );
    $stmtRecusasDb->execute([$idAutor, $idAutor]);
    $revisoesRecusadas = max((int) ($u['revisoes_recusadas_usuario'] ?? 0), (int) $stmtRecusasDb->fetchColumn());

    // 5. Denúncias procedentes contra obras, histórias, comentários, mensagens de chat ou perfil do autor
    $stmtDenProc = $pdo->prepare(
        "SELECT COUNT(*)
           FROM denuncias d
      LEFT JOIN autor_dos_livros a ON d.tipo_denuncias = 'obra_autoral' AND a.id_autor_dos_livros = d.item_denuncia
      LEFT JOIN historias h ON d.tipo_denuncias = 'historia' AND h.id_historias = d.item_denuncia
      LEFT JOIN comentarios cm ON d.tipo_denuncias = 'comentario' AND cm.id_comentarios = d.item_denuncia
      LEFT JOIN chat_mensagens ch ON d.tipo_denuncias = 'mensagem_chat' AND ch.id_mensagem = d.item_denuncia
          WHERE d.status_denuncias = 'procedente'
            AND (
                a.id_autor = ?
                OR h.id_usuario = ?
                OR cm.id_usuario = ?
                OR ch.id_remetente = ?
                OR (d.tipo_denuncias = 'usuario' AND d.item_denuncia = ?)
            )"
    );
    $stmtDenProc->execute([$idAutor, $idAutor, $idAutor, $idAutor, $idAutor]);
    $denunciasProcedentes = (int) $stmtDenProc->fetchColumn();

    // 6. Suspensões preventivas ativas contra obras do autor
    $stmtSuspAtiva = $pdo->prepare(
        "SELECT COUNT(*)
           FROM denuncias d
           JOIN autor_dos_livros a ON d.tipo_denuncias = 'obra_autoral' AND a.id_autor_dos_livros = d.item_denuncia
          WHERE a.id_autor = ?
            AND d.status_denuncias IN ('aberta', 'em_analise')
            AND d.suspensao_preventiva = 1"
    );
    $stmtSuspAtiva->execute([$idAutor]);
    $suspensoesAtivas = (int) $stmtSuspAtiva->fetchColumn();

    $avisosModeracao = (int) ($u['avisos_moderacao_usuario'] ?? 0);
    $violacoesConfirmadas = max(
        (int) ($u['violacoes_confirmadas_usuario'] ?? 0),
        $denunciasProcedentes + $obrasRemovidas
    );

    $modalidadeAtual = (string) ($u['modalidade_publicacao_usuario'] ?? 'avaliacao');
    $justificativaAdmin = (string) ($u['justificativa_modalidade_usuario'] ?? '');

    $temViolacaoOuPenalidade = (
        $violacoesConfirmadas > (int) $criterios['max_violacoes_confirmadas']
        || $denunciasProcedentes > (int) $criterios['max_denuncias_procedentes']
        || $avisosModeracao > (int) $criterios['max_avisos_moderacao']
        || $obrasRemovidas > 0
    );

    $cumpriuCriteriosPositivos = (
        !$temViolacaoOuPenalidade
        && $revisoesRecusadas <= (int) $criterios['max_revisoes_recusadas']
        && $suspensoesAtivas === 0
        && $capsAprovadosHumano >= (int) $criterios['min_capitulos_aprovados_humano']
        && $obrasAprovadas >= (int) $criterios['min_obras_aprovadas']
    );

    // Se o autor estava qualificado como 'automatica' e sofreu violação confirmada, perde a qualificação imediatamente
    if ($modalidadeAtual === 'automatica' && $temViolacaoOuPenalidade) {
        $novaModalidade = 'suspensa';
        $motivoAuto = 'Permissão de publicação automática suspensa devido a violação confirmada ou denúncia procedente.';
        if ($persistirMudanca) {
            $ownTx = !$pdo->inTransaction();
            if ($ownTx) {
                $pdo->beginTransaction();
            }
            try {
                $pdo->prepare(
                    'UPDATE usuario
                        SET modalidade_publicacao_usuario = ?,
                            violacoes_confirmadas_usuario = ?,
                            justificativa_modalidade_usuario = ?
                      WHERE id_usuario = ?'
                )->execute([$novaModalidade, $violacoesConfirmadas, $motivoAuto, $idAutor]);
                folivro_registrar_historico_moderacao(
                    $pdo,
                    $idAutor,
                    null,
                    'violacao_confirmada',
                    $motivoAuto,
                    $modalidadeAtual,
                    $novaModalidade
                );
                if ($ownTx) {
                    $pdo->commit();
                }
            } catch (Throwable $e) {
                if ($ownTx && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }
        $modalidadeAtual = $novaModalidade;
        $justificativaAdmin = $motivoAuto;
    } elseif ($modalidadeAtual === 'avaliacao' && $cumpriuCriteriosPositivos) {
        // Promoção automática após cumprir todos os critérios objetivos de confiança
        $novaModalidade = 'automatica';
        $motivoAuto = 'Autor qualificado para publicação automática após cumprir os critérios objetivos de histórico positivo e ausência de violações.';
        if ($persistirMudanca) {
            $ownTx = !$pdo->inTransaction();
            if ($ownTx) {
                $pdo->beginTransaction();
            }
            try {
                $pdo->prepare(
                    'UPDATE usuario
                        SET modalidade_publicacao_usuario = ?,
                            capitulos_aprovados_usuario = ?,
                            justificativa_modalidade_usuario = ?,
                            data_qualificacao_usuario = COALESCE(data_qualificacao_usuario, CURRENT_TIMESTAMP)
                      WHERE id_usuario = ?'
                )->execute([$novaModalidade, $capsAprovadosHumano, $motivoAuto, $idAutor]);
                folivro_registrar_historico_moderacao(
                    $pdo,
                    $idAutor,
                    null,
                    'qualificacao_automatica',
                    $motivoAuto,
                    $modalidadeAtual,
                    $novaModalidade
                );
                if ($ownTx) {
                    $pdo->commit();
                }
            } catch (Throwable $e) {
                if ($ownTx && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }
        $modalidadeAtual = $novaModalidade;
        $justificativaAdmin = $motivoAuto;
    }

    $qualificadoAutomatico = ($modalidadeAtual === 'automatica' && !$temViolacaoOuPenalidade && $suspensoesAtivas === 0);

    if ($qualificadoAutomatico) {
        $msgInterface = 'Você está qualificado para publicação automática.';
    } elseif ($modalidadeAtual === 'suspensa') {
        $msgInterface = 'Sua permissão de publicação automática foi suspensa. Sua obra está sujeita à revisão humana.';
    } else {
        $msgInterface = 'Sua obra está sujeita à revisão humana.';
    }

    return [
        'modalidade' => $modalidadeAtual,
        'qualificado_automatico' => $qualificadoAutomatico,
        'mensagem_interface' => $msgInterface,
        'justificativa_admin' => $justificativaAdmin,
        'metricas' => [
            'capitulos_aprovados_humano' => $capsAprovadosHumano,
            'capitulos_automaticos'      => $capsAutomaticos,
            'obras_aprovadas'            => $obrasAprovadas,
            'violacoes_confirmadas'      => $violacoesConfirmadas,
            'denuncias_procedentes'      => $denunciasProcedentes,
            'revisoes_recusadas'         => $revisoesRecusadas,
            'avisos_moderacao'           => $avisosModeracao,
            'suspensoes_ativas'          => $suspensoesAtivas,
        ],
    ];
}

/**
 * Avalia preventivamente a conformidade de segurança e direitos autorais de um capítulo
 * antes de permitir publicação automática por autor qualificado.
 *
 * @return array{acao: 'permitir'|'reter_revisao_humana'|'bloquear', motivo: string}
 */
function folivro_avaliar_seguranca_capitulo(
    PDO $pdo,
    string $tituloObra,
    string $tituloCapitulo,
    string $conteudoCapitulo,
    string $origemObra,
    int $idObra = 0
): array {
    if (folivro_conteudo_foi_removido($pdo, $tituloObra, $conteudoCapitulo, $idObra)) {
        return [
            'acao' => 'bloquear',
            'motivo' => 'Bloqueio de segurança: este capítulo corresponde a material anteriormente removido pela moderação por violação de direitos autorais.',
        ];
    }

    if (in_array($origemObra, ['fanfic_sem_autorizacao', 'dominio_publico_nao_verificado'], true)) {
        return [
            'acao' => 'reter_revisao_humana',
            'motivo' => 'Sua publicação foi encaminhada para revisão humana: obras com base de direitos incerta exigem verificação prévia.',
        ];
    }

    $textoPlano = strip_tags($tituloCapitulo . ' ' . $conteudoCapitulo);
    $avalLinks = folivro_chat_avaliar_conteudo($textoPlano);
    if ($avalLinks['acao'] !== 'permitir') {
        return [
            'acao' => 'reter_revisao_humana',
            'motivo' => 'Sua publicação foi encaminhada para revisão humana por conter links externos ou referências que exigem verificação manual.',
        ];
    }

    return ['acao' => 'permitir', 'motivo' => ''];
}

/** Lê uma configuração administrativa do sistema (tabela sistema_config). */
function folivro_sistema_config_obter(PDO $pdo, string $chave, string $padrao = ''): string
{
    try {
        $st = $pdo->prepare('SELECT valor FROM sistema_config WHERE chave = ? LIMIT 1');
        $st->execute([$chave]);
        $v = $st->fetchColumn();
        return is_string($v) && $v !== '' ? $v : $padrao;
    } catch (Throwable $e) {
        return $padrao;
    }
}

/** Grava uma configuração administrativa do sistema (tabela sistema_config). */
function folivro_sistema_config_salvar(PDO $pdo, string $chave, string $valor, ?int $uidAdmin = null): void
{
    $st = $pdo->prepare(
        'INSERT INTO sistema_config (chave, valor, atualizado_por) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_por = VALUES(atualizado_por)'
    );
    $st->execute([$chave, $valor, $uidAdmin]);
}

/** Verifica se a aprovação automática global de obras postadas está ativada pelo administrador. */
function folivro_aprovacao_automatica_obras_ativa(PDO $pdo): bool
{
    return folivro_sistema_config_obter($pdo, 'aprovacao_automatica_obras', '0') === '1';
}




