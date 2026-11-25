<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/dominio_publico.php';
require_once __DIR__ . '/config/traducao.php';

$pdo = folivro_database();
folivro_require_login();

$uid = (int) $_SESSION['id_usuario'];
$idLivroParam = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : 0;
$idLivroParam = is_int($idLivroParam) ? $idLivroParam : 0;
$workParam = isset($_GET['work']) && is_string($_GET['work']) ? trim($_GET['work']) : '';

// Validação de entrada
$workId = '';
if ($workParam !== '' && preg_match('/^OL[0-9]+W$/', $workParam)) {
    $workId = $workParam;
}

$idLivro = $idLivroParam > 0 ? $idLivroParam : null;
$livroLocal = null;

// Busca registro local pelo id_livro ou pelo id_externo_livro
if ($idLivro !== null) {
    $stmt = $pdo->prepare('SELECT * FROM livro WHERE id_livro = ? LIMIT 1');
    $stmt->execute([$idLivro]);
    $livroLocal = $stmt->fetch();
    if ($livroLocal && !empty($livroLocal['id_externo_livro']) && preg_match('/^OL[0-9]+W$/', (string) $livroLocal['id_externo_livro'])) {
        $workId = (string) $livroLocal['id_externo_livro'];
    }
} elseif ($workId !== '') {
    $stmt = $pdo->prepare('SELECT * FROM livro WHERE id_externo_livro = ? LIMIT 1');
    $stmt->execute([$workId]);
    $livroLocal = $stmt->fetch();
    if ($livroLocal) {
        $idLivro = (int) $livroLocal['id_livro'];
    }
}

// Se não tiver nem ID válido nem work ID válido, exibe erro 404
if ($idLivro === null && $workId === '') {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Livro não encontrado | FOLIVRO</title>
        <link rel="stylesheet" href="layout.css?v=20261125_01">
    </head>
    <body>
        <?php $menuAtivo = 'pesquisar'; require __DIR__ . '/includes/menu-lateral.php'; ?>
        <main class="conteudo" id="conteudo-principal">
            <div class="estado-vazio estado-erro" style="max-width: 600px; margin: 40px auto; text-align: center;">
                <h2>Livro não localizado</h2>
                <p>Nenhum identificador de livro foi fornecido ou o registro não existe no catálogo.</p>
                <p><a href="pesquisa.php" class="botao-mais" style="display:inline-block; margin-top:16px;">Buscar no catálogo</a></p>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Procura metadados do livro nos arquivos de cache locais de busca/destaques (fallback rápido quando a Open Library está lenta).
 */
function folivro_buscar_no_cache_local(string $cacheDir, string $wId): ?array
{
    foreach (['cat_*.json', 'destaques_*.json', 'search_*.json'] as $padrao) {
        $arquivos = glob($cacheDir . '/' . $padrao) ?: [];
        foreach ($arquivos as $arq) {
            $lista = json_decode((string) @file_get_contents($arq), true);
            if (!is_array($lista)) {
                continue;
            }
            foreach ($lista as $item) {
                if (!is_array($item) || ($item['workId'] ?? '') !== $wId) {
                    continue;
                }
                $coverM = $item['coverUrl'] ?? null;
                $coverL = is_string($coverM) ? preg_replace('/-M\.jpg$/', '-L.jpg', $coverM) : null;
                $avg = isset($item['ratingAvg']) && is_numeric($item['ratingAvg']) ? (float) $item['ratingAvg'] : 0.0;
                $cnt = isset($item['ratingsCount']) && is_numeric($item['ratingsCount']) ? (int) $item['ratingsCount'] : 0;
                return [
                    'workId' => $wId,
                    'title' => (string) ($item['originalTitle'] ?? $item['title'] ?? 'Sem título'),
                    'author' => !empty($item['authors']) && is_array($item['authors']) ? implode(', ', $item['authors']) : 'Autoria não informada',
                    'description' => '',
                    'subjects' => [],
                    'coverUrl' => $coverM,
                    'coverLargeUrl' => $coverL,
                    'firstPublishYear' => isset($item['year']) && is_numeric($item['year']) ? (int) $item['year'] : null,
                    'ratings' => ['average' => $avg, 'count' => $cnt, 'counts' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]],
                    'workUrl' => 'https://openlibrary.org/works/' . $wId,
                ];
            }
        }
    }
    return null;
}

/**
 * Função de consulta e cache de metadados e avaliações da Open Library.
 */
