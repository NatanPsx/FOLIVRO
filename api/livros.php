<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/traducao.php';

folivro_require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$rawQuery = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$isDestaques = isset($_GET['destaques']) || !isset($_GET['q']) || in_array(mb_strtolower($rawQuery, 'UTF-8'), ['', 'destaques', 'populares', 'trending'], true);

if ($isDestaques) {
    $query = 'destaques';
} else {
    $query = $rawQuery;
    $queryLength = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);
    if ($queryLength > 480) {
        http_response_code(400);
        echo json_encode(['error' => 'Informe um termo de busca válido.', 'items' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$limit = isset($_GET['limit']) ? filter_var($_GET['limit'], FILTER_VALIDATE_INT) : 12;
$limit = max(1, min(24, is_int($limit) ? $limit : 12));

$offset = isset($_GET['offset']) ? filter_var($_GET['offset'], FILTER_VALIDATE_INT) : null;
if (!is_int($offset) || $offset < 0) {
    $page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : 1;
    $page = max(1, is_int($page) ? $page : 1);
    $offset = ($page - 1) * $limit;
}

$sort = isset($_GET['sort']) && is_string($_GET['sort']) ? trim($_GET['sort']) : 'readinglog';
if (!in_array($sort, ['readinglog', 'new', 'old', 'title'], true)) {
    $sort = 'readinglog';
}

if (!function_exists('folivro_remover_acentos')) {
    function folivro_remover_acentos(string $str): string {
        static $map = [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n',
            'Á'=>'a','À'=>'a','Ã'=>'a','Â'=>'a','Ä'=>'a',
            'É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e',
            'Í'=>'i','Ì'=>'i','Î'=>'i','Ï'=>'i',
            'Ó'=>'o','Ò'=>'o','Õ'=>'o','Ô'=>'o','Ö'=>'o',
            'Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u',
            'Ç'=>'c','Ñ'=>'n'
        ];
        return strtr($str, $map);
    }
}

if (!function_exists('folivro_normalizar_titulo_chave')) {
    function folivro_normalizar_titulo_chave(string $title): string {
        $t = mb_strtolower($title, 'UTF-8');
        // Remove parênteses e colchetes (inclusive se não foram fechados na resposta bruta)
        $t = preg_replace('/\([^\)]*(\)|$)/u', '', $t);
        $t = preg_replace('/\[[^\]]*(\]|$)/u', '', $t);
        $parts = preg_split('/[:\-—]/u', $t);
        $main = trim($parts[0] ?? $t);
        $main = preg_replace('/^(o|a|os|as|um|uma|uns|umas|the|an)\s+/iu', '', $main);
        $main = folivro_remover_acentos($main);
        $main = preg_replace('/[^a-z0-9]/i', '', $main);
        return strtolower($main);
    }
}

if (!function_exists('folivro_executar_curl_multi')) {
    function folivro_executar_curl_multi(array $urls, int $timeout = 10, bool $verifySsl = true): array {
        if (empty($urls)) return [];
        if (!function_exists('curl_multi_init')) {
            $results = [];
            $ctx = stream_context_create([
                'http' => ['method' => 'GET', 'timeout' => $timeout, 'header' => "User-Agent: FOLIVRO-Academic-Demo/1.0\r\nAccept: application/json\r\n"],
                'ssl' => ['verify_peer' => $verifySsl, 'verify_peer_name' => $verifySsl]
            ]);
            foreach ($urls as $k => $u) {
                $r = @file_get_contents($u, false, $ctx);
                if ($r) $results[$k] = $r;
            }
            if (empty($results) && $verifySsl) {
                return folivro_executar_curl_multi($urls, $timeout, false);
            }
            return $results;
        }

        $mh = curl_multi_init();
        if (defined('CURLMOPT_PIPELINING') && defined('CURLPIPE_NOTHING')) {
            @curl_multi_setopt($mh, CURLMOPT_PIPELINING, CURLPIPE_NOTHING);
        }
        $handles = [];
        foreach ($urls as $k => $u) {
            $ch = curl_init($u);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => $verifySsl,
                CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
                CURLOPT_USERAGENT => 'FOLIVRO-Academic-Demo/1.0 (TCC Platform; contact@folivro.local)',
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$k] = $ch;
        }

        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
            if ($active) {
                if (curl_multi_select($mh, 0.1) === -1) {
                    usleep(20000);
                }
            }
        } while ($active && $mrc === CURLM_OK);

        $results = [];
        foreach ($handles as $k => $ch) {
            $content = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode === 200 && is_string($content) && $content !== '') {
                $results[$k] = $content;
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        if (empty($results) && $verifySsl) {
            return folivro_executar_curl_multi($urls, $timeout, false);
        }
        return $results;
    }
}

if (!function_exists('folivro_consultar_e_filtrar_catalogo')) {
    function folivro_consultar_e_filtrar_catalogo(string $query, string $sort): array {
        $cacheDir = __DIR__ . '/../cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
            $cacheDir = sys_get_temp_dir() . '/folivro_cache';
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0777, true);
            }
        }

        $fields = 'key,title,author_name,first_publish_year,cover_i,number_of_pages_median,ratings_count,ratings_average,ratings_sortable,readinglog_count,edition_count';

        // MODO 1: Catálogo de Destaques Gerais (Livros mais populares e bem avaliados)
        if ($query === 'destaques') {
            $cacheFile = $cacheDir . '/destaques_populares_' . md5($sort) . '.json';
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
                $cached = json_decode((string) file_get_contents($cacheFile), true);
                if (is_array($cached) && count($cached) >= 18) {
                    return $cached;
                }
            }

            $urlsDestaques = [
                'global' => 'https://openlibrary.org/search.json?subject=classic_literature&sort=readinglog&limit=55&fields=' . rawurlencode($fields),
                'br' => 'https://openlibrary.org/search.json?subject=brazilian_literature&sort=readinglog&limit=35&fields=' . rawurlencode($fields),
                'machado' => 'https://openlibrary.org/search.json?author=Machado+de+Assis&sort=readinglog&limit=15&fields=' . rawurlencode($fields),
            ];

            $resMulti = folivro_executar_curl_multi($urlsDestaques, 10);
            $docs1 = isset($resMulti['global']) ? (json_decode($resMulti['global'], true)['docs'] ?? []) : [];
            $docs2 = isset($resMulti['br']) ? (json_decode($resMulti['br'], true)['docs'] ?? []) : [];
            $docs3 = isset($resMulti['machado']) ? (json_decode($resMulti['machado'], true)['docs'] ?? []) : [];
            $allDocs = array_merge($docs1, $docs2, $docs3);

            $cleanBooksByNorm = [];

            foreach ($allDocs as $book) {
                if (!is_array($book) || empty($book['title'])) {
                    continue;
                }
                $rawTitle = $book['title'];
                $title = trim(preg_replace('/\s+/u', ' ', is_string($rawTitle) ? $rawTitle : ''));
                if ($title === '' || mb_strlen($title, 'UTF-8') < 2) {
                    continue;
                }

                // Descarta caracteres puramente orientais, cirílicos ou não-latinos caso não tenham tradução mapeada
                $infoTitulo = folivro_localizar_titulo($title);
                $tituloFormatado = mb_substr($infoTitulo['titulo'], 0, 200, 'UTF-8');
                if (preg_match('/[\x{0400}-\x{04FF}\x{4e00}-\x{9fa5}\x{3040}-\x{30ff}\x{ac00}-\x{d7af}]/u', $tituloFormatado)) {
                    continue;
                }

                $workId = isset($book['key']) && is_string($book['key']) && preg_match('#^/works/(OL[0-9]+W)$#', $book['key'], $match)
                    ? $match[1]
                    : '';
                if ($workId === '') {
                    continue;
                }

                $authors = isset($book['author_name']) && is_array($book['author_name'])
                    ? array_slice(array_values(array_filter(array_map(function ($a) {
                        if (!is_string($a)) return '';
                        // Limpa ideogramas caso o nome latino esteja entre parênteses, ex: "孙武 (Sun Tzu)" -> "Sun Tzu"
                        $a = trim((string) preg_replace('/[\x{4e00}-\x{9fa5}\x{3040}-\x{30ff}\x{ac00}-\x{d7af}]+/u', '', $a));
                        $a = trim($a, " ()[]\t\n\r\0\x0B");
                        return $a;
                    }, $book['author_name']), function ($a) {
                        if ($a === '' || mb_strlen($a, 'UTF-8') <= 1) return false;
                        $al = mb_strtolower($a, 'UTF-8');
                        return !str_contains($al, 'not identified') && !str_contains($al, 'unknown') && !str_contains($al, 'desconhecido');
                    })), 0, 3)
                    : [];
                if (empty($authors) || count($book['author_name'] ?? []) > 5) {
                    continue;
                }

                $coverId = isset($book['cover_i']) ? filter_var($book['cover_i'], FILTER_VALIDATE_INT) : false;
                $hasCover = ($coverId !== false && $coverId > 0);
                $editions = isset($book['edition_count']) && is_numeric($book['edition_count']) ? (int) $book['edition_count'] : 0;
                $ratings = isset($book['ratings_count']) && is_numeric($book['ratings_count']) ? (int) $book['ratings_count'] : 0;
                $ratingAvg = isset($book['ratings_average']) && is_numeric($book['ratings_average']) ? round((float) $book['ratings_average'], 2) : null;
                $ratingSortable = isset($book['ratings_sortable']) && is_numeric($book['ratings_sortable']) ? round((float) $book['ratings_sortable'], 4) : 0.0;
                $readingLog = isset($book['readinglog_count']) && is_numeric($book['readinglog_count']) ? (int) $book['readinglog_count'] : 0;

                // Na vitrine de destaques ("mais populares e bem avaliados"), exige capa e avaliação real (> 0 votos)
                if (!$hasCover || $ratings < 1 || $ratingAvg === null) {
                    continue;
                }

                $origTitle = $infoTitulo['original'];
                if (is_string($origTitle) && preg_match('/[\x{0400}-\x{04FF}\x{4e00}-\x{9fa5}\x{3040}-\x{30ff}\x{ac00}-\x{d7af}]/u', $origTitle)) {
                    $origTitle = null;
                }

                $normKey = folivro_normalizar_titulo_chave($tituloFormatado);
                if ($normKey === '') {
                    $normKey = $workId;
                }

                $candidato = [
                    'workId' => $workId,
                    'title' => $tituloFormatado,
                    'originalTitle' => $origTitle,
                    'authors' => $authors,
                    'year' => isset($book['first_publish_year']) && is_numeric($book['first_publish_year']) && (int) $book['first_publish_year'] > 0 ? (int) $book['first_publish_year'] : null,
                    'pages' => isset($book['number_of_pages_median']) && is_numeric($book['number_of_pages_median']) && (int) $book['number_of_pages_median'] > 0 ? (int) $book['number_of_pages_median'] : 0,
                    'coverUrl' => $hasCover ? 'https://covers.openlibrary.org/b/id/' . $coverId . '-M.jpg' : null,
                    'workUrl' => 'https://openlibrary.org/works/' . $workId,
                    'editions' => $editions,
                    'readingLog' => $readingLog,
                    'ratingsCount' => $ratings,
                    'ratingAvg' => $ratingAvg,
                    'ratingSortable' => $ratingSortable,
                    'hasCover' => $hasCover,
                ];

                if (isset($cleanBooksByNorm[$normKey])) {
                    $existente = $cleanBooksByNorm[$normKey];
                    if (!$existente['hasCover'] && $candidato['hasCover']) {
                        $cleanBooksByNorm[$normKey] = $candidato;
                    } elseif ($existente['hasCover'] === $candidato['hasCover']) {
                        if ($candidato['ratingSortable'] > $existente['ratingSortable'] || ($candidato['readingLog'] + $candidato['editions']) > ($existente['readingLog'] + $existente['editions'])) {
                            $cleanBooksByNorm[$normKey] = $candidato;
                        }
                    }
                } else {
                    $cleanBooksByNorm[$normKey] = $candidato;
                }
            }

            $finalList = array_values($cleanBooksByNorm);

            // Ordena os destaques: os mais bem avaliados e mais populares primeiro
            usort($finalList, function ($a, $b) {
                $aTemNota = ($a['ratingsCount'] > 0 && $a['ratingAvg'] !== null);
                $bTemNota = ($b['ratingsCount'] > 0 && $b['ratingAvg'] !== null);

                if ($aTemNota && !$bTemNota) return -1;
                if (!$aTemNota && $bTemNota) return 1;

                if ($aTemNota && $bTemNota) {
                    if (abs($a['ratingSortable'] - $b['ratingSortable']) > 0.0001) {
                        return ($b['ratingSortable'] <=> $a['ratingSortable']);
                    }
                    if (abs($a['ratingAvg'] - $b['ratingAvg']) > 0.01) {
                        return ($b['ratingAvg'] <=> $a['ratingAvg']);
                    }
                    return ($b['ratingsCount'] <=> $a['ratingsCount']);
                }

                if ($b['readingLog'] !== $a['readingLog']) {
                    return ($b['readingLog'] <=> $a['readingLog']);
                }
                return ($b['editions'] <=> $a['editions']);
            });

            if (count($finalList) >= 18) {
                @file_put_contents($cacheFile, json_encode($finalList, JSON_UNESCAPED_UNICODE));
                return $finalList;
            }
            if (empty($finalList) && file_exists($cacheFile)) {
                $cached = json_decode((string) file_get_contents($cacheFile), true);
                if (is_array($cached) && !empty($cached)) {
                    return $cached;
                }
            }
            return $finalList;
        }

        // MODO 2: Busca por termo específico com inteligência bilíngue (Português <-> Inglês)
        $normBusca = function_exists('folivro_normalizar_busca') ? folivro_normalizar_busca($query) : mb_strtolower($query, 'UTF-8');
        $cacheKey = md5($normBusca . '_' . $sort);
        $cacheFile = $cacheDir . '/search_' . $cacheKey . '.json';

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        // Obtém termos equivalentes em português e inglês para a busca
        $equiv = function_exists('folivro_obter_equivalencias_busca') ? folivro_obter_equivalencias_busca($query) : ['queries_ol' => [$query], 'palavras_relevancia' => []];
        $queriesParaBuscar = $equiv['queries_ol'];
        $palavrasRelevancia = $equiv['palavras_relevancia'];

        // Se não tiver palavras de relevância específicas, extrai da consulta original
        if (empty($palavrasRelevancia)) {
            $stopWords = ['dom', 'don', 'de', 'do', 'da', 'dos', 'das', 'the', 'of', 'and', 'em', 'um', 'uma', 'uns', 'umas', 'por', 'com', 'sem', 'sob', 'sobre'];
            $rawWords = array_filter(array_map('trim', explode(' ', mb_strtolower(folivro_remover_acentos($query), 'UTF-8'))), function ($w) {
                return mb_strlen($w, 'UTF-8') >= 2;
            });
            $palavrasRelevancia = array_values(array_filter($rawWords, function ($w) use ($stopWords) {
                return !in_array($w, $stopWords, true) && mb_strlen($w, 'UTF-8') >= 3;
            }));
            if (empty($palavrasRelevancia)) {
                $palavrasRelevancia = $rawWords;
            }
        }

        // Constrói URLs de consulta e busca em paralelo com curl_multi
        $urlsBusca = [];
        foreach ($queriesParaBuscar as $idx => $qOl) {
            $urlsBusca['q_' . $idx] = 'https://openlibrary.org/search.json?q=' . rawurlencode($qOl)
                . '&sort=' . rawurlencode($sort)
                . '&limit=40'
                . '&fields=' . rawurlencode($fields);
        }

        $resMulti = folivro_executar_curl_multi($urlsBusca, 6);
        $allDocs = [];
        foreach ($resMulti as $conteudoJson) {
            $payload = json_decode($conteudoJson, true);
            if (is_array($payload) && isset($payload['docs']) && is_array($payload['docs'])) {
                $allDocs = array_merge($allDocs, $payload['docs']);
            }
        }

        if (empty($allDocs)) {
            if (file_exists($cacheFile)) {
                $cached = json_decode((string) file_get_contents($cacheFile), true);
                if (is_array($cached) && !empty($cached)) {
                    return $cached;
                }
            }
            return [];
        }

        $cleanBooksByNorm = [];

        foreach ($allDocs as $book) {
            if (!is_array($book) || !isset($book['title'])) {
                continue;
            }

            $rawTitle = $book['title'];
            $title = trim(preg_replace('/\s+/u', ' ', is_string($rawTitle) ? $rawTitle : ''));
            if ($title === '' || mb_strlen($title, 'UTF-8') < 2) {
                continue;
            }

            if (preg_match('/^[\s\.\-_,\?!:;·\(\)\[\]{}#@&]+$/u', $title)) {
                continue;
            }

            $titleLower = mb_strtolower($title, 'UTF-8');
            if (in_array($titleLower, ['untitled', '[untitled]', 'sem título', 'no title', 'unknown', 'desconhecido'], true)) {
                continue;
            }

            $workId = isset($book['key']) && is_string($book['key']) && preg_match('#^/works/(OL[0-9]+W)$#', $book['key'], $match)
                ? $match[1]
                : '';
            if ($workId === '') {
                continue;
            }

            $authors = isset($book['author_name']) && is_array($book['author_name'])
                ? array_slice(array_values(array_filter(array_map('trim', $book['author_name']), function ($a) {
                    return is_string($a) && $a !== '' && mb_strlen($a, 'UTF-8') > 1;
                })), 0, 3)
                : [];
            if (empty($authors) || count($book['author_name'] ?? []) > 5) {
                continue;
            }

            $coverId = isset($book['cover_i']) ? filter_var($book['cover_i'], FILTER_VALIDATE_INT) : false;
            $hasCover = ($coverId !== false && $coverId > 0);
            $editions = isset($book['edition_count']) && is_numeric($book['edition_count']) ? (int) $book['edition_count'] : 0;
            $ratings = isset($book['ratings_count']) && is_numeric($book['ratings_count']) ? (int) $book['ratings_count'] : 0;
            $ratingAvg = isset($book['ratings_average']) && is_numeric($book['ratings_average']) ? round((float) $book['ratings_average'], 2) : null;
            $ratingSortable = isset($book['ratings_sortable']) && is_numeric($book['ratings_sortable']) ? round((float) $book['ratings_sortable'], 4) : 0.0;
            $readingLog = isset($book['readinglog_count']) && is_numeric($book['readinglog_count']) ? (int) $book['readinglog_count'] : 0;

            // Tradução do título para localização e verificação de relevância
            $infoTitulo = folivro_localizar_titulo($title);
            $tituloFormatado = mb_substr($infoTitulo['titulo'], 0, 200, 'UTF-8');
            $tituloOriginal = $infoTitulo['original'] ?? $title;

            // FILTRO DE RELEVÂNCIA BILÍNGUE:
            // Testa as palavras-chave contra o título traduzido em português, o título original em inglês e os autores!
            $authorsText = mb_strtolower(implode(' ', $authors), 'UTF-8');
            $textoBuscaCompleto = mb_strtolower(folivro_remover_acentos("{$tituloFormatado} {$tituloOriginal} {$authorsText}"), 'UTF-8');

            $relevante = false;
            foreach ($palavrasRelevancia as $qw) {
                $qwNorm = mb_strtolower(folivro_remover_acentos($qw), 'UTF-8');
                if ($qwNorm !== '' && strpos($textoBuscaCompleto, $qwNorm) !== false) {
                    $relevante = true;
                    break;
                }
                // Tolerância a pequenas diferenças de grafia
                if (mb_strlen($qwNorm, 'UTF-8') >= 5) {
                    $targetWords = preg_split('/[\s,\.\-_:\?!;\(\)\[\]"\'\/]+/u', $textoBuscaCompleto);
                    foreach ($targetWords as $tw) {
                        if (mb_strlen($tw, 'UTF-8') >= 4 && (levenshtein($qwNorm, $tw) <= 1 || (mb_strlen($qwNorm, 'UTF-8') >= 7 && levenshtein($qwNorm, $tw) <= 2))) {
                            $relevante = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$relevante) {
                continue;
            }

            // FILTRO ESTRITO DE OBRAS VAZIAS:
            // Livros sem capa só permanecem se tiverem avaliações reais (> 0) e edições relevantes (>= 3).
            if (!$hasCover && ($ratings === 0 || $editions <= 2)) {
                continue;
            }

            $normKey = folivro_normalizar_titulo_chave($tituloFormatado);
            if ($normKey === '') {
                $normKey = $workId;
            }

            $candidato = [
                'workId' => $workId,
                'title' => $tituloFormatado,
                'originalTitle' => $tituloOriginal,
                'authors' => $authors,
                'year' => isset($book['first_publish_year']) && is_numeric($book['first_publish_year']) && (int) $book['first_publish_year'] > 0 ? (int) $book['first_publish_year'] : null,
                'pages' => isset($book['number_of_pages_median']) && is_numeric($book['number_of_pages_median']) && (int) $book['number_of_pages_median'] > 0 ? (int) $book['number_of_pages_median'] : 0,
                'coverUrl' => $hasCover ? 'https://covers.openlibrary.org/b/id/' . $coverId . '-M.jpg' : null,
                'workUrl' => 'https://openlibrary.org/works/' . $workId,
                'editions' => $editions,
                'readingLog' => $readingLog,
                'ratingsCount' => $ratings,
                'ratingAvg' => $ratingAvg,
                'ratingSortable' => $ratingSortable,
                'hasCover' => $hasCover,
            ];

            // Deduplicação inteligente: mantém versão com capa ou com maior número de leitores/avaliações
            if (isset($cleanBooksByNorm[$normKey])) {
                $existente = $cleanBooksByNorm[$normKey];
                if (!$existente['hasCover'] && $candidato['hasCover']) {
                    $cleanBooksByNorm[$normKey] = $candidato;
                } elseif ($existente['hasCover'] === $candidato['hasCover']) {
                    if ($candidato['ratingSortable'] > $existente['ratingSortable'] || ($candidato['readingLog'] + $candidato['editions']) > ($existente['readingLog'] + $existente['editions'])) {
                        $cleanBooksByNorm[$normKey] = $candidato;
                    }
                }
            } else {
                $cleanBooksByNorm[$normKey] = $candidato;
            }
        }

        $finalList = array_values($cleanBooksByNorm);

        // ORDENAÇÃO: Os livros mais bem avaliados de preferência!
        usort($finalList, function ($a, $b) {
            $aTemNota = ($a['ratingsCount'] > 0 && $a['ratingAvg'] !== null);
            $bTemNota = ($b['ratingsCount'] > 0 && $b['ratingAvg'] !== null);

            if ($aTemNota && !$bTemNota) return -1;
            if (!$aTemNota && $bTemNota) return 1;

            if ($aTemNota && $bTemNota) {
                if (abs($a['ratingSortable'] - $b['ratingSortable']) > 0.0001) {
                    return ($b['ratingSortable'] <=> $a['ratingSortable']);
                }
                if (abs($a['ratingAvg'] - $b['ratingAvg']) > 0.01) {
                    return ($b['ratingAvg'] <=> $a['ratingAvg']);
                }
                return ($b['ratingsCount'] <=> $a['ratingsCount']);
            }

            if ($b['readingLog'] !== $a['readingLog']) {
                return ($b['readingLog'] <=> $a['readingLog']);
            }
            return ($b['editions'] <=> $a['editions']);
        });

        if (!empty($finalList)) {
            @file_put_contents($cacheFile, json_encode($finalList, JSON_UNESCAPED_UNICODE));
        }
        return $finalList;
    }
}

$allItems = folivro_consultar_e_filtrar_catalogo($query, $sort);
$total = count($allItems);
$slice = array_slice($allItems, $offset, $limit);

$maiorDeIdade = folivro_usuario_maior_de_idade();
foreach ($slice as &$item) {
    $textoClass = (string) ($item['title'] ?? '') . ' ' . (string) ($item['originalTitle'] ?? '');
    $classNorm = folivro_estimar_classificacao_catalogo($textoClass, (string) ($item['workId'] ?? ''));
    $bloqueado = !$maiorDeIdade && folivro_classificacao_eh_maior18($classNorm);
    $item['classificacao'] = $classNorm;
    $item['classificacaoSigla'] = folivro_classificacao_sigla($classNorm);
    $item['classificacaoLabel'] = folivro_aviso_conteudo_label($classNorm);
    $item['bloqueadoIdade'] = $bloqueado;
    $item['motivoBloqueioIdade'] = $bloqueado
        ? 'Bloqueado: livro classificado para maiores de 18 anos (+18)'
        : '';
    if ($bloqueado) {
        $item['title'] = 'Título oculto (+18)';
        $item['originalTitle'] = null;
    }
}
unset($item);

echo json_encode([
    'items' => $slice,
    'offset' => $offset,
    'limit' => $limit,
    'total' => $total,
    'count' => count($slice),
    'hasMore' => ($offset + count($slice) < $total),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
