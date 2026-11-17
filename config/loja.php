<?php
declare(strict_types=1);

/**
 * FOLIVRO — Camada de domínio da LOJA DE AUTORES (marketplace leitor ↔ autor).
 *
 * Regras centrais:
 *  - A compra é sempre entre leitor e autor; a plataforma só retém a comissão.
 *  - Preços e valores são SEMPRE lidos do banco (nunca do navegador) e tratados em centavos.
 *  - Toda alteração de pedido + biblioteca + ledger acontece numa transação PDO.
 *  - Liberar/estornar é idempotente: repetir o mesmo evento não duplica nada.
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/lib/pagamento/PaymentGatewayInterface.php';
require_once dirname(__DIR__) . '/lib/pagamento/SimulatedGateway.php';
require_once dirname(__DIR__) . '/lib/pagamento/MercadoPagoGateway.php';

const FOLIVRO_LOJA_TERMO_VERSAO = 'loja-1.0';
const FOLIVRO_LOJA_ARQUIVO_MAX_BYTES = 20 * 1024 * 1024; // 20 MB
const FOLIVRO_LOJA_CAPA_MAX_BYTES = 2 * 1024 * 1024;     // 2 MB (mesmo limite do estúdio)
const FOLIVRO_LOJA_PRECO_MAX_CENTAVOS = 999999;          // R$ 9.999,99
const FOLIVRO_LOJA_REPASSE_MIN_CENTAVOS = 100;           // R$ 1,00

// ---------------------------------------------------------------------
// Configuração e seleção do gateway
// ---------------------------------------------------------------------

function folivro_loja_config(): array
{
    $modoEnv = strtolower(trim((string) folivro_env('PAYMENT_MODE', 'simulated')));
    // Se não houver override de teste em memória, prioriza a configuração salva pelo admin no banco.
    if (!array_key_exists('PAYMENT_MODE', $GLOBALS['__folivro_env_override'] ?? [])) {
        try {
            $modoDb = folivro_sistema_config_obter(folivro_database(), 'payment_mode', '');
            if ($modoDb === 'real' || $modoDb === 'simulated') {
                $modoEnv = $modoDb;
            }
        } catch (Throwable $e) {
            // Mantém o valor do .env caso o banco não esteja disponível.
        }
    }
    $modo = $modoEnv === 'real' ? 'real' : 'simulado';

    $taxa = (float) str_replace(',', '.', (string) folivro_env('PLATFORM_FEE_PERCENT', '10'));
    if (!is_finite($taxa) || $taxa < 0) {
        $taxa = 0.0;
    }
    $taxa = min(100.0, round($taxa, 2));

    $appUrl = rtrim((string) folivro_env('APP_URL', ''), '/');
    return [
        'modo' => $modo,
        'taxa_percentual' => $taxa,
        'app_url' => $appUrl,
        'mp_access_token' => (string) folivro_env('MP_ACCESS_TOKEN', ''),
        'mp_public_key' => (string) folivro_env('MP_PUBLIC_KEY', ''),
        'mp_webhook_secret' => (string) folivro_env('MP_WEBHOOK_SECRET', ''),
        'mp_permitir_producao' => strtolower((string) folivro_env('MP_ALLOW_PRODUCTION', 'false')) === 'true',
        'sim_webhook_secret' => (string) folivro_env('SIMULATED_WEBHOOK_SECRET', ''),
        'sim_pix_expiracao_min' => (int) folivro_env('SIMULATED_PIX_EXPIRATION_MIN', '15'),
        'sim_pix_expira_curta_seg' => (int) folivro_env('SIMULATED_PIX_SHORT_EXPIRATION_SEC', '60'),
        'mp_pix_expiracao_min' => (int) folivro_env('MP_PIX_EXPIRATION_MIN', '30'),
    ];
}

function folivro_loja_modo(): string
{
    return folivro_loja_config()['modo'];
}

/** Instancia o gateway pelo nome gravado no pedido ou, se omitido, pelo modo configurado. */
function folivro_loja_gateway(PDO $pdo, ?string $nome = null): PaymentGatewayInterface
{
    $cfg = folivro_loja_config();
    $nome = $nome ?? ($cfg['modo'] === 'real' ? 'mercadopago' : 'simulado');
    if ($nome === 'simulado') {
        return new SimulatedGateway($pdo, [
            'webhook_secret' => $cfg['sim_webhook_secret'],
            'pix_expiracao_min' => $cfg['sim_pix_expiracao_min'],
            'pix_expira_curta_seg' => $cfg['sim_pix_expira_curta_seg'],
        ]);
    }
    if ($nome === 'mercadopago') {
        return new MercadoPagoGateway([
            'access_token' => $cfg['mp_access_token'],
            'webhook_secret' => $cfg['mp_webhook_secret'],
            'notification_url' => $cfg['app_url'] !== '' ? $cfg['app_url'] . '/api/loja-webhook.php?gateway=mercadopago' : '',
            'pix_expiracao_min' => $cfg['mp_pix_expiracao_min'],
            'permitir_producao' => $cfg['mp_permitir_producao'],
        ], $GLOBALS['__folivro_mp_http'] ?? null); // cliente HTTP injetável apenas nos testes
    }
    throw new PaymentGatewayException('Gateway de pagamento desconhecido.');
}

/** Informações do modo para o selo da interface (MODO SIMULADO / MODO REAL). */
function folivro_loja_info_modo(): array
{
    $cfg = folivro_loja_config();
    if ($cfg['modo'] === 'simulado') {
        return ['modo' => 'simulado', 'rotulo' => 'MODO SIMULADO', 'descricao' => 'Demonstração acadêmica: nenhum valor real é cobrado.', 'disponivel' => true, 'erro' => null, 'public_key' => ''];
    }
    $sandbox = MercadoPagoGateway::ehCredencialTeste($cfg['mp_access_token']);
    $erro = null;
    if ($cfg['mp_access_token'] === '' || $cfg['mp_public_key'] === '') {
        $erro = 'Modo real sem credenciais: configure MP_ACCESS_TOKEN e MP_PUBLIC_KEY no arquivo .env.';
    } elseif (!$sandbox && !$cfg['mp_permitir_producao']) {
        $erro = 'Credenciais de produção bloqueadas. Use credenciais de TESTE ou defina MP_ALLOW_PRODUCTION=true.';
    }
    return [
        'modo' => 'real',
        'rotulo' => $sandbox ? 'MODO REAL · SANDBOX (TESTE)' : 'MODO REAL · PRODUÇÃO',
        'descricao' => $sandbox ? 'Mercado Pago com credenciais de teste: use cartões e usuários de teste.' : 'Cobranças reais via Mercado Pago.',
        'disponivel' => $erro === null,
        'erro' => $erro,
        'public_key' => $cfg['mp_public_key'],
    ];
}

function folivro_loja_selo_modo_html(): string
{
    $info = folivro_loja_info_modo();
    return '<span class="selo-modo-pagamento selo-modo-' . folivro_escape($info['modo']) . '" title="' . folivro_escape($info['descricao']) . '">'
        . folivro_escape($info['rotulo']) . '</span>';
}

// ---------------------------------------------------------------------
// Dinheiro (sempre em centavos inteiros)
// ---------------------------------------------------------------------

/**
 * Calcula bruto / taxa da plataforma / líquido do autor.
 * Arredondamento "meio para cima" no centavo; taxa + líquido = bruto sempre.
 */
function folivro_loja_calcular_valores(int $brutoCentavos, ?float $taxaPercentual = null): array
{
    $brutoCentavos = max(0, $brutoCentavos);
    $pct = $taxaPercentual ?? folivro_loja_config()['taxa_percentual'];
    $pct = max(0.0, min(100.0, $pct));
    $pontosBase = (int) round($pct * 100); // 10% = 1000 pontos-base
    $taxa = intdiv($brutoCentavos * $pontosBase + 5000, 10000);
    $taxa = min($taxa, $brutoCentavos);
    return [
        'bruto' => $brutoCentavos,
        'taxa_percentual' => round($pontosBase / 100, 2),
        'taxa' => $taxa,
        'liquido' => $brutoCentavos - $taxa,
    ];
}

/** Converte "19,90", "19.90", "1.234,56" ou "R$ 5" em centavos. Retorna null se inválido. */
function folivro_loja_preco_para_centavos(string $texto): ?int
{
    $t = trim(str_ireplace(['R$', ' ', "\u{00A0}"], '', $texto));
    if ($t === '') {
        return null;
    }
    if (str_contains($t, ',')) {
        $t = str_replace('.', '', $t);
        $t = str_replace(',', '.', $t);
    }
    if (!preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/', $t, $m)) {
        return null;
    }
    $centavos = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    return $centavos;
}

function folivro_loja_formatar(int $centavos): string
{
    $neg = $centavos < 0 ? '- ' : '';
    return $neg . 'R$ ' . number_format(abs($centavos) / 100, 2, ',', '.');
}

function folivro_loja_centavos_para_input(int $centavos): string
{
    return number_format($centavos / 100, 2, ',', '');
}

// ---------------------------------------------------------------------
// Utilidades gerais: transação, código de pedido, logs e rate limit
// ---------------------------------------------------------------------