function folivro_obter_dados_openlibrary(string $wId, string $autorConhecido = ''): ?array {
    $obraDp = folivro_dp_encontrar_obra($wId);
    if ($obraDp !== null) {
        return [
            'workId' => $obraDp['workId'],
            'title' => $obraDp['title'],
            'originalTitle' => null,
            'author' => $obraDp['authors'][0] ?? 'Autor Clássico',
            'description' => $obraDp['sinopse'] . ' — ' . $obraDp['edicaoReferencia'],
            'subjects' => $obraDp['generos'],
            'coverUrl' => $obraDp['coverUrl'],
            'coverLargeUrl' => $obraDp['coverUrl'],
            'firstPublishYear' => $obraDp['year'],
            'ratings' => ['average' => 4.9, 'count' => 120, 'sortable' => 4.9, 'counts' => [5 => 108, 4 => 12, 3 => 0, 2 => 0, 1 => 0]],
            'workUrl' => null,
            'dominioPublico' => true,
        ];
    }
    if (!preg_match('/^OL[0-9]+W$/', $wId)) {
        return null;
    }

    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
        $cacheDir = sys_get_temp_dir() . '/folivro_cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
    }

    $cacheFile = $cacheDir . '/ol_work_' . $wId . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 604800)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    // Busca metadados da obra e avaliações em paralelo com curl_multi para máxima velocidade
    $workRaw = null;
    $ratingsRaw = null;

    if (function_exists('curl_multi_init')) {
        foreach ([true, false] as $verifySsl) {
            $mh = curl_multi_init();
            if (defined('CURLMOPT_PIPELINING') && defined('CURLPIPE_NOTHING')) {
                @curl_multi_setopt($mh, CURLMOPT_PIPELINING, CURLPIPE_NOTHING);
            }
            $chWork = curl_init("https://openlibrary.org/works/{$wId}.json");
            $chRatings = curl_init("https://openlibrary.org/works/{$wId}/ratings.json");

            foreach ([$chWork, $chRatings] as $ch) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_ENCODING, '');
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);
                curl_setopt($ch, CURLOPT_USERAGENT, "FOLIVRO-Academic-Demo/1.0 (TCC Platform; contact@folivro.local)");
                curl_multi_add_handle($mh, $ch);
            }

            $running = null;
            do {
                $mrc = curl_multi_exec($mh, $running);
                if ($running > 0) {
                    if (curl_multi_select($mh, 0.1) === -1) {
                        usleep(10000);
                    }
                }
            } while ($running > 0 && $mrc === CURLM_OK);

            $workRaw = curl_multi_getcontent($chWork);
            $ratingsRaw = curl_multi_getcontent($chRatings);
            $workErr = curl_errno($chWork);

            curl_multi_remove_handle($mh, $chWork);
            curl_multi_remove_handle($mh, $chRatings);
            curl_multi_close($mh);
            curl_close($chWork);
            curl_close($chRatings);

            if ($workRaw || $workErr !== 60) {
                break;
            }
        }
    } else {
        foreach ([true, false] as $verifySsl) {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 3,
                    'header' => "User-Agent: FOLIVRO-Academic-Demo/1.0 (TCC Platform; contact@folivro.local)\r\nAccept: application/json\r\n",
                ],
                'ssl' => ['verify_peer' => $verifySsl, 'verify_peer_name' => $verifySsl],
            ]);
            $workRaw = @file_get_contents("https://openlibrary.org/works/{$wId}.json", false, $ctx);
            $ratingsRaw = @file_get_contents("https://openlibrary.org/works/{$wId}/ratings.json", false, $ctx);
            if ($workRaw) {
                break;
            }
        }
    }

    if (!$workRaw) {
        if (file_exists($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }
        return folivro_buscar_no_cache_local($cacheDir, $wId);
    }
    $work = json_decode($workRaw, true);
    if (!is_array($work)) {
        return folivro_buscar_no_cache_local($cacheDir, $wId);
    }

    // Autor: reaproveita autor já conhecido do banco/cache de busca antes de fazer uma 3ª requisição HTTP
    $autorNome = trim($autorConhecido);
    if ($autorNome === '') {
        $localHit = folivro_buscar_no_cache_local($cacheDir, $wId);
        if ($localHit && !empty($localHit['author']) && $localHit['author'] !== 'Autoria não informada') {
            $autorNome = (string) $localHit['author'];
        }
    }
    if ($autorNome === '' && !empty($work['authors'][0]['author']['key'])) {
        $authorKey = (string) $work['authors'][0]['author']['key'];
        $authorCacheFile = $cacheDir . '/ol_author_' . md5($authorKey) . '.json';
        if (file_exists($authorCacheFile) && (time() - filemtime($authorCacheFile) < 86400 * 30)) {
            $authorData = json_decode((string) file_get_contents($authorCacheFile), true);
            $autorNome = $authorData['name'] ?? '';
        } else {
            $authorRaw = null;
            if (function_exists('curl_init')) {
                foreach ([true, false] as $verifySsl) {
                    $chA = curl_init("https://openlibrary.org{$authorKey}.json");
                    curl_setopt_array($chA, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CONNECTTIMEOUT => 1,
                        CURLOPT_TIMEOUT => 2,
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        CURLOPT_ENCODING => '',
                        CURLOPT_SSL_VERIFYPEER => $verifySsl,
                        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
                        CURLOPT_USERAGENT => 'FOLIVRO-Academic-Demo/1.0',
                    ]);
                    $authorRaw = curl_exec($chA);
                    $errA = curl_errno($chA);
                    curl_close($chA);
                    if ($authorRaw || $errA !== 60) {
                        break;
                    }
                }
            }
            if ($authorRaw) {
                $authorData = json_decode($authorRaw, true);
                if (is_array($authorData) && !empty($authorData['name'])) {
                    $autorNome = (string) $authorData['name'];
                    @file_put_contents($authorCacheFile, $authorRaw);
                }
            } elseif (file_exists($authorCacheFile)) {
                $authorData = json_decode((string) file_get_contents($authorCacheFile), true);
                $autorNome = $authorData['name'] ?? '';
            }
        }
    }

    // Avaliações (Ratings API oficial da Open Library)
    $ratings = ['average' => null, 'count' => 0, 'sortable' => null, 'counts' => []];
    if ($ratingsRaw) {
        $ratingsData = json_decode($ratingsRaw, true);
        if (is_array($ratingsData)) {
            $ratings['average'] = isset($ratingsData['summary']['average']) ? (float) $ratingsData['summary']['average'] : null;
            $ratings['count'] = isset($ratingsData['summary']['count']) ? (int) $ratingsData['summary']['count'] : 0;
            $ratings['sortable'] = isset($ratingsData['summary']['sortable']) ? (float) $ratingsData['summary']['sortable'] : null;
            $ratings['counts'] = is_array($ratingsData['counts'] ?? null) ? $ratingsData['counts'] : [];
        }
    }

    // Sinopse / Descrição
    $desc = '';
    if (isset($work['description'])) {
        if (is_string($work['description'])) {
            $desc = $work['description'];
        } elseif (is_array($work['description']) && isset($work['description']['value'])) {
            $desc = (string) $work['description']['value'];
        }
    }

    // Capas (Alta definição e média)
    $capaMedia = null;
    $capaAlta = null;
    if (!empty($work['covers']) && is_array($work['covers'])) {
        $firstCover = (int) $work['covers'][0];
        if ($firstCover > 0) {
            $capaMedia = "https://covers.openlibrary.org/b/id/{$firstCover}-M.jpg";
            $capaAlta = "https://covers.openlibrary.org/b/id/{$firstCover}-L.jpg";
        }
    }

    // Assuntos / Gêneros
    $assuntos = [];
    if (!empty($work['subjects']) && is_array($work['subjects'])) {
        $assuntos = array_slice(array_values(array_filter($work['subjects'], 'is_string')), 0, 8);
    }

    // Ano da primeira publicação
    $anoPublicacao = null;
    if (!empty($work['first_publish_date'])) {
        if (preg_match('/\b([12][0-9]{3})\b/', (string) $work['first_publish_date'], $m)) {
            $anoPublicacao = (int) $m[1];
        }
    }

    $resultado = [
        'workId' => $wId,
        'title' => (string) ($work['title'] ?? 'Sem título'),
        'author' => $autorNome,
        'description' => $desc,
        'subjects' => $assuntos,
        'coverUrl' => $capaMedia,
        'coverLargeUrl' => $capaAlta,
        'firstPublishYear' => $anoPublicacao,
        'ratings' => $ratings,
        'workUrl' => "https://openlibrary.org/works/{$wId}",
    ];

    @file_put_contents($cacheFile, json_encode($resultado, JSON_UNESCAPED_UNICODE));
    return $resultado;
}

// Obtém dados remotos se tivermos workId
$dadosOl = $workId !== '' ? folivro_obter_dados_openlibrary($workId, (string) ($livroLocal['autor_livro'] ?? '')) : null;

// Combina os dados: prioriza o banco local se existir, complementado pelos dados da Open Library
$tituloLivroRaw = $livroLocal['titulo_livro'] ?? ($dadosOl['title'] ?? 'Obra sem título');
$autorLivro  = $livroLocal['autor_livro'] ?? ($dadosOl['author'] ?? 'Autoria não informada');
$capaLivro   = $dadosOl['coverLargeUrl'] ?? ($livroLocal['capa_livro'] ?? ($dadosOl['coverUrl'] ?? ''));
$sinopseLivroRaw = $livroLocal['sinopse_livro'] ?? ($dadosOl['description'] ?? '');
$generosRaw = $dadosOl['subjects'] ?? [];
if (empty($generosRaw) && !empty($livroLocal['genero_livro'])) {
    $generosRaw = array_map('trim', explode(',', (string) $livroLocal['genero_livro']));
}

// Localização e tradução automática para Português do Brasil (PT-BR)
$infoTitulo = folivro_localizar_titulo($tituloLivroRaw);
$tituloLivro = $infoTitulo['titulo'];
$tituloOriginal = $infoTitulo['original'] ?? ($dadosOl['title'] ?? null);
if ($tituloOriginal === $tituloLivro) {
    $tituloOriginal = null;
}
$sinopseLivro = folivro_traduzir_sinopse($sinopseLivroRaw);
$generos = folivro_traduzir_generos($generosRaw);

$anoPublicacao = $dadosOl['firstPublishYear'] ?? null;
if (!$anoPublicacao && !empty($livroLocal['data_publicacao_livro'])) {
    $anoPublicacao = (int) date('Y', strtotime($livroLocal['data_publicacao_livro']));
}
$paginasEstimadas = (int) ($livroLocal['paginas_livro'] ?? 0);

$ehLivroCatalogoInterno = $livroLocal && (
    ($livroLocal['fonte_livro'] ?? '') === 'local'
    || str_starts_with((string) ($livroLocal['id_externo_livro'] ?? ''), 'LOJA-')
);
$classLojaAutoral = null;
if ($livroLocal && $idLivro !== null) {
    $stClassLoja = $pdo->prepare(
        'SELECT COALESCE(NULLIF(h.aviso_conteudo_historias, ""), p.classificacao_produto)
           FROM loja_produtos p
      LEFT JOIN autor_dos_livros a ON a.id_autor_dos_livros = p.id_autor_dos_livros
      LEFT JOIN historias h ON h.id_historias = a.id_historias
          WHERE p.id_livro = ?
          LIMIT 1'
    );
    $stClassLoja->execute([$idLivro]);
    $valClassLoja = $stClassLoja->fetchColumn();
    if ($valClassLoja !== false && $valClassLoja !== null && trim((string) $valClassLoja) !== '') {
        $classLojaAutoral = folivro_normalizar_classificacao((string) $valClassLoja);
        $ehLivroCatalogoInterno = true;
    }
}
if ($classLojaAutoral !== null) {
    $classLivro = $classLojaAutoral;
} elseif ($ehLivroCatalogoInterno) {
    $classLivro = folivro_normalizar_classificacao((string) ($livroLocal['classificacao_livro'] ?? 'livre'));
} else {
    $classEstimada = folivro_estimar_classificacao_catalogo($tituloLivro . ' ' . ($tituloOriginal ?? ''), $workId);
    $classLivro = !empty($livroLocal['classificacao_livro']) && $livroLocal['classificacao_livro'] !== 'livre'
        ? folivro_normalizar_classificacao((string) $livroLocal['classificacao_livro'])
        : $classEstimada;
}
$siglaIdadeLivro = folivro_classificacao_sigla($classLivro);
$labelIdadeLivro = folivro_aviso_conteudo_label($classLivro);

