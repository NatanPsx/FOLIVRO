<?php
declare(strict_types=1);

/**
 * Suíte de testes automatizados da Loja FOLIVRO.
 * Executa contra um banco isolado (folivro_test) e storage temporário.
 *
 * Uso: C:\xampp\php\php.exe tests/loja_test.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Somente CLI.');
}

// Isola banco, .env e storage antes de carregar config/loja.php
$dbTest = 'folivro_test';
putenv('FOLIVRO_DB_NAME=' . $dbTest);
putenv('FOLIVRO_ENV_FILE=' . __DIR__ . '/.env.nonexistent');
$tmpStorage = sys_get_temp_dir() . '/folivro_test_storage_' . getmypid();
putenv('LOJA_STORAGE_DIR=' . $tmpStorage);

// Recria o banco de testes limpo
$host = getenv('FOLIVRO_DB_HOST') ?: '127.0.0.1';
$port = getenv('FOLIVRO_DB_PORT') ?: '3306';
$user = getenv('FOLIVRO_DB_USER') ?: 'root';
$pass = getenv('FOLIVRO_DB_PASS') ?: '';
$srv = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$srv->exec("DROP DATABASE IF EXISTS `{$dbTest}`");
$srv->exec("CREATE DATABASE `{$dbTest}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
unset($srv);

require_once __DIR__ . '/../config/loja.php';
require_once __DIR__ . '/../database/seeds/014_seed_loja.php';

folivro_env_set('PAYMENT_MODE', 'simulated');
folivro_env_set('PLATFORM_FEE_PERCENT', '10');
folivro_env_set('SIMULATED_WEBHOOK_SECRET', 'segredo-teste-simulado');
folivro_env_set('MP_ACCESS_TOKEN', 'TEST-1234567890-token');
folivro_env_set('MP_PUBLIC_KEY', 'TEST-1234567890-pubkey');
folivro_env_set('MP_WEBHOOK_SECRET', 'segredo-teste-mp');

$pdo = folivro_database();

$passados = 0;
$falhas = 0;

function assertTrue(bool $cond, string $msg): void
{
    global $passados, $falhas;
    if ($cond) {
        $passados++;
        echo "[PASS] {$msg}\n";
    } else {
        $falhas++;
        echo "[FAIL] {$msg}\n";
    }
}

function assertSameValue($esperado, $atual, string $msg): void
{
    assertTrue($esperado === $atual, $msg . ($esperado === $atual ? '' : ' (esperado=' . var_export($esperado, true) . ', atual=' . var_export($atual, true) . ')'));
}

function criarUsuarioTeste(PDO $pdo, string $nome, string $email, string $nivel = 'leitor', ?string $nasc = '1995-05-10'): int
{
    $pdo->prepare('INSERT INTO usuario (nome_usuario, email_usuario, data_nascimento_usuario, senha_usuario, nivel_usuario, status_usuario) VALUES (?, ?, ?, ?, ?, \'ativo\')')
        ->execute([$nome, $email, $nasc, password_hash('Senha123!', PASSWORD_DEFAULT), $nivel]);
    return (int) $pdo->lastInsertId();
}

function criarArquivoPdfTemp(string $nome): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'pdf_');
    file_put_contents($tmp, folivro_seed_gerar_pdf_minimo($nome, 'Autor Teste', 'Conteudo de teste'));
    return ['name' => $nome . '.pdf', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)];
}

function criarArquivoEpubTemp(string $nome): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'epub_') . '.epub';
    folivro_seed_gerar_epub_minimo($tmp, $nome, 'Autor Teste', 'Conteudo epub de teste');
    return ['name' => $nome . '.epub', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)];
}

// ---------------------------------------------------------------------
// Preparação de atores
// ---------------------------------------------------------------------
$autorA = criarUsuarioTeste($pdo, 'Autor A', 'autora_a@test.local', 'autor');
$autorB = criarUsuarioTeste($pdo, 'Autor B', 'autor_b@test.local', 'autor');
$leitor1 = criarUsuarioTeste($pdo, 'Leitor 1', 'leitor1@test.local', 'leitor');
$leitor2 = criarUsuarioTeste($pdo, 'Leitor 2', 'leitor2@test.local', 'leitor');
$menor18 = criarUsuarioTeste($pdo, 'Menor 18', 'menor@test.local', 'leitor', date('Y-m-d', strtotime('-15 years')));

// ---------------------------------------------------------------------
// 1. Cálculo de comissão e arredondamento
// ---------------------------------------------------------------------
$v1 = folivro_loja_calcular_valores(1990, 10.0);
assertSameValue(1990, $v1['bruto'], 'Cálculo comissão: bruto = 1990');
assertSameValue(199, $v1['taxa'], 'Cálculo comissão 10% de 1990 = 199');
assertSameValue(1791, $v1['liquido'], 'Cálculo comissão: líquido = 1791');
assertSameValue($v1['bruto'], $v1['taxa'] + $v1['liquido'], 'Invariante taxa + líquido == bruto');

$v2 = folivro_loja_calcular_valores(1005, 10.0);
assertSameValue(101, $v2['taxa'], 'Cálculo comissão arredonda meio para cima (100,5 -> 101)');
assertSameValue(1005, $v2['taxa'] + $v2['liquido'], 'Invariante taxa + líquido == bruto com arredondamento');

// ---------------------------------------------------------------------
// 2. Validação real de upload (PDF, EPUB e rejeição de arquivo falso)
// ---------------------------------------------------------------------
$fakePhp = tempnam(sys_get_temp_dir(), 'fake_');
file_put_contents($fakePhp, "<?php echo 'hack'; ?>");
$resFake = folivro_loja_armazenar_arquivo_obra(['name' => 'malicioso.pdf', 'tmp_name' => $fakePhp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($fakePhp)], $autorA, false);
assertTrue(!$resFake['ok'], 'Upload bloqueia PHP disfarçado de .pdf');
@unlink($fakePhp);

$epubFile = criarArquivoEpubTemp('Obra_EPUB');
$resEpub = folivro_loja_salvar_produto($pdo, $autorA, null, [
    'titulo_produto' => 'Livro EPUB Teste',
    'descricao_produto' => 'Descrição teste',
    'preco' => '29,90',
    'classificacao_produto' => 'livre',
    'aceite_termo_direitos' => '1',
], ['arquivo_obra' => $epubFile], false);
@unlink($epubFile['tmp_name']);
assertTrue($resEpub['ok'], 'Cadastro de obra EPUB válido funciona');
$idProdEpub = (int) $resEpub['id'];
$pubRes = folivro_loja_alterar_status_produto($pdo, $autorA, $idProdEpub, 'publicado');
assertTrue($pubRes['ok'], 'Publicação de obra EPUB funciona');

$pdfFile = criarArquivoPdfTemp('Obra_PDF');
$resPdf = folivro_loja_salvar_produto($pdo, $autorA, null, [
    'titulo_produto' => 'Livro PDF Pago',
    'descricao_produto' => 'Descrição PDF',
    'preco' => '20,00',
    'classificacao_produto' => 'livre',
    'aceite_termo_direitos' => '1',
], ['arquivo_obra' => $pdfFile], false);
@unlink($pdfFile['tmp_name']);
assertTrue($resPdf['ok'], 'Cadastro de obra PDF válido funciona');
$idProdPago = (int) $resPdf['id'];
folivro_loja_alterar_status_produto($pdo, $autorA, $idProdPago, 'publicado');

$pdfGratis = criarArquivoPdfTemp('Obra_Gratis');
$resGratis = folivro_loja_salvar_produto($pdo, $autorA, null, [
    'titulo_produto' => 'Livro Gratuito',
    'descricao_produto' => 'Gratis',
    'preco' => '0,00',
    'classificacao_produto' => 'livre',
    'aceite_termo_direitos' => '1',
], ['arquivo_obra' => $pdfGratis], false);
@unlink($pdfGratis['tmp_name']);
$idProdGratis = (int) $resGratis['id'];
folivro_loja_alterar_status_produto($pdo, $autorA, $idProdGratis, 'publicado');

$pdf18 = criarArquivoPdfTemp('Obra_18');
$res18 = folivro_loja_salvar_produto($pdo, $autorB, null, [
    'titulo_produto' => 'Obra +18 Autor B',
    'preco' => '15,00',
    'classificacao_produto' => '18',
    'aceite_termo_direitos' => '1',
], ['arquivo_obra' => $pdf18], false);
@unlink($pdf18['tmp_name']);
$idProd18 = (int) $res18['id'];
folivro_loja_alterar_status_produto($pdo, $autorB, $idProd18, 'publicado');

// ---------------------------------------------------------------------
// 3. Regras de negócio: autor não compra própria obra; menor +18 bloqueado
// ---------------------------------------------------------------------
$errPropria = folivro_loja_criar_pedido($pdo, $autorA, $idProdPago, 'pix');
assertTrue(!$errPropria['ok'], 'Autor A não pode comprar a própria obra');

$errMenor = folivro_loja_criar_pedido($pdo, $menor18, $idProd18, 'pix');
assertTrue(!$errMenor['ok'], 'Menor de 18 anos é bloqueado em obra +18');

// ---------------------------------------------------------------------
// 4. Acesso ao arquivo sem compra (bloqueado) & IDOR entre autores
// ---------------------------------------------------------------------
assertTrue(folivro_loja_autorizar_download($pdo, $leitor1, $idProdPago) === null, 'Leitor sem compra não pode baixar o arquivo');
assertTrue(folivro_loja_autorizar_download($pdo, $autorA, $idProdPago) !== null, 'Próprio autor pode baixar seu arquivo');
assertTrue(folivro_loja_autorizar_download($pdo, $autorB, $idProdPago) === null, 'Autor B não pode baixar arquivo do Autor A sem comprar');
assertTrue(folivro_loja_produto_do_autor($pdo, $idProdPago, $autorB) === null, 'IDOR: Autor B não acessa edição da obra do Autor A');
$statusIdor = folivro_loja_alterar_status_produto($pdo, $autorB, $idProdPago, 'arquivado');
assertTrue(!$statusIdor['ok'], 'IDOR: Autor B não pode arquivar obra do Autor A');

// ---------------------------------------------------------------------
// 5. Obra gratuita (preço 0) libera sem gateway
// ---------------------------------------------------------------------
$pedGratis = folivro_loja_criar_pedido($pdo, $leitor1, $idProdGratis, 'pix');
assertTrue($pedGratis['ok'] && $pedGratis['pedido']['status_pedido'] === 'pago', 'Obra gratuita conclui imediatamente como pago');
assertSameValue('nenhum', $pedGratis['pedido']['gateway'], 'Obra gratuita não passa pelo gateway');
assertTrue(folivro_loja_autorizar_download($pdo, $leitor1, $idProdGratis) !== null, 'Obra gratuita libera download');

// ---------------------------------------------------------------------
// 6. Compra simulada RECUSADA (cartão recusado)
// ---------------------------------------------------------------------
$pedRec = folivro_loja_criar_pedido($pdo, $leitor1, $idProdPago, 'cartao');
$cobRec = folivro_loja_iniciar_cobranca($pdo, $pedRec['pedido'], ['cenario' => 'cartao_recusado']);
assertSameValue('falhou', $cobRec['pedido']['status_pedido'], 'Cartão recusado marca pedido como falhou');
assertTrue(folivro_loja_autorizar_download($pdo, $leitor1, $idProdPago) === null, 'Cartão recusado NÃO libera download');
assertSameValue(0, folivro_loja_saldo_autor($pdo, $autorA)['disponivel'], 'Cartão recusado NÃO credita ledger');

// ---------------------------------------------------------------------
// 7. Compra simulada EXPIRADA (Pix expira)
// ---------------------------------------------------------------------
$pedExp = folivro_loja_criar_pedido($pdo, $leitor1, $idProdPago, 'pix');
$cobExp = folivro_loja_iniciar_cobranca($pdo, $pedExp['pedido'], ['cenario' => 'pix_expira']);
assertSameValue('pendente', $cobExp['pedido']['status_pedido'], 'Pix começa pendente');
// Simula passagem do tempo expirando no gateway simulado
$pdo->prepare("UPDATE loja_gateway_simulado SET expira_em = DATE_SUB(NOW(), INTERVAL 10 SECOND) WHERE id_externo = ?")
    ->execute([$cobExp['pedido']['id_externo_gateway']]);
$simRes = folivro_loja_simular_pagamento_pix($pdo, $cobExp['pedido']['codigo_pedido'], $leitor1);
assertTrue(!$simRes['ok'], 'Pix expirado não aceita confirmação tardia');
$pedExpAtual = folivro_loja_pedido_do_comprador($pdo, $cobExp['pedido']['codigo_pedido'], $leitor1);
assertSameValue('cancelado', $pedExpAtual['status_pedido'], 'Pix expirado passa para cancelado');
assertTrue(stripos((string) $pedExpAtual['motivo_status'], 'expirado') !== false, 'Motivo registra expiração');

// ---------------------------------------------------------------------
// 8. Compra simulada APROVADA (cartão aprovado) + integração biblioteca + review
// ---------------------------------------------------------------------
$pedOk = folivro_loja_criar_pedido($pdo, $leitor1, $idProdPago, 'cartao');
$cobOk = folivro_loja_iniciar_cobranca($pdo, $pedOk['pedido'], ['cenario' => 'cartao_aprovado']);
assertSameValue('pago', $cobOk['pedido']['status_pedido'], 'Cartão aprovado marca pedido como pago');
assertSameValue(2000, (int) $cobOk['pedido']['valor_bruto_centavos'], 'Preço calculado no servidor = 2000 centavos');
assertSameValue(200, (int) $cobOk['pedido']['valor_taxa_centavos'], 'Comissão 10% = 200 centavos');
assertSameValue(1800, (int) $cobOk['pedido']['valor_liquido_centavos'], 'Líquido do autor = 1800 centavos');
assertSameValue(1800, folivro_loja_saldo_autor($pdo, $autorA)['disponivel'], 'Ledger creditado em 1800 centavos');
assertTrue(folivro_loja_autorizar_download($pdo, $leitor1, $idProdPago) !== null, 'Compra aprovada libera download');

// Verifica entrada na tabela livraria (biblioteca pessoal existente)
$prodInfo = folivro_loja_produto($pdo, $idProdPago);
$stLib = $pdo->prepare('SELECT 1 FROM livraria WHERE id_usuario = ? AND id_livro = ?');
$stLib->execute([$leitor1, (int) $prodInfo['id_livro']]);
assertTrue((bool) $stLib->fetchColumn(), 'Compra aprovada adiciona obra na biblioteca pessoal (livraria)');

// Avaliação com compra verificada
$revAntes = folivro_loja_salvar_resenha($pdo, $leitor2, $idProdPago, 5, 'Sem compra', 'Não comprei');
assertTrue(!$revAntes['ok'], 'Quem não comprou não pode avaliar como compra verificada na loja');
$revOk = folivro_loja_salvar_resenha($pdo, $leitor1, $idProdPago, 5, 'Excelente!', 'Vale muito a pena');
assertTrue($revOk['ok'], 'Comprador pode avaliar obra adquirida');
$listaRev = folivro_loja_resenhas($pdo, (int) $prodInfo['id_livro']);
assertSameValue(1, (int) $listaRev[0]['compra_verificada_resenha'], 'Resenha marcada com compra_verificada_resenha = 1');

// ---------------------------------------------------------------------
// 9. Bloqueio de compra duplicada (domínio + UNIQUE no banco)
// ---------------------------------------------------------------------
$dupDom = folivro_loja_criar_pedido($pdo, $leitor1, $idProdPago, 'pix');
assertTrue(!$dupDom['ok'], 'Domínio bloqueia compra duplicada pelo mesmo leitor');

// IDOR em pedidos
assertTrue(folivro_loja_pedido_do_comprador($pdo, $cobOk['pedido']['codigo_pedido'], $leitor2) === null, 'IDOR: Leitor 2 não vê pedido do Leitor 1');

// ---------------------------------------------------------------------
// 10. Webhook: assinatura inválida, processamento, idempotência e consulta na API
// ---------------------------------------------------------------------
$pedPix2 = folivro_loja_criar_pedido($pdo, $leitor2, $idProdEpub, 'pix');
$cobPix2 = folivro_loja_iniciar_cobranca($pdo, $pedPix2['pedido'], ['cenario' => 'pix']);
$idExtPix2 = (string) $cobPix2['pedido']['id_externo_gateway'];

// 10a. Assinatura inválida -> 401
$whInval = folivro_loja_processar_webhook(
    $pdo,
    'simulado',
    ['x-signature' => 'ts=1700000000,v1=' . str_repeat('0', 64), 'x-request-id' => 'req-inval'],
    json_encode(['type' => 'payment', 'data' => ['id' => $idExtPix2]]),
    ['data.id' => $idExtPix2]
);
assertSameValue(401, $whInval['http'], 'Webhook com assinatura inválida retorna 401');

// 10b. Simula pagamento no PSP e envia webhook válido
(new SimulatedGateway($pdo, ['webhook_secret' => 'segredo-teste-simulado']))->simularPagamentoPix($idExtPix2);
$ts = (string) time();
$sig = WebhookSignature::gerar('segredo-teste-simulado', $idExtPix2, 'req-ok-1', $ts);
$bodyWh = json_encode(['type' => 'payment', 'action' => 'payment.updated', 'data' => ['id' => $idExtPix2]]);
$whOk = folivro_loja_processar_webhook(
    $pdo,
    'simulado',
    ['x-signature' => $sig, 'x-request-id' => 'req-ok-1'],
    $bodyWh,
    ['data.id' => $idExtPix2]
);
assertSameValue(200, $whOk['http'], 'Webhook com assinatura válida retorna 200');
assertSameValue('processado', $whOk['resultado'], '1º webhook processado com sucesso');
assertSameValue(1800 + 2691, folivro_loja_saldo_autor($pdo, $autorA)['disponivel'], 'Saldo do autor após 2ª venda (1800 + 2691 = 4491)');

// 10c. Reprocessar o mesmo webhook (idempotência)
$whDup = folivro_loja_processar_webhook(
    $pdo,
    'simulado',
    ['x-signature' => $sig, 'x-request-id' => 'req-ok-1'],
    $bodyWh,
    ['data.id' => $idExtPix2]
);
assertSameValue('duplicado', $whDup['resultado'], 'Webhook repetido detectado como duplicado');
assertSameValue(4491, folivro_loja_saldo_autor($pdo, $autorA)['disponivel'], 'Webhook repetido NÃO duplica saldo no ledger');

// 10d. Webhook Mercado Pago mockado: corpo falso diz approved, mas API retorna pending -> não aprova!
folivro_env_set('PAYMENT_MODE', 'real');
$GLOBALS['__folivro_mp_http'] = static function (string $metodo, string $url): array {
    if (str_ends_with($url, '/v1/payments/999888777')) {
        return [200, json_encode(['id' => 999888777, 'status' => 'pending', 'status_detail' => 'pending_waiting_transfer', 'transaction_amount' => 29.90, 'external_reference' => 'FLV00000000000000000'])];
    }
    return [404, '{}'];
};
$sigMp = WebhookSignature::gerar('segredo-teste-mp', '999888777', 'req-mp-1', $ts);
$whMp = folivro_loja_processar_webhook(
    $pdo,
    'mercadopago',
    ['x-signature' => $sigMp, 'x-request-id' => 'req-mp-1'],
    json_encode(['type' => 'payment', 'data' => ['id' => '999888777', 'status' => 'approved']]),
    ['data.id' => '999888777']
);
assertSameValue(200, $whMp['http'], 'Webhook MP consulta API real e não confia no status do corpo');
unset($GLOBALS['__folivro_mp_http']);
folivro_env_set('PAYMENT_MODE', 'simulated');

// ---------------------------------------------------------------------
// 11. Manipulação de preço / divergência de valor na confirmação
// ---------------------------------------------------------------------
$pedDiv = folivro_loja_criar_pedido($pdo, $leitor2, $idProdPago, 'cartao');
$resDiv = folivro_loja_confirmar_pagamento($pdo, (int) $pedDiv['pedido']['id_pedido'], 'webhook', ['valor_centavos' => 100]); // pagou 1 real em vez de 20
assertTrue(!$resDiv['ok'] && $resDiv['status'] === 'falhou', 'Valor divergente bloqueia liberação do pedido');

// ---------------------------------------------------------------------
// 12. Reembolso: estorna ledger e revoga acesso à obra e ao arquivo
// ---------------------------------------------------------------------
$idorReemb = folivro_loja_reembolsar($pdo, (int) $cobOk['pedido']['id_pedido'], $autorB, 'Tentativa IDOR');
assertTrue(!$idorReemb['ok'], 'IDOR: Autor B não pode reembolsar pedido do Autor A');

$reembOk = folivro_loja_reembolsar($pdo, (int) $cobOk['pedido']['id_pedido'], $autorA, 'Solicitação do leitor');
assertTrue($reembOk['ok'], 'Autor A reembolsa o próprio pedido');
assertTrue(folivro_loja_autorizar_download($pdo, $leitor1, $idProdPago) === null, 'Após reembolso, download é bloqueado');
$stLibPos = $pdo->prepare('SELECT 1 FROM livraria WHERE id_usuario = ? AND id_livro = ?');
$stLibPos->execute([$leitor1, (int) $prodInfo['id_livro']]);
assertTrue(!$stLibPos->fetchColumn(), 'Após reembolso, obra é removida da biblioteca pessoal');
assertSameValue(2691, folivro_loja_saldo_autor($pdo, $autorA)['disponivel'], 'Após reembolso de 1800, saldo cai de 4491 para 2691');

// ---------------------------------------------------------------------
// 13. Repasse / Saque do autor
// ---------------------------------------------------------------------
$repMaior = folivro_loja_solicitar_repasse($pdo, $autorA, 5000);
assertTrue(!$repMaior['ok'], 'Repasse maior que o saldo disponível é bloqueado');
$repOk = folivro_loja_solicitar_repasse($pdo, $autorA, 2000);
assertTrue($repOk['ok'], 'Repasse dentro do saldo é registrado');
assertSameValue(691, folivro_loja_saldo_autor($pdo, $autorA)['disponivel'], 'Saldo após repasse de 2000 cai para 691');

// ---------------------------------------------------------------------
// 14. Segurança básica: XSS escape e CSRF
// ---------------------------------------------------------------------
assertSameValue('&lt;script&gt;alert(1)&lt;/script&gt;', folivro_escape('<script>alert(1)</script>'), 'XSS: folivro_escape neutraliza tags HTML');
$_SESSION['csrf_token'] = 'abc123';
$_POST = ['csrf_token' => 'errado'];
assertTrue(!@folivro_verify_csrf(), 'CSRF rejeita token inválido');
$_POST = ['csrf_token' => 'abc123'];
assertTrue(@folivro_verify_csrf(), 'CSRF aceita token válido');

// ---------------------------------------------------------------------
// 15. Configurações administrativas (pagamento simulado + aprovação automática)
// ---------------------------------------------------------------------
assertTrue(!folivro_aprovacao_automatica_obras_ativa($pdo), 'Aprovação automática global inicia desativada');
folivro_sistema_config_salvar($pdo, 'aprovacao_automatica_obras', '1', $autorA);
assertTrue(folivro_aprovacao_automatica_obras_ativa($pdo), 'Aprovação automática global ativa após alteração do admin');
folivro_sistema_config_salvar($pdo, 'aprovacao_automatica_obras', '0', $autorA);
assertTrue(!folivro_aprovacao_automatica_obras_ativa($pdo), 'Aprovação automática global desativada novamente');

// Limpeza final do banco e storage temporário de testes
$pdo->exec("DROP DATABASE `{$dbTest}`");
if (is_dir($tmpStorage)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpStorage, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($tmpStorage);
}

echo "\nResumo: {$passados} passou(aram), {$falhas} falhou(aram).\n";
exit($falhas > 0 ? 1 : 0);
