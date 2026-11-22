<?php
declare(strict_types=1);

/**
 * Acervo Nacional de Domínio Público (Lei nº 9.610/1998 — Art. 41 e Art. 45).
 * Integra obras clássicas da literatura brasileira cujos autores faleceram há mais de 70 anos,
 * buscando o texto integral em português via API pública do Wikisource PT (pt.wikisource.org/w/api.php)
 * com cache local e fallback embutido.
 */

function folivro_dp_catalogo(): array
{
    return [
        'OL263058W' => [
            'workId' => 'OL263058W',
            'title' => 'Dom Casmurro',
            'authors' => ['Machado de Assis'],
            'authorDeathYear' => 1908,
            'year' => 1899,
            'coverUrl' => null,
            'classificacao' => 'livre',
            'generos' => ['Romance', 'Literatura Brasileira', 'Clássico', 'Domínio Público'],
            'sinopse' => 'Clássico absoluto de Machado de Assis (1899), narrado por Bento Santiago (Bentinho), que revisita sua juventude no Rio de Janeiro e seu relacionamento com Capitu.',
            'edicaoReferencia' => 'Rio de Janeiro: H. Garnier, Livreiro-Editor, 1899 (Domínio Público — Art. 41 da Lei nº 9.610/1998).',
            'capitulos' => [
                ['ordem' => 1, 'titulo' => 'Capítulo I — Do título', 'wikiPage' => 'Dom_Casmurro/I'],
                ['ordem' => 2, 'titulo' => 'Capítulo II — Do livro', 'wikiPage' => 'Dom_Casmurro/II'],
                ['ordem' => 3, 'titulo' => 'Capítulo III — A denúncia', 'wikiPage' => 'Dom_Casmurro/III'],
                ['ordem' => 4, 'titulo' => 'Capítulo IV — Um dever amaríssimo', 'wikiPage' => 'Dom_Casmurro/IV'],
                ['ordem' => 5, 'titulo' => 'Capítulo V — O agregado', 'wikiPage' => 'Dom_Casmurro/V'],
                ['ordem' => 6, 'titulo' => 'Capítulo VI — Tio Cosme', 'wikiPage' => 'Dom_Casmurro/VI'],
                ['ordem' => 7, 'titulo' => 'Capítulo VII — D. Glória', 'wikiPage' => 'Dom_Casmurro/VII'],
                ['ordem' => 8, 'titulo' => 'Capítulo VIII — É tempo', 'wikiPage' => 'Dom_Casmurro/VIII'],
                ['ordem' => 9, 'titulo' => 'Capítulo IX — A ópera', 'wikiPage' => 'Dom_Casmurro/IX'],
                ['ordem' => 10, 'titulo' => 'Capítulo X — Aceito a teoria', 'wikiPage' => 'Dom_Casmurro/X'],
                ['ordem' => 11, 'titulo' => 'Capítulo XI — A promessa', 'wikiPage' => 'Dom_Casmurro/XI'],
                ['ordem' => 12, 'titulo' => 'Capítulo XII — Na varanda', 'wikiPage' => 'Dom_Casmurro/XII'],
                ['ordem' => 13, 'titulo' => 'Capítulo XIII — Capitu', 'wikiPage' => 'Dom_Casmurro/XIII'],
                ['ordem' => 14, 'titulo' => 'Capítulo XIV — A inscrição', 'wikiPage' => 'Dom_Casmurro/XIV'],
                ['ordem' => 15, 'titulo' => 'Capítulo XV — Outra voz repentina', 'wikiPage' => 'Dom_Casmurro/XV'],
            ],
            'fallbackCap1' => '<p>Uma noite destas, vindo da cidade para o Engenho Novo, encontrei no trem da Central um rapaz aqui do bairro, que eu conheço de vista e de chapéu. Cumprimentou-me, sentou-se ao pé de mim, falou da lua e dos ministros, e acabou recitando-me versos. A viagem era curta, e os versos pode ser que não fossem inteiramente maus. Sucedeu, porém, que como eu estava cansado, fechei os olhos três ou quatro vezes; tanto bastou para que ele interrompesse a leitura e metesse os versos no bolso.</p><p>— Continue, disse eu acordando.</p><p>— Já acabei, murmurou ele.</p><p>— São muito bonitos.</p><p>Vi-lhe fazer um gesto para tirá-los outra vez do bolso, mas não passou do gesto; estava amuado. No dia seguinte entrou a dizer de mim nomes feios, e acabou alcunhando-me <em>Dom Casmurro</em>. Os vizinhos, que não gostam dos meus hábitos reclusos e calados, deram curso à alcunha, que afinal pegou.</p><p>Não consultes dicionários. <em>Casmurro</em> não está aqui no sentido que eles lhe dão, mas no que lhe pôs o vulgo de homem calado e metido consigo. <em>Dom</em> veio por ironia, para atribuir-me fumos de fidalgo. Tudo por estar cochilando! Também não achei melhor título para a minha narração; se não tiver outro daqui até ao fim do livro, vai este mesmo.</p>',
        ],
        'OL262884W' => [
            'workId' => 'OL262884W',
            'title' => 'Memórias Póstumas de Brás Cubas',
            'authors' => ['Machado de Assis'],
            'authorDeathYear' => 1908,
            'year' => 1881,
            'coverUrl' => null,
            'classificacao' => 'livre',
            'generos' => ['Romance', 'Literatura Brasileira', 'Realismo', 'Domínio Público'],
            'sinopse' => 'Marco inaugural do Realismo no Brasil (1881), em que o defunto-autor Brás Cubas narra com ironia e liberdade filosófica sua própria trajetória.',
            'edicaoReferencia' => 'Rio de Janeiro: Tipografia Nacional, 1881 (Domínio Público — Art. 41 da Lei nº 9.610/1998).',
            'capitulos' => [
                ['ordem' => 1, 'titulo' => 'Capítulo I — Óbito do autor', 'wikiPage' => 'Memórias_Póstumas_de_Brás_Cubas/I'],
                ['ordem' => 2, 'titulo' => 'Capítulo II — O emplasto', 'wikiPage' => 'Memórias_Póstumas_de_Brás_Cubas/II'],
                ['ordem' => 3, 'titulo' => 'Capítulo III — Genealogia', 'wikiPage' => 'Memórias_Póstumas_de_Brás_Cubas/III'],
                ['ordem' => 4, 'titulo' => 'Capítulo IV — A ideia fixa', 'wikiPage' => 'Memórias_Póstumas_de_Brás_Cubas/IV'],
                ['ordem' => 5, 'titulo' => 'Capítulo V — Em que aparece a orelha de uma mulher', 'wikiPage' => 'Memórias_Póstumas_de_Brás_Cubas/V'],
                ['ordem' => 6, 'titulo' => 'Capítulo VI — Chimène, qui l’eût dit?', 'wikiPage' => 'Memórias_Póstumas_de_Brás_Cubas/VI'],
                ['ordem' => 7, 'titulo' => 'Capítulo VII — O delírio', 'wikiPage' => 'Memórias_Póstumas_de_Brás_Cubas/VII'],
                ['ordem' => 8, 'titulo' => 'Capítulo VIII — Razão contra sandice', 'wikiPage' => 'Memórias_Póstumas_de_Brás_Cubas/VIII'],
                ['ordem' => 9, 'titulo' => 'Capítulo IX — Transição', 'wikiPage' => 'Memórias_Póstumas_de_Brás_Cubas/IX'],
                ['ordem' => 10, 'titulo' => 'Capítulo X — Naquele dia', 'wikiPage' => 'Memórias_Póstumas_de_Brás_Cubas/X'],
            ],
            'fallbackCap1' => '<p>Algum tempo hesitei se devia abrir estas memórias pelo princípio ou pelo fim, isto é, se poria em primeiro lugar o meu nascimento ou a minha morte. Suposto o uso vulgar seja começar pelo nascimento, duas considerações me levaram a adotar diferente método: a primeira é que eu não sou propriamente um autor defunto, mas um defunto autor, para quem a campa foi outro berço; a segunda é que o escrito ficaria assim mais galante e mais novo. Moisés, que também contou a sua morte, não a pôs no intróito, mas no cabo: diferença radical entre este livro e o Pentateuco.</p>',
        ],
        'OL262969W' => [
            'workId' => 'OL262969W',
            'title' => 'O Alienista',
            'authors' => ['Machado de Assis'],
            'authorDeathYear' => 1908,
            'year' => 1882,
            'coverUrl' => null,
            'classificacao' => 'livre',
            'generos' => ['Novela', 'Sátira', 'Literatura Brasileira', 'Domínio Público'],
            'sinopse' => 'O médico Dr. Simão Bacamarte funda a Casa Verde em Itaguaí para estudar as fronteiras entre a razão e a loucura humana.',
            'edicaoReferencia' => 'Rio de Janeiro: B. L. Garnier, 1882 — Papéis Avulsos (Domínio Público — Art. 41 da Lei nº 9.610/1998).',
            'capitulos' => [
                ['ordem' => 1, 'titulo' => 'Capítulo I — De como Itaguaí ganhou uma casa de Orates', 'wikiPage' => 'O_Alienista/I'],
                ['ordem' => 2, 'titulo' => 'Capítulo II — Torrente de loucos', 'wikiPage' => 'O_Alienista/II'],
                ['ordem' => 3, 'titulo' => 'Capítulo III — Deus sabe o que faz!', 'wikiPage' => 'O_Alienista/III'],
                ['ordem' => 4, 'titulo' => 'Capítulo IV — Uma teoria nova', 'wikiPage' => 'O_Alienista/IV'],
                ['ordem' => 5, 'titulo' => 'Capítulo V — O terror', 'wikiPage' => 'O_Alienista/V'],
                ['ordem' => 6, 'titulo' => 'Capítulo VI — A rebelião', 'wikiPage' => 'O_Alienista/VI'],
                ['ordem' => 7, 'titulo' => 'Capítulo VII — O inesperado', 'wikiPage' => 'O_Alienista/VII'],
                ['ordem' => 8, 'titulo' => 'Capítulo VIII — As angústias do boticário', 'wikiPage' => 'O_Alienista/VIII'],
                ['ordem' => 9, 'titulo' => 'Capítulo IX — Dois lindos casos', 'wikiPage' => 'O_Alienista/IX'],
                ['ordem' => 10, 'titulo' => 'Capítulo X — A restauração', 'wikiPage' => 'O_Alienista/X'],
                ['ordem' => 11, 'titulo' => 'Capítulo XI — O assombro de Itaguaí', 'wikiPage' => 'O_Alienista/XI'],
                ['ordem' => 12, 'titulo' => 'Capítulo XII — O final do § 4º', 'wikiPage' => 'O_Alienista/XII'],
                ['ordem' => 13, 'titulo' => 'Capítulo XIII — Plus Ultra!', 'wikiPage' => 'O_Alienista/XIII'],
            ],
            'fallbackCap1' => '<p>As crônicas da vila de Itaguaí dizem que em tempos remotos vivera ali um certo médico, o Dr. Simão Bacamarte, filho da nobreza da terra e o maior dos médicos do Brasil, de Portugal e das Espanhas. Estudara em Coimbra e Pádua. Aos trinta e quatro anos regressou ao Brasil, não podendo el-rei alcançar dele que ficasse em Coimbra, regendo a universidade, ou em Lisboa, expedindo os negócios da monarquia.</p>',
        ],
        'OL1140846W' => [
            'workId' => 'OL1140846W',
            'title' => 'O Cortiço',
            'authors' => ['Aluísio Azevedo'],
            'authorDeathYear' => 1913,
            'year' => 1890,
            'coverUrl' => null,
            'classificacao' => '14',
            'generos' => ['Romance', 'Naturalismo', 'Literatura Brasileira', 'Domínio Público'],
            'sinopse' => 'Obra-prima do Naturalismo brasileiro (1890), retrata o cotidiano vibrante da estalagem de São Romão e a ascensão do comerciante João Romão no Rio de Janeiro.',
            'edicaoReferencia' => 'Rio de Janeiro: B. L. Garnier, 1890 (Domínio Público — Art. 41 da Lei nº 9.610/1998).',
            'capitulos' => [
                ['ordem' => 1, 'titulo' => 'Capítulo I', 'wikiPage' => 'O_Cortiço/I'],
                ['ordem' => 2, 'titulo' => 'Capítulo II', 'wikiPage' => 'O_Cortiço/II'],
                ['ordem' => 3, 'titulo' => 'Capítulo III', 'wikiPage' => 'O_Cortiço/III'],
                ['ordem' => 4, 'titulo' => 'Capítulo IV', 'wikiPage' => 'O_Cortiço/IV'],
                ['ordem' => 5, 'titulo' => 'Capítulo V', 'wikiPage' => 'O_Cortiço/V'],
                ['ordem' => 6, 'titulo' => 'Capítulo VI', 'wikiPage' => 'O_Cortiço/VI'],
                ['ordem' => 7, 'titulo' => 'Capítulo VII', 'wikiPage' => 'O_Cortiço/VII'],
                ['ordem' => 8, 'titulo' => 'Capítulo VIII', 'wikiPage' => 'O_Cortiço/VIII'],
            ],
            'fallbackCap1' => '<p>João Romão foi, dos treze aos vinte e cinco anos, empregado de um vendeiro que enriqueceu entre as quatro paredes de uma suja e obscura taverna nos refolhos do bairro do Botafogo; e tanto economizou do pouco que ganhara nessa dúzia de anos, que, ao retirar-se o patrão para a terra, lhe deixou, em pagamento de ordenados vencidos, nem só a venda com o que estava dentro, como ainda um conto e quinhentos em dinheiro.</p>',
        ],
        'OL1109715W' => [
            'workId' => 'OL1109715W',
            'title' => 'Iracema',
            'authors' => ['José de Alencar'],
            'authorDeathYear' => 1877,
            'year' => 1865,
            'coverUrl' => null,
            'classificacao' => 'livre',
            'generos' => ['Romance', 'Romantismo', 'Literatura Brasileira', 'Domínio Público'],
            'sinopse' => 'Lenda do Ceará (1865): a prosa poética de José de Alencar sobre o encontro entre a jovem tabajara Iracema, a virgem dos lábios de mel, e o guerreiro português Martim.',
            'edicaoReferencia' => 'Rio de Janeiro: Tipografia de Viana & Filhos, 1865 (Domínio Público — Art. 41 da Lei nº 9.610/1998).',
            'capitulos' => [
                ['ordem' => 1, 'titulo' => 'Capítulo I', 'wikiPage' => 'Iracema/I'],
                ['ordem' => 2, 'titulo' => 'Capítulo II', 'wikiPage' => 'Iracema/II'],
                ['ordem' => 3, 'titulo' => 'Capítulo III', 'wikiPage' => 'Iracema/III'],
                ['ordem' => 4, 'titulo' => 'Capítulo IV', 'wikiPage' => 'Iracema/IV'],
                ['ordem' => 5, 'titulo' => 'Capítulo V', 'wikiPage' => 'Iracema/V'],
                ['ordem' => 6, 'titulo' => 'Capítulo VI', 'wikiPage' => 'Iracema/VI'],
                ['ordem' => 7, 'titulo' => 'Capítulo VII', 'wikiPage' => 'Iracema/VII'],
                ['ordem' => 8, 'titulo' => 'Capítulo VIII', 'wikiPage' => 'Iracema/VIII'],
                ['ordem' => 9, 'titulo' => 'Capítulo IX', 'wikiPage' => 'Iracema/IX'],
                ['ordem' => 10, 'titulo' => 'Capítulo X', 'wikiPage' => 'Iracema/X'],
            ],
            'fallbackCap1' => '<p>Verdes mares bravios de minha terra natal, onde canta a jandaia nas frondes da carnaúba!</p><p>Verdes mares, que brilhais como líquida esmeralda aos raios do sol nascente, perlongando as alvas praias ensombradas de coqueiros!</p><p>Serenai, verdes mares, e alisai docemente a vaga impetuosa, para que o barco aventureiro manso resvale à flor das águas.</p>',
        ],
        'OL263000W' => [
            'workId' => 'OL263000W',
            'title' => 'Quincas Borba',
            'authors' => ['Machado de Assis'],
            'authorDeathYear' => 1908,
            'year' => 1891,
            'coverUrl' => null,
            'classificacao' => 'livre',
            'generos' => ['Romance', 'Realismo', 'Literatura Brasileira', 'Domínio Público'],
            'sinopse' => 'O professor Rubião herda a fortuna e o cão do filósofo Quincas Borba, criador do Humanitismo, e parte de Barbacena para a corte do Rio de Janeiro.',
            'edicaoReferencia' => 'Rio de Janeiro: B. L. Garnier, 1891 (Domínio Público — Art. 41 da Lei nº 9.610/1998).',
            'capitulos' => [
                ['ordem' => 1, 'titulo' => 'Capítulo I', 'wikiPage' => 'Quincas_Borba/I'],
                ['ordem' => 2, 'titulo' => 'Capítulo II', 'wikiPage' => 'Quincas_Borba/II'],
                ['ordem' => 3, 'titulo' => 'Capítulo III', 'wikiPage' => 'Quincas_Borba/III'],
                ['ordem' => 4, 'titulo' => 'Capítulo IV', 'wikiPage' => 'Quincas_Borba/IV'],
                ['ordem' => 5, 'titulo' => 'Capítulo V', 'wikiPage' => 'Quincas_Borba/V'],
                ['ordem' => 6, 'titulo' => 'Capítulo VI', 'wikiPage' => 'Quincas_Borba/VI'],
                ['ordem' => 7, 'titulo' => 'Capítulo VII', 'wikiPage' => 'Quincas_Borba/VII'],
                ['ordem' => 8, 'titulo' => 'Capítulo VIII', 'wikiPage' => 'Quincas_Borba/VIII'],
            ],
            'fallbackCap1' => '<p>Rubião fitava a enseada, — eram oito horas da manhã. Quem o visse, com os polegares metidos no cordão do chambre, à janela de uma grande casa de Botafogo, cuidaria que ele admirava aquele pedaço de água quieta; mas, em verdade, vos digo que pensava em outra coisa. Cotejava o passado com o presente. Que era, há um ano? Professor. Que é agora? Capitalista.</p>',
        ],
        'OL14862849W' => [
            'workId' => 'OL14862849W',
            'title' => 'A Cartomante',
            'authors' => ['Machado de Assis'],
            'authorDeathYear' => 1908,
            'year' => 1884,
            'coverUrl' => null,
            'classificacao' => 'livre',
            'generos' => ['Conto', 'Mistério', 'Literatura Brasileira', 'Domínio Público'],
            'sinopse' => 'Um dos contos mais célebres da literatura brasileira, publicado originalmente em 1884 e reunido em Várias Histórias (1896), sobre o triângulo entre Camilo, Rita e Vilela.',
            'edicaoReferencia' => 'Rio de Janeiro: Laemmert & C., Editores, 1896 — Várias Histórias (Domínio Público — Art. 41 da Lei nº 9.610/1998).',
            'capitulos' => [
                ['ordem' => 1, 'titulo' => 'Conto Integral — A Cartomante', 'wikiPage' => 'A_Cartomante'],
            ],
            'fallbackCap1' => '<p>Hamlet observa a Horácio que há mais cousas no céu e na terra do que sonha a nossa filosofia. Era a mesma explicação que dava a bela Rita ao moço Camilo, numa sexta-feira de novembro de 1869, quando este ria dela, por ter ido na véspera consultar uma cartomante; a diferença é que o fazia por outras palavras.</p><p>— Ria, ria. Os homens são assim; não acreditam em nada. Pois saiba que fui, e que ela adivinhou o motivo da consulta, antes mesmo que eu lhe dissesse o que era.</p>',
        ],
        'OL1893502W' => [
            'workId' => 'OL1893502W',
            'title' => 'O Ateneu',
            'authors' => ['Raul Pompeia'],
            'authorDeathYear' => 1895,
            'year' => 1888,
            'coverUrl' => null,
            'classificacao' => 'livre',
            'generos' => ['Romance', 'Literatura Brasileira', 'Clássico', 'Domínio Público'],
            'sinopse' => 'Crônica de saudades (1888): Sérgio narra suas memórias como interno no colégio Ateneu, dirigido pelo imponente Aristarco.',
            'edicaoReferencia' => 'Rio de Janeiro: Tipografia da Gazeta de Notícias, 1888 (Domínio Público — Art. 41 da Lei nº 9.610/1998).',
            'capitulos' => [
                ['ordem' => 1, 'titulo' => 'Capítulo I', 'wikiPage' => 'O_Ateneu/I'],
                ['ordem' => 2, 'titulo' => 'Capítulo II', 'wikiPage' => 'O_Ateneu/II'],
                ['ordem' => 3, 'titulo' => 'Capítulo III', 'wikiPage' => 'O_Ateneu/III'],
                ['ordem' => 4, 'titulo' => 'Capítulo IV', 'wikiPage' => 'O_Ateneu/IV'],
                ['ordem' => 5, 'titulo' => 'Capítulo V', 'wikiPage' => 'O_Ateneu/V'],
                ['ordem' => 6, 'titulo' => 'Capítulo VI', 'wikiPage' => 'O_Ateneu/VI'],
            ],
            'fallbackCap1' => '<p>«Vais encontrar o mundo, disse-me meu pai, à porta do Ateneu. Coragem para a luta.»</p><p>Bastante experimentei depois a verdade deste aviso, que me despia, num gesto, das ilusões de criança educada exoticamente na estufa de carinho que é o regime do amor doméstico, diferente do que se encontra fora, tão diferente, que parece o poema dos cuidados maternos um artifício sentimental, com a vantagem única de fazer mais sensível a criatura à impressão rude do primeiro ensinamento, têmpera brusca da vitalidade na influência de um novo clima rigoroso.</p>',
        ],
    ];
}