if (folivro_classificacao_eh_maior18($classLivro) && !folivro_usuario_maior_de_idade($pdo, $uid)) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Livro Bloqueado (+18) | FOLIVRO</title>
        <link rel="stylesheet" href="layout.css?v=20261125_01">
        <link rel="stylesheet" href="home.css?v=20261123_01">
    </head>
    <body>
        <?php $menuAtivo = 'pesquisar'; require __DIR__ . '/includes/menu-lateral.php'; ?>
        <?php require __DIR__ . '/includes/menu-autor.php'; ?>
        <main class="conteudo" id="conteudo-principal">
            <article class="livro-card livro-card-bloqueado-idade" style="max-width: 560px; margin: 28px auto;">
                <div class="livro-capa capa-bloqueada-18" style="height: 240px;">
                    <?php if ($capaLivro): ?>
                        <img src="<?= folivro_escape($capaLivro) ?>" alt="Capa bloqueada por classificação indicativa +18">
                    <?php else: ?>
                        <div class="capa-fallback">Obra +18</div>
                    <?php endif; ?>
                    <div class="capa-bloqueio-idade-overlay" role="img" aria-label="Conteúdo bloqueado: exclusivo para maiores de 18 anos">
                        <span class="icone-olho-bloqueado-circulo">
                            <?= folivro_icone_olho_bloqueado_svg(36) ?>
                        </span>
                        <strong class="titulo-bloqueio-capa">Classificação +18</strong>
                        <span class="motivo-bloqueio-capa">Bloqueado: exclusivo para maiores de 18 anos</span>
                    </div>
                    <span class="selo-classificacao-idade selo-classificacao-capa selo-idade-18">+18</span>
                </div>
                <div class="livro-info">
                    <span class="selo-classificacao-idade selo-idade-18">+18 · Proibido para menores de 18 anos</span>
                    <h2><span class="titulo-oculto-idade">Título oculto (+18)</span></h2>
                    <p class="texto-motivo-bloqueio-card">
                        <strong>Por que este livro está bloqueado?</strong><br>
                        Este livro possui classificação indicativa <strong>+18</strong> e sua conta possui menos de 18 anos (de acordo com a data de nascimento informada no cadastro).
                    </p>
                    <p><a class="botao-adicionar-livro" href="pesquisa.php">Voltar para o catálogo</a></p>
                </div>
            </article>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Se o livro já existe no banco local, mantém atualizado com título e sinopse em PT-BR
if ($livroLocal && $idLivro !== null) {
    $pdo->prepare('UPDATE livro SET titulo_livro = ?, sinopse_livro = ?, classificacao_livro = ? WHERE id_livro = ?')
        ->execute([$tituloLivro, $sinopseLivro !== '' ? $sinopseLivro : null, $classLivro, $idLivro]);
}

// Processamento de Ações do Usuário (Salvar na biblioteca, Alterar status, Avaliar/Resenhar)
$msgSucesso = '';
$msgErro = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!folivro_verify_csrf()) {
        $msgErro = 'Sessão expirada. Por favor, atualize a página e tente novamente.';
    } else {
        $acao = (string) ($_POST['acao'] ?? '');

        // 1. Adicionar à biblioteca pessoal
        if ($acao === 'adicionar_biblioteca') {
            $statusDesejado = (string) ($_POST['status_livraria'] ?? 'quero_ler');
            if (!in_array($statusDesejado, ['quero_ler', 'lendo', 'lido', 'abandonado'], true)) {
                $statusDesejado = 'quero_ler';
            }

            // Garante que o livro existe na tabela `livro`
            if ($idLivro === null) {
                $ins = $pdo->prepare(
                    "INSERT INTO livro (titulo_livro, autor_livro, sinopse_livro, capa_livro, data_publicacao_livro, fonte_livro, id_externo_livro)
                     VALUES (?, ?, ?, ?, ?, 'openlibrary', ?)"
                );
                $dtPub = $anoPublicacao ? sprintf('%04d-01-01', $anoPublicacao) : null;
                $ins->execute([
                    mb_substr($tituloLivro, 0, 200, 'UTF-8'),
                    mb_substr($autorLivro, 0, 150, 'UTF-8'),
                    $sinopseLivro !== '' ? $sinopseLivro : null,
                    $capaLivro !== '' ? mb_substr($capaLivro, 0, 255, 'UTF-8') : null,
                    $dtPub,
                    $workId
                ]);
                $idLivro = (int) $pdo->lastInsertId();
            }

            $libStmt = $pdo->prepare(
                'INSERT INTO livraria (id_usuario, id_livro, status_livraria)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE status_livraria = VALUES(status_livraria), data_modificacao_livraria = CURRENT_TIMESTAMP'
            );
            $libStmt->execute([$uid, $idLivro, $statusDesejado]);

            $progStmt = $pdo->prepare('INSERT IGNORE INTO progressao_leitura (id_usuario, id_livro, total_paginas_progressao) VALUES (?, ?, ?)');
            $progStmt->execute([$uid, $idLivro, $paginasEstimadas]);

            $msgSucesso = 'Livro salvo na sua biblioteca com status “' . folivro_status_label($statusDesejado) . '”!';

        // 2. Alterar status na biblioteca ou remover
        } elseif ($acao === 'alterar_status') {
            $novoStatus = (string) ($_POST['status_livraria'] ?? '');
            if ($novoStatus === 'remover') {
                if ($idLivro !== null) {
                    $pdo->prepare('DELETE FROM livraria WHERE id_usuario = ? AND id_livro = ?')->execute([$uid, $idLivro]);
                    $msgSucesso = 'Livro removido da sua biblioteca.';
                }
            } elseif (in_array($novoStatus, ['quero_ler', 'lendo', 'lido', 'abandonado'], true)) {
                if ($idLivro !== null) {
                    $pdo->prepare('UPDATE livraria SET status_livraria = ?, data_modificacao_livraria = CURRENT_TIMESTAMP WHERE id_usuario = ? AND id_livro = ?')
                        ->execute([$novoStatus, $uid, $idLivro]);
                    $msgSucesso = 'Status de leitura atualizado para “' . folivro_status_label($novoStatus) . '”.';
                }
            }

        // 3. Avaliar / Escrever Resenha
        } elseif ($acao === 'avaliar_livro') {
            $nota = isset($_POST['nota_resenha']) ? filter_var($_POST['nota_resenha'], FILTER_VALIDATE_INT) : null;
            $tituloResenha = trim((string) ($_POST['titulo_resenha'] ?? ''));
            $textoResenha = trim((string) ($_POST['resenha_resenha'] ?? ''));

            if (!is_int($nota) || $nota < 1 || $nota > 5) {
                $msgErro = 'Selecione uma nota válida de 1 a 5 estrelas.';
            } elseif ($textoResenha === '' || mb_strlen($textoResenha, 'UTF-8') > 5000 || mb_strlen($tituloResenha, 'UTF-8') > 150) {
                $msgErro = 'Escreva sua resenha (até 5.000 caracteres) e um título de até 150 caracteres.';
            } else {
                // Garante que o livro existe na tabela `livro` antes de gravar a resenha
                if ($idLivro === null) {
                    $ins = $pdo->prepare(
                        "INSERT INTO livro (titulo_livro, autor_livro, sinopse_livro, capa_livro, data_publicacao_livro, fonte_livro, id_externo_livro)
                         VALUES (?, ?, ?, ?, ?, 'openlibrary', ?)"
                    );
                    $dtPub = $anoPublicacao ? sprintf('%04d-01-01', $anoPublicacao) : null;
                    $ins->execute([
                        mb_substr($tituloLivro, 0, 200, 'UTF-8'),
                        mb_substr($autorLivro, 0, 150, 'UTF-8'),
                        $sinopseLivro !== '' ? $sinopseLivro : null,
                        $capaLivro !== '' ? mb_substr($capaLivro, 0, 255, 'UTF-8') : null,
                        $dtPub,
                        $workId
                    ]);
                    $idLivro = (int) $pdo->lastInsertId();

                    // Adiciona também na biblioteca como 'lido'
                    $pdo->prepare("INSERT IGNORE INTO livraria (id_usuario, id_livro, status_livraria) VALUES (?, ?, 'lido')")->execute([$uid, $idLivro]);
                }

                $resStmt = $pdo->prepare(
                    'INSERT INTO resenha (id_usuario, id_livro, nota_resenha, titulo_resenha, resenha_resenha)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        nota_resenha = VALUES(nota_resenha),
                        titulo_resenha = VALUES(titulo_resenha),
                        resenha_resenha = VALUES(resenha_resenha),
                        data_avaliacao_resenha = CURRENT_TIMESTAMP'
                );
                $resStmt->execute([$uid, $idLivro, $nota, $tituloResenha !== '' ? $tituloResenha : null, $textoResenha]);
                $msgSucesso = 'Sua resenha e avaliação foram registradas!';
            }
        }
    }
}

