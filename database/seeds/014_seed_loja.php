<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/loja.php';

/**
 * Gera um PDF mínimo válido (com assinatura %PDF-1.4 e xref) para obras de demonstração.
 */
function folivro_seed_gerar_pdf_minimo(string $titulo, string $autor, string $texto): string
{
    $limpar = static function (string $s): string {
        $ascii = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) : $s;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], preg_replace('/[^\x20-\x7E]/', ' ', $ascii) ?? '');
    };
    $conteudo = "BT\n/F1 18 Tf\n72 720 Td\n(" . $limpar($titulo) . ") Tj\n"
        . "/F1 12 Tf\n0 -28 Td\n(Autor: " . $limpar($autor) . ") Tj\n"
        . "0 -28 Td\n(" . $limpar(mb_substr($texto, 0, 120, 'UTF-8')) . ") Tj\nET\n";

    $objs = [
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n",
        "4 0 obj\n<< /Length " . strlen($conteudo) . " >>\nstream\n" . $conteudo . "endstream\nendobj\n",
        "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objs as $o) {
        $offsets[] = strlen($pdf);
        $pdf .= $o;
    }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objs); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF\n";
    return $pdf;
}

/**
 * Gera um EPUB mínimo válido (ZIP com mimetype não comprimido como primeiro arquivo + META-INF/container.xml).
 */