/** Executa $fn numa transação (reaproveita a transação externa, se existir). */
function folivro_loja_tx(PDO $pdo, callable $fn)
{
    if ($pdo->inTransaction()) {
        return $fn($pdo);
    }
    $pdo->beginTransaction();
    try {
        $r = $fn($pdo);
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function folivro_loja_gerar_codigo_pedido(): string
{
    return 'FLV' . strtoupper(substr(bin2hex(random_bytes(9)), 0, 17));
}

function folivro_loja_codigo_valido(string $codigo): bool
{
    return (bool) preg_match('/^FLV[A-F0-9]{17}$/', $codigo);
}

function folivro_loja_dir_storage(string $sub = ''): string
{
    $base = (string) folivro_env('LOJA_STORAGE_DIR', dirname(__DIR__) . '/storage');
    $base = rtrim(str_replace('\\', '/', $base), '/');
    if (!is_dir($base)) {
        @mkdir($base, 0750, true);
    }
    // Defesa em profundidade: nada nesta pasta é servido diretamente pelo Apache.
    if (!is_file($base . '/.htaccess')) {
        @file_put_contents($base . '/.htaccess', "Require all denied\nOptions -Indexes\n<IfModule mod_php.c>\nphp_flag engine off\n</IfModule>\n");
    }
    $dir = $sub === '' ? $base : $base . '/' . trim($sub, '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir;
}

/** Log estruturado SEM dados sensíveis (e-mails, tokens, documentos, cartão, QR, segredos). */
function folivro_loja_log(string $evento, array $ctx = []): void
{
    $proibidas = '/(email|token|card|cartao|cpf|cnpj|identification|payer|senha|password|secret|segredo|authorization|qr|signature|assinatura|cvv|documento)/i';
    $limpar = static function ($valor, $chave = '') use (&$limpar, $proibidas) {
        if (is_string($chave) && $chave !== '' && preg_match($proibidas, $chave)) {
            return '[omitido]';
        }
        if (is_array($valor)) {
            $out = [];
            foreach ($valor as $k => $v) {
                $out[$k] = $limpar($v, (string) $k);
            }
            return $out;
        }
        if (is_string($valor)) {
            $valor = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $valor) ?? '';
            return mb_substr($valor, 0, 300, 'UTF-8');
        }
        return $valor;
    };
    $linha = json_encode(['ts' => date('c'), 'evento' => $evento, 'ctx' => $limpar($ctx)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents(folivro_loja_dir_storage('logs') . '/loja-' . date('Y-m') . '.log', $linha . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/** Rate limit por janela fixa. Retorna true se a ação ainda é permitida. */
function folivro_loja_rate_limit(PDO $pdo, string $chave, int $max, int $janelaSeg): bool
{
    $janelaSeg = max(1, $janelaSeg);
    $inicio = intdiv(time(), $janelaSeg) * $janelaSeg;
    $hash = hash('sha256', $chave);
    $pdo->prepare(
        'INSERT INTO loja_rate_limit (chave, janela_inicio, contador) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE contador = contador + 1'
    )->execute([$hash, $inicio]);
    $st = $pdo->prepare('SELECT contador FROM loja_rate_limit WHERE chave = ? AND janela_inicio = ?');
    $st->execute([$hash, $inicio]);
    $contador = (int) $st->fetchColumn();
    if (random_int(1, 50) === 1) {
        $pdo->prepare('DELETE FROM loja_rate_limit WHERE janela_inicio < ?')->execute([time() - 86400]);
    }
    return $contador <= $max;
}

function folivro_loja_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'desconhecido';
}

// ---------------------------------------------------------------------
// Papéis (leitor / autor)
// ---------------------------------------------------------------------

function folivro_loja_usuario(PDO $pdo, int $uid): ?array
{
    $st = $pdo->prepare('SELECT id_usuario, nome_usuario, email_usuario, nivel_usuario, status_usuario, data_nascimento_usuario FROM usuario WHERE id_usuario = ? LIMIT 1');
    $st->execute([$uid]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    return $u ?: null;
}

/** Autor = nível 'autor' ou 'admin' com conta ativa (o leitor vira autor aceitando o termo). */
function folivro_loja_eh_autor(PDO $pdo, int $uid): bool
{
    $u = folivro_loja_usuario($pdo, $uid);
    return $u !== null && $u['status_usuario'] === 'ativo' && in_array($u['nivel_usuario'], ['autor', 'admin'], true);
}

/** Promove leitor → autor (mesma regra usada em escrever.php) e registra o aceite do termo de vendedor. */
function folivro_loja_tornar_autor(PDO $pdo, int $uid): void
{
    folivro_loja_tx($pdo, static function (PDO $pdo) use ($uid): void {
        $pdo->prepare("UPDATE usuario SET nivel_usuario = 'autor' WHERE id_usuario = ? AND nivel_usuario = 'leitor' AND status_usuario = 'ativo'")
            ->execute([$uid]);
        $pdo->prepare("INSERT INTO termos_aceitos (id_usuario, tipo_termos, versao_termos) VALUES (?, 'declaracao_comercializacao', ?)")
            ->execute([$uid, FOLIVRO_LOJA_TERMO_VERSAO]);
    });
    if (session_status() === PHP_SESSION_ACTIVE && (int) ($_SESSION['id_usuario'] ?? 0) === $uid && ($_SESSION['nivel_usuario'] ?? '') === 'leitor') {
        $_SESSION['nivel_usuario'] = 'autor';
    }
}

// ---------------------------------------------------------------------
// Produtos (obras à venda)
// ---------------------------------------------------------------------

function folivro_loja_status_produto_label(string $s): string
{
    return ['rascunho' => 'Rascunho', 'publicado' => 'Publicado', 'arquivado' => 'Arquivado'][$s] ?? $s;
}

function folivro_loja_produto(PDO $pdo, int $idProduto): ?array
{
    $st = $pdo->prepare(
        'SELECT p.*, u.nome_usuario AS autor_nome, u.status_usuario AS autor_status
           FROM loja_produtos p
           JOIN usuario u ON u.id_usuario = p.id_autor
          WHERE p.id_produto = ? LIMIT 1'
    );
    $st->execute([$idProduto]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    return $p ?: null;
}

/** Produto somente se pertencer ao autor informado (proteção contra IDOR). */
function folivro_loja_produto_do_autor(PDO $pdo, int $idProduto, int $uid): ?array
{
    $p = folivro_loja_produto($pdo, $idProduto);
    return ($p !== null && (int) $p['id_autor'] === $uid) ? $p : null;
}

/** Produto visível na vitrine (publicado e autor ativo). */
function folivro_loja_produto_publicado(PDO $pdo, int $idProduto): ?array
{
    $p = folivro_loja_produto($pdo, $idProduto);
    return ($p !== null && $p['status_produto'] === 'publicado' && $p['autor_status'] === 'ativo') ? $p : null;
}

/** Detecta o tipo REAL do arquivo pelo conteúdo (assinatura binária + estrutura), não pela extensão. */
function folivro_loja_detectar_tipo_obra(string $caminho): ?string
{
    if (!is_file($caminho) || filesize($caminho) < 8) {
        return null;
    }
    $fh = fopen($caminho, 'rb');
    if (!$fh) {
        return null;
    }
    $cabecalho = (string) fread($fh, 64);
    fclose($fh);
    $mime = '';
    if (class_exists('finfo')) {
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($caminho);
    }

    if (str_starts_with($cabecalho, '%PDF-') && $mime === 'application/pdf') {
        return 'pdf';
    }
    // EPUB: ZIP cujo 1º item é "mimetype" (sem compressão) contendo "application/epub+zip".
    if (substr($cabecalho, 0, 4) === "PK\x03\x04"
        && substr($cabecalho, 30, 8) === 'mimetype'
        && substr($cabecalho, 38, 20) === 'application/epub+zip'
        && in_array($mime, ['application/epub+zip', 'application/zip', 'application/octet-stream'], true)
    ) {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($caminho) !== true) {
                return null;
            }
            $temContainer = $zip->locateName('META-INF/container.xml') !== false;
            $zip->close();
            if (!$temContainer) {
                return null;
            }
        }
        return 'epub';
    }
    return null;
}

/** Nome de arquivo seguro para Content-Disposition (ASCII, sem caminhos). */
function folivro_loja_nome_seguro(string $nome, string $ext): string
{
    $base = pathinfo($nome, PATHINFO_FILENAME);
    $ascii = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) : $base;
    $ascii = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $ascii), '-.');
    if ($ascii === '') {
        $ascii = 'obra';
    }
    return substr($ascii, 0, 80) . '.' . $ext;
}

/**
 * Valida e armazena o arquivo da obra (PDF/EPUB) fora do acesso público.
 * $exigirUpload=false é usado apenas por seeds/testes (arquivo local, não HTTP).
 * @return array{ok:bool,erro?:string,arquivo?:string,tipo?:string,tamanho?:int,sha256?:string,nome_original?:string}
 */
function folivro_loja_armazenar_arquivo_obra(array $file, int $uid, bool $exigirUpload = true): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'erro' => 'Falha no envio do arquivo da obra. Verifique o tamanho (máx. 20 MB) e tente novamente.'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($exigirUpload && !is_uploaded_file($tmp)) {
        return ['ok' => false, 'erro' => 'Envio de arquivo inválido.'];
    }
    $tamanho = (int) (filesize($tmp) ?: 0);
    if ($tamanho <= 0 || $tamanho > FOLIVRO_LOJA_ARQUIVO_MAX_BYTES) {
        return ['ok' => false, 'erro' => 'O arquivo da obra deve ter no máximo 20 MB.'];
    }
    $tipo = folivro_loja_detectar_tipo_obra($tmp);
    if ($tipo === null) {
        return ['ok' => false, 'erro' => 'Formato inválido: envie um PDF ou EPUB legítimo (o conteúdo do arquivo é verificado, não só a extensão).'];
    }
    $extDeclarada = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($extDeclarada !== $tipo) {
        return ['ok' => false, 'erro' => 'A extensão do arquivo não corresponde ao seu conteúdo real (' . strtoupper($tipo) . ').'];
    }
    $nomeInterno = 'obra_' . $uid . '_' . bin2hex(random_bytes(12)) . '.' . $tipo;
    $destino = folivro_loja_dir_storage('obras') . '/' . $nomeInterno;
    $movido = $exigirUpload ? move_uploaded_file($tmp, $destino) : copy($tmp, $destino);
    if (!$movido) {
        return ['ok' => false, 'erro' => 'Não foi possível armazenar o arquivo da obra.'];
    }
    return [
        'ok' => true,
        'arquivo' => $nomeInterno,
        'tipo' => $tipo,
        'tamanho' => $tamanho,
        'sha256' => (string) hash_file('sha256', $destino),
        'nome_original' => folivro_loja_nome_seguro((string) ($file['name'] ?? 'obra'), $tipo),
    ];
}

/** Mesmo padrão do upload de capa já existente (escrever.php / gerenciar-obra.php). */
function folivro_loja_armazenar_capa(array $file, int $uid, bool $exigirUpload = true): array
{
    $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'erro' => 'Falha no envio da imagem de capa. Tente um arquivo menor.'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($exigirUpload && !is_uploaded_file($tmp)) {
        return ['ok' => false, 'erro' => 'Envio de capa inválido.'];
    }
    if ((int) (filesize($tmp) ?: 0) > FOLIVRO_LOJA_CAPA_MAX_BYTES) {
        return ['ok' => false, 'erro' => 'A capa da obra deve ter no máximo 2 MB.'];
    }
    $info = @getimagesize($tmp);
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    if (!isset($tipos[$mime])) {
        return ['ok' => false, 'erro' => 'Formato de capa inválido. Envie apenas JPG, PNG ou WebP.'];
    }
    $dir = dirname(__DIR__) . '/uploads/capas';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/.htaccess', "Options -Indexes\nphp_flag engine off\n");
    }
    $nome = 'capa_loja_' . $uid . '_' . bin2hex(random_bytes(8)) . '.' . $tipos[$mime];
    $ok = $exigirUpload ? move_uploaded_file($tmp, $dir . '/' . $nome) : copy($tmp, $dir . '/' . $nome);
    return $ok ? ['ok' => true, 'caminho' => 'uploads/capas/' . $nome] : ['ok' => false, 'erro' => 'Não foi possível salvar a imagem de capa.'];
}

/**
 * Cria ou edita uma obra do autor. Autorização no servidor: só o dono edita.
 * @return array{ok:bool,erro?:string,id?:int}
 */