// Verifica status do usuário atual para este livro
$registroLivraria = null;
$progressoUsuario = null;
if ($idLivro !== null) {
    $chk = $pdo->prepare('SELECT * FROM livraria WHERE id_usuario = ? AND id_livro = ? LIMIT 1');
    $chk->execute([$uid, $idLivro]);
    $registroLivraria = $chk->fetch();

    $chkProg = $pdo->prepare('SELECT * FROM progressao_leitura WHERE id_usuario = ? AND id_livro = ? LIMIT 1');
    $chkProg->execute([$uid, $idLivro]);
    $progressoUsuario = $chkProg->fetch();
}

$produtoLojaLivro = null;
$temAcessoLojaLivro = false;
$bloqueadoModLivro = false;
if ($idLivro !== null) {
    $stProdL = $pdo->prepare(
        'SELECT p.id_produto, p.id_autor, p.id_autor_dos_livros, p.preco_centavos, p.arquivo_produto, p.arquivo_tipo, p.status_produto,
                a.status_autor_dos_livros AS estudio_status
           FROM loja_produtos p
      LEFT JOIN autor_dos_livros a ON a.id_autor_dos_livros = p.id_autor_dos_livros
          WHERE p.id_livro = ? LIMIT 1'
    );
    $stProdL->execute([$idLivro]);
    $produtoLojaLivro = $stProdL->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($produtoLojaLivro) {
        require_once __DIR__ . '/config/loja.php';
        $ehAutorL = (int) $produtoLojaLivro['id_autor'] === $uid;
        $bloqueadoModLivro = (($produtoLojaLivro['estudio_status'] ?? '') === 'removido')
            || (($produtoLojaLivro['estudio_status'] ?? '') === 'suspenso' && !$ehAutorL);
        if (!$bloqueadoModLivro) {
            $temAcessoLojaLivro = $ehAutorL
                || folivro_loja_ja_possui($pdo, $uid, (int) $produtoLojaLivro['id_produto'])
                || ((int) $produtoLojaLivro['preco_centavos'] === 0 && $produtoLojaLivro['status_produto'] === 'publicado');
        }
    }
}

// Resenhas da Comunidade FOLIVRO
$resenhasComunidade = [];
$minhaResenha = null;
$mediaComunidade = null;
$totalComunidade = 0;
$comunidadeCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

if ($idLivro !== null) {
    $resQ = $pdo->prepare(
        'SELECT r.*, u.nome_usuario
           FROM resenha r
           JOIN usuario u ON u.id_usuario = r.id_usuario
          WHERE r.id_livro = ?
       ORDER BY (r.id_usuario = ?) DESC, r.data_avaliacao_resenha DESC
          LIMIT 25'
    );
    $resQ->execute([$idLivro, $uid]);
    $resenhasComunidade = $resQ->fetchAll();

    foreach ($resenhasComunidade as $r) {
        if ((int) $r['id_usuario'] === $uid) {
            $minhaResenha = $r;
            break;
        }
    }

    $mediaQ = $pdo->prepare('SELECT AVG(nota_resenha) AS media, COUNT(*) AS total FROM resenha WHERE id_livro = ?');
    $mediaQ->execute([$idLivro]);
    $rowMedia = $mediaQ->fetch();
    if ($rowMedia && $rowMedia['total'] > 0) {
        $mediaComunidade = round((float) $rowMedia['media'], 1);
        $totalComunidade = (int) $rowMedia['total'];
    }

    $distQ = $pdo->prepare('SELECT nota_resenha, COUNT(*) AS qtd FROM resenha WHERE id_livro = ? GROUP BY nota_resenha');
    $distQ->execute([$idLivro]);
    foreach ($distQ->fetchAll() as $dRow) {
        $notaEstrela = (int) ($dRow['nota_resenha'] ?? 0);
        if ($notaEstrela >= 1 && $notaEstrela <= 5) {
            $comunidadeCounts[$notaEstrela] = (int) $dRow['qtd'];
        }
    }
}

// Cálculo do Índice Crítico Literário (Inspirado no Metacritic & IMDb)
$olAvg = $dadosOl['ratings']['average'] ?? null;
$olCount = $dadosOl['ratings']['count'] ?? 0;
$olCounts = $dadosOl['ratings']['counts'] ?? [];

// Distribuição combinada de notas (Open Library + Comunidade FOLIVRO)
$distCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$somaOlCounts = 0;
for ($eDist = 1; $eDist <= 5; $eDist++) {
    $somaOlCounts += (int) ($olCounts[$eDist] ?? $olCounts[(string) $eDist] ?? 0);
}
if ($somaOlCounts === 0 && $olAvg !== null && $olAvg > 0 && $olCount > 0) {
    $mediaClamped = max(1.0, min(5.0, (float) $olAvg));
    $piso = (int) floor($mediaClamped);
    $teto = min(5, $piso + 1);
    $frac = $mediaClamped - $piso;
    $olEstimado = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    if ($piso === $teto) {
        $olEstimado[$piso] = (int) $olCount;
    } else {
        $qtdTeto = (int) round($olCount * $frac);
        $qtdPiso = max(0, (int) $olCount - $qtdTeto);
        $olEstimado[$teto] = $qtdTeto;
        $olEstimado[$piso] = $qtdPiso;
    }
    $olCounts = $olEstimado;
}
$distTotal = 0;
for ($eDist = 1; $eDist <= 5; $eDist++) {
    $qtdOlEst = (int) ($olCounts[$eDist] ?? $olCounts[(string) $eDist] ?? 0);
    $qtdComEst = (int) ($comunidadeCounts[$eDist] ?? 0);
    $distCounts[$eDist] = $qtdOlEst + $qtdComEst;
    $distTotal += $distCounts[$eDist];
}

// Média ponderada global: combina Open Library e Comunidade FOLIVRO
$pontosMetascore = null;
$classeMetascore = 'score-neutro';
$rotuloConsenso = 'Aguardando avaliações';
$descricaoConsenso = 'Esta obra ainda não possui avaliações computadas suficientes no catálogo global.';

if ($olAvg !== null && $olCount > 0) {
    // Escala de 0 a 100 baseada na média (1.0 a 5.0)
    $pontosMetascore = (int) round(($olAvg / 5.0) * 100);

    if ($pontosMetascore >= 80) {
        $classeMetascore = 'score-excelente';
        $rotuloConsenso = 'Aclamação Universal da Crítica e Leitores';
        $descricaoConsenso = 'Aclamado unanimemente pela crítica literária e leitores globais como uma obra indispensável.';
    } elseif ($pontosMetascore >= 60) {
        $classeMetascore = 'score-favoravel';
        $rotuloConsenso = 'Recepção Crítica Geralmente Favorável';
        $descricaoConsenso = 'Avaliações amplamente positivas, com forte recomendação da comunidade leitora.';
    } elseif ($pontosMetascore >= 40) {
        $classeMetascore = 'score-misto';
        $rotuloConsenso = 'Avaliações Mistas ou Médias';
        $descricaoConsenso = 'A obra divide opiniões entre os leitores e os críticos literários.';
    } else {
        $classeMetascore = 'score-baixo';
        $rotuloConsenso = 'Recepção Desfavorável';
        $descricaoConsenso = 'A obra recebeu majoritariamente críticas e notas abaixo da média esperada.';
    }
} elseif ($mediaComunidade !== null && $totalComunidade > 0) {
    // Se a Open Library não tiver notas registradas, utiliza o consenso da comunidade FOLIVRO
    $pontosMetascore = (int) round(($mediaComunidade / 5.0) * 100);

    if ($pontosMetascore >= 80) {
        $classeMetascore = 'score-excelente';
        $rotuloConsenso = 'Aclamação da Comunidade FOLIVRO';
        $descricaoConsenso = 'Aclamado com pontuação máxima pelos leitores e resenhistas da comunidade FOLIVRO.';
    } elseif ($pontosMetascore >= 60) {
        $classeMetascore = 'score-favoravel';
        $rotuloConsenso = 'Recepção Favorável da Comunidade';
        $descricaoConsenso = 'Avaliações amplamente positivas recomendadas pelos leitores do FOLIVRO.';
    } elseif ($pontosMetascore >= 40) {
        $classeMetascore = 'score-misto';
        $rotuloConsenso = 'Recepção Mista da Comunidade';
        $descricaoConsenso = 'A obra divide opiniões entre os leitores da comunidade FOLIVRO.';
    } else {
        $classeMetascore = 'score-baixo';
        $rotuloConsenso = 'Recepção Desfavorável';
        $descricaoConsenso = 'A maioria dos leitores da comunidade avaliou esta obra abaixo da média.';
    }
}

$csrf = folivro_escape(folivro_csrf_token());
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= folivro_escape($tituloLivro) ?> — Críticas e Detalhes | FOLIVRO</title>
    <link rel="preconnect" href="https://covers.openlibrary.org" crossorigin>
    <link rel="preconnect" href="https://openlibrary.org">
    <link rel="dns-prefetch" href="https://covers.openlibrary.org">
    <link rel="stylesheet" href="layout.css?v=20261125_01">
    <link rel="stylesheet" href="pesquisa.css?v=20261123_01">
    <meta name="csrf-token" content="<?= $csrf ?>">