function folivro_dp_normalizar(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $map = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
    ];
    $s = strtr($s, $map);
    return preg_replace('/[^a-z0-9]+/', '', $s) ?: '';
}

function folivro_dp_encontrar_obra(?string $workId, ?string $titulo = null): ?array
{
    $cat = folivro_dp_catalogo();
    if ($workId !== null && $workId !== '' && isset($cat[$workId])) {
        return $cat[$workId];
    }
    if ($titulo !== null && trim($titulo) !== '') {
        $alvo = folivro_dp_normalizar($titulo);
        foreach ($cat as $item) {
            $chave = folivro_dp_normalizar($item['title']);
            if ($chave !== '' && ($alvo === $chave || str_contains($alvo, $chave) || str_contains($chave, $alvo))) {
                return $item;
            }
        }
    }
    return null;
}

/**
 * Busca e higieniza o texto de uma página do Wikisource em Português (pt.wikisource.org/w/api.php).
 */
function folivro_dp_buscar_html_wikisource(string $wikiPage): ?string
{
    $cacheDir = __DIR__ . '/../cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    $cacheFile = $cacheDir . '/dp_ws_' . md5($wikiPage) . '.html';
    if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < 86400 * 30) {
        $cached = (string) @file_get_contents($cacheFile);
        if (strlen($cached) > 80) {
            return $cached;
        }
    }

    $url = 'https://pt.wikisource.org/w/api.php?action=parse&page=' . urlencode($wikiPage) . '&prop=text&format=json';
    $raw = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'FOLIVRO-Academic-Reader/1.0 (https://github.com/NatanPsx/FOLIVRO)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 7,
        ]);
        $raw = (string) curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => ['timeout' => 7, 'header' => "User-Agent: FOLIVRO-Academic-Reader/1.0\r\n"],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $raw = (string) @file_get_contents($url, false, $ctx);
    }

    if ($raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    $html = (string) ($data['parse']['text']['*'] ?? '');
    if ($html === '') {
        return null;
    }

    // Extrai apenas os parágrafos literários <p>...</p>, removendo cabeçalhos de navegação do Wikisource.
    $html = preg_replace('~<div class="ws-noexport">.*?</div></div>~su', '', $html) ?? $html;
    $html = preg_replace('~<div id="navigationHeader".*?</div></div></div>~su', '', $html) ?? $html;
    $html = preg_replace('~<div id="navigationNotes".*?</div></div></div>~su', '', $html) ?? $html;
    $html = preg_replace('~<span class="pagenum[^>]*>.*?</span>~su', '', $html) ?? $html;

    preg_match_all('~<p\b[^>]*>(.*?)</p>~isu', $html, $matches);
    $paragrafos = [];
    foreach ($matches[1] ?? [] as $pInner) {
        $limpo = trim(strip_tags($pInner, '<em><i><strong><b><br>'));
        $textoPuro = trim(html_entity_decode(strip_tags($limpo), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $textoPuro = trim($textoPuro, " \t\n\r\0\x0B\xC2\xA0");
        if ($textoPuro === '' || mb_strlen($textoPuro, 'UTF-8') < 2) {
            continue;
        }
        if (preg_match('/^(edi[çc][ãa]o de refer[êe]ncia|projetos irm[ãa]os|publicado originalmente|ou[çc]a este texto)/iu', $textoPuro)) {
            continue;
        }
        $paragrafos[] = '<p>' . $limpo . '</p>';
    }

    if (!$paragrafos) {
        return null;
    }

    $resultado = implode("\n", $paragrafos);
    @file_put_contents($cacheFile, $resultado);
    return $resultado;
}
