<?php
declare(strict_types=1);
require_once __DIR__ . '/config/loja.php';

$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];

$idProduto = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($idProduto <= 0) {
    http_response_code(400);
    exit('Obra inválida.');
}

if (!folivro_loja_rate_limit($pdo, 'download:' . $uid, 40, 60)) {
    http_response_code(429);
    exit('Muitas solicitações de download em sequência. Aguarde um instante.');
}

// Autorização estrita no servidor: somente o comprador com pedido 'pago' ou o próprio autor.
$produto = folivro_loja_autorizar_download($pdo, $uid, $idProduto);
if ($produto === null) {
    folivro_loja_log('download_negado', ['id_produto' => $idProduto, 'id_usuario' => $uid]);
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('Acesso negado: você precisa adquirir esta obra para baixar o arquivo.');
}

$caminho = folivro_loja_caminho_arquivo($produto);
if ($caminho === null) {
    http_response_code(404);
    exit('Arquivo da obra não encontrado.');
}

$tipo = (string) ($produto['arquivo_tipo'] ?? 'pdf');
$mime = $tipo === 'epub' ? 'application/epub+zip' : 'application/pdf';
$nomeDownload = folivro_loja_nome_seguro((string) $produto['titulo_produto'], $tipo);
$tamanho = (int) filesize($caminho);
$inline = ($tipo === 'pdf' && isset($_GET['inline']) && $_GET['inline'] === '1');
if ($tipo === 'pdf' && !$inline) {
    folivro_loja_log('download_pdf_bloqueado', ['id_produto' => $idProduto, 'id_usuario' => $uid]);
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('O download de livros em PDF foi desativado. Utilize o leitor online.');
}
$disposition = $inline ? 'inline' : 'attachment';

folivro_loja_log('download_autorizado', ['id_produto' => $idProduto, 'id_usuario' => $uid, 'tipo' => $tipo]);

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . $nomeDownload . '"');
header('Content-Length: ' . $tamanho);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
readfile($caminho);
exit;
