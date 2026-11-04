<?php
declare(strict_types=1);

/**
 * Módulo de Tradução e Localização para Português do Brasil (PT-BR) — FOLIVRO
 * Traduz automaticamente títulos, sinopses e gêneros da Open Library.
 */

if (!function_exists('folivro_remover_acentos')) {
    function folivro_remover_acentos($str): string {
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
        return strtr((string) $str, $map);
    }
}

if (!function_exists('folivro_normalizar_busca')) {
    function folivro_normalizar_busca($str): string {
        $s = mb_strtolower(trim((string) $str), 'UTF-8');
        $s = folivro_remover_acentos($s);
        $s = preg_replace('/^(o|a|os|as|um|uma|uns|umas|the|an)\s+/iu', '', $s);
        $s = preg_replace('/[^a-z0-9\s]/iu', '', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }
}

/**
 * Catálogo bilíngue canônico dos grandes clássicos e best-sellers mundiais.
 */
function folivro_obter_catalogo_bilingue(): array {
    static $pares = null;
    if ($pares !== null) {
        return $pares;
    }

    $pares = [
        'the lord of the rings' => 'O Senhor dos Anéis',
        'the fellowship of the ring' => 'A Sociedade do Anel',
        'the two towers' => 'As Duas Torres',
        'the return of the king' => 'O Retorno do Rei',
        'the hobbit' => 'O Hobbit',
        'the silmarillion' => 'O Silmarillion',
        'pride and prejudice' => 'Orgulho e Preconceito',
        'sense and sensibility' => 'Razão e Sensibilidade',
        'emma' => 'Emma',
        'persuasion' => 'Persuasão',
        'northanger abbey' => 'A Abadia de Northanger',
        'mansfield park' => 'Mansfield Park',
        'animal farm' => 'A Revolução dos Bichos',
        '1984' => '1984',
        'nineteen eighty-four' => '1984',
        'the little prince' => 'O Pequeno Príncipe',
        'le petit prince' => 'O Pequeno Príncipe',
        'crime and punishment' => 'Crime e Castigo',
        'преступление и наказание' => 'Crime e Castigo',
        'the brothers karamazov' => 'Os Irmãos Karamazov',
        'братья карамазовы' => 'Os Irmãos Karamazov',
        'the idiot' => 'O Idiota',
        'идиот' => 'O Idiota',
        'war and peace' => 'Guerra e Paz',
        'война и мир' => 'Guerra e Paz',
        'anna karenina' => 'Anna Kariênina',
        'анна каренина' => 'Anna Kariênina',
        'wuthering heights' => 'O Morro dos Ventos Uivantes',
        'brave new world' => 'Admirável Mundo Novo',
        'to kill a mockingbird' => 'O Sol é Para Todos',
        'the great gatsby' => 'O Grande Gatsby',
        'one hundred years of solitude' => 'Cem Anos de Solidão',
        'the catcher in the rye' => 'O Apanhador no Campo de Centeio',
        'the fault in our stars' => 'A Culpa é das Estrelas',
        'the hunger games' => 'Jogos Vorazes',
        'catching fire' => 'Em Chamas',
        'mockingjay' => 'A Esperança',
        'the book thief' => 'A Menina que Roubava Livros',
        'the metamorphosis' => 'A Metamorfose',
        'metamorphosis' => 'A Metamorfose',
        'the picture of dorian gray' => 'O Retrato de Dorian Gray',
        'the count of monte cristo' => 'O Conde de Monte Cristo',
        'le comte de monte-cristo' => 'O Conde de Monte Cristo',
        'les misérables' => 'Os Miseráveis',
        'les miserables' => 'Os Miseráveis',
        'frankenstein' => 'Frankenstein',
        'dracula' => 'Drácula',
        'the old man and the sea' => 'O Velho e o Mar',
        'the stranger' => 'O Estrangeiro',
        'l\'étranger' => 'O Estrangeiro',
        'l\'etranger' => 'O Estrangeiro',
        'the plague' => 'A Peste',
        'the trial' => 'O Processo',
        'the castle' => 'O Castelo',
        'the bell jar' => 'A Redoma de Vidro',
        'the da vinci code' => 'O Código Da Vinci',
        'angels & demons' => 'Anjos e Demônios',
        'a game of thrones' => 'A Guerra dos Tronos',
        'a clash of kings' => 'A Fúria dos Reis',
        'a storm of swords' => 'A Tormenta das Espadas',
        'dune' => 'Duna',
        'foundation' => 'Fundação',
        'neuromancer' => 'Neuromancer',
        'the chronicles of narnia' => 'As Crônicas de Nárnia',
        'the lion, the witch and the wardrobe' => 'O Leão, a Feiticeira e o Guarda-Roupa',
        'the lion the witch and the wardrobe' => 'O Leão, a Feiticeira e o Guarda-Roupa',
        'little women' => 'Mulherzinhas',
        'the alchemist' => 'O Alquimista',
        'blindness' => 'Ensaio sobre a Cegueira',
        'the strange case of dr jekyll and mr hyde' => 'O Médico e o Monstro',
        'the divine comedy' => 'A Divina Comédia',
        'war and peace' => 'Guerra e Paz',
        'anna karenina' => 'Anna Kariênina',
        'don quixote' => 'Dom Quixote',
        'the adventures of sherlock holmes' => 'As Aventuras de Sherlock Holmes',
        'the adventures of sherlock holmes [12 stories]' => 'As Aventuras de Sherlock Holmes',
        'the adventures of sherlock holmes: 12 stories' => 'As Aventuras de Sherlock Holmes',
        'a study in scarlet' => 'Um Estudo em Vermelho',
        'the hound of the baskervilles' => 'O Cão dos Baskerville',
        'epitaph of a small winner' => 'Memórias Póstumas de Brás Cubas',
        'the post-humous memoirs of bras cubas' => 'Memórias Póstumas de Brás Cubas',
        'posthumous memoirs of brás cubas' => 'Memórias Póstumas de Brás Cubas',
        'the posthumous memoirs of bras cubas' => 'Memórias Póstumas de Brás Cubas',
        'the psychiatrist' => 'O Alienista',
        'the alienist' => 'O Alienista',
        'the alienist art of the novella' => 'O Alienista',
        'the alienist: art of the novella' => 'O Alienista',
        'the alienist (art of the novella)' => 'O Alienista',
        'dom casmurro' => 'Dom Casmurro',
        'dom casmurro: a novel' => 'Dom Casmurro',
        'philosopher or dog?' => 'Quincas Borba',
        'quincas borba' => 'Quincas Borba',
        'counselor ayres\' memoirs' => 'Memorial de Aires',
        'the devil\'s church and other stories' => 'A Igreja do Diabo e Outros Contos',
        'the handmaid\'s tale' => 'O Conto da Aia',
        'lord of the flies' => 'O Senhor das Moscas',
        'the art of war' => 'A Arte da Guerra',
        'the color purple' => 'A Cor Púrpura',
        'romeo and juliet' => 'Romeu e Julieta',
        'the odyssey' => 'A Odisseia',
        'the iliad' => 'A Ilíada',
        'alice\'s adventures in wonderland' => 'Alice no País das Maravilhas',
        'through the looking-glass' => 'Alice Através do Espelho',
        'anne of green gables' => 'Anne de Green Gables',
        'slaughterhouse-five' => 'Matadouro 5',
        'it ends with us' => 'É Assim que Acaba',
        'it starts with us' => 'É Assim que Começa',
        'the seven husbands of evelyn hugo' => 'Os Sete Maridos de Evelyn Hugo',
        'red, white & royal blue' => 'Vermelho, Branco e Sangue Azul',
        'the house of the spirits' => 'A Casa dos Espíritos',
        'the name of the rose' => 'O Nome da Rosa',
        'jane eyre' => 'Jane Eyre',
        'matilda' => 'Matilda',
        'the city & the mountains' => 'A Cidade e as Serras',
        'the city and the mountains' => 'A Cidade e as Serras',
        'cidade e as serras' => 'A Cidade e as Serras',
        'a cidade e as serras' => 'A Cidade e as Serras',
        'theadventures of tom sawyer' => 'As Aventuras de Tom Sawyer',
        'the adventures of tom sawyer' => 'As Aventuras de Tom Sawyer',
        'adventures of huckleberry finn' => 'As Aventuras de Huckleberry Finn',
        'the adventures of huckleberry finn' => 'As Aventuras de Huckleberry Finn',
        'frankenstein or the modern prometheus' => 'Frankenstein',
        'frankenstein; or, the modern prometheus' => 'Frankenstein',
        'treasure island' => 'A Ilha do Tesouro',
        'the time machine' => 'A Máquina do Tempo',
        'a christmas carol' => 'Um Conto de Natal',
        'the scarlet letter' => 'A Letra Escarlate',
        'the secret garden' => 'O Jardim Secreto',
        'the call of the wild' => 'O Chamado Selvagem',
        'heart of darkness' => 'O Coração das Trevas',
        'around the world in eighty days' => 'A Volta ao Mundo em 80 Dias',
        'twenty thousand leagues under the sea' => 'Vinte Mil Léguas Submarinas',
        'the wonderful wizard of oz' => 'O Mágico de Oz',
        'the wind in the willows' => 'O Vento nos Salgueiros',
        'of mice and men' => 'Ratos e Homens',
        'fahrenheit 451' => 'Fahrenheit 451',
        'the grapes of wrath' => 'As Vinhas da Ira',
        'ulysses' => 'Ulisses',
        'hamlet' => 'Hamlet',
        'macbeth' => 'Macbeth',
        'othello' => 'Otelo',
        'king lear' => 'Rei Lear',
        'a midsummer night\'s dream' => 'Sonho de uma Noite de Verão',
        'the three musketeers' => 'Os Três Mosqueteiros',
        'les trois mousquetaires' => 'Os Três Mosqueteiros',
        'the war of the worlds' => 'A Guerra dos Mundos',
        'gulliver\'s travels' => 'As Viagens de Gulliver',
        'robinson crusoe' => 'Robinson Crusoé',
        'moby-dick' => 'Moby Dick',
        'moby dick' => 'Moby Dick',
        'a tale of two cities' => 'Um Conto de Duas Cidades',
        'great expectations' => 'Grandes Esperanças',
        'oliver twist' => 'Oliver Twist',
        'david copperfield' => 'David Copperfield',
        'sense and sensibility' => 'Razão e Sensibilidade',
        'a paixão segundo g.h.' => 'A Paixão Segundo G.H.',
        'the passion according to g.h.' => 'A Paixão Segundo G.H.',
        'the hour of the star' => 'A Hora da Estrela',
        'a hora da estrela' => 'A Hora da Estrela',
        'captains of the sands' => 'Capitães da Areia',
        'capitães da areia' => 'Capitães da Areia',
        'barren lives' => 'Vidas Secas',
        'vidas secas' => 'Vidas Secas',
        'the devil to pay in the backlands' => 'Grande Sertão: Veredas',
        'grande sertão: veredas' => 'Grande Sertão: Veredas',
        'harry potter and the philosopher\'s stone' => 'Harry Potter e a Pedra Filosofal',
        'harry potter and the sorcerer\'s stone' => 'Harry Potter e a Pedra Filosofal',
        'harry potter and the chamber of secrets' => 'Harry Potter e a Câmara Secreta',
        'harry potter and the prisoner of azkaban' => 'Harry Potter e o Prisioneiro de Azkaban',
        'harry potter and the goblet of fire' => 'Harry Potter e o Cálice de Fogo',
        'harry potter and the order of the phoenix' => 'Harry Potter e a Ordem da Fênix',
        'harry potter and the half-blood prince' => 'Harry Potter e o Enigma do Príncipe',
        'harry potter and the deathly hallows' => 'Harry Potter e as Relíquias da Morte',
    ];

    return $pares;
}

/**
 * Localiza e traduz títulos internacionais para suas versões consagradas em português.
 * Retorna ['titulo' => string, 'original' => ?string]
 */
function folivro_localizar_titulo(string $titulo): array
{
    $tituloLimpo = trim($titulo);
    // Remove sufixos editoriais de coleção entre parênteses/colchetes (ex: "(Classicos Da Literatura Brasileira)")
    $tituloLimpo = trim((string) preg_replace('/\s*[\(\[](?:cl[aá]ssicos|classics|cole[cç][aã]o|s[eé]rie|editions?|penguin|oxford|everyman|art of the novella|12 stories)[^\)\]]*[\)\]]/iu', '', $tituloLimpo));
    if ($tituloLimpo === '') {
        return ['titulo' => 'Obra sem título', 'original' => null];
    }

    $pares = folivro_obter_catalogo_bilingue();
    $chave = mb_strtolower($tituloLimpo, 'UTF-8');

    // 1. Busca direta exata em minúsculas
    if (isset($pares[$chave])) {
        return [
            'titulo' => $pares[$chave],
            'original' => ($pares[$chave] !== $tituloLimpo) ? $tituloLimpo : null,
        ];
    }

    // 2. Busca normalizada (remove pontuação, acentos e artigos)
    $norm = folivro_normalizar_busca($tituloLimpo);
    if ($norm !== '') {
        foreach ($pares as $en => $pt) {
            $en = (string) $en;
            $pt = (string) $pt;
            $normEn = folivro_normalizar_busca($en);
            $normPt = folivro_normalizar_busca($pt);

            if ($norm === $normEn) {
                return [
                    'titulo' => $pt,
                    'original' => ($pt !== $tituloLimpo) ? $en : null,
                ];
            }

            if ($norm === $normPt) {
                return [
                    'titulo' => $pt,
                    'original' => ($pt !== $en) ? $en : null,
                ];
            }
        }
    }

    // Se o título não estiver no catálogo, preserva o original
    return [
        'titulo' => $tituloLimpo,
        'original' => null,
    ];
}

/**
 * Analisa um termo de busca e retorna suas equivalências bilíngues (PT <-> EN),
 * as consultas otimizadas a serem enviadas à Open Library e palavras-chave de relevância.
 */
function folivro_obter_equivalencias_busca(string $termo): array
{
    $termoLimpo = trim($termo);
    $norm = folivro_normalizar_busca($termoLimpo);
    $pares = folivro_obter_catalogo_bilingue();

    $queriesOl = [$termoLimpo];
    $palavrasPt = array_filter(explode(' ', $norm), function($w) { return strlen($w) >= 3; });
    $palavrasEn = [];
    $ptMatched = null;
    $enMatched = null;

    // 1. Correspondência direta ou por subtítulo no catálogo bilíngue
    foreach ($pares as $en => $pt) {
        $en = (string) $en;
        $pt = (string) $pt;
        $normPt = folivro_normalizar_busca($pt);
        $normEn = folivro_normalizar_busca($en);

        $match = false;
        if ($norm === $normPt || $norm === $normEn) {
            $match = true;
        } elseif (strlen($norm) >= 5 && (strpos($normPt, $norm) !== false || strpos($normEn, $norm) !== false || strpos($norm, $normPt) !== false || strpos($norm, $normEn) !== false)) {
            $match = true;
        }

        if ($match) {
            $ptMatched = $pt;
            $enMatched = $en;
            $queriesOl[] = $en; // Busca prioritária do catálogo mundial da Open Library
            $queriesOl[] = $pt; // Busca de edições nacionais
            $palavrasEn = array_merge($palavrasEn, explode(' ', $normEn));
            $palavrasPt = array_merge($palavrasPt, explode(' ', $normPt));
            break;
        }
    }

    // 2. Dicionário complementar de termos literários comuns
    static $mapaTermos = [
        'senhor' => 'lord',
        'aneis' => 'rings',
        'anel' => 'ring',
        'sociedade' => 'fellowship',
        'torres' => 'towers',
        'rei' => 'king',
        'retorno' => 'return',
        'principe' => 'prince',
        'pequeno' => 'little',
        'revolucao' => 'animal farm revolution',
        'bichos' => 'animal farm animals',
        'orgulho' => 'pride',
        'preconceito' => 'prejudice',
        'castigo' => 'punishment',
        'crime' => 'crime',
        'guerra' => 'war',
        'paz' => 'peace',
        'morro' => 'heights',
        'ventos' => 'winds',
        'sol' => 'mockingbird sun',
        'mundo' => 'world',
        'novo' => 'new',
        'solidao' => 'solitude',
        'apanhador' => 'catcher',
        'centeio' => 'rye',
        'campo' => 'rye field',
        'menina' => 'book thief girl',
        'roubava' => 'thief',
        'estrelas' => 'stars',
        'culpa' => 'fault',
        'jogos' => 'hunger games',
        'vorazes' => 'hunger games',
        'chamas' => 'catching fire',
        'esperanca' => 'mockingjay',
        'alquimista' => 'alchemist',
        'cegueira' => 'blindness',
        'medico' => 'jekyll',
        'monstro' => 'hyde',
        'mar' => 'sea',
        'velho' => 'old man',
        'estrangeiro' => 'stranger',
        'miseraveis' => 'miserables',
        'pedra' => 'philosopher stone',
        'filosofal' => 'philosopher stone',
        'camara' => 'chamber',
        'secreta' => 'secrets',
        'prisioneiro' => 'prisoner',
        'azkaban' => 'azkaban',
        'calice' => 'goblet',
        'fogo' => 'fire',
        'ordem' => 'order',
        'fenix' => 'phoenix',
        'enigma' => 'half-blood prince',
        'reliquias' => 'deathly hallows',
        'morte' => 'deathly hallows',
        'aia' => 'handmaid',
        'conto' => 'tale',
        'moscas' => 'flies',
        'narnia' => 'narnia',
        'feiticeira' => 'witch',
        'guarda' => 'wardrobe',
        'roupa' => 'wardrobe',
        'abencoada' => 'blessed',
        'abencoado' => 'blessed',
    ];

    $palavrasTraduzidas = [];
    foreach ($palavrasPt as $p) {
        if (isset($mapaTermos[$p])) {
            $termosEn = explode(' ', $mapaTermos[$p]);
            $palavrasEn = array_merge($palavrasEn, $termosEn);
            $palavrasTraduzidas = array_merge($palavrasTraduzidas, $termosEn);
        }
    }

    if (!empty($palavrasTraduzidas) && count($queriesOl) === 1) {
        $queriesOl[] = implode(' ', array_unique($palavrasTraduzidas));
    }

    $palavrasRelevancia = array_values(array_unique(array_filter(array_merge($palavrasPt, $palavrasEn), function($w) {
        return strlen($w) >= 3 && !in_array($w, ['the', 'and', 'for', 'dos', 'das', 'uma', 'uns'], true);
    })));

    return [
        'queries_ol' => array_values(array_unique($queriesOl)),
        'palavras_relevancia' => $palavrasRelevancia,
        'pt_titulo' => $ptMatched,
        'en_titulo' => $enMatched,
    ];
}

/**
 * Traduz termos de gêneros e assuntos da Open Library para Português do Brasil.
 */
function folivro_traduzir_genero(string $termo): string
{
    $termoLimpo = trim($termo);
    if ($termoLimpo === '') {
        return '';
    }

    static $mapa = [
        'fiction' => 'Ficção',
        'romance' => 'Romance',
        'historical' => 'Romance Histórico',
        'historical fiction' => 'Ficção Histórica',
        'regency' => 'Época da Regência',
        'courtship' => 'Relacionamentos',
        'brothers and sisters' => 'Família e Irmãos',
        'drama' => 'Drama',
        'english fiction' => 'Literatura Inglesa',
        'english literature' => 'Clássicos da Literatura',
        'families' => 'Família',
        'family' => 'Família',
        'family life' => 'Vida Familiar',
        'young adult' => 'Jovem Adulto',
        'contemporary' => 'Contemporâneo',
        'fantasy' => 'Fantasia',
        'high fantasy' => 'Alta Fantasia',
        'science fiction' => 'Ficção Científica',
        'sci-fi' => 'Ficção Científica',
        'horror' => 'Terror',
        'mystery' => 'Mistério',
        'detective' => 'Investigação e Detetive',
        'suspense' => 'Suspense',
        'thriller' => 'Thriller',
        'adventure' => 'Aventura',
        'action' => 'Ação e Aventura',
        'biography' => 'Biografia',
        'autobiography' => 'Autobiografia',
        'memoir' => 'Memórias',
        'poetry' => 'Poesia',
        'poems' => 'Poemas',
        'philosophy' => 'Filosofia',
        'psychology' => 'Psicologia',
        'sociology' => 'Sociologia',
        'history' => 'História',
        'short stories' => 'Contos',
        'classic literature' => 'Literatura Clássica',
        'classics' => 'Clássicos',
        'brazilian literature' => 'Literatura Brasileira',
        'portuguese literature' => 'Literatura Lusófona',
        'children\'s stories' => 'Infantil',
        'juvenile fiction' => 'Infantojuvenil',
        'fairy tales' => 'Contos de Fadas',
        'mythology' => 'Mitologia',
        'dystopia' => 'Distopia',
        'dystopian' => 'Ficção Distópica',
        'humor' => 'Humor e Sátira',
        'satire' => 'Sátira',
        'social life and customs' => 'Costumes e Sociedade',
        'man-woman relationships' => 'Relações Amorosas',
        'interpersonal relations' => 'Relações Humanas',
    ];

    $chave = mb_strtolower($termoLimpo, 'UTF-8');
    if (isset($mapa[$chave])) {
        return $mapa[$chave];
    }

    // Simplifica termos longos de catálogo
    if (stripos($chave, 'british and irish fiction') !== false) {
        return 'Literatura Britânica';
    }
    if (stripos($chave, 'american fiction') !== false) {
        return 'Literatura Americana';
    }
    if (stripos($chave, 'brazilian') !== false) {
        return 'Literatura Brasileira';
    }

    return $termoLimpo;
}

/**
 * Traduz lista de gêneros literários para português.
 * @param string[] $generos
 * @return string[]
 */
function folivro_traduzir_generos(array $generos): array
{
    $traduzidos = [];
    foreach ($generos as $g) {
        if (!is_string($g)) continue;
        $subpartes = str_contains($g, ',') ? array_map('trim', explode(',', $g)) : [$g];
        foreach ($subpartes as $sub) {
            $trad = folivro_traduzir_genero($sub);
            if ($trad !== '' && !in_array($trad, $traduzidos, true)) {
                $traduzidos[] = $trad;
            }
        }
    }
    return $traduzidos;
}

/**
 * Traduz automaticamente a sinopse em inglês para Português do Brasil (PT-BR).
 * Utiliza cache permanente e timeout estrito de 2s para navegação instantânea.
 */
function folivro_traduzir_sinopse(string $texto): string
{
    $textoLimpo = trim($texto);
    if ($textoLimpo === '') {
        return '';
    }

    // Se o texto já estiver em português, não gasta requisição
    $palavrasPt = [' de ', ' da ', ' do ', ' em ', ' para ', ' com ', ' uma ', ' um ', ' não ', ' que ', ' seu ', ' sua ', ' este ', ' esta ', ' sobre ', ' romance ', ' livro '];
    $matchPt = 0;
    foreach ($palavrasPt as $p) {
        if (stripos($textoLimpo, $p) !== false) {
            $matchPt++;
        }
    }
    if ($matchPt >= 3) {
        return $textoLimpo;
    }

    // Cache local em disco (vitalício para a obra, embutido na pasta do projeto para funcionar em outros PCs)
    $hash = md5($textoLimpo);
    $cacheDir = __DIR__ . '/../cache/trad';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
        $cacheDir = sys_get_temp_dir() . '/folivro_cache_trad';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
    }
    $cacheFile = $cacheDir . '/trad_' . $hash . '.txt';
    if (file_exists($cacheFile) && filesize($cacheFile) > 0) {
        return (string) file_get_contents($cacheFile);
    }

    // Divide parágrafos
    $paragrafos = preg_split('/\n\s*\n/', $textoLimpo);
    $traduzidos = [];

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 2, // Timeout estrito de 2s para evitar qualquer lentidão no carregamento
            'header' => "User-Agent: FOLIVRO-TCC-Demo/1.0 (academic; Brazilian Portuguese)\r\n",
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $traduziuAlgum = false;
    $countTrad = 0;

    foreach ($paragrafos as $p) {
        $p = trim($p);
        if ($p === '') continue;

        // Não traduz referências de links técnicos, listas ou notas de rodapé da Wikipedia
        if (preg_match('/^(?:from|de|fonte:?)\s*\[/i', $p) || str_starts_with($p, '- ') || str_starts_with($p, '* ') || str_starts_with($p, '###') || str_starts_with($p, '---')) {
            $traduzidos[] = $p;
            continue;
        }

        // Traduz até os primeiros 2 blocos narrativos da sinopse de forma rápida
        if ($countTrad < 2 && mb_strlen($p, 'UTF-8') < 500) {
            $trad = folivro_executar_traducao_bloco($p, $ctx);
            if ($trad !== '' && $trad !== $p) {
                $traduzidos[] = $trad;
                $traduziuAlgum = true;
                $countTrad++;
                continue;
            }
        }

        $traduzidos[] = $p;
    }

    $resultadoFinal = implode("\n\n", $traduzidos);
    if ($resultadoFinal !== '' && $traduziuAlgum) {
        @file_put_contents($cacheFile, $resultadoFinal);
    }
    return $resultadoFinal !== '' ? $resultadoFinal : $textoLimpo;
}