function folivro_loja_salvar_produto(PDO $pdo, int $uid, ?int $idProduto, array $dados, array $arquivos = [], bool $exigirUpload = true): array
{
    if (!folivro_loja_eh_autor($pdo, $uid)) {
        return ['ok' => false, 'erro' => 'Apenas autores podem cadastrar obras na loja.'];
    }
    $atual = null;
    if ($idProduto !== null) {
        $atual = folivro_loja_produto_do_autor($pdo, $idProduto, $uid);
        if ($atual === null) {
            return ['ok' => false, 'erro' => 'Obra não encontrada.'];
        }
    }

    $titulo = trim((string) ($dados['titulo_produto'] ?? ''));
    $descricao = trim((string) ($dados['descricao_produto'] ?? ''));
    $preview = trim((string) ($dados['preview_produto'] ?? ''));
    $precoTxt = (string) ($dados['preco'] ?? '');
    $preco = folivro_loja_preco_para_centavos($precoTxt === '' ? '0' : $precoTxt);
    $generos = folivro_generos_para_string($dados['generos_produto'] ?? [], (string) ($dados['generos_personalizados'] ?? ''));
    $classifBruta = (string) ($dados['classificacao_produto'] ?? 'livre');
    $classif = folivro_normalizar_classificacao($classifBruta);
    $aceite = ($dados['aceite_termo_direitos'] ?? '') === '1';
    $idAdl = filter_var($dados['id_autor_dos_livros'] ?? null, FILTER_VALIDATE_INT) ?: null;

    if (mb_strlen($titulo, 'UTF-8') < 2 || mb_strlen($titulo, 'UTF-8') > 200) {
        return ['ok' => false, 'erro' => 'Informe um título entre 2 e 200 caracteres.'];
    }
    if (mb_strlen($descricao, 'UTF-8') > 5000 || mb_strlen($preview, 'UTF-8') > 3000) {
        return ['ok' => false, 'erro' => 'A descrição pode ter até 5.000 caracteres e o trecho de prévia até 3.000.'];
    }
    if ($preco === null || $preco > FOLIVRO_LOJA_PRECO_MAX_CENTAVOS) {
        return ['ok' => false, 'erro' => 'Preço inválido. Use o formato 19,90 (de R$ 0,00 — gratuito — até R$ 9.999,99).'];
    }
    if (!array_key_exists($classifBruta, folivro_classificacoes_idade())) {
        return ['ok' => false, 'erro' => 'Selecione uma classificação indicativa válida.'];
    }
    // Lei 9.610/98: o autor declara ser titular dos direitos patrimoniais da obra.
    if (!$aceite && (int) ($atual['aceite_termo_direitos'] ?? 0) !== 1) {
        return ['ok' => false, 'erro' => 'É obrigatório declarar a titularidade dos direitos autorais (Lei 9.610/98) e aceitar o termo de venda.'];
    }
    if ($idAdl !== null) {
        $chk = $pdo->prepare('SELECT 1 FROM autor_dos_livros WHERE id_autor_dos_livros = ? AND id_autor = ?');
        $chk->execute([$idAdl, $uid]);
        if (!$chk->fetchColumn()) {
            return ['ok' => false, 'erro' => 'A obra do estúdio selecionada não pertence a você.'];
        }
    }

    $novoArquivo = null;
    if (!empty($arquivos['arquivo_obra']) && ($arquivos['arquivo_obra']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $novoArquivo = folivro_loja_armazenar_arquivo_obra($arquivos['arquivo_obra'], $uid, $exigirUpload);
        if (!$novoArquivo['ok']) {
            return ['ok' => false, 'erro' => $novoArquivo['erro']];
        }
    }
    $novaCapa = null;
    if (!empty($arquivos['capa_produto']) && ($arquivos['capa_produto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $novaCapa = folivro_loja_armazenar_capa($arquivos['capa_produto'], $uid, $exigirUpload);
        if (!$novaCapa['ok']) {
            if ($novoArquivo) {
                @unlink(folivro_loja_dir_storage('obras') . '/' . $novoArquivo['arquivo']);
            }
            return ['ok' => false, 'erro' => $novaCapa['erro']];
        }
    }

    $agora = date('Y-m-d H:i:s');
    try {
        $id = folivro_loja_tx($pdo, static function (PDO $pdo) use ($atual, $uid, $titulo, $descricao, $preview, $preco, $generos, $classif, $aceite, $idAdl, $novoArquivo, $novaCapa, $agora): int {
            if ($atual === null) {
                $pdo->prepare(
                    'INSERT INTO loja_produtos (id_autor, id_autor_dos_livros, titulo_produto, descricao_produto, preview_produto, capa_produto,
                                                generos_produto, classificacao_produto, preco_centavos, arquivo_produto, arquivo_nome_original,
                                                arquivo_tipo, arquivo_tamanho, arquivo_sha256, status_produto, aceite_termo_direitos, data_aceite_termo, versao_termo)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'rascunho\', 1, ?, ?)'
                )->execute([
                    $uid, $idAdl, $titulo, $descricao ?: null, $preview ?: null, $novaCapa['caminho'] ?? null,
                    $generos ?: null, $classif, $preco,
                    $novoArquivo['arquivo'] ?? null, $novoArquivo['nome_original'] ?? null, $novoArquivo['tipo'] ?? null,
                    $novoArquivo['tamanho'] ?? null, $novoArquivo['sha256'] ?? null, $agora, FOLIVRO_LOJA_TERMO_VERSAO,
                ]);
                $id = (int) $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO termos_aceitos (id_usuario, tipo_termos, versao_termos) VALUES (?, 'declaracao_autoria', ?)")
                    ->execute([$uid, FOLIVRO_LOJA_TERMO_VERSAO]);
                return $id;
            }
            $id = (int) $atual['id_produto'];
            $pdo->prepare(
                'UPDATE loja_produtos
                    SET titulo_produto = ?, descricao_produto = ?, preview_produto = ?, generos_produto = ?, classificacao_produto = ?,
                        preco_centavos = ?, id_autor_dos_livros = ?,
                        capa_produto = COALESCE(?, capa_produto),
                        arquivo_produto = COALESCE(?, arquivo_produto), arquivo_nome_original = COALESCE(?, arquivo_nome_original),
                        arquivo_tipo = COALESCE(?, arquivo_tipo), arquivo_tamanho = COALESCE(?, arquivo_tamanho), arquivo_sha256 = COALESCE(?, arquivo_sha256),
                        aceite_termo_direitos = 1,
                        data_aceite_termo = IF(? = 1, ?, data_aceite_termo), versao_termo = IF(? = 1, ?, versao_termo)
                  WHERE id_produto = ? AND id_autor = ?'
            )->execute([
                $titulo, $descricao ?: null, $preview ?: null, $generos ?: null, $classif, $preco, $idAdl,
                $novaCapa['caminho'] ?? null,
                $novoArquivo['arquivo'] ?? null, $novoArquivo['nome_original'] ?? null, $novoArquivo['tipo'] ?? null,
                $novoArquivo['tamanho'] ?? null, $novoArquivo['sha256'] ?? null,
                $aceite ? 1 : 0, $agora, $aceite ? 1 : 0, FOLIVRO_LOJA_TERMO_VERSAO,
                $id, $uid,
            ]);
            return $id;
        });
    } catch (Throwable $e) {
        if ($novoArquivo) {
            @unlink(folivro_loja_dir_storage('obras') . '/' . $novoArquivo['arquivo']);
        }
        folivro_loja_log('produto_erro_salvar', ['id_autor' => $uid, 'erro' => $e->getMessage()]);
        return ['ok' => false, 'erro' => 'Não foi possível salvar a obra.'];
    }

    // Arquivo antigo substituído: os compradores passam a baixar a nova versão.
    if ($novoArquivo && !empty($atual['arquivo_produto'])) {
        @unlink(folivro_loja_dir_storage('obras') . '/' . basename((string) $atual['arquivo_produto']));
    }
    $produto = folivro_loja_produto($pdo, $id);
    if ($produto && $produto['status_produto'] === 'publicado') {
        folivro_loja_sincronizar_livro($pdo, $id);
    }
    folivro_loja_log($atual ? 'produto_editado' : 'produto_criado', ['id_produto' => $id, 'id_autor' => $uid, 'preco' => $preco]);
    return ['ok' => true, 'id' => $id];
}

/** Publicar / despublicar / arquivar — somente o autor dono. */
function folivro_loja_alterar_status_produto(PDO $pdo, int $uid, int $idProduto, string $novo): array
{
    $p = folivro_loja_produto_do_autor($pdo, $idProduto, $uid);
    if ($p === null || !folivro_loja_eh_autor($pdo, $uid)) {
        return ['ok' => false, 'erro' => 'Obra não encontrada.'];
    }
    if (!in_array($novo, ['rascunho', 'publicado', 'arquivado'], true)) {
        return ['ok' => false, 'erro' => 'Status inválido.'];
    }
    if ($novo === 'publicado') {
        if (empty($p['arquivo_produto']) || !is_file(folivro_loja_dir_storage('obras') . '/' . basename((string) $p['arquivo_produto']))) {
            return ['ok' => false, 'erro' => 'Envie o arquivo PDF/EPUB da obra antes de publicar.'];
        }
        if ((int) $p['aceite_termo_direitos'] !== 1) {
            return ['ok' => false, 'erro' => 'Aceite o termo de titularidade dos direitos antes de publicar.'];
        }
    }
    $pdo->prepare(
        'UPDATE loja_produtos SET status_produto = ?, data_publicacao = IF(? = \'publicado\', COALESCE(data_publicacao, ?), data_publicacao)
          WHERE id_produto = ? AND id_autor = ?'
    )->execute([$novo, $novo, date('Y-m-d H:i:s'), $idProduto, $uid]);
    if ($novo === 'publicado') {
        folivro_loja_sincronizar_livro($pdo, $idProduto);
    }
    folivro_loja_log('produto_status', ['id_produto' => $idProduto, 'id_autor' => $uid, 'status' => $novo]);
    return ['ok' => true];
}

/**
 * Integra a obra ao catálogo existente (tabela livro, fonte 'loja'):
 * é por ela que a compra entra na biblioteca pessoal (livraria) e nas avaliações (resenha).
 */
function folivro_loja_sincronizar_livro(PDO $pdo, int $idProduto): ?int
{
    $p = folivro_loja_produto($pdo, $idProduto);
    if ($p === null) {
        return null;
    }
    $genero = $p['generos_produto'] ? mb_substr((string) $p['generos_produto'], 0, 80, 'UTF-8') : null;
    if (empty($p['id_livro'])) {
        $pdo->prepare(
            "INSERT INTO livro (titulo_livro, autor_livro, sinopse_livro, capa_livro, genero_livro, classificacao_livro, fonte_livro, id_externo_livro)
             VALUES (?, ?, ?, ?, ?, ?, 'loja', ?)
             ON DUPLICATE KEY UPDATE id_livro = LAST_INSERT_ID(id_livro)"
        )->execute([$p['titulo_produto'], mb_substr((string) $p['autor_nome'], 0, 150, 'UTF-8'), $p['descricao_produto'], $p['capa_produto'], $genero, $p['classificacao_produto'], 'LOJA-' . $idProduto]);
        $idLivro = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE loja_produtos SET id_livro = ? WHERE id_produto = ?')->execute([$idLivro, $idProduto]);
        return $idLivro;
    }
    $pdo->prepare(
        'UPDATE livro SET titulo_livro = ?, autor_livro = ?, sinopse_livro = ?, capa_livro = ?, genero_livro = ?, classificacao_livro = ? WHERE id_livro = ?'
    )->execute([$p['titulo_produto'], mb_substr((string) $p['autor_nome'], 0, 150, 'UTF-8'), $p['descricao_produto'], $p['capa_produto'], $genero, $p['classificacao_produto'], (int) $p['id_livro']]);
    return (int) $p['id_livro'];
}

/** Escapa curingas do LIKE. */
function folivro_loja_like(string $termo): string
{
    return '%' . addcslashes($termo, '\\%_') . '%';
}

/**
 * Vitrine com busca, filtros (gênero, faixa de preço, autor) e ordenação — tudo no servidor.
 * @return array{itens:array,total:int,paginas:int,pagina:int}
 */
function folivro_loja_listar_vitrine(PDO $pdo, array $f, int $pagina = 1, int $porPagina = 12): array
{
    $where = ["p.status_produto = 'publicado'", "u.status_usuario = 'ativo'"];
    $params = [];
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '' && mb_strlen($q, 'UTF-8') <= 100) {
        $where[] = '(p.titulo_produto LIKE ? OR u.nome_usuario LIKE ? OR p.generos_produto LIKE ? OR p.descricao_produto LIKE ?)';
        $like = folivro_loja_like($q);
        array_push($params, $like, $like, $like, $like);
    }
    $genero = trim((string) ($f['genero'] ?? ''));
    if ($genero !== '' && mb_strlen($genero, 'UTF-8') <= 45) {
        $where[] = 'p.generos_produto LIKE ?';
        $params[] = folivro_loja_like($genero);
    }
    $autor = (int) ($f['autor'] ?? 0);
    if ($autor > 0) {
        $where[] = 'p.id_autor = ?';
        $params[] = $autor;
    }
    $faixas = [
        'gratis' => 'p.preco_centavos = 0',
        'ate20' => 'p.preco_centavos BETWEEN 1 AND 2000',
        '20a50' => 'p.preco_centavos BETWEEN 2001 AND 5000',
        'acima50' => 'p.preco_centavos > 5000',
    ];
    $faixa = (string) ($f['preco'] ?? '');
    if (isset($faixas[$faixa])) {
        $where[] = $faixas[$faixa];
    }
    $ordens = [
        'recentes' => 'p.data_publicacao DESC, p.id_produto DESC',
        'preco_asc' => 'p.preco_centavos ASC, p.id_produto DESC',
        'preco_desc' => 'p.preco_centavos DESC, p.id_produto DESC',
        'avaliacao' => 'media_nota DESC, total_resenhas DESC, p.id_produto DESC',
        'vendas' => 'total_vendas DESC, p.id_produto DESC',
        'titulo' => 'p.titulo_produto ASC',
    ];
    $ordem = $ordens[(string) ($f['ordenar'] ?? 'recentes')] ?? $ordens['recentes'];
    $whereSql = implode(' AND ', $where);

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM loja_produtos p JOIN usuario u ON u.id_usuario = p.id_autor WHERE {$whereSql}");
    $cnt->execute($params);
    $total = (int) $cnt->fetchColumn();
    $porPagina = max(1, min(48, $porPagina));
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina = max(1, min($pagina, $paginas));
    $offset = ($pagina - 1) * $porPagina;

    $st = $pdo->prepare(
        "SELECT p.id_produto, p.id_autor, p.id_livro, p.id_autor_dos_livros, p.titulo_produto, p.descricao_produto, p.capa_produto, p.generos_produto,
                p.classificacao_produto, p.preco_centavos, p.arquivo_tipo, p.data_publicacao, u.nome_usuario AS autor_nome,
                COALESCE((SELECT AVG(r.nota_resenha) FROM resenha r WHERE r.id_livro = p.id_livro), 0) AS media_nota,
                (SELECT COUNT(*) FROM resenha r WHERE r.id_livro = p.id_livro) AS total_resenhas,
                (SELECT COUNT(*) FROM loja_pedidos o WHERE o.id_produto = p.id_produto AND o.status_pedido = 'pago') AS total_vendas
           FROM loja_produtos p
           JOIN usuario u ON u.id_usuario = p.id_autor
          WHERE {$whereSql}
       ORDER BY {$ordem}
          LIMIT " . (int) $porPagina . ' OFFSET ' . (int) $offset
    );
    $st->execute($params);
    return ['itens' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'paginas' => $paginas, 'pagina' => $pagina];
}

function folivro_loja_autores_vitrine(PDO $pdo): array
{
    return $pdo->query(
        "SELECT DISTINCT u.id_usuario, u.nome_usuario
           FROM loja_produtos p JOIN usuario u ON u.id_usuario = p.id_autor
          WHERE p.status_produto = 'publicado' AND u.status_usuario = 'ativo'
       ORDER BY u.nome_usuario"
    )->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------------------------------------------------------------
// Pedidos
// ---------------------------------------------------------------------

function folivro_loja_status_pedido_label(string $status, ?string $motivo = null): string
{
    if ($status === 'cancelado' && $motivo !== null && stripos($motivo, 'expirad') !== false) {
        return 'Expirado';
    }
    return [
        'pendente' => 'Aguardando pagamento',
        'pago' => 'Pago',
        'falhou' => 'Recusado / falhou',
        'cancelado' => 'Cancelado',
        'reembolsado' => 'Reembolsado',
    ][$status] ?? $status;
}

function folivro_loja_ja_possui(PDO $pdo, int $uid, int $idProduto): bool
{
    $st = $pdo->prepare("SELECT 1 FROM loja_pedidos WHERE id_comprador = ? AND id_produto = ? AND status_pedido = 'pago' LIMIT 1");
    $st->execute([$uid, $idProduto]);
    return (bool) $st->fetchColumn();
}

/** Regras de negócio da compra. Retorna mensagem de erro ou null se permitido. */
function folivro_loja_validar_compra(PDO $pdo, int $uid, ?array $produto): ?string
{
    if ($produto === null || $produto['status_produto'] !== 'publicado' || ($produto['autor_status'] ?? 'ativo') !== 'ativo' || empty($produto['arquivo_produto'])) {
        return 'Esta obra não está disponível para compra.';
    }
    if ((int) $produto['id_autor'] === $uid) {
        return 'Você não pode comprar sua própria obra.';
    }
    $comprador = folivro_loja_usuario($pdo, $uid);
    if ($comprador === null || $comprador['status_usuario'] !== 'ativo') {
        return 'Conta inválida para compras.';
    }
    if (folivro_classificacao_eh_maior18((string) $produto['classificacao_produto'])) {
        $idade = folivro_calcular_idade($comprador['data_nascimento_usuario'] ?? null);
        if ($idade === null || $idade < 18) {
            return 'Compra bloqueada (+18): esta obra é restrita para maiores de 18 anos conforme a data de nascimento do seu cadastro.';
        }
    }
    if (folivro_loja_ja_possui($pdo, $uid, (int) $produto['id_produto'])) {
        return 'Você já possui esta obra. Ela está disponível em "Minhas compras".';
    }
    return null;
}

function folivro_loja_buscar_pedido(PDO $pdo, int $idPedido): ?array
{
    $st = $pdo->prepare('SELECT * FROM loja_pedidos WHERE id_pedido = ? LIMIT 1');
    $st->execute([$idPedido]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    return $p ?: null;
}

/** Pedido do comprador logado (IDOR: o código sozinho não dá acesso). */
function folivro_loja_pedido_do_comprador(PDO $pdo, string $codigo, int $uid): ?array
{
    if (!folivro_loja_codigo_valido($codigo)) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT o.*, p.capa_produto, p.id_livro, p.arquivo_tipo, p.arquivo_produto, u.nome_usuario AS vendedor_nome
           FROM loja_pedidos o
           JOIN loja_produtos p ON p.id_produto = o.id_produto
           JOIN usuario u ON u.id_usuario = o.id_vendedor
          WHERE o.codigo_pedido = ? AND o.id_comprador = ? LIMIT 1'
    );
    $st->execute([$codigo, $uid]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    return $p ?: null;
}

/** Pedido visível ao vendedor (autor) — somente das próprias vendas. */
function folivro_loja_pedido_do_vendedor(PDO $pdo, int $idPedido, int $uid): ?array
{
    $st = $pdo->prepare('SELECT * FROM loja_pedidos WHERE id_pedido = ? AND id_vendedor = ? LIMIT 1');
    $st->execute([$idPedido, $uid]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    return $p ?: null;
}

/**
 * Cria o pedido com valores calculados no servidor a partir do banco.
 * Qualquer preço/valor enviado pelo cliente é ignorado.
 * @return array{ok:bool,erro?:string,pedido?:array,reutilizado?:bool}
 */
function folivro_loja_criar_pedido(PDO $pdo, int $uid, int $idProduto, string $metodo, ?string $gatewayForcado = null): array
{
    $produto = folivro_loja_produto($pdo, $idProduto);
    $erro = folivro_loja_validar_compra($pdo, $uid, $produto);
    if ($erro !== null) {
        return ['ok' => false, 'erro' => $erro];
    }
    $preco = (int) $produto['preco_centavos']; // fonte única de verdade do preço
    if ($preco === 0) {
        $metodo = 'gratuito';
    } elseif (!in_array($metodo, ['pix', 'cartao'], true)) {
        return ['ok' => false, 'erro' => 'Escolha Pix ou cartão.'];
    }

    $cfg = folivro_loja_config();
    $gateway = $preco === 0 ? 'nenhum' : ($gatewayForcado ?? ($cfg['modo'] === 'real' ? 'mercadopago' : 'simulado'));
    $modo = $gateway === 'mercadopago' ? 'real' : ($gateway === 'simulado' ? 'simulado' : $cfg['modo']);

    // Reaproveita um pedido pendente equivalente (evita cobranças duplicadas em cliques repetidos).
    $pend = $pdo->prepare(
        "SELECT * FROM loja_pedidos
          WHERE id_comprador = ? AND id_produto = ? AND status_pedido = 'pendente'
       ORDER BY id_pedido DESC"
    );
    $pend->execute([$uid, $idProduto]);
    foreach ($pend->fetchAll(PDO::FETCH_ASSOC) as $ped) {
        $vigente = $ped['expira_em'] === null || strtotime((string) $ped['expira_em']) > time();
        if ($ped['metodo_pagamento'] === $metodo && $ped['gateway'] === $gateway && (int) $ped['valor_bruto_centavos'] === $preco && $vigente && $metodo === 'pix') {
            return ['ok' => true, 'pedido' => $ped, 'reutilizado' => true];
        }
        if ($ped['id_externo_gateway'] === null || !$vigente) {
            $pdo->prepare("UPDATE loja_pedidos SET status_pedido = 'cancelado', motivo_status = 'Substituído por um novo pedido' WHERE id_pedido = ? AND status_pedido = 'pendente'")
                ->execute([(int) $ped['id_pedido']]);
        }
    }

    $v = folivro_loja_calcular_valores($preco, $cfg['taxa_percentual']);
    $codigo = folivro_loja_gerar_codigo_pedido();
    $pdo->prepare(
        'INSERT INTO loja_pedidos (codigo_pedido, id_comprador, id_vendedor, id_produto, titulo_produto_snapshot,
                                   valor_bruto_centavos, taxa_percentual, valor_taxa_centavos, valor_liquido_centavos,
                                   metodo_pagamento, modo_pagamento, gateway, status_pedido)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pendente\')'
    )->execute([
        $codigo, $uid, (int) $produto['id_autor'], $idProduto, $produto['titulo_produto'],
        $v['bruto'], $v['taxa_percentual'], $v['taxa'], $v['liquido'], $metodo, $modo, $gateway,
    ]);
    $idPedido = (int) $pdo->lastInsertId();
    folivro_loja_log('pedido_criado', ['pedido' => $codigo, 'id_produto' => $idProduto, 'id_comprador' => $uid, 'metodo' => $metodo, 'modo' => $modo, 'bruto' => $v['bruto']]);

    if ($metodo === 'gratuito') {
        // Obra gratuita: não passa pelo gateway.
        folivro_loja_confirmar_pagamento($pdo, $idPedido, 'gratuito', ['status_detalhe' => 'free', 'valor_centavos' => 0]);
    }
    return ['ok' => true, 'pedido' => folivro_loja_buscar_pedido($pdo, $idPedido), 'reutilizado' => false];
}

function folivro_loja_registrar_pagamento(PDO $pdo, array $pedido, string $origem, string $status, ?string $detalhe, ?int $valor, $payload, ?string $idExterno = null): void
{
    $json = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare(
        'INSERT INTO loja_pagamentos (id_pedido, gateway, id_externo, origem_evento, status_pagamento, status_detalhe, valor_centavos, payload_bruto)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        (int) $pedido['id_pedido'], (string) $pedido['gateway'], $idExterno ?? $pedido['id_externo_gateway'], $origem,
        mb_substr($status, 0, 30), $detalhe !== null ? mb_substr($detalhe, 0, 120, 'UTF-8') : null, $valor,
        $json !== false && $json !== null ? mb_substr((string) $json, 0, 16000, 'UTF-8') : null,
    ]);
}

/**
 * Inicia a cobrança no gateway do pedido. Idempotente: se a cobrança já existe, não cria outra.
 * @return array{ok:bool,erro?:string,pedido?:array}
 */
function folivro_loja_iniciar_cobranca(PDO $pdo, array $pedido, array $dados): array
{
    if ($pedido['status_pedido'] !== 'pendente') {
        return ['ok' => $pedido['status_pedido'] === 'pago', 'pedido' => $pedido, 'erro' => 'Este pedido não está mais aguardando pagamento.'];
    }
    if ($pedido['id_externo_gateway'] !== null) {
        return ['ok' => true, 'pedido' => $pedido];
    }
    try {
        $gw = folivro_loja_gateway($pdo, (string) $pedido['gateway']);
        $comprador = folivro_loja_usuario($pdo, (int) $pedido['id_comprador']);
        $res = $gw->criarCobranca($pedido + ['comprador_email' => (string) ($comprador['email_usuario'] ?? '')], $dados);
    } catch (PaymentGatewayException $e) {
        $pdo->prepare("UPDATE loja_pedidos SET status_pedido = 'falhou', motivo_status = ? WHERE id_pedido = ? AND status_pedido = 'pendente'")
            ->execute([mb_substr($e->getMessage(), 0, 255, 'UTF-8'), (int) $pedido['id_pedido']]);
        folivro_loja_log('cobranca_erro', ['pedido' => $pedido['codigo_pedido'], 'gateway' => $pedido['gateway'], 'erro' => $e->getMessage()]);
        return ['ok' => false, 'erro' => $e->getMessage(), 'pedido' => folivro_loja_buscar_pedido($pdo, (int) $pedido['id_pedido'])];
    }

    $pix = $res['pix'] ?? null;
    $pdo->prepare(
        'UPDATE loja_pedidos SET id_externo_gateway = ?, pix_copia_cola = ?, pix_qr_base64 = ?, pix_ticket_url = ?, expira_em = ?
          WHERE id_pedido = ?'
    )->execute([
        $res['id_externo'], $pix['copia_cola'] ?? null, $pix['qr_base64'] ?? null, $pix['ticket_url'] ?? null, $pix['expira_em'] ?? null,
        (int) $pedido['id_pedido'],
    ]);
    $pedido = folivro_loja_buscar_pedido($pdo, (int) $pedido['id_pedido']);
    folivro_loja_registrar_pagamento($pdo, $pedido, 'criacao', $res['status'], $res['status_detalhe'], $res['valor_centavos'], $res['payload'], $res['id_externo']);
    folivro_loja_log('cobranca_criada', ['pedido' => $pedido['codigo_pedido'], 'gateway' => $pedido['gateway'], 'status' => $res['status'], 'detalhe' => $res['status_detalhe']]);
    folivro_loja_aplicar_status($pdo, (int) $pedido['id_pedido'], $res['status'], $res, 'criacao');
    return ['ok' => true, 'pedido' => folivro_loja_buscar_pedido($pdo, (int) $pedido['id_pedido'])];
}

/**
 * Máquina de estados central (idempotente). Usada por criação, consulta (polling) e webhook.
 * @return array{ok:bool,status:string,mudou:bool,mensagem?:string}
 */
function folivro_loja_aplicar_status(PDO $pdo, int $idPedido, string $statusRemoto, array $info, string $origem): array
{
    switch ($statusRemoto) {
        case 'pago':
            return folivro_loja_confirmar_pagamento($pdo, $idPedido, $origem, $info);
        case 'reembolsado':
            return folivro_loja_registrar_reembolso_local($pdo, $idPedido, $origem, $info);
        case 'falhou':
        case 'cancelado':
            $detalhe = (string) ($info['status_detalhe'] ?? '');
            $motivo = $statusRemoto === 'falhou'
                ? 'Pagamento recusado' . ($detalhe !== '' ? ' (' . $detalhe . ')' : '')
                : ($detalhe === 'expired' ? 'Pagamento expirado (Pix não pago no prazo)' : 'Pagamento cancelado no gateway');
            $st = $pdo->prepare('UPDATE loja_pedidos SET status_pedido = ?, motivo_status = ? WHERE id_pedido = ? AND status_pedido = \'pendente\'');
            $st->execute([$statusRemoto, mb_substr($motivo, 0, 255, 'UTF-8'), $idPedido]);
            $mudou = $st->rowCount() === 1;
            if ($mudou) {
                $ped = folivro_loja_buscar_pedido($pdo, $idPedido);
                folivro_loja_registrar_pagamento($pdo, $ped, $detalhe === 'expired' ? 'expiracao' : $origem, $statusRemoto, $detalhe, null, ['origem' => $origem, 'status_detail' => $detalhe]);
                folivro_loja_log('pedido_' . $statusRemoto, ['pedido' => $ped['codigo_pedido'], 'origem' => $origem, 'detalhe' => $detalhe]);
            }
            return ['ok' => true, 'status' => $statusRemoto, 'mudou' => $mudou];
        default:
            return ['ok' => true, 'status' => 'pendente', 'mudou' => false];
    }
}

/**
 * Pagamento aprovado: pedido 'pago' + crédito no ledger do autor + obra na biblioteca do leitor,
 * tudo numa única transação. Reprocessar o mesmo evento não duplica nada.
 */
function folivro_loja_confirmar_pagamento(PDO $pdo, int $idPedido, string $origem, array $info = []): array
{
    $duplicado = false;
    try {
        $r = folivro_loja_tx($pdo, static function (PDO $pdo) use ($idPedido, $origem, $info, &$duplicado): array {
            $st = $pdo->prepare('SELECT * FROM loja_pedidos WHERE id_pedido = ? FOR UPDATE');
            $st->execute([$idPedido]);
            $p = $st->fetch(PDO::FETCH_ASSOC);
            if (!$p) {
                return ['ok' => false, 'status' => 'inexistente', 'mudou' => false, 'mensagem' => 'Pedido não encontrado.'];
            }
            if ($p['status_pedido'] === 'pago') {
                return ['ok' => true, 'status' => 'pago', 'mudou' => false]; // idempotência
            }
            if ($p['status_pedido'] === 'reembolsado') {
                return ['ok' => false, 'status' => 'reembolsado', 'mudou' => false, 'mensagem' => 'Pedido já reembolsado.'];
            }
            // Confere o valor efetivamente pago com o valor do pedido gravado no servidor.
            if (array_key_exists('valor_centavos', $info) && $info['valor_centavos'] !== null && (int) $info['valor_centavos'] !== (int) $p['valor_bruto_centavos']) {
                $pdo->prepare("UPDATE loja_pedidos SET status_pedido = 'falhou', motivo_status = 'Valor pago divergente do valor do pedido' WHERE id_pedido = ?")
                    ->execute([$idPedido]);
                folivro_loja_registrar_pagamento($pdo, $p, $origem, 'divergente', 'amount_mismatch', (int) $info['valor_centavos'], ['alerta' => 'valor divergente']);
                folivro_loja_log('pagamento_valor_divergente', ['pedido' => $p['codigo_pedido'], 'esperado' => (int) $p['valor_bruto_centavos'], 'recebido' => (int) $info['valor_centavos']]);
                return ['ok' => false, 'status' => 'falhou', 'mudou' => true, 'mensagem' => 'Valor divergente.'];
            }
            $agora = date('Y-m-d H:i:s');
            $pdo->prepare("UPDATE loja_pedidos SET status_pedido = 'pago', pago_em = ?, motivo_status = NULL WHERE id_pedido = ?")
                ->execute([$agora, $idPedido]); // UNIQUE uq_loja_pedido_pago_unico bloqueia a 2ª compra paga

            if ((int) $p['valor_liquido_centavos'] > 0) {
                $pdo->prepare(
                    "INSERT INTO loja_ledger_autor (id_autor, id_pedido, tipo_lancamento, valor_centavos, descricao)
                     VALUES (?, ?, 'credito_venda', ?, ?)"
                )->execute([(int) $p['id_vendedor'], $idPedido, (int) $p['valor_liquido_centavos'], 'Venda ' . $p['codigo_pedido'] . ' — ' . mb_substr((string) $p['titulo_produto_snapshot'], 0, 150, 'UTF-8')]);
            }

            $idLivro = (int) ($pdo->query('SELECT COALESCE(id_livro, 0) FROM loja_produtos WHERE id_produto = ' . (int) $p['id_produto'])->fetchColumn());
            if ($idLivro === 0) {
                $idLivro = (int) folivro_loja_sincronizar_livro($pdo, (int) $p['id_produto']);
            }
            if ($idLivro > 0) {
                // Integração com a biblioteca pessoal existente.
                $pdo->prepare("INSERT IGNORE INTO livraria (id_usuario, id_livro, status_livraria) VALUES (?, ?, 'quero_ler')")
                    ->execute([(int) $p['id_comprador'], $idLivro]);
                $pdo->prepare('UPDATE resenha SET compra_verificada_resenha = 1 WHERE id_usuario = ? AND id_livro = ?')
                    ->execute([(int) $p['id_comprador'], $idLivro]);
            }
            $idAdlSync = (int) ($pdo->query('SELECT COALESCE(id_autor_dos_livros, 0) FROM loja_produtos WHERE id_produto = ' . (int) $p['id_produto'])->fetchColumn());
            if ($idAdlSync > 0) {
                $pdo->prepare('INSERT IGNORE INTO compra (id_usuario, id_autor_dos_livros, valor_pago_compra, percentual_autor_compra, ingresso_autor_compra, percentual_plataforma_compra, status_compra) VALUES (?, ?, ?, ?, ?, ?, \'concluida\')')
                    ->execute([(int) $p['id_comprador'], $idAdlSync, round(((int) $p['valor_bruto_centavos']) / 100, 2), round(100 - (float) $p['taxa_percentual'], 2), round(((int) $p['valor_liquido_centavos']) / 100, 2), (float) $p['taxa_percentual']]);
                $idCompraLegado = (int) $pdo->lastInsertId();
                if ($idCompraLegado > 0) {
                    $pdo->prepare('INSERT IGNORE INTO livraria_digital (id_usuario, id_autor_dos_livros, id_compra) VALUES (?, ?, ?)')
                        ->execute([(int) $p['id_comprador'], $idAdlSync, $idCompraLegado]);
                }
            }
            folivro_loja_registrar_pagamento($pdo, $p, $origem, 'pago', (string) ($info['status_detalhe'] ?? 'approved'), isset($info['valor_centavos']) ? (int) $info['valor_centavos'] : (int) $p['valor_bruto_centavos'], ['origem' => $origem, 'status_detail' => $info['status_detalhe'] ?? null]);
            folivro_loja_log('pedido_pago', ['pedido' => $p['codigo_pedido'], 'origem' => $origem, 'bruto' => (int) $p['valor_bruto_centavos'], 'liquido' => (int) $p['valor_liquido_centavos']]);
            return ['ok' => true, 'status' => 'pago', 'mudou' => true];
        });
        return $r;
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            throw $e;
        }
        $duplicado = true;
    }
    if ($duplicado) {
        // Leitor já possuía a obra por outro pedido pago: este pagamento precisa ser devolvido.
        $p = folivro_loja_buscar_pedido($pdo, $idPedido);
        $pdo->prepare("UPDATE loja_pedidos SET status_pedido = 'cancelado', motivo_status = 'Compra duplicada: obra já adquirida — pagamento devolvido' WHERE id_pedido = ?")
            ->execute([$idPedido]);
        folivro_loja_log('pedido_duplicado', ['pedido' => $p['codigo_pedido'] ?? null]);
        if ($p && $p['id_externo_gateway'] && $p['gateway'] !== 'nenhum') {
            try {
                folivro_loja_gateway($pdo, (string) $p['gateway'])->reembolsar((string) $p['id_externo_gateway']);
            } catch (Throwable $e) {
                folivro_loja_log('pedido_duplicado_reembolso_erro', ['pedido' => $p['codigo_pedido'], 'erro' => $e->getMessage()]);
            }
        }
    }
    return ['ok' => false, 'status' => 'cancelado', 'mudou' => true, 'mensagem' => 'Compra duplicada bloqueada.'];
}

/** Reembolso refletido localmente: status, estorno no ledger e remoção do acesso — numa transação. */
function folivro_loja_registrar_reembolso_local(PDO $pdo, int $idPedido, string $origem, array $info = []): array
{
    return folivro_loja_tx($pdo, static function (PDO $pdo) use ($idPedido, $origem, $info): array {
        $st = $pdo->prepare('SELECT * FROM loja_pedidos WHERE id_pedido = ? FOR UPDATE');
        $st->execute([$idPedido]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            return ['ok' => false, 'status' => 'inexistente', 'mudou' => false];
        }
        if ($p['status_pedido'] === 'reembolsado') {
            return ['ok' => true, 'status' => 'reembolsado', 'mudou' => false]; // idempotência
        }
        if ($p['status_pedido'] !== 'pago') {
            return ['ok' => false, 'status' => $p['status_pedido'], 'mudou' => false, 'mensagem' => 'Somente pedidos pagos podem ser reembolsados.'];
        }
        $motivo = mb_substr((string) ($info['motivo'] ?? 'Reembolso confirmado pelo gateway'), 0, 255, 'UTF-8');
        $pdo->prepare("UPDATE loja_pedidos SET status_pedido = 'reembolsado', reembolsado_em = ?, motivo_status = ? WHERE id_pedido = ?")
            ->execute([date('Y-m-d H:i:s'), $motivo, $idPedido]);
        if ((int) $p['valor_liquido_centavos'] > 0) {
            $pdo->prepare(
                "INSERT INTO loja_ledger_autor (id_autor, id_pedido, tipo_lancamento, valor_centavos, descricao)
                 VALUES (?, ?, 'estorno_venda', ?, ?)"
            )->execute([(int) $p['id_vendedor'], $idPedido, -1 * (int) $p['valor_liquido_centavos'], 'Estorno ' . $p['codigo_pedido'] . ' — ' . $motivo]);
        }
        $idLivro = (int) ($pdo->query('SELECT COALESCE(id_livro, 0) FROM loja_produtos WHERE id_produto = ' . (int) $p['id_produto'])->fetchColumn());
        if ($idLivro > 0) {
            // Remove o acesso: sai da biblioteca e perde o selo de compra verificada.
            $pdo->prepare('DELETE FROM livraria WHERE id_usuario = ? AND id_livro = ?')->execute([(int) $p['id_comprador'], $idLivro]);
            $pdo->prepare('UPDATE resenha SET compra_verificada_resenha = 0 WHERE id_usuario = ? AND id_livro = ?')->execute([(int) $p['id_comprador'], $idLivro]);
        }
        $idAdlSync = (int) ($pdo->query('SELECT COALESCE(id_autor_dos_livros, 0) FROM loja_produtos WHERE id_produto = ' . (int) $p['id_produto'])->fetchColumn());
        if ($idAdlSync > 0) {
            $pdo->prepare('DELETE FROM livraria_digital WHERE id_usuario = ? AND id_autor_dos_livros = ?')
                ->execute([(int) $p['id_comprador'], $idAdlSync]);
        }
        folivro_loja_registrar_pagamento($pdo, $p, $origem, 'reembolsado', (string) ($info['status_detalhe'] ?? 'refunded'), (int) $p['valor_bruto_centavos'], ['origem' => $origem, 'motivo' => $motivo]);
        folivro_loja_log('pedido_reembolsado', ['pedido' => $p['codigo_pedido'], 'origem' => $origem, 'estorno_liquido' => (int) $p['valor_liquido_centavos']]);
        return ['ok' => true, 'status' => 'reembolsado', 'mudou' => true];
    });
}

/** Reembolso solicitado pelo autor-vendedor (ou admin). Autorização no servidor. */
function folivro_loja_reembolsar(PDO $pdo, int $idPedido, int $uidSolicitante, string $motivo): array
{
    $u = folivro_loja_usuario($pdo, $uidSolicitante);
    $p = folivro_loja_buscar_pedido($pdo, $idPedido);
    $autorizado = $p !== null && $u !== null && $u['status_usuario'] === 'ativo'
        && ((int) $p['id_vendedor'] === $uidSolicitante || $u['nivel_usuario'] === 'admin');
    if (!$autorizado) {
        return ['ok' => false, 'erro' => 'Pedido não encontrado.'];
    }
    if ($p['status_pedido'] !== 'pago') {
        return ['ok' => false, 'erro' => 'Somente pedidos pagos podem ser reembolsados.'];
    }
    $motivo = trim($motivo) !== '' ? mb_substr(trim($motivo), 0, 200, 'UTF-8') : 'Reembolso solicitado pelo autor';
    if ($p['gateway'] !== 'nenhum' && $p['id_externo_gateway']) {
        try {
            $res = folivro_loja_gateway($pdo, (string) $p['gateway'])->reembolsar((string) $p['id_externo_gateway']);
        } catch (PaymentGatewayException $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
        if (!$res['ok']) {
            folivro_loja_log('reembolso_recusado_gateway', ['pedido' => $p['codigo_pedido'], 'detalhe' => $res['status_detalhe']]);
            return ['ok' => false, 'erro' => 'O gateway não confirmou o reembolso. Tente novamente mais tarde.'];
        }
    }
    $r = folivro_loja_registrar_reembolso_local($pdo, $idPedido, 'reembolso', ['motivo' => $motivo, 'status_detalhe' => 'refunded']);
    return $r['ok'] ? ['ok' => true] : ['ok' => false, 'erro' => $r['mensagem'] ?? 'Não foi possível reembolsar.'];
}

/** O comprador cancela um pedido ainda pendente (somente o próprio). */
function folivro_loja_cancelar_pendente(PDO $pdo, string $codigo, int $uid): bool
{
    $p = folivro_loja_pedido_do_comprador($pdo, $codigo, $uid);
    if ($p === null || $p['status_pedido'] !== 'pendente') {
        return false;
    }
    $st = $pdo->prepare("UPDATE loja_pedidos SET status_pedido = 'cancelado', motivo_status = 'Cancelado pelo comprador' WHERE id_pedido = ? AND status_pedido = 'pendente'");
    $st->execute([(int) $p['id_pedido']]);
    if ($st->rowCount() === 1) {
        folivro_loja_registrar_pagamento($pdo, $p, 'consulta', 'cancelado', 'cancelled_by_buyer', null, ['origem' => 'comprador']);
        return true;
    }
    return false;
}

/**
 * Confirma o status consultando o gateway (fonte de verdade) e aplica a transição.
 * Usado pelo polling da tela de status e pelo webhook.
 */
function folivro_loja_sincronizar_com_gateway(PDO $pdo, array $pedido, bool $forcar = false): array
{
    if ($pedido['gateway'] === 'nenhum' || !in_array($pedido['status_pedido'], ['pendente', 'pago'], true)) {
        return $pedido;
    }
    if ($pedido['id_externo_gateway'] === null) {
        return $pedido;
    }
    if (!$forcar && $pedido['ultima_consulta_em'] !== null && strtotime((string) $pedido['ultima_consulta_em']) > time() - 4) {
        return $pedido; // limita consultas ao gateway durante o polling
    }
    if ($pedido['status_pedido'] === 'pago' && !$forcar) {
        return $pedido;
    }
    $pdo->prepare('UPDATE loja_pedidos SET ultima_consulta_em = ? WHERE id_pedido = ?')->execute([date('Y-m-d H:i:s'), (int) $pedido['id_pedido']]);
    try {
        $res = folivro_loja_gateway($pdo, (string) $pedido['gateway'])->consultarStatus((string) $pedido['id_externo_gateway']);
    } catch (PaymentGatewayException $e) {
        folivro_loja_log('consulta_erro', ['pedido' => $pedido['codigo_pedido'], 'erro' => $e->getMessage()]);
        return $pedido;
    }
    if ($res['referencia'] !== null && $res['referencia'] !== $pedido['codigo_pedido']) {
        folivro_loja_log('consulta_referencia_divergente', ['pedido' => $pedido['codigo_pedido']]);
        return $pedido;
    }
    folivro_loja_aplicar_status($pdo, (int) $pedido['id_pedido'], $res['status'], $res, 'consulta');

    // Modo real: Pix pendente muito além do vencimento é encerrado localmente.
    $atual = folivro_loja_buscar_pedido($pdo, (int) $pedido['id_pedido']);
    if ($atual['status_pedido'] === 'pendente' && $atual['expira_em'] !== null && strtotime((string) $atual['expira_em']) < time() - 600) {
        folivro_loja_aplicar_status($pdo, (int) $atual['id_pedido'], 'cancelado', ['status_detalhe' => 'expired'], 'expiracao');
        $atual = folivro_loja_buscar_pedido($pdo, (int) $pedido['id_pedido']);
    }
    return $atual;
}

/** Simulação: o leitor "paga" o Pix fictício e a loja confirma consultando o gateway simulado. */
function folivro_loja_simular_pagamento_pix(PDO $pdo, string $codigo, int $uid): array
{
    $p = folivro_loja_pedido_do_comprador($pdo, $codigo, $uid);
    if ($p === null || $p['gateway'] !== 'simulado' || $p['metodo_pagamento'] !== 'pix' || folivro_loja_modo() !== 'simulado') {
        return ['ok' => false, 'erro' => 'Pedido não encontrado.'];
    }
    if ($p['status_pedido'] !== 'pendente') {
        return ['ok' => $p['status_pedido'] === 'pago', 'erro' => 'Este pedido não está aguardando pagamento.'];
    }
    $gw = folivro_loja_gateway($pdo, 'simulado');
    if (!$gw instanceof SimulatedGateway) {
        return ['ok' => false, 'erro' => 'Gateway inválido.'];
    }
    $pagou = $gw->simularPagamentoPix((string) $p['id_externo_gateway']);
    $p = folivro_loja_sincronizar_com_gateway($pdo, folivro_loja_buscar_pedido($pdo, (int) $p['id_pedido']), true);
    if (!$pagou && $p['status_pedido'] !== 'pago') {
        return ['ok' => false, 'erro' => 'O Pix simulado expirou ou não pôde ser confirmado. Gere um novo pedido.'];
    }
    return ['ok' => $p['status_pedido'] === 'pago'];
}

// ---------------------------------------------------------------------
// Webhook (endpoint público) — assinatura, idempotência e confirmação na API
// ---------------------------------------------------------------------

/**
 * @param array<string,string> $headers nomes em minúsculas
 * @param array<string,string> $query   query string com nomes preservados (ex.: 'data.id')
 * @return array{http:int,resultado:string}
 */
function folivro_loja_processar_webhook(PDO $pdo, string $nomeGateway, array $headers, string $body, array $query, string $ip = 'cli'): array
{
    if (!in_array($nomeGateway, ['mercadopago', 'simulado'], true)) {
        return ['http' => 404, 'resultado' => 'gateway_desconhecido'];
    }
    if ($nomeGateway === 'simulado' && folivro_loja_modo() !== 'simulado') {
        return ['http' => 404, 'resultado' => 'gateway_desabilitado'];
    }
    if (!folivro_loja_rate_limit($pdo, 'webhook:' . $ip, 120, 60)) {
        folivro_loja_log('webhook_rate_limit', ['gateway' => $nomeGateway, 'ip_hash' => substr(hash('sha256', $ip), 0, 12)]);
        return ['http' => 429, 'resultado' => 'rate_limit'];
    }
    if (strlen($body) > 65536) {
        return ['http' => 413, 'resultado' => 'payload_grande'];
    }
    try {
        $gw = folivro_loja_gateway($pdo, $nomeGateway);
    } catch (PaymentGatewayException $e) {
        return ['http' => 503, 'resultado' => 'gateway_indisponivel'];
    }
    if (!$gw->validarWebhook($headers, $body, $query)) {
        folivro_loja_log('webhook_assinatura_invalida', ['gateway' => $nomeGateway, 'ip_hash' => substr(hash('sha256', $ip), 0, 12)]);
        return ['http' => 401, 'resultado' => 'assinatura_invalida'];
    }
    $idRecurso = $gw->idRecursoWebhook($headers, $body, $query);
    if ($idRecurso === null) {
        return ['http' => 200, 'resultado' => 'ignorado_tipo'];
    }

    $json = json_decode($body, true);
    $acao = is_array($json) ? (string) ($json['action'] ?? '') : '';
    $requestId = (string) ($headers['x-request-id'] ?? '');
    $chave = hash('sha256', $nomeGateway . '|' . ($requestId !== '' ? $requestId : hash('sha256', $body)) . '|' . $idRecurso . '|' . $acao);

    $pdo->prepare(
        'INSERT INTO loja_webhook_eventos (gateway, chave_idempotencia, id_recurso, tipo_evento, assinatura_valida, payload_bruto)
         VALUES (?, ?, ?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE tentativas = tentativas + 1'
    )->execute([$nomeGateway, $chave, $idRecurso, mb_substr($acao !== '' ? $acao : 'payment', 0, 60), mb_substr($body, 0, 16000, 'UTF-8')]);
    $ev = $pdo->prepare('SELECT id_evento, status_processamento FROM loja_webhook_eventos WHERE gateway = ? AND chave_idempotencia = ?');
    $ev->execute([$nomeGateway, $chave]);
    $evento = $ev->fetch(PDO::FETCH_ASSOC);
    if (in_array($evento['status_processamento'], ['processado', 'ignorado'], true)) {
        return ['http' => 200, 'resultado' => 'duplicado'];
    }
    $finalizar = static function (string $status, string $resultado) use ($pdo, $evento): void {
        $pdo->prepare('UPDATE loja_webhook_eventos SET status_processamento = ?, resultado = ?, processado_em = ? WHERE id_evento = ?')
            ->execute([$status, mb_substr($resultado, 0, 255), date('Y-m-d H:i:s'), (int) $evento['id_evento']]);
    };

    // Não confiamos no corpo recebido: o status é confirmado na API do gateway.
    try {
        $res = $gw->consultarStatus($idRecurso);
    } catch (PaymentGatewayException $e) {
        $finalizar('erro', 'consulta_falhou');
        folivro_loja_log('webhook_consulta_erro', ['gateway' => $nomeGateway, 'recurso' => $idRecurso]);
        return ['http' => 500, 'resultado' => 'consulta_falhou']; // o gateway reenvia depois
    }

    $st = $pdo->prepare('SELECT * FROM loja_pedidos WHERE gateway = ? AND id_externo_gateway = ? LIMIT 1');
    $st->execute([$nomeGateway, $res['id_externo']]);
    $pedido = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pedido && $res['referencia'] !== null && folivro_loja_codigo_valido((string) $res['referencia'])) {
        $st = $pdo->prepare('SELECT * FROM loja_pedidos WHERE gateway = ? AND codigo_pedido = ? AND (id_externo_gateway IS NULL OR id_externo_gateway = ?) LIMIT 1');
        $st->execute([$nomeGateway, $res['referencia'], $res['id_externo']]);
        $pedido = $st->fetch(PDO::FETCH_ASSOC);
        if ($pedido && $pedido['id_externo_gateway'] === null) {
            $pdo->prepare('UPDATE loja_pedidos SET id_externo_gateway = ? WHERE id_pedido = ?')->execute([$res['id_externo'], (int) $pedido['id_pedido']]);
            $pedido['id_externo_gateway'] = $res['id_externo'];
        }
    }
    if (!$pedido) {
        $finalizar('ignorado', 'pedido_nao_encontrado');
        folivro_loja_log('webhook_sem_pedido', ['gateway' => $nomeGateway, 'recurso' => $idRecurso]);
        return ['http' => 200, 'resultado' => 'pedido_nao_encontrado'];
    }
    if ($res['referencia'] !== null && $res['referencia'] !== $pedido['codigo_pedido']) {
        $finalizar('ignorado', 'referencia_divergente');
        return ['http' => 200, 'resultado' => 'referencia_divergente'];
    }

    folivro_loja_registrar_pagamento($pdo, $pedido, 'webhook', $res['status'], $res['status_detalhe'], $res['valor_centavos'], $body, $res['id_externo']);
    $r = folivro_loja_aplicar_status($pdo, (int) $pedido['id_pedido'], $res['status'], $res, 'webhook');
    $finalizar('processado', $res['status'] . ($r['mudou'] ? ':alterado' : ':sem_alteracao'));
    folivro_loja_log('webhook_processado', ['gateway' => $nomeGateway, 'pedido' => $pedido['codigo_pedido'], 'status' => $res['status'], 'mudou' => $r['mudou']]);
    return ['http' => 200, 'resultado' => 'processado'];
}

// ---------------------------------------------------------------------
// Entrega protegida de arquivos
// ---------------------------------------------------------------------

/** Retorna o produto se o usuário pode baixar o arquivo (comprador com pedido pago ou o próprio autor). */
function folivro_loja_autorizar_download(PDO $pdo, int $uid, int $idProduto): ?array
{
    if ($uid <= 0 || $idProduto <= 0) {
        return null;
    }
    $p = folivro_loja_produto($pdo, $idProduto);
    if ($p === null || empty($p['arquivo_produto'])) {
        return null;
    }
    if ((int) $p['id_autor'] === $uid || folivro_loja_ja_possui($pdo, $uid, $idProduto)) {
        return $p;
    }
    return null;
}

function folivro_loja_caminho_arquivo(array $produto): ?string
{
    $nome = basename((string) $produto['arquivo_produto']);
    if (!preg_match('/^obra_\d+_[a-f0-9]{16,32}\.(pdf|epub)$/', $nome)) {
        return null;
    }
    $caminho = folivro_loja_dir_storage('obras') . '/' . $nome;
    return is_file($caminho) ? $caminho : null;
}

// ---------------------------------------------------------------------
// Avaliações com "compra verificada" (tabela resenha existente)
// ---------------------------------------------------------------------

function folivro_loja_salvar_resenha(PDO $pdo, int $uid, int $idProduto, int $nota, string $titulo, string $texto): array
{
    $p = folivro_loja_produto($pdo, $idProduto);
    if ($p === null || !folivro_loja_ja_possui($pdo, $uid, $idProduto) || empty($p['id_livro'])) {
        return ['ok' => false, 'erro' => 'Somente leitores que compraram a obra podem avaliá-la aqui.'];
    }
    $titulo = trim($titulo);
    $texto = trim($texto);
    if ($nota < 1 || $nota > 5) {
        return ['ok' => false, 'erro' => 'Selecione uma nota de 1 a 5 estrelas.'];
    }
    if ($texto === '' || mb_strlen($texto, 'UTF-8') > 5000 || mb_strlen($titulo, 'UTF-8') > 150) {
        return ['ok' => false, 'erro' => 'Escreva sua resenha (até 5.000 caracteres) e um título de até 150 caracteres.'];
    }
    $pdo->prepare(
        'INSERT INTO resenha (id_usuario, id_livro, nota_resenha, titulo_resenha, resenha_resenha, compra_verificada_resenha)
         VALUES (?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE nota_resenha = VALUES(nota_resenha), titulo_resenha = VALUES(titulo_resenha),
                                 resenha_resenha = VALUES(resenha_resenha), compra_verificada_resenha = 1,
                                 data_avaliacao_resenha = CURRENT_TIMESTAMP'
    )->execute([$uid, (int) $p['id_livro'], $nota, $titulo !== '' ? $titulo : null, $texto]);
    return ['ok' => true];
}

/** Mantém o selo coerente quando a resenha é escrita fora da loja (ex.: leitor.php). */
function folivro_loja_sincronizar_resenha_verificada(PDO $pdo, int $uid, int $idLivro): void
{
    $pdo->prepare(
        "UPDATE resenha r
            SET r.compra_verificada_resenha = EXISTS (
                SELECT 1 FROM loja_pedidos o JOIN loja_produtos p ON p.id_produto = o.id_produto
                 WHERE o.id_comprador = r.id_usuario AND p.id_livro = r.id_livro AND o.status_pedido = 'pago')
          WHERE r.id_usuario = ? AND r.id_livro = ?"
    )->execute([$uid, $idLivro]);
}

function folivro_loja_resenhas(PDO $pdo, int $idLivro): array
{
    $st = $pdo->prepare(
        'SELECT r.id_usuario, r.nota_resenha, r.titulo_resenha, r.resenha_resenha, r.data_avaliacao_resenha, r.compra_verificada_resenha, u.nome_usuario
           FROM resenha r JOIN usuario u ON u.id_usuario = r.id_usuario
          WHERE r.id_livro = ?
       ORDER BY r.compra_verificada_resenha DESC, r.data_avaliacao_resenha DESC'
    );
    $st->execute([$idLivro]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------------------------------------------------------------
// Saldo, ledger e repasses do autor
// ---------------------------------------------------------------------

function folivro_loja_saldo_autor(PDO $pdo, int $uid): array
{
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE WHEN tipo_lancamento = 'credito_venda' THEN valor_centavos END), 0) AS creditos,
                COALESCE(SUM(CASE WHEN tipo_lancamento = 'estorno_venda' THEN valor_centavos END), 0) AS estornos,
                COALESCE(SUM(CASE WHEN tipo_lancamento IN ('repasse','repasse_cancelado') THEN valor_centavos END), 0) AS repasses,
                COALESCE(SUM(valor_centavos), 0) AS disponivel
           FROM loja_ledger_autor WHERE id_autor = ?"
    );
    $st->execute([$uid]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    return array_map('intval', $r + ['creditos' => 0, 'estornos' => 0, 'repasses' => 0, 'disponivel' => 0]);
}

/**
 * Saque do saldo. Serializa por autor (SELECT ... FOR UPDATE) para impedir sacar duas vezes o mesmo saldo.
 * No modo simulado o repasse é marcado como pago na hora (sem transferência real).
 */
function folivro_loja_solicitar_repasse(PDO $pdo, int $uid, int $valorCentavos): array
{
    if (!folivro_loja_eh_autor($pdo, $uid)) {
        return ['ok' => false, 'erro' => 'Apenas autores possuem saldo de vendas.'];
    }
    if ($valorCentavos < FOLIVRO_LOJA_REPASSE_MIN_CENTAVOS) {
        return ['ok' => false, 'erro' => 'O valor mínimo para repasse é ' . folivro_loja_formatar(FOLIVRO_LOJA_REPASSE_MIN_CENTAVOS) . '.'];
    }
    $modo = folivro_loja_modo();
    return folivro_loja_tx($pdo, static function (PDO $pdo) use ($uid, $valorCentavos, $modo): array {
        $pdo->prepare('SELECT id_usuario FROM usuario WHERE id_usuario = ? FOR UPDATE')->execute([$uid]);
        $saldo = folivro_loja_saldo_autor($pdo, $uid)['disponivel'];
        if ($valorCentavos > $saldo) {
            return ['ok' => false, 'erro' => 'Saldo insuficiente. Disponível: ' . folivro_loja_formatar($saldo) . '.'];
        }
        $simulado = $modo === 'simulado';
        $pdo->prepare(
            'INSERT INTO loja_repasses (id_autor, valor_centavos, status_repasse, modo_pagamento, observacao, processado_em) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $uid, $valorCentavos, $simulado ? 'pago' : 'solicitado', $modo,
            $simulado ? 'Repasse simulado (nenhuma transferência real foi feita).' : 'Aguardando transferência pela plataforma.',
            $simulado ? date('Y-m-d H:i:s') : null,
        ]);
        $idRepasse = (int) $pdo->lastInsertId();
        $pdo->prepare(
            "INSERT INTO loja_ledger_autor (id_autor, id_repasse, tipo_lancamento, valor_centavos, descricao) VALUES (?, ?, 'repasse', ?, ?)"
        )->execute([$uid, $idRepasse, -1 * $valorCentavos, 'Repasse #' . $idRepasse]);
        folivro_loja_log('repasse_solicitado', ['id_autor' => $uid, 'valor' => $valorCentavos, 'modo' => $modo]);
        return ['ok' => true, 'id' => $idRepasse];
    });
}

/** Indicadores do painel de vendas do autor. */
function folivro_loja_resumo_vendas(PDO $pdo, int $uid): array
{
    $st = $pdo->prepare(
        "SELECT COUNT(CASE WHEN status_pedido = 'pago' THEN 1 END) AS vendas,
                COALESCE(SUM(CASE WHEN status_pedido = 'pago' THEN valor_bruto_centavos END), 0) AS bruto,
                COALESCE(SUM(CASE WHEN status_pedido = 'pago' THEN valor_taxa_centavos END), 0) AS taxa,
                COALESCE(SUM(CASE WHEN status_pedido = 'pago' THEN valor_liquido_centavos END), 0) AS liquido,
                COUNT(CASE WHEN status_pedido = 'reembolsado' THEN 1 END) AS reembolsos,
                COALESCE(SUM(CASE WHEN status_pedido = 'reembolsado' THEN valor_bruto_centavos END), 0) AS valor_reembolsado,
                COUNT(CASE WHEN status_pedido = 'pendente' THEN 1 END) AS pendentes
           FROM loja_pedidos WHERE id_vendedor = ?"
    );
    $st->execute([$uid]);
    $tot = array_map('intval', $st->fetch(PDO::FETCH_ASSOC) ?: []);

    // Últimos 6 meses (inclui o atual), com meses sem venda zerados.
    $meses = [];
    for ($i = 5; $i >= 0; $i--) {
        $chave = date('Y-m', strtotime(date('Y-m-01') . " -{$i} months"));
        $meses[$chave] = ['bruto' => 0, 'liquido' => 0, 'vendas' => 0];
    }
    $m = $pdo->prepare(
        "SELECT DATE_FORMAT(pago_em, '%Y-%m') AS mes, SUM(valor_bruto_centavos) AS bruto, SUM(valor_liquido_centavos) AS liquido, COUNT(*) AS vendas
           FROM loja_pedidos
          WHERE id_vendedor = ? AND status_pedido = 'pago' AND pago_em >= ?
       GROUP BY mes"
    );
    $m->execute([$uid, array_key_first($meses) . '-01 00:00:00']);
    foreach ($m->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($meses[$row['mes']])) {
            $meses[$row['mes']] = ['bruto' => (int) $row['bruto'], 'liquido' => (int) $row['liquido'], 'vendas' => (int) $row['vendas']];
        }
    }

    $p = $pdo->prepare(
        "SELECT p.id_produto, p.titulo_produto, p.status_produto, p.preco_centavos, p.capa_produto, p.arquivo_tipo,
                COUNT(CASE WHEN o.status_pedido = 'pago' THEN 1 END) AS vendas,
                COALESCE(SUM(CASE WHEN o.status_pedido = 'pago' THEN o.valor_bruto_centavos END), 0) AS bruto,
                COALESCE(SUM(CASE WHEN o.status_pedido = 'pago' THEN o.valor_liquido_centavos END), 0) AS liquido,
                COUNT(CASE WHEN o.status_pedido = 'reembolsado' THEN 1 END) AS reembolsos
           FROM loja_produtos p
      LEFT JOIN loja_pedidos o ON o.id_produto = p.id_produto
          WHERE p.id_autor = ?
       GROUP BY p.id_produto
       ORDER BY p.data_criacao DESC"
    );
    $p->execute([$uid]);
    return ['totais' => $tot, 'meses' => $meses, 'produtos' => $p->fetchAll(PDO::FETCH_ASSOC)];
}

/**
 * Gera um arquivo PDF válido a partir do texto e capítulos de uma obra criada no estúdio (escrever.php).
 */
function folivro_loja_gerar_pdf_obra(string $titulo, string $autor, string $sinopse, array $capitulos): string
{
    $limpar = static function (string $s): string {
        $plano = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $ascii = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $plano) : $plano;
        $ascii = preg_replace('/[^\x20-\x7E\n]/', ' ', $ascii) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    };

    $linhas = [
        $limpar($titulo),
        'Autor: ' . $limpar($autor),
        str_repeat('-', 50),
    ];
    if ($sinopse !== '') {
        foreach (explode("\n", wordwrap($limpar($sinopse), 78, "\n", true)) as $ln) {
            $linhas[] = $ln;
        }
        $linhas[] = '';
    }
    foreach ($capitulos as $cap) {
        $linhas[] = '=== ' . $limpar((string) ($cap['titulo_capitulos'] ?? 'Capitulo')) . ' ===';
        $textoCap = $limpar((string) ($cap['conteudo_capitulos'] ?? ''));
        foreach (explode("\n", wordwrap($textoCap, 78, "\n", true)) as $ln) {
            $linhas[] = $ln;
        }
        $linhas[] = '';
    }

    $stream = "BT\n/F1 11 Tf\n54 740 Td\n15 TL\n";
    foreach (array_slice($linhas, 0, 44) as $ln) {
        $stream .= '(' . $ln . ") Tj T*\n";
    }
    $stream .= "ET\n";

    $objs = [
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n",
        "4 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream\nendobj\n",
        "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objs as $o) {
        $offsets[] = strlen($pdf);
        $pdf .= $o;
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objs); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    return $pdf . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
}