</head>

<body>

    <?php $menuAtivo = 'pesquisar'; require __DIR__ . '/includes/menu-lateral.php'; ?>
    <?php require __DIR__ . '/includes/menu-autor.php'; ?>

    <main class="conteudo" id="conteudo-principal">

        <!-- Navegação de migalhas / Breadcrumbs -->
        <nav class="migalhas-navegacao" aria-label="Navegação estrutural">
            <a href="home.php">Início</a> &rsaquo;
            <a href="pesquisa.php">Catálogo de Livros</a> &rsaquo;
            <span aria-current="page"><?= folivro_escape(mb_substr($tituloLivro, 0, 40, 'UTF-8')) ?></span>
        </nav>

        <?php if ($msgSucesso): ?>
            <p class="estado-vazio estado-sucesso" role="status" style="margin-bottom: 24px;"><?= folivro_escape($msgSucesso) ?></p>
        <?php endif; ?>
        <?php if ($msgErro): ?>
            <p class="estado-vazio estado-erro" role="alert" style="margin-bottom: 24px;"><?= folivro_escape($msgErro) ?></p>
        <?php endif; ?>

        <div class="livro-detalhes-container">

            <!-- Coluna Esquerda: Capa, Ações de Leitura e Ficha Técnica Bibliográfica -->
            <aside class="livro-coluna-lateral">
                <div class="livro-capa-destaque">
                    <img src="<?= folivro_escape(folivro_capa_url($capaLivro, (string) $tituloLivroRaw, (string) $autorLivro)) ?>" alt="Capa de <?= folivro_escape($tituloLivro) ?>" class="capa-imagem-alta" loading="lazy">
                </div>

                <!-- Painel de Ações do Leitor -->
                <div class="painel-acoes-livro">
                    <?php if ($bloqueadoModLivro): ?>
                        <div class="cartao-status-leitura">
                            <p class="estado-vazio estado-erro" style="margin:0;">
                                Esta obra foi bloqueada ou suspensa pela moderação e não está disponível para leitura ou aquisição.
                            </p>
                        </div>
                    <?php elseif ($registroLivraria): ?>
                        <?php
                        $st = (string) $registroLivraria['status_livraria'];
                        $pctLido = $progressoUsuario ? min(100, max(0, (int) round((float) $progressoUsuario['porcentagem_progressao']))) : 0;
                        ?>
                        <div class="cartao-status-leitura">
                            <span id="selo-status-sidebar" class="selo-status selo-<?= folivro_escape($st) ?>">
                                Na sua biblioteca: <?= folivro_escape(folivro_status_label($st)) ?>
                            </span>

                            <div class="mini-progresso" role="progressbar" aria-valuenow="<?= $pctLido ?>" aria-valuemin="0" aria-valuemax="100" style="margin: 10px 0 6px;">
                                <span id="barra-progresso-sidebar" style="width: <?= $pctLido ?>%"></span>
                            </div>
                            <p id="texto-progresso-sidebar" style="font-size: 13px; color: var(--texto-secundario); margin-bottom: 12px;"><?= $pctLido ?>% concluído</p>

                            <?php if ($produtoLojaLivro): ?>
                                <?php if (!$temAcessoLojaLivro): ?>
                                    <a href="loja-obra.php?id=<?= (int) $produtoLojaLivro['id_produto'] ?>" class="botao-adicionar-livro botao-destaque-leitor" style="display:flex; align-items:center; justify-content:center; gap:8px; text-align:center; text-decoration:none; margin-bottom: 12px;">
                                        Adquirir obra na loja para ler
                                    </a>
                                <?php elseif (!empty($produtoLojaLivro['id_autor_dos_livros'])): ?>
                                    <a href="obra.php?id=<?= (int) $produtoLojaLivro['id_autor_dos_livros'] ?>" class="botao-adicionar-livro botao-destaque-leitor" style="display:flex; align-items:center; justify-content:center; gap:8px; text-align:center; text-decoration:none; margin-bottom: 12px;">
                                        <?= folivro_icone('livro-abrindo', 'folivro-icon-sm') ?> Iniciar / Continuar Leitura
                                    </a>
                                <?php elseif (!empty($produtoLojaLivro['arquivo_produto']) && ($produtoLojaLivro['arquivo_tipo'] ?? '') === 'pdf'): ?>
                                    <a href="leitor.php?id=<?= (int) $idLivro ?>" class="botao-adicionar-livro botao-destaque-leitor" style="display:flex; align-items:center; justify-content:center; gap:8px; text-align:center; text-decoration:none; margin-bottom: 12px;">
                                        <?= folivro_icone('livro-abrindo', 'folivro-icon-sm') ?> Continuar Leitura no Leitor
                                    </a>
                                <?php elseif (!empty($produtoLojaLivro['arquivo_produto'])): ?>
                                    <a href="loja-download.php?id=<?= (int) $produtoLojaLivro['id_produto'] ?>" class="botao-adicionar-livro botao-destaque-leitor" style="display:flex; align-items:center; justify-content:center; gap:8px; text-align:center; text-decoration:none; margin-bottom: 12px;">
                                        Baixar <?= strtoupper(folivro_escape((string) $produtoLojaLivro['arquivo_tipo'])) ?> para leitura
                                    </a>
                                <?php else: ?>
                                    <p style="font-size: 13px; color: var(--texto-secundario); margin-bottom: 12px;">Conteúdo de leitura indisponível para esta obra.</p>
                                <?php endif; ?>
                            <?php elseif ($workId !== '' && folivro_dp_encontrar_obra($workId, $tituloLivroRaw) !== null): ?>
                                <div id="acao-leitor-ol" data-work-id="<?= folivro_escape($workId) ?>">
                                    <a href="leitor.php?id=<?= (int) $idLivro ?>" class="botao-adicionar-livro botao-destaque-leitor" style="display:flex; align-items:center; justify-content:center; gap:8px; text-align:center; text-decoration:none; margin-bottom: 12px;">
                                        <?= folivro_icone('livro-abrindo', 'folivro-icon-sm') ?> Continuar Leitura no Leitor
                                    </a>
                                </div>
                            <?php elseif ($workId !== ''): ?>
                                <a href="https://openlibrary.org/works/<?= folivro_escape($workId) ?>" target="_blank" rel="noopener noreferrer" class="botao-adicionar-livro botao-destaque-leitor" style="display:flex; align-items:center; justify-content:center; gap:8px; text-align:center; text-decoration:none; margin-bottom: 12px;">
                                    Ver obra na Open Library ↗
                                </a>
                            <?php else: ?>
                                <p style="font-size: 13px; color: var(--texto-secundario); margin-bottom: 12px;">Este registro não possui conteúdo de leitura online disponível.</p>
                            <?php endif; ?>

                            <!-- Alteração rápida de status -->
                            <form method="post" class="form-alterar-status">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="acao" value="alterar_status">
                                <label for="sel-status" style="font-size: 12px; font-weight: 600; display:block; margin-bottom: 4px;">Alterar status:</label>
                                <div style="display:flex; gap:6px;">
                                    <select name="status_livraria" id="sel-status" class="campo-select-status" style="flex:1; padding: 6px 8px; border-radius: 8px; border: 1px solid var(--borda-suave);">
                                        <option value="quero_ler" <?= $st === 'quero_ler' ? 'selected' : '' ?>>Quero ler</option>
                                        <option value="lendo" <?= $st === 'lendo' ? 'selected' : '' ?>>Lendo</option>
                                        <option value="lido" <?= $st === 'lido' ? 'selected' : '' ?>>Lido</option>
                                        <option value="abandonado" <?= $st === 'abandonado' ? 'selected' : '' ?>>Abandonado</option>
                                        <option value="remover">Remover da biblioteca</option>
                                    </select>
                                    <button type="submit" class="filtro-pilula" style="border:none; cursor:pointer;">OK</button>
                                </div>
                            </form>
                        </div>
                    <?php elseif ($produtoLojaLivro): ?>
                        <div class="cartao-status-leitura">
                            <?php if ($temAcessoLojaLivro): ?>
                                <?php if (!empty($produtoLojaLivro['id_autor_dos_livros'])): ?>
                                    <a href="obra.php?id=<?= (int) $produtoLojaLivro['id_autor_dos_livros'] ?>" class="botao-adicionar-livro botao-destaque-leitor" style="display:flex; align-items:center; justify-content:center; gap:8px; text-align:center; text-decoration:none; margin-bottom: 10px;">
                                        <?= folivro_icone('livro-abrindo', 'folivro-icon-sm') ?> Iniciar Leitura
                                    </a>
                                <?php elseif (!empty($produtoLojaLivro['arquivo_produto']) && ($produtoLojaLivro['arquivo_tipo'] ?? '') === 'pdf'): ?>
                                    <a href="leitor.php?id=<?= (int) $idLivro ?>" class="botao-adicionar-livro botao-destaque-leitor" style="display:flex; align-items:center; justify-content:center; gap:8px; text-align:center; text-decoration:none; margin-bottom: 10px;">
                                        <?= folivro_icone('livro-abrindo', 'folivro-icon-sm') ?> Iniciar Leitura
                                    </a>
                                <?php elseif (!empty($produtoLojaLivro['arquivo_produto'])): ?>
                                    <a href="loja-download.php?id=<?= (int) $produtoLojaLivro['id_produto'] ?>" class="botao-adicionar-livro botao-destaque-leitor" style="display:flex; align-items:center; justify-content:center; gap:8px; text-align:center; text-decoration:none; margin-bottom: 10px;">
                                        Baixar <?= strtoupper(folivro_escape((string) $produtoLojaLivro['arquivo_tipo'])) ?>
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="loja-obra.php?id=<?= (int) $produtoLojaLivro['id_produto'] ?>" class="botao-adicionar-livro botao-destaque-leitor" style="display:flex; align-items:center; justify-content:center; gap:8px; text-align:center; text-decoration:none; margin-bottom: 10px;">
                                    Ver na Loja FOLIVRO
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="acao" value="adicionar_biblioteca">
                            <input type="hidden" name="status_livraria" value="quero_ler">
                            <button type="submit" class="botao-adicionar-livro botao-destaque-leitor" style="width:100%; font-size: 15px; padding: 12px 18px; margin-bottom: 10px; display:inline-flex; align-items:center; justify-content:center; gap:8px;">
                                <?= folivro_icone('pasta-biblioteca', 'folivro-icon-sm') ?> Salvar na Minha Biblioteca
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Ficha Técnica Resumida -->
                <div class="ficha-tecnica-livro">
                    <h3>Ficha Bibliográfica</h3>
                    <dl class="lista-detalhes-meta">
                        <div>
                            <dt>Autoria original</dt>
                            <dd><?= folivro_escape($autorLivro) ?></dd>
                        </div>
                        <?php if ($anoPublicacao): ?>
                            <div>
                                <dt>Primeira publicação</dt>
                                <dd><?= (int) $anoPublicacao ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if ($paginasEstimadas > 0): ?>
                            <div>
                                <dt>Volume aproximado</dt>
                                <dd><?= $paginasEstimadas ?> páginas</dd>
                            </div>
                        <?php endif; ?>
                        <div>
                            <dt>Classificação indicativa</dt>
                            <dd>
                                <span class="selo-classificacao-idade selo-idade-<?= folivro_escape($classLivro) ?>">
                                    <?= folivro_escape($siglaIdadeLivro) ?>
                                </span>
                                <span style="margin-left: 6px;"><?= folivro_escape($labelIdadeLivro) ?></span>
                            </dd>
                        </div>
                        <div>
                            <dt>Origem do catálogo</dt>
                            <dd>
                                <?php if (folivro_dp_encontrar_obra($workId, $tituloLivroRaw) !== null): ?>
                                    <strong>Acervo Nacional de Domínio Público</strong> (Art. 41 da Lei nº 9.610/1998)
                                <?php elseif ($workId !== ''): ?>
                                    <a href="https://openlibrary.org/works/<?= folivro_escape($workId) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--cor-vinho); font-weight:600;">
                                        Open Library (<?= folivro_escape($workId) ?>) ↗
                                    </a>
                                <?php else: ?>
                                    Catálogo Interno FOLIVRO
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="aviso-direitos-caixa">
                    <p>
                        <strong>Direitos Autorais e Catálogo:</strong> Os dados biográficos e de recepção crítica são consultados para fins de estudos literários e organização pessoal (Art. 46 da Lei 9.610/1998). O FOLIVRO não comercializa nem redistribui obras de terceiros.
                    </p>
                </div>
            </aside>

            <!-- Coluna Direita: Informações Principais, Metascore/Crítica, Sinopse e Resenhas -->
            <section class="livro-coluna-principal">

                <!-- Cabeçalho da Obra -->
                <header class="cabecalho-livro-detalhes">
                    <h1 class="titulo-livro-obra"><?= folivro_escape($tituloLivro) ?></h1>
                    <?php if ($tituloOriginal && $tituloOriginal !== $tituloLivro): ?>
                        <p style="font-size: 13.5px; color: var(--texto-secundario); margin: 2px 0 6px;">
                            Título original: <em><?= folivro_escape($tituloOriginal) ?></em>
                        </p>
                    <?php endif; ?>
                    <p class="autoria-subtitulo">
                        Por <strong class="nome-autor-destaque"><?= folivro_escape($autorLivro) ?></strong>
                        <?php if ($anoPublicacao): ?>
                            <span class="ano-subtitulo">· Publicado originalmente em <?= (int) $anoPublicacao ?></span>
                        <?php endif; ?>
                    </p>

                    <!-- Tags de Assuntos / Gêneros -->
                    <?php if (!empty($generos)): ?>
                        <div class="tags-generos-container" aria-label="Gêneros literários">
                            <?php foreach ($generos as $g): ?>
                                <a href="pesquisa.php?busca=<?= rawurlencode($g) ?>" class="tag-genero-item" title="Buscar mais livros sobre <?= folivro_escape($g) ?>">
                                    #<?= folivro_escape($g) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </header>

                <!-- PAINEL DE CRÍTICA & RECEPÇÃO LITERÁRIA (ESTILO METACRITIC / IMDB) -->
                <article class="painel-critica-receptividade" aria-label="Recepção crítica e consenso literário">
                    <div class="topo-critica-header">
                        <div>
                            <span class="tag-critica-selo">Índice de Recepção Crítica & Consenso de Leitores</span>
                            <h2>Consenso Crítico Literário</h2>
                        </div>
                        <span class="badge-inspiracao">Baseado em Metacritic & Catálogo Internacional</span>
                    </div>

                    <div class="grade-critica-conteudo">
                        <!-- Metascore Gauge / Placa de Pontuação -->
                        <div class="card-metascore-principal <?= $classeMetascore ?>">
                            <div class="circulo-ou-caixa-score" aria-label="Pontuação Metacrítica: <?= $pontosMetascore !== null ? $pontosMetascore : 'N/A' ?> de 100">
                                <span class="numero-score"><?= $pontosMetascore !== null ? $pontosMetascore : '—' ?></span>
                                <span class="escala-score">/ 100</span>
                            </div>
                            <div class="info-score-rotulo">
                                <h3 class="titulo-consenso"><?= folivro_escape($rotuloConsenso) ?></h3>
                                <p class="desc-consenso"><?= folivro_escape($descricaoConsenso) ?></p>
                                <?php if ($olAvg !== null): ?>
                                    <p class="detalhes-media-estrelas">
                                        <img src="img/icones/star.png" alt="" class="icone-btn-png" style="width:16px; height:16px; vertical-align:text-bottom; filter: invert(75%) sepia(85%) saturate(550%) hue-rotate(350deg);"> <strong><?= number_format($olAvg, 2, ',', '.') ?></strong> / 5.0
                                        <span class="separador-ponto">·</span>
                                        <?= number_format($olCount, 0, ',', '.') ?> votos computados no catálogo global
                                    </p>
                                <?php elseif ($mediaComunidade !== null && $totalComunidade > 0): ?>
                                    <p class="detalhes-media-estrelas">
                                        <img src="img/icones/star.png" alt="" class="icone-btn-png" style="width:16px; height:16px; vertical-align:text-bottom; filter: invert(75%) sepia(85%) saturate(550%) hue-rotate(350deg);"> <strong><?= number_format($mediaComunidade, 1, ',', '.') ?></strong> / 5.0
                                        <span class="separador-ponto">·</span>
                                        <?= $totalComunidade ?> <?= $totalComunidade === 1 ? 'avaliação registrada no FOLIVRO' : 'avaliações registradas no FOLIVRO' ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Distribuição de Avaliações em Barras (Estilo IMDb / Metacritic) -->
                        <div class="card-distribuicao-estrelas">
                            <h4 class="subtitulo-distribuicao">Distribuição de Notas dos Leitores</h4>
                            <div class="barras-distribuicao-lista">
                                <?php
                                for ($estrela = 5; $estrela >= 1; $estrela--):
                                    $qtdEstrela = (int) ($distCounts[$estrela] ?? 0);
                                    $pctEstrela = $distTotal > 0 ? (float) (($qtdEstrela / $distTotal) * 100) : 0.0;
                                ?>
                                    <div class="linha-barra-estrela">
                                        <span class="rotulo-estrela"><?= $estrela ?> ★</span>
                                        <div class="trilho-barra-critica" role="progressbar" aria-valuenow="<?= (int) round($pctEstrela) ?>" aria-valuemin="0" aria-valuemax="100">
                                            <span class="preenchimento-barra cor-estrela-<?= $estrela ?>" style="width: <?= round($pctEstrela, 1) ?>%"></span>
                                        </div>
                                        <span class="valor-contagem-estrela">
                                            <?= number_format($qtdEstrela, 0, ',', '.') ?>
                                            <small>(<?= (int) round($pctEstrela) ?>%)</small>
                                        </span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($totalComunidade > 0): ?>
                        <div class="resumo-folivro-comunidade-banner">
                            <span class="icone-comunidade"><?= folivro_icone('folhas-voando-f', 'folivro-icon-md') ?></span>
                            <div>
                                <strong>Comunidade FOLIVRO:</strong> Média de <strong><?= $mediaComunidade ?> <img src="img/icones/star.png" alt="" class="icone-btn-png" style="width:14px; height:14px; vertical-align:text-bottom; filter: invert(75%) sepia(85%) saturate(550%) hue-rotate(350deg);"></strong> baseada em <?= $totalComunidade ?> resenha(s) de leitores da nossa plataforma.
                            </div>
                        </div>
                    <?php endif; ?>
                </article>

                <!-- Sinopse e Apresentação da Obra -->
                <?php
                $ehOpenLibraryFicha = !$produtoLojaLivro
                    && $workId !== ''
                    && folivro_dp_encontrar_obra($workId, $tituloLivroRaw) === null;
                ?>
                <?php if ($ehOpenLibraryFicha && !$bloqueadoModLivro): ?>
                    <section class="card-secao-livro" aria-label="Organização de leitura na biblioteca">
                        <h2 class="titulo-secao-livro">Organização de Leitura na Biblioteca</h2>
                        <?php if ($registroLivraria): ?>
                            <?php
                            $stFicha = (string) ($registroLivraria['status_livraria'] ?? 'lendo');
                            $pagAtualFicha = (int) ($progressoUsuario['pagina_atual_progressao'] ?? 0);
                            $totalPagFicha = (int) (($progressoUsuario['total_paginas_progressao'] ?? 0) ?: $paginasEstimadas ?: 1);
                            ?>
                            <div class="grade-painel-progresso-leitor">
                                <form id="form-progresso-ficha" class="progresso-form form-largura-total card-progresso-nivelado">
                                    <h3 class="titulo-com-icone-folivro"><?= folivro_icone('pagina-marcador', 'folivro-icon-sm') ?> <span>Registrar progresso de páginas</span></h3>
                                    <p class="texto-apoio-pequeno" style="margin:0;">Atualize a página em que você parou para sincronizar sua barra de leitura e metas.</p>
                                    <div class="grade-campos-dupla">
                                        <label for="pagina_atual_ficha">Página atual
                                            <input id="pagina_atual_ficha" type="number" name="pagina_atual" min="0" value="<?= $pagAtualFicha ?>" required>
                                        </label>
                                        <label for="total_paginas_ficha">Total de páginas
                                            <input id="total_paginas_ficha" type="number" name="total_paginas" min="1" value="<?= $totalPagFicha ?>" required>
                                        </label>
                                    </div>
                                    <div class="rodape-card-nivelado">
                                        <button class="botao-adicionar-livro" type="submit">Salvar progresso</button>
                                        <span id="progresso-mensagem-ficha" class="msg-inline-progresso" role="status"></span>
                                    </div>
                                </form>

                                <form method="post" class="progresso-form form-largura-total card-progresso-nivelado">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="acao" value="alterar_status">
                                    <h3 class="titulo-com-icone-folivro"><?= folivro_icone('pasta-biblioteca', 'folivro-icon-sm') ?> <span>Status na biblioteca</span></h3>
                                    <p class="texto-apoio-pequeno" style="margin:0;">Organize em qual estante este livro fica na sua biblioteca pessoal.</p>
                                    <label for="status_livraria_ficha">Situação atual da leitura
                                        <select id="status_livraria_ficha" name="status_livraria">
                                            <?php foreach (['quero_ler' => 'Quero ler', 'lendo' => 'Lendo atualmente', 'lido' => 'Concluído (Lido)', 'abandonado' => 'Pausado / Abandonado', 'remover' => 'Remover da biblioteca'] as $valSt => $rotuloSt): ?>
                                                <option value="<?= $valSt ?>" <?= $stFicha === $valSt ? 'selected' : '' ?>><?= $rotuloSt ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <div class="rodape-card-nivelado">
                                        <button class="botao-adicionar-livro" type="submit">Atualizar status</button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="post" class="progresso-form form-largura-total" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="acao" value="adicionar_biblioteca">
                                <p class="texto-apoio-pequeno" style="margin:0 0 12px;">Adicione esta obra à sua biblioteca pessoal para acompanhar páginas lidas, metas e estantes.</p>
                                <div class="grade-campos-dupla" style="align-items: end;">
                                    <label for="status_inicial_ficha">Escolher estante inicial
                                        <select id="status_inicial_ficha" name="status_livraria">
                                            <option value="quero_ler">Quero ler</option>
                                            <option value="lendo" selected>Lendo atualmente</option>
                                            <option value="lido">Concluído (Lido)</option>
                                        </select>
                                    </label>
                                    <div>
                                        <button class="botao-adicionar-livro" type="submit" style="width:100%;">Salvar na minha biblioteca</button>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <section class="card-secao-livro">
                    <h2 class="titulo-secao-livro">Sinopse e Contexto da Obra</h2>
                    <div class="texto-sinopse-formatado">
                        <?php if (!empty($sinopseLivro)): ?>
                            <?= folivro_formatar_sinopse_html($sinopseLivro) ?>
                        <?php else: ?>
                            <div class="card-sinopse-contextual" style="padding: 20px 24px; background: var(--fundo-card); border-radius: 12px; border: 1px solid var(--borda-suave); line-height: 1.65;">
                                <p style="margin-bottom: 12px; font-size: 15px;">
                                    <strong><?= folivro_escape($tituloLivro) ?></strong> é uma criação literária de <strong><?= folivro_escape($autorLivro) ?></strong><?= $anoPublicacao ? ', com primeira publicação registrada em <strong>' . (int) $anoPublicacao . '</strong>' : '' ?>.
                                </p>
                                <p style="margin-bottom: 12px; font-size: 14px; color: var(--texto-secundario);">
                                    Esta obra está integrada ao acervo bibliográfico aberto do FOLIVRO para organização pessoal, estudos e preservação cultural. Embora o texto completo do resumo editorial ainda não conste nos índices abertos de catálogo, você pode consultar a ficha técnica bibliográfica e os tópicos temáticos associados.
                                </p>
                                <?php if (!empty($generos)): ?>
                                    <p style="margin-bottom: 12px; font-size: 13.5px; color: var(--texto-principal);">
                                        <strong>Tópicos literários catalogados:</strong> <?= folivro_escape(implode(', ', $generos)) ?>.
                                    </p>
                                <?php endif; ?>
                                <p style="font-size: 13.5px; color: var(--cor-vinho); font-weight: 500; margin-bottom: 0;">
                                    Você já leu esta obra? Ajude outros leitores compartilhando suas reflexões e avaliação crítica no formulário abaixo!
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Resenhas e Opiniões da Comunidade FOLIVRO -->
                <section class="card-secao-livro" id="resenhas-comunidade">
                    <div class="topo-resenhas-flex">
                        <div>
                            <h2 class="titulo-secao-livro" style="margin-bottom: 2px;">Resenhas da Comunidade FOLIVRO</h2>
                            <p style="font-size: 14px; color: var(--texto-secundario);">Críticas escritas pelos próprios leitores e autores do FOLIVRO</p>
                        </div>
                        <?php if ($minhaResenha): ?>
                            <span class="selo-status selo-lido">Você já resenhou esta obra</span>
                        <?php endif; ?>
                    </div>

                    <!-- Formulário para Escrever / Atualizar Resenha -->
                    <div class="caixa-formulario-resenha">
                        <h3><?= $minhaResenha ? 'Editar sua resenha' : 'Deixar sua avaliação e crítica' ?></h3>
                        <form method="post" class="form-resenha-estilizado">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="acao" value="avaliar_livro">

                            <div class="grupo-campo-resenha">
                                <label for="nota_resenha" class="rotulo-campo">Sua nota de avaliação:</label>
                                <select name="nota_resenha" id="nota_resenha" required class="select-nota-estrela">
                                    <option value="">Selecione de 1 a 5 estrelas</option>
                                    <option value="5" <?= ($minhaResenha['nota_resenha'] ?? 0) === 5 ? 'selected' : '' ?>>★★★★★ — 5 estrelas (Excelente / Obra-prima)</option>
                                    <option value="4" <?= ($minhaResenha['nota_resenha'] ?? 0) === 4 ? 'selected' : '' ?>>★★★★☆ — 4 estrelas (Muito bom)</option>
                                    <option value="3" <?= ($minhaResenha['nota_resenha'] ?? 0) === 3 ? 'selected' : '' ?>>★★★☆☆ — 3 estrelas (Regular / Bom)</option>
                                    <option value="2" <?= ($minhaResenha['nota_resenha'] ?? 0) === 2 ? 'selected' : '' ?>>★★☆☆☆ — 2 estrelas (Abaixo do esperado)</option>
                                    <option value="1" <?= ($minhaResenha['nota_resenha'] ?? 0) === 1 ? 'selected' : '' ?>>★☆☆☆☆ — 1 estrela (Fraco / Não recomendo)</option>
                                </select>
                            </div>

                            <div class="grupo-campo-resenha">
                                <label for="titulo_resenha" class="rotulo-campo">Título da resenha (opcional):</label>
                                <input type="text" name="titulo_resenha" id="titulo_resenha" maxlength="150" placeholder="Ex: Uma reflexão profunda sobre os costumes da época" value="<?= folivro_escape((string) ($minhaResenha['titulo_resenha'] ?? '')) ?>" class="input-titulo-resenha">
                            </div>

                            <div class="grupo-campo-resenha">
                                <label for="resenha_resenha" class="rotulo-campo">Texto da resenha:</label>
                                <textarea name="resenha_resenha" id="resenha_resenha" rows="4" maxlength="5000" required placeholder="Escreva sua opinião crítica sobre a escrita, enredo, personagens e ritmo da obra..." class="textarea-resenha"><?= folivro_escape((string) ($minhaResenha['resenha_resenha'] ?? '')) ?></textarea>
                            </div>

                            <button type="submit" class="botao-adicionar-livro" style="font-size: 14px; padding: 10px 22px;">
                                <?= $minhaResenha ? 'Atualizar minha resenha' : 'Publicar minha resenha' ?>
                            </button>
                        </form>
                    </div>

                    <!-- Lista de Resenhas da Comunidade -->
                    <div class="lista-resenhas-comunidade">
                        <?php if (empty($resenhasComunidade)): ?>
                            <p class="estado-vazio" style="margin-top: 16px;">
                                Nenhuma resenha foi publicada pela comunidade FOLIVRO para esta obra até o momento. Seja o primeiro a compartilhar sua análise crítica no formulário acima!
                            </p>
                        <?php else: ?>
                            <?php foreach ($resenhasComunidade as $res): ?>
                                <article class="item-resenha-card <?= (int) $res['id_usuario'] === $uid ? 'minha-resenha-destaque' : '' ?>">
                                    <header class="topo-item-resenha">
                                        <div class="avatar-nome-leitor">
                                            <span class="avatar-leitor-inicial"><?= folivro_escape(mb_strtoupper(mb_substr($res['nome_usuario'], 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                                            <div>
                                                <strong><?= folivro_escape($res['nome_usuario']) ?></strong>
                                                <?php if ((int) $res['id_usuario'] === $uid): ?>
                                                    <span class="selo-voce">(Sua resenha)</span>
                                                <?php endif; ?>
                                                <time class="data-resenha" datetime="<?= folivro_escape($res['data_avaliacao_resenha']) ?>">
                                                    <?= date('d/m/Y', strtotime($res['data_avaliacao_resenha'])) ?>
                                                </time>
                                            </div>
                                        </div>
                                        <div class="estrelas-resenha-item" aria-label="Avaliação: <?= (int) $res['nota_resenha'] ?> estrelas">
                                            <?= str_repeat('★', (int) $res['nota_resenha']) ?><?= str_repeat('☆', 5 - (int) $res['nota_resenha']) ?>
                                        </div>
                                    </header>

                                    <?php if (!empty($res['titulo_resenha'])): ?>
                                        <h4 class="titulo-item-resenha"><?= folivro_escape($res['titulo_resenha']) ?></h4>
                                    <?php endif; ?>

                                    <p class="corpo-item-resenha"><?= nl2br(folivro_escape($res['resenha_resenha'])) ?></p>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- ESPAÇO PREPARADO PARA RECOMENDAÇÕES FUTURAS (MODULAR & DESACOPLADO) -->
                <section class="card-secao-livro slot-recomendacoes-futuras">
                    <div class="topo-recomendacao-flex">
                        <div>
                            <span class="selo-recom-futuro">Módulo Inteligente em Preparação</span>
                            <h2 class="titulo-secao-livro">Obras Recomendadas e Livros Semelhantes</h2>
                        </div>
                    </div>

                    <div class="card-aviso-recomendacao-ia">
                        <div class="icone-ia-box"><?= folivro_icone('circulo-gradiente-f', 'folivro-icon-lg') ?></div>
                        <div class="texto-ia-box">
                            <h3>Motor de Recomendação Literária (Em Breve)</h3>
                            <p>
                                A arquitetura do FOLIVRO já possui o espaço e as rotas preparadas para a futura integração da <strong>API de recomendação inteligente</strong>, que sugerirá títulos personalizados com base no seu histórico de leituras, preferências de gênero e afinidade literária.
                            </p>
                            <?php if (!empty($generos)): ?>
                                <p style="margin-top: 10px; font-size: 13px;">
                                    <strong>Enquanto isso, explore mais livros semelhantes no catálogo:</strong>
                                </p>
                                <div class="atalhos-generos-recomendacao">
                                    <?php foreach (array_slice($generos, 0, 4) as $s): ?>
                                        <a href="pesquisa.php?busca=<?= rawurlencode($s) ?>" class="botao-mais" style="font-size: 12px; padding: 5px 12px;">
                                            Ver mais sobre “<?= folivro_escape($s) ?>” &rarr;
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

            </section>
        </div>

    </main>

    <script>
        (() => {
            const box = document.getElementById('acao-leitor-ol');
            if (!box || !box.dataset.workId) return;
            fetch(`api/leitura-disponivel.php?work=${encodeURIComponent(box.dataset.workId)}`)
                .then((r) => (r.ok ? r.json() : null))
                .then((data) => {
                    if (!data) return;
                    if (data.availability === 'unavailable') {
                        const aviso = document.createElement('p');
                        aviso.style.fontSize = '13px';
                        aviso.style.color = 'var(--texto-secundario)';
                        aviso.style.marginBottom = '12px';
                        aviso.textContent = 'Leitura online indisponível na Open Library para esta edição.';
                        box.replaceChildren(aviso);
                    }
                })
                .catch(() => {});
        })();

        (() => {
            const formProg = document.getElementById('form-progresso-ficha');
            if (!formProg) return;
            const idLivroAtual = <?= (int) ($idLivro ?? 0) ?>;
            const msg = document.getElementById('progresso-mensagem-ficha');
            formProg.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                if (!idLivroAtual) return;
                if (msg) msg.textContent = 'Salvando…';
                try {
                    const r = await fetch('api/progresso.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({
                            id_livro: idLivroAtual,
                            pagina_atual: Number(formProg.pagina_atual.value),
                            total_paginas: Number(formProg.total_paginas.value)
                        })
                    });
                    const d = await r.json();
                    if (!r.ok) throw new Error(d.error || 'Falha ao salvar progresso.');
                    const pct = Math.max(0, Math.min(100, Math.round(Number(d.porcentagem) || 0)));
                    if (msg) msg.textContent = `Progresso salvo: ${pct}%.`;
                    const barraSide = document.getElementById('barra-progresso-sidebar');
                    const textoSide = document.getElementById('texto-progresso-sidebar');
                    const seloSide = document.getElementById('selo-status-sidebar');
                    const selSide = document.getElementById('sel-status');
                    const selFicha = document.getElementById('status_livraria_ficha');
                    if (barraSide) barraSide.style.width = `${pct}%`;
                    if (textoSide) textoSide.textContent = `${pct}% concluído`;
                    if (d.status && d.statusLabel && seloSide) {
                        seloSide.className = `selo-status selo-${d.status}`;
                        seloSide.textContent = `Na sua biblioteca: ${d.statusLabel}`;
                    }
                    if (d.status && selSide) selSide.value = d.status;
                    if (d.status && selFicha) selFicha.value = d.status;
                } catch (e) {
                    if (msg) msg.textContent = e.message || 'Não foi possível salvar.';
                }
            });
        })();
    </script>

</body>

</html>
