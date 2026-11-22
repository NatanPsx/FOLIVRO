<?php
declare(strict_types=1);

/**
 * Testes integrados das 4 correções do FOLIVRO:
 * 1. Exclusão definitiva de obras bloqueadas pela moderação
 * 2. Leitura de livros da biblioteca (ficha completa -> leitor)
 * 3. Sistema de contestação na moderação (aceitar/rejeitar + saída do modo de inspeção)
 * 4. Barra de pesquisa integrada (FOLIVRO + Open Library sem duplicados/bloqueados)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/loja.php';

$pdo = folivro_database();

$passou = 0;
$falhou = 0;
function ok(bool $cond, string $msg): void
{
    global $passou, $falhou;
    if ($cond) {
        $passou++;
        echo "[PASS] {$msg}\n";
    } else {
        $falhou++;
        echo "[FAIL] {$msg}\n";
    }
}

function http_req(string $url, string $method = 'GET', array $post = [], string &$cookieJar = ''): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    if ($cookieJar !== '') {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [
        'status' => $status,
        'headers' => is_string($raw) ? substr($raw, 0, $hSize) : '',
        'body' => is_string($raw) ? substr($raw, $hSize) : '',
    ];
}

$cookieAdmin = tempnam(sys_get_temp_dir(), 'fl_adm_');
$cookieLeitor = tempnam(sys_get_temp_dir(), 'fl_lei_');
$base = 'http://127.0.0.1:8099';

// Cria usuário temporário de teste com senha conhecida para testes HTTP
$emailTemp = 'teste_bugs_' . bin2hex(random_bytes(4)) . '@folivro.local';
$hashTemp = password_hash('SenhaTeste123!', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO usuario (nome_usuario, email_usuario, senha_usuario, nivel_usuario, status_usuario, data_nascimento_usuario) VALUES ('Autor Teste Bugs', ?, ?, 'autor', 'ativo', '1995-01-01')")
    ->execute([$emailTemp, $hashTemp]);
$idAutorTemp = (int) $pdo->lastInsertId();

$emailAdmTemp = 'admin_bugs_' . bin2hex(random_bytes(4)) . '@folivro.local';
$pdo->prepare("INSERT INTO usuario (nome_usuario, email_usuario, senha_usuario, nivel_usuario, status_usuario, data_nascimento_usuario) VALUES ('Admin Teste Bugs', ?, ?, 'admin', 'ativo', '1990-01-01')")
    ->execute([$emailAdmTemp, $hashTemp]);
$idAdminTemp = (int) $pdo->lastInsertId();

$idHistTemp = 0;
$idAdlTemp = 0;
$idProdTemp = 0;
$idDenTemp = 0;

try {
    // Login HTTP do Autor e do Admin
    $getLogin = http_req($base . '/login.php', 'GET', [], $cookieLeitor);
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $getLogin['body'], $mCsrfL);
    $loginAutor = http_req($base . '/login.php', 'POST', [
        'csrf_token' => $mCsrfL[1] ?? '',
        'email' => $emailTemp,
        'senha' => 'SenhaTeste123!',
    ], $cookieLeitor);
    ok($loginAutor['status'] === 302, 'Login HTTP do autor de teste');

    $getLoginAdm = http_req($base . '/login.php', 'GET', [], $cookieAdmin);
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $getLoginAdm['body'], $mCsrfA);
    $loginAdm = http_req($base . '/login.php', 'POST', [
        'csrf_token' => $mCsrfA[1] ?? '',
        'email' => $emailAdmTemp,
        'senha' => 'SenhaTeste123!',
    ], $cookieAdmin);
    ok($loginAdm['status'] === 302, 'Login HTTP do admin de teste');

    // Cria obra publicada no estúdio + sincroniza com loja_produtos
    $pdo->prepare("INSERT INTO historias (id_usuario, titulo_historias, sinopse_historias, generos_historias, status_historias, aviso_conteudo_historias) VALUES (?, 'Obra Exclusiva Teste Bugs', 'Sinopse de teste para busca local.', 'Ficção Científica, Aventura', 'publicada', 'livre')")
        ->execute([$idAutorTemp]);
    $idHistTemp = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO capitulos (id_historias, titulo_capitulos, conteudo_capitulos, ordem_capitulos, status_capitulos, modo_publicacao_capitulos, revisao_capitulos) VALUES (?, 'Capítulo 1', 'Conteúdo completo do capítulo 1 para leitura e PDF.', 1, 'publicado', 'revisao_humana', 'aprovada')")
        ->execute([$idHistTemp]);

    $pdo->prepare("INSERT INTO autor_dos_livros (id_historias, id_autor, titulo_autor_dos_livros, preco_dos_livros_autor, declaracao_autoria, status_autor_dos_livros, modalidade_distribuicao, declaracao_comercializacao, revisao_publicacao) VALUES (?, ?, 'Obra Exclusiva Teste Bugs', 0.00, 1, 'a_venda', 'gratuita', 1, 'aprovada')")
        ->execute([$idHistTemp, $idAutorTemp]);
    $idAdlTemp = (int) $pdo->lastInsertId();

    $idProdTemp = (int) folivro_loja_sincronizar_obra_estudio($pdo, $idAdlTemp);
    ok($idProdTemp > 0, 'Obra de teste sincronizada com loja_produtos');

    // --- TESTE 4: Barra de pesquisa encontra obra local e NÃO duplica ---
    $resBusca = http_req($base . '/api/livros.php?q=' . urlencode('Obra Exclusiva Teste Bugs'), 'GET', [], $cookieLeitor);
    $jsonBusca = json_decode($resBusca['body'], true);
    $achouLocal = false;
    foreach ($jsonBusca['items'] ?? [] as $it) {
        if (($it['source'] ?? '') === 'folivro' && (int) ($it['idProduto'] ?? 0) === $idProdTemp) {
            $achouLocal = true;
        }
    }
    ok($resBusca['status'] === 200 && $achouLocal, 'Pesquisa encontra obra publicada localmente no FOLIVRO');

    // --- TESTE 1: Denúncia + Bloqueio definitivo pela moderação ---
    $pdo->prepare("INSERT INTO denuncias (id_usuario, tipo_denuncias, item_denuncia, motivo_denuncia, status_denuncias, suspensao_preventiva) VALUES (?, 'obra_autoral', ?, 'Violação de direitos autorais', 'aberta', 0)")
        ->execute([1, $idAdlTemp]);
    $idDenTemp = (int) $pdo->lastInsertId();

    // Obra apenas denunciada (sem suspensão) ainda pode ser listada
    ok(folivro_loja_produto_publicado($pdo, $idProdTemp) !== null, 'Obra apenas denunciada (sem suspensão) permanece visível');

    // Admin bloqueia definitivamente via moderacao.php HTTP POST
    $modGet = http_req($base . '/moderacao.php', 'GET', [], $cookieAdmin);
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $modGet['body'], $mCsrfMod);
    $modPost = http_req($base . '/moderacao.php', 'POST', [
        'csrf_token' => $mCsrfMod[1] ?? '',
        'id_denuncias' => $idDenTemp,
        'decisao' => 'remover_item',
        'justificativa_moderacao' => 'Violação confirmada em teste automatizado.',
    ], $cookieAdmin);
    ok($modPost['status'] === 200, 'Moderação executou remoção definitiva via POST');

    // Verifica no banco e nas APIs de loja/compra/pesquisa
    $prodAposBloq = folivro_loja_produto($pdo, $idProdTemp);
    ok(($prodAposBloq['status_produto'] ?? '') === 'arquivado', 'loja_produtos foi arquivado após bloqueio da moderação');
    ok(folivro_loja_produto_publicado($pdo, $idProdTemp) === null, 'folivro_loja_produto_publicado retorna null para obra bloqueada');
    ok(folivro_loja_validar_compra($pdo, 40, $prodAposBloq) !== null, 'Backend bloqueia compra da obra removida');

    $vitrine = folivro_loja_listar_vitrine($pdo, ['q' => 'Obra Exclusiva Teste Bugs'], 1, 12);
    ok((int) $vitrine['total'] === 0, 'Obra bloqueada não aparece na vitrine da loja');

    $resLojaObra = http_req($base . '/loja-obra.php?id=' . $idProdTemp, 'GET', [], $cookieLeitor);
    // Nota: $idAutorTemp é o autor, então testamos com admin/outro leitor ou verificamos checkout
    $resCheckout = http_req($base . '/loja-checkout.php?id=' . $idProdTemp, 'GET', [], $cookieAdmin);
    ok(strpos($resCheckout['body'], 'não está disponível para compra') !== false || (!folivro_pagamentos_ativos() && $resCheckout['status'] === 302), 'URL direta de checkout bloqueia obra removida');

    $resBuscaBloq = http_req($base . '/api/livros.php?q=' . urlencode('Obra Exclusiva Teste Bugs'), 'GET', [], $cookieLeitor);
    $jsonBuscaBloq = json_decode($resBuscaBloq['body'], true);
    $achouBloq = false;
    foreach ($jsonBuscaBloq['items'] ?? [] as $it) {
        if ((int) ($it['idProduto'] ?? 0) === $idProdTemp || (int) ($it['idAutorDosLivros'] ?? 0) === $idAdlTemp) {
            $achouBloq = true;
        }
    }
    ok(!$achouBloq, 'Obra bloqueada NÃO aparece nos resultados da barra de pesquisa');

    // --- TESTE 3: Contestação do autor e resolução na área de inspeção (obra.php) ---
    $pdo->prepare("UPDATE denuncias SET contestacao_autor = 'Tenho autorização formal do titular.', data_contestacao = DATE_ADD(NOW(), INTERVAL 1 SECOND) WHERE id_denuncias = ?")
        ->execute([$idDenTemp]);

    $obraInsp = http_req($base . '/obra.php?id=' . $idAdlTemp, 'GET', [], $cookieAdmin);
    ok(strpos($obraInsp['body'], 'Modo de inspeção da moderação') !== false, 'Admin visualiza modo de inspeção com contestação pendente');
    ok(strpos($obraInsp['body'], 'Acolher contestação e restaurar obra') !== false, 'Área de inspeção exibe ação para resolver contestação');

    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $obraInsp['body'], $mCsrfObra);
    $resCont = http_req($base . '/obra.php?id=' . $idAdlTemp, 'POST', [
        'csrf_token' => $mCsrfObra[1] ?? '',
        'acao' => 'resolver_contestacao_moderacao',
        'id_denuncias' => $idDenTemp,
        'decisao' => 'improcedente',
        'justificativa_moderacao' => 'Documentação de autoria validada na contestação.',
    ], $cookieAdmin);
    ok($resCont['status'] === 302, 'Resolução de contestação redireciona após concluir');

    $obraApos = http_req($base . '/obra.php?id=' . $idAdlTemp . '&mod_resolvida=1', 'GET', [], $cookieAdmin);
    ok(strpos($obraApos['body'], 'Modo de inspeção da moderação') === false, 'Modo de inspeção é encerrado após acolher contestação');

    $stDenCheck = $pdo->prepare('SELECT status_denuncias, suspensao_preventiva FROM denuncias WHERE id_denuncias = ?');
    $stDenCheck->execute([$idDenTemp]);
    $denRow = $stDenCheck->fetch();
    ok($denRow && $denRow['status_denuncias'] === 'improcedente' && (int) $denRow['suspensao_preventiva'] === 0, 'Status da denúncia atualizado para improcedente no banco');
    ok(folivro_loja_produto_publicado($pdo, $idProdTemp) !== null, 'Obra restaurada voltou a ficar disponível na loja');

    // --- TESTE 2: Leitura de livros da biblioteca (ficha completa -> leitor) ---
    // Usuário admin (id=1) tem id_livro=19 (LOJA-1) na livraria. Vamos testar com $idAdminTemp adicionando id_livro=19 na livraria e comprando id_produto=1 ou gratuito.
    $stL19 = $pdo->query("SELECT id_livro FROM livro WHERE id_externo_livro = 'LOJA-1' LIMIT 1")->fetchColumn();
    if ($stL19) {
        // Simula livro na biblioteca do admin temp com pedido pago do produto 1
        $pdo->prepare("INSERT IGNORE INTO livraria (id_usuario, id_livro, status_livraria) VALUES (?, ?, 'lendo')")->execute([$idAdminTemp, (int) $stL19]);
        $pdo->prepare("INSERT INTO loja_pedidos (codigo_pedido, id_produto, id_comprador, id_vendedor, titulo_produto_snapshot, valor_bruto_centavos, taxa_percentual, valor_taxa_centavos, valor_liquido_centavos, metodo_pagamento, gateway, status_pedido, pago_em) VALUES (?, 1, ?, 44, 'Cartas do Observatório Sul', 1990, 10.00, 199, 1791, 'pix', 'simulado', 'pago', NOW())")
            ->execute(['TEST' . strtoupper(bin2hex(random_bytes(5))), $idAdminTemp]);
        $idPedTemp = (int) $pdo->lastInsertId();

        $resFicha = http_req($base . '/livro.php?id=' . (int) $stL19, 'GET', [], $cookieAdmin);
        ok($resFicha['status'] === 200 && (strpos($resFicha['body'], 'obra.php?id=') !== false || strpos($resFicha['body'], 'leitor.php?id=' . (int) $stL19) !== false), 'Ficha completa (livro.php) exibe botão de leitura para livro da biblioteca');

        $resLeitor = http_req($base . '/leitor.php?id=' . (int) $stL19, 'GET', [], $cookieAdmin);
        ok(($resLeitor['status'] === 302 && stripos($resLeitor['headers'], 'obra.php?id=') !== false) || ($resLeitor['status'] === 200 && strpos($resLeitor['body'], '"idProduto":1') !== false), 'Leitor (leitor.php) redireciona obra da loja para o leitor original (obra.php)');

        $resPdfInline = http_req($base . '/loja-download.php?id=1&inline=1', 'GET', [], $cookieAdmin);
        ok($resPdfInline['status'] === 200 && stripos($resPdfInline['headers'], 'Content-Disposition: inline') !== false, 'loja-download.php serve PDF inline para o leitor integrado');

        $pdo->prepare('DELETE FROM loja_pedidos WHERE id_pedido = ?')->execute([$idPedTemp]);
        $pdo->prepare('DELETE FROM livraria WHERE id_usuario = ?')->execute([$idAdminTemp]);
    }
} finally {
    // Limpeza dos dados temporários de teste
    if ($idDenTemp > 0) {
        $pdo->prepare('DELETE FROM denuncias WHERE id_denuncias = ?')->execute([$idDenTemp]);
    }
    if ($idProdTemp > 0) {
        $pdo->prepare('DELETE FROM loja_produtos WHERE id_produto = ?')->execute([$idProdTemp]);
    }
    if ($idAdlTemp > 0) {
        $pdo->prepare('DELETE FROM historico_moderacao_autor WHERE id_autor_dos_livros = ?')->execute([$idAdlTemp]);
        $pdo->prepare('DELETE FROM autor_dos_livros WHERE id_autor_dos_livros = ?')->execute([$idAdlTemp]);
    }
    if ($idHistTemp > 0) {
        $pdo->prepare('DELETE FROM capitulos WHERE id_historias = ?')->execute([$idHistTemp]);
        $pdo->prepare('DELETE FROM historias WHERE id_historias = ?')->execute([$idHistTemp]);
    }
    $pdo->prepare('DELETE FROM usuario WHERE id_usuario IN (?, ?)')->execute([$idAutorTemp, $idAdminTemp]);
    @unlink($cookieAdmin);
    @unlink($cookieLeitor);
}

echo "\nResumo bugs_fix_test: {$passou} passou(aram), {$falhou} falhou(aram).\n";
exit($falhou > 0 ? 1 : 0);