/**
 * Sincroniza uma obra do estúdio (autor_dos_livros + historias) com o catálogo da Loja (loja_produtos).
 * Obras aprovadas e à venda são publicadas automaticamente na loja com PDF gerado dos capítulos;
 * obras suspensas/despublicadas/removidas ficam fora da vitrine.
 */
function folivro_loja_sincronizar_obra_estudio(PDO $pdo, int $idAutorDosLivros): ?int
{
    $st = $pdo->prepare(
        "SELECT a.*, h.sinopse_historias, h.capa_historias, h.generos_historias,
                h.aviso_conteudo_historias, h.status_historias, u.nome_usuario AS autor_nome
           FROM autor_dos_livros a
           JOIN historias h ON h.id_historias = a.id_historias
           JOIN usuario u ON u.id_usuario = a.id_autor
          WHERE a.id_autor_dos_livros = ? LIMIT 1"
    );
    $st->execute([$idAutorDosLivros]);
    $obra = $st->fetch(PDO::FETCH_ASSOC);
    if (!$obra) {
        return null;
    }

    $elegivel = (
        $obra['status_autor_dos_livros'] === 'a_venda'
        && $obra['revisao_publicacao'] === 'aprovada'
        && $obra['status_historias'] === 'publicada'
        && (int) $obra['declaracao_autoria'] === 1
    );

    $stExist = $pdo->prepare('SELECT * FROM loja_produtos WHERE id_autor_dos_livros = ? LIMIT 1');
    $stExist->execute([$idAutorDosLivros]);
    $existente = $stExist->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$elegivel) {
        if ($existente) {
            $statusNovo = in_array($obra['status_autor_dos_livros'], ['removido', 'suspenso'], true) ? 'arquivado' : 'rascunho';
            $pdo->prepare('UPDATE loja_produtos SET status_produto = ? WHERE id_produto = ?')
                ->execute([$statusNovo, (int) $existente['id_produto']]);
            return (int) $existente['id_produto'];
        }
        return null;
    }

    $stCaps = $pdo->prepare(
        "SELECT titulo_capitulos, conteudo_capitulos FROM capitulos
          WHERE id_historias = ? AND status_capitulos = 'publicado'
       ORDER BY ordem_capitulos ASC"
    );
    $stCaps->execute([(int) $obra['id_historias']]);
    $capitulos = $stCaps->fetchAll(PDO::FETCH_ASSOC);

    $preview = '';
    if (!empty($capitulos[0]['conteudo_capitulos'])) {
        $preview = mb_strimwidth(
            trim(html_entity_decode(strip_tags((string) $capitulos[0]['conteudo_capitulos']), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            0,
            600,
            '...',
            'UTF-8'
        );
    }

    $precoCentavos = ($obra['modalidade_distribuicao'] ?? '') === 'gratuita'
        ? 0
        : max(0, (int) round(((float) $obra['preco_dos_livros_autor']) * 100));

    $pdfBin = folivro_loja_gerar_pdf_obra(
        (string) $obra['titulo_autor_dos_livros'],
        (string) $obra['autor_nome'],
        (string) ($obra['sinopse_historias'] ?? ''),
        $capitulos
    );
    $nomeArquivo = $existente && !empty($existente['arquivo_produto'])
        ? basename((string) $existente['arquivo_produto'])
        : ('obra_' . (int) $obra['id_autor'] . '_' . bin2hex(random_bytes(12)) . '.pdf');
    $caminhoPdf = folivro_loja_dir_storage('obras') . '/' . $nomeArquivo;
    file_put_contents($caminhoPdf, $pdfBin);
    $tamanho = (int) filesize($caminhoPdf);
    $sha = (string) hash_file('sha256', $caminhoPdf);
    $nomeOriginal = folivro_loja_nome_seguro((string) $obra['titulo_autor_dos_livros'], 'pdf');
    $classif = folivro_normalizar_classificacao((string) ($obra['aviso_conteudo_historias'] ?? 'livre'));

    if ($existente) {
        $idProd = (int) $existente['id_produto'];
        $pdo->prepare(
            "UPDATE loja_produtos
                SET titulo_produto = ?, descricao_produto = ?, preview_produto = ?,
                    capa_produto = COALESCE(?, capa_produto), generos_produto = ?,
                    classificacao_produto = ?, preco_centavos = ?,
                    arquivo_produto = ?, arquivo_nome_original = ?, arquivo_tipo = 'pdf',
                    arquivo_tamanho = ?, arquivo_sha256 = ?,
                    status_produto = 'publicado',
                    data_publicacao = COALESCE(data_publicacao, ?)
              WHERE id_produto = ?"
        )->execute([
            $obra['titulo_autor_dos_livros'],
            $obra['sinopse_historias'] ?: null,
            $preview ?: null,
            $obra['capa_historias'] ?: null,
            $obra['generos_historias'] ?: null,
            $classif,
            $precoCentavos,
            $nomeArquivo,
            $nomeOriginal,
            $tamanho,
            $sha,
            $obra['data_publicacao_autor'] ?: date('Y-m-d H:i:s'),
            $idProd,
        ]);
    } else {
        $pdo->prepare(
            "INSERT INTO loja_produtos
                (id_autor, id_autor_dos_livros, titulo_produto, descricao_produto, preview_produto,
                 capa_produto, generos_produto, classificacao_produto, preco_centavos,
                 arquivo_produto, arquivo_nome_original, arquivo_tipo, arquivo_tamanho, arquivo_sha256,
                 status_produto, aceite_termo_direitos, data_aceite_termo, versao_termo, data_publicacao)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pdf', ?, ?, 'publicado', 1, NOW(), ?, ?)"
        )->execute([
            (int) $obra['id_autor'],
            $idAutorDosLivros,
            $obra['titulo_autor_dos_livros'],
            $obra['sinopse_historias'] ?: null,
            $preview ?: null,
            $obra['capa_historias'] ?: null,
            $obra['generos_historias'] ?: null,
            $classif,
            $precoCentavos,
            $nomeArquivo,
            $nomeOriginal,
            $tamanho,
            $sha,
            FOLIVRO_LOJA_TERMO_VERSAO,
            $obra['data_publicacao_autor'] ?: date('Y-m-d H:i:s'),
        ]);
        $idProd = (int) $pdo->lastInsertId();
    }

    folivro_loja_sincronizar_livro($pdo, $idProd);
    return $idProd;
}