function folivro_seed_gerar_epub_minimo(string $caminhoDestino, string $titulo, string $autor, string $texto): bool
{
    if (!class_exists('ZipArchive')) {
        return false;
    }
    $zip = new ZipArchive();
    if ($zip->open($caminhoDestino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }
    $zip->addFromString('mimetype', 'application/epub+zip');
    if (method_exists($zip, 'setCompressionName')) {
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
    }
    $zip->addFromString(
        'META-INF/container.xml',
        '<?xml version="1.0" encoding="UTF-8"?>'
        . '<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">'
        . '<rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles>'
        . '</container>'
    );
    $tEsc = htmlspecialchars($titulo, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $aEsc = htmlspecialchars($autor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $pEsc = htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $zip->addFromString(
        'OEBPS/content.opf',
        '<?xml version="1.0" encoding="UTF-8"?>'
        . '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="uid">'
        . '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:identifier id="uid">folivro-demo</dc:identifier>'
        . "<dc:title>{$tEsc}</dc:title><dc:creator>{$aEsc}</dc:creator><dc:language>pt-BR</dc:language>"
        . '</metadata><manifest><item id="c1" href="cap1.xhtml" media-type="application/xhtml+xml"/></manifest>'
        . '<spine><itemref idref="c1"/></spine></package>'
    );
    $zip->addFromString(
        'OEBPS/cap1.xhtml',
        '<?xml version="1.0" encoding="UTF-8"?>'
        . '<html xmlns="http://www.w3.org/1999/xhtml"><head><title>' . $tEsc . '</title></head>'
        . '<body><h1>' . $tEsc . '</h1><p><em>' . $aEsc . '</em></p><p>' . $pEsc . '</p></body></html>'
    );
    $zip->close();
    return is_file($caminhoDestino);
}

/**
 * Popula a loja com uma autora de demonstração, 4 obras publicadas (gratuita + pagas em PDF/EPUB),
 * 1 venda concluída para o leitor de teste e 1 avaliação com compra verificada.
 */
function folivro_seed_loja(PDO $pdo): void
{
    $jaTem = (int) $pdo->query('SELECT COUNT(*) FROM loja_produtos')->fetchColumn();
    if ($jaTem > 0) {
        return;
    }

    // 1. Garante conta da autora de demonstração
    $emailAutora = 'autora@folivro.local';
    $stAutora = $pdo->prepare('SELECT id_usuario FROM usuario WHERE email_usuario = ? LIMIT 1');
    $stAutora->execute([$emailAutora]);
    $idAutora = (int) $stAutora->fetchColumn();
    if ($idAutora <= 0) {
        $pdo->prepare(
            "INSERT INTO usuario (nome_usuario, email_usuario, data_nascimento_usuario, senha_usuario, biografia_usuario, nivel_usuario, status_usuario)
             VALUES (?, ?, '1993-05-14', ?, ?, 'autor', 'ativo')"
        )->execute([
            'Clara Mendes (Autora)',
            $emailAutora,
            password_hash('Autor123!', PASSWORD_DEFAULT),
            'Escritora independente de fantasia, ficção científica e crônicas no FOLIVRO.',
        ]);
        $idAutora = (int) $pdo->lastInsertId();
    } else {
        $pdo->prepare("UPDATE usuario SET nivel_usuario = 'autor', status_usuario = 'ativo' WHERE id_usuario = ?")->execute([$idAutora]);
    }

    $leitorId = (int) $pdo->query("SELECT id_usuario FROM usuario WHERE email_usuario = 'leitor@folivro.local' LIMIT 1")->fetchColumn();
    if ($leitorId > 0) {
        // Garante data de nascimento maior de 18 para o leitor de teste caso esteja vazia, ou mantém se já houver.
        $pdo->prepare("UPDATE usuario SET data_nascimento_usuario = COALESCE(data_nascimento_usuario, '1998-03-20') WHERE id_usuario = ?")->execute([$leitorId]);
    }

    $dirObras = folivro_loja_dir_storage('obras');
    $obrasSeed = [
        [
            'titulo' => 'Cartas do Observatório Sul',
            'descricao' => 'Coletânea de crônicas astronômicas e reflexões sobre o céu do hemisfério sul. Edição digital gratuita para novos leitores.',
            'preview' => 'Naquela noite de inverno, o telescópio apontava para além das nuvens baixas da serra, onde cada estrela parecia guardar uma carta não enviada.',
            'generos' => 'Crônica, Literatura Nacional',
            'classificacao' => 'livre',
            'preco' => 0,
            'tipo' => 'pdf',
            'capa' => null,
        ],
        [
            'titulo' => 'O Arquipélago de Vidro',
            'descricao' => 'Fantasia épica sobre cartógrafos que navegam entre ilhas translúcidas cujos mapas mudam a cada maré.',
            'preview' => 'Capítulo 1 — A bússola de quartzo: Lira ajustou o sextante enquanto a costa de vidro refletia o amanhecer em sete tons de âmbar.',
            'generos' => 'Fantasia, Aventura',
            'classificacao' => '12',
            'preco' => 2490,
            'tipo' => 'pdf',
            'capa' => null,
        ],
        [
            'titulo' => 'Algoritmo da Neblina',
            'descricao' => 'Ficção científica investigativa em uma São Paulo submersa por dados e neblina sintética.',
            'preview' => 'Os servidores da zona leste ainda zumbiam sob a garoa fina quando o último arquivo criptografado chegou ao terminal.',
            'generos' => 'Ficção Científica, Suspense, Distopia',
            'classificacao' => '14',
            'preco' => 3490,
            'tipo' => 'epub',
            'capa' => null,
        ],
        [
            'titulo' => 'Crônicas da Torre Cinzenta',
            'descricao' => 'Suspense gótico sobre um restaurador de manuscritos que descobre anotações cifradas em um convento isolado.',
            'preview' => 'Havia tinta fresca nas margens do códice de 1740, como se alguém tivesse continuado o relato minutos antes da minha chegada.',
            'generos' => 'Mistério, Terror, Histórico',
            'classificacao' => '16',
            'preco' => 1990,
            'tipo' => 'pdf',
            'capa' => null,
        ],
    ];

    $idsProdutos = [];
    foreach ($obrasSeed as $idx => $item) {
        $nomeInterno = 'obra_' . $idAutora . '_' . bin2hex(random_bytes(12)) . '.' . $item['tipo'];
        $caminho = $dirObras . '/' . $nomeInterno;
        if ($item['tipo'] === 'pdf') {
            file_put_contents($caminho, folivro_seed_gerar_pdf_minimo($item['titulo'], 'Clara Mendes', $item['preview']));
        } else {
            if (!folivro_seed_gerar_epub_minimo($caminho, $item['titulo'], 'Clara Mendes', $item['preview'])) {
                // Fallback para PDF caso ZipArchive não esteja habilitado no ambiente
                $item['tipo'] = 'pdf';
                $nomeInterno = 'obra_' . $idAutora . '_' . bin2hex(random_bytes(12)) . '.pdf';
                $caminho = $dirObras . '/' . $nomeInterno;
                file_put_contents($caminho, folivro_seed_gerar_pdf_minimo($item['titulo'], 'Clara Mendes', $item['preview']));
            }
        }
        $tamanho = (int) filesize($caminho);
        $sha = (string) hash_file('sha256', $caminho);

        $pdo->prepare(
            "INSERT INTO loja_produtos
                (id_autor, titulo_produto, descricao_produto, preview_produto, capa_produto, generos_produto,
                 classificacao_produto, preco_centavos, arquivo_produto, arquivo_nome_original, arquivo_tipo,
                 arquivo_tamanho, arquivo_sha256, status_produto, aceite_termo_direitos, data_aceite_termo, versao_termo, data_publicacao)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'publicado', 1, NOW(), ?, DATE_SUB(NOW(), INTERVAL ? DAY))"
        )->execute([
            $idAutora,
            $item['titulo'],
            $item['descricao'],
            $item['preview'],
            $item['capa'],
            $item['generos'],
            $item['classificacao'],
            $item['preco'],
            $nomeInterno,
            folivro_loja_nome_seguro($item['titulo'], $item['tipo']),
            $item['tipo'],
            $tamanho,
            $sha,
            FOLIVRO_LOJA_TERMO_VERSAO,
            (count($obrasSeed) - $idx) * 3,
        ]);
        $idProd = (int) $pdo->lastInsertId();
        folivro_loja_sincronizar_livro($pdo, $idProd);
        $idsProdutos[] = $idProd;
    }

    // 2. Cria uma compra simulada concluída para o leitor de teste (em "O Arquipélago de Vidro")
    if ($leitorId > 0 && isset($idsProdutos[1])) {
        $pedRes = folivro_loja_criar_pedido($pdo, $leitorId, $idsProdutos[1], 'cartao', 'simulado');
        if (!empty($pedRes['ok']) && !empty($pedRes['pedido'])) {
            folivro_loja_iniciar_cobranca($pdo, $pedRes['pedido'], ['cenario' => 'cartao_aprovado']);
            folivro_loja_salvar_resenha(
                $pdo,
                $leitorId,
                $idsProdutos[1],
                5,
                'Construção de mundo envolvente',
                'A leitura flui muito bem e a edição em PDF está impecável. Recomendo para quem gosta de fantasia de exploração.'
            );
        }
    }
}