/**
 * Executa chamada à API neural pública de tradução para um bloco de texto.
 */
function folivro_executar_traducao_bloco(string $bloco, $ctx): string
{
    $bloco = trim($bloco);
    if ($bloco === '') {
        return '';
    }

    $url = 'https://api.mymemory.translated.net/get?q=' . rawurlencode($bloco) . '&langpair=en|pt-BR';
    $res = @file_get_contents($url, false, $ctx);
    if ($res) {
        $data = json_decode($res, true);
        if (isset($data['responseData']['translatedText']) && is_string($data['responseData']['translatedText'])) {
            $t = trim($data['responseData']['translatedText']);
            if ($t !== '' && stripos($t, 'MYMEMORY WARNING') === false) {
                return $t;
            }
        }
    }
    return $bloco;
}

/**
 * Formata sinopse em HTML limpo, transformando links markdown em links funcionais,
 * removendo notas de rodapé de enciclopédias e formatando listas e citações.
 */
function folivro_formatar_sinopse_html(string $texto): string
{
    $texto = trim($texto);
    if ($texto === '') {
        return '';
    }

    // 1. Remove barras invertidas de escape comuns em Markdown
    $texto = str_replace(['\\[', '\\]', '\(', '\)'], ['[', ']', '(', ')'], $texto);

    // 2. Remove notas de rodapé da Wikipedia como .[1](https://...) ou [2](https://...) ou [2]
    $texto = preg_replace('/\.\s*\[[0-9]+\]\([^\)]+\)/u', '.', $texto);
    $texto = preg_replace('/\[[0-9]+\]\([^\)]+\)/u', '', $texto);
    $texto = preg_replace('/\[[0-9]+\]/u', '', $texto);

    // 3. Remove linhas isoladas que sejam apenas marcadores como ### ou --- ou ***
    $texto = preg_replace('/^(?:#{1,6}|-{3,}|\*{3,})\s*$/m', '', $texto);

    // 4. Divide em blocos por quebras de linha duplas
    $blocos = preg_split('/\n\s*\n/', $texto);
    $htmlParagrafos = [];

    foreach ($blocos as $bloco) {
        $bloco = trim($bloco);
        if ($bloco === '') {
            continue;
        }

        // Se for linha de cabeçalho tipo "### Ver também:" ou "Ver também:"
        if (preg_match('/^(?:#{1,4}\s*)?(Ver também|See also|Outras edições|Leia também):?/iu', $bloco, $m)) {
            $htmlParagrafos[] = '<h4 class="subtitulo-sinopse">' . htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8') . '</h4>';
            continue;
        }

        // Se for indicação de fonte editorial no topo (ex: "De [wikipedia](url):" ou "From [wikipedia](url):")
        if (preg_match('/^(?:from|de|fonte:?)\s*\[([^\]]+)\]\((https?:\/\/[^\)]+)\):?/iu', $bloco, $m)) {
            $nomeFonte = htmlspecialchars(ucfirst($m[1]), ENT_QUOTES, 'UTF-8');
            $urlFonte = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            $htmlParagrafos[] = '<div class="selo-fonte-sinopse"><span class="texto-fonte-desc">Resumo editorial via</span> <a href="' . $urlFonte . '" target="_blank" rel="noopener noreferrer" class="link-fonte-destaque">' . $nomeFonte . ' ↗</a></div>';
            continue;
        }

        // Se o bloco for uma lista com marcadores ("- [item](url)" ou "* item")
        if (preg_match('/^[\-\*]\s+/m', $bloco)) {
            $linhas = explode("\n", $bloco);
            $itensHtml = '';
            foreach ($linhas as $linha) {
                $linha = trim($linha);
                if ($linha === '') continue;
                $conteudoLinha = preg_replace('/^[\-\*]\s+/u', '', $linha);

                // Converte links markdown da linha
                $linhaFormatada = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\)\s]+|\/[^\)\s]+)\)/u', function ($lm) {
                    $rotulo = htmlspecialchars(trim($lm[1]), ENT_QUOTES, 'UTF-8');
                    $url = trim($lm[2]);
                    if (str_starts_with($url, '/')) {
                        $url = 'https://openlibrary.org' . $url;
                    }
                    $urlSegura = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                    return '<a href="' . $urlSegura . '" target="_blank" rel="noopener noreferrer" class="link-sinopse-externo">' . $rotulo . ' <span class="setinha-externa">↗</span></a>';
                }, htmlspecialchars($conteudoLinha, ENT_QUOTES, 'UTF-8'));

                // Desfaz escape dos links recém-criados
                $linhaFormatada = preg_replace('/&lt;a href=&quot;([^&]+)&quot; target=&quot;_blank&quot; rel=&quot;noopener noreferrer&quot; class=&quot;link-sinopse-externo&quot;&gt;(.*?)&lt;\/a&gt;/', '<a href="$1" target="_blank" rel="noopener noreferrer" class="link-sinopse-externo">$2</a>', $linhaFormatada);

                $itensHtml .= '<li class="item-sinopse-lista">' . $linhaFormatada . '</li>';
            }
            if ($itensHtml !== '') {
                $htmlParagrafos[] = '<ul class="lista-sinopse-links">' . $itensHtml . '</ul>';
            }
            continue;
        }

        // Bloco normal de texto: escapa e converte links markdown funcionais
        $blocoFormatado = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\)\s]+|\/[^\)\s]+)\)/u', function ($m) {
            $rotulo = htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8');
            $url = trim($m[2]);
            if (str_starts_with($url, '/')) {
                $url = 'https://openlibrary.org' . $url;
            }
            $urlSegura = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            return '<a href="' . $urlSegura . '" target="_blank" rel="noopener noreferrer" class="link-sinopse-externo">' . $rotulo . ' <span class="setinha-externa">↗</span></a>';
        }, htmlspecialchars($bloco, ENT_QUOTES, 'UTF-8'));

        $blocoFormatado = preg_replace('/&lt;a href=&quot;([^&]+)&quot; target=&quot;_blank&quot; rel=&quot;noopener noreferrer&quot; class=&quot;link-sinopse-externo&quot;&gt;(.*?)&lt;\/a&gt;/', '<a href="$1" target="_blank" rel="noopener noreferrer" class="link-sinopse-externo">$2</a>', $blocoFormatado);

        // Suporte seguro a **negrito** e *itálico*
        $blocoFormatado = preg_replace('/\*\*([^\*]+)\*\*/u', '<strong>$1</strong>', $blocoFormatado);
        $blocoFormatado = preg_replace('/\*([^\*]+)\*/u', '<em>$1</em>', $blocoFormatado);

        $htmlParagrafos[] = '<p class="paragrafo-sinopse">' . nl2br($blocoFormatado) . '</p>';
    }

    return implode("\n", $htmlParagrafos);
}
