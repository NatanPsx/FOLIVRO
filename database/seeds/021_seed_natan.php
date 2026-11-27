<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/loja.php';

/**
 * Semeia a conta completa de demonstração do Natan (natan@folivro.br / Natan123!)
 * com estante, progressos, resenhas, metas, pontos, chave Pix de apoio e obras autorais,
 * garantindo que funcione automaticamente em qualquer computador onde o projeto seja aberto.
 */
function folivro_seed_natan(PDO $pdo): void
{
    $email = 'natan@folivro.br';
    $stUser = $pdo->prepare('SELECT id_usuario FROM usuario WHERE LOWER(TRIM(email_usuario)) = ? LIMIT 1');
    $stUser->execute([$email]);
    $idNatan = (int) $stUser->fetchColumn();

    if ($idNatan <= 0) {
        $insUser = $pdo->prepare(
            "INSERT INTO usuario
                (nome_usuario, email_usuario, data_nascimento_usuario, senha_usuario,
                 biografia_usuario, link_apoio_usuario, chave_pix_usuario, mensagem_apoio_usuario,
                 nivel_usuario, status_usuario, modalidade_publicacao_usuario, capitulos_aprovados_usuario)
             VALUES (?, ?, ?, ?, ?, NULL, ?, ?, 'autor', 'ativo', 'automatica', 3)"
        );
        $insUser->execute([
            'Natan',
            $email,
            '2010-08-19',
            password_hash('Natan123!', PASSWORD_DEFAULT),
            'Estudante do Ensino Médio, apaixonado por literatura brasileira, fantasia juvenil e ficção científica. Escrevo histórias autorais nas horas vagas e organizo minhas metas de leitura aqui no FOLIVRO.',
            'fdd73700-2452-41e9-8521-4fbb7aa762b3',
            'Muito obrigado por chegar junto! Seu apoio ajuda a manter as minhas historias vivas e com gás novo. Seja muito bem-vindo(a)!',
        ]);
        $idNatan = (int) $pdo->lastInsertId();
    }

    if ($idNatan <= 0) {
        return;
    }

    // Termos aceitos (LGPD)
    $chkTermos = $pdo->prepare('SELECT COUNT(*) FROM termos_aceitos WHERE id_usuario = ?');
    $chkTermos->execute([$idNatan]);
    if ((int) $chkTermos->fetchColumn() === 0) {
        $insTerm = $pdo->prepare('INSERT INTO termos_aceitos (id_usuario, tipo_termos, versao_termos) VALUES (?, ?, ?)');
        $insTerm->execute([$idNatan, 'termos_uso', '1.0']);
        $insTerm->execute([$idNatan, 'privacidade', '1.0']);
    }

    // Pontos de leitura
    $pdo->prepare('INSERT IGNORE INTO contador_pontos (id_usuario, pontos_contador_de_pontos) VALUES (?, 469)')
        ->execute([$idNatan]);

    // Helper para localizar ou criar livro no catálogo
    $findLivro = static function (PDO $db, ?string $extId, string $titulo) : int {
        if ($extId !== null && $extId !== '') {
            $s = $db->prepare('SELECT id_livro FROM livro WHERE id_externo_livro = ? LIMIT 1');
            $s->execute([$extId]);
            $id = (int) $s->fetchColumn();
            if ($id > 0) {
                return $id;
            }
        }
        $s2 = $db->prepare('SELECT id_livro FROM livro WHERE titulo_livro = ? LIMIT 1');
        $s2->execute([$titulo]);
        return (int) $s2->fetchColumn();
    };

    $livrosEstante = [
        [
            'ext' => 'OL66554W', 'titulo' => 'Orgulho e Preconceito', 'autor' => 'Jane Austen',
            'fonte' => 'openlibrary', 'capa' => 'uploads/capas/cache_ol/14348537-M.jpg',
            'genero' => 'Romance, Clássico', 'classif' => '12', 'paginas' => 351, 'pub' => '1813-01-01',
            'status' => 'abandonado', 'atual' => 210, 'total' => 351, 'pct' => 59.83,
            'sinopse' => 'Orgulho e Preconceito é um romance clássico de Jane Austen sobre Elizabeth Bennet e os costumes sociais da Inglaterra do século XIX.',
        ],
        [
            'ext' => 'OL138052W', 'titulo' => 'Alice no País das Maravilhas', 'autor' => 'Lewis Carroll',
            'fonte' => 'openlibrary', 'capa' => 'uploads/capas/cache_ol/10527843-L.jpg',
            'genero' => 'Fantasia, Aventura', 'classif' => 'livre', 'paginas' => 192, 'pub' => '1869-01-01',
            'status' => 'lido', 'atual' => 192, 'total' => 192, 'pct' => 100.00,
            'sinopse' => 'Clássico da fantasia de Lewis Carroll em que a jovem Alice cai em uma toca de coelho e explora um mundo cheio de criaturas extraordinárias.',
        ],
        [
            'ext' => 'LOJA-1', 'titulo' => 'Cartas do Observatório Sul', 'autor' => 'GePeTo',
            'fonte' => 'loja', 'capa' => 'uploads/capas/loja_observatorio_sul.svg',
            'genero' => 'Crônica, Literatura Nacional', 'classif' => '10', 'paginas' => 5, 'pub' => null,
            'status' => 'lendo', 'atual' => 3, 'total' => 5, 'pct' => 60.00,
            'sinopse' => 'Coletânea de crônicas astronômicas e reflexões sobre o céu do hemisfério sul.',
        ],
        [
            'ext' => 'LOJA-6', 'titulo' => 'Crônicas da Biblioteca de Papel', 'autor' => 'GePeTo',
            'fonte' => 'loja', 'capa' => 'uploads/capas/loja_biblioteca_papel.svg',
            'genero' => 'Ficção, Drama, Literatura Nacional', 'classif' => 'livre', 'paginas' => 5, 'pub' => null,
            'status' => 'lendo', 'atual' => 3, 'total' => 5, 'pct' => 60.00,
            'sinopse' => 'Uma jornada literária acolhedora sobre o poder da leitura e a preservação de memórias em uma biblioteca comunitária.',
        ],
        [
            'ext' => 'OL263058W', 'titulo' => 'Dom Casmurro', 'autor' => 'Machado de Assis',
            'fonte' => 'openlibrary', 'capa' => 'uploads/capas/dp_dom_casmurro.svg',
            'genero' => 'Clássico, Literatura Nacional', 'classif' => 'livre', 'paginas' => 15, 'pub' => '1899-01-01',
            'status' => 'lendo', 'atual' => 6, 'total' => 15, 'pct' => 40.00,
            'sinopse' => 'Clássico absoluto de Machado de Assis (1899), narrado por Bento Santiago (Bentinho), que revisita sua juventude e seu relacionamento com Capitu.',
        ],
        [
            'ext' => 'OL262884W', 'titulo' => 'Memórias Póstumas de Brás Cubas', 'autor' => 'Machado de Assis',
            'fonte' => 'openlibrary', 'capa' => 'uploads/capas/dp_bras_cubas.svg',
            'genero' => 'Clássico, Literatura Nacional', 'classif' => '14', 'paginas' => 10, 'pub' => '1881-01-01',
            'status' => 'abandonado', 'atual' => 2, 'total' => 10, 'pct' => 20.00,
            'sinopse' => 'Marco inaugural do Realismo no Brasil (1881), em que o defunto-autor Brás Cubas narra com ironia sua própria trajetória.',
        ],
        [
            'ext' => 'OL262969W', 'titulo' => 'O Alienista', 'autor' => 'Machado de Assis',
            'fonte' => 'openlibrary', 'capa' => 'uploads/capas/dp_o_alienista.svg',
            'genero' => 'Clássico, Literatura Nacional', 'classif' => 'livre', 'paginas' => 13, 'pub' => '1882-01-01',
            'status' => 'lido', 'atual' => 13, 'total' => 13, 'pct' => 100.00,
            'sinopse' => 'Novela satírica de Machado de Assis sobre o Dr. Simão Bacamarte e a fundação da Casa Verde em Itaguaí.',
        ],
        [
            'ext' => 'OL1168083W', 'titulo' => '1984', 'autor' => 'George Orwell',
            'fonte' => 'openlibrary', 'capa' => null,
            'genero' => 'Distopia, Ficção Científica', 'classif' => '16', 'paginas' => 328, 'pub' => '1949-01-01',
            'status' => 'lendo', 'atual' => 0, 'total' => 328, 'pct' => 0.00,
            'sinopse' => 'Romance distópico de George Orwell sobre vigilância em massa, controle da linguagem e autoritarismo.',
        ],
        [
            'ext' => 'OL36287W', 'titulo' => 'O Conde de Monte Cristo', 'autor' => 'Alexandre Dumas',
            'fonte' => 'openlibrary', 'capa' => null,
            'genero' => 'Aventura, Clássico', 'classif' => '14', 'paginas' => 588, 'pub' => '1844-01-01',
            'status' => 'lendo', 'atual' => 0, 'total' => 588, 'pct' => 0.00,
            'sinopse' => 'A jornada de Edmond Dantès, preso injustamente no Castelo de If, que retorna anos depois como o misterioso Conde de Monte Cristo.',
        ],
        [
            'ext' => 'OL1168007W', 'titulo' => 'A Revolução dos Bichos', 'autor' => 'George Orwell',
            'fonte' => 'openlibrary', 'capa' => null,
            'genero' => 'Fábula Política, Clássico', 'classif' => '14', 'paginas' => 128, 'pub' => '1945-01-01',
            'status' => 'lendo', 'atual' => 0, 'total' => 128, 'pct' => 0.00,
            'sinopse' => 'Sátira política em forma de fábula sobre os animais de uma fazenda que se rebelam contra os humanos.',
        ],
    ];

    $insLivro = $pdo->prepare(
        "INSERT INTO livro (titulo_livro, autor_livro, isbn_livro, sinopse_livro, capa_livro, genero_livro, classificacao_livro, paginas_livro, data_publicacao_livro, fonte_livro, id_externo_livro)
         VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insLiv = $pdo->prepare('INSERT IGNORE INTO livraria (id_usuario, id_livro, status_livraria) VALUES (?, ?, ?)');
    $insProg = $pdo->prepare(
        'INSERT IGNORE INTO progressao_leitura (id_usuario, id_livro, pagina_atual_progressao, total_paginas_progressao, porcentagem_progressao)
         VALUES (?, ?, ?, ?, ?)'
    );

    $mapaLivros = [];
    foreach ($livrosEstante as $item) {
        $idLiv = $findLivro($pdo, $item['ext'], $item['titulo']);
        if ($idLiv <= 0) {
            $insLivro->execute([
                $item['titulo'], $item['autor'], $item['sinopse'], $item['capa'],
                $item['genero'], $item['classif'], $item['paginas'], $item['pub'],
                $item['fonte'], $item['ext'],
            ]);
            $idLiv = (int) $pdo->lastInsertId();
        }
        $mapaLivros[$item['ext']] = $idLiv;
        if ($idLiv > 0) {
            $insLiv->execute([$idNatan, $idLiv, $item['status']]);
            $insProg->execute([$idNatan, $idLiv, $item['atual'], $item['total'], $item['pct']]);
        }
    }

    // Resenhas
    $resenhas = [
        ['OL66554W', 4, 'Diálogos afiados e personagens marcantes', 'Elizabeth Bennet é uma protagonista incrível. A leitura flui muito bem e a crítica social da época continua atual e divertida.'],
        ['OL138052W', 5, 'Criatividade sem limites', 'Reli este clássico recentemente e continuei encantado com os jogos de palavras, o Chapeleiro Maluco e a forma como a história brinca com a lógica.'],
        ['LOJA-1', 5, 'Atmosfera acolhedora e poética', 'Adorei o ritmo das cartas e a forma delicada como o observatório é descrito. Ótima obra independente da comunidade FOLIVRO!'],
        ['OL263058W', 5, 'Ironia brilhante e narrativa inesquecível', 'Machado de Assis constrói um narrador tão envolvente quanto suspeito. Cada capítulo faz a gente questionar o que realmente aconteceu entre Bentinho e Capitu.'],
        ['OL262969W', 4, 'Sátira inteligente sobre a razão', 'O Dr. Simão Bacamarte leva o método científico ao extremo de um jeito genial. Curto, direto e cheio de humor ácido.'],
    ];
    $insRes = $pdo->prepare('INSERT IGNORE INTO resenha (id_usuario, id_livro, nota_resenha, titulo_resenha, resenha_resenha) VALUES (?, ?, ?, ?, ?)');
    foreach ($resenhas as [$extKey, $nota, $titR, $txtR]) {
        $idL = $mapaLivros[$extKey] ?? 0;
        if ($idL > 0) {
            $insRes->execute([$idNatan, $idL, $nota, $titR, $txtR]);
        }
    }

    // Metas de leitura
    $chkMetas = $pdo->prepare('SELECT COUNT(*) FROM metas_leitura WHERE id_usuario = ?');
    $chkMetas->execute([$idNatan]);
    if ((int) $chkMetas->fetchColumn() === 0) {
        $insMeta = $pdo->prepare(
            'INSERT INTO metas_leitura (id_usuario, titulo_metas, tipo_metas, quantidade_metas, progresso_metas, data_inicio_metas, data_fim_metas, status_metas)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insMeta->execute([$idNatan, 'Ler 12 livros em 2026', 'livros', 12, 2, '2026-01-01', '2026-12-31', 'em_andamento']);
        $insMeta->execute([$idNatan, 'Desafio 500 Páginas', 'paginas', 500, 429, '2026-11-25', '2026-12-25', 'em_andamento']);
        $insMeta->execute([$idNatan, 'Clássicos Brasileiros', 'livros', 2, 2, '2026-11-10', '2026-12-10', 'concluida']);
        $insMeta->execute([$idNatan, 'Meta anual 2026', 'livros', 12, 2, '2026-01-01', '2026-12-31', 'em_andamento']);
    }

    // Obras autorais do Natan (1 publicada com 3 capítulos + 1 rascunho)
    $obrasAutorais = [
        [
            'titulo' => 'O Caderno das Estrelas Perdidas',
            'sinopse' => 'Quando Sofia encontra um caderno antigo no sótão da biblioteca municipal, descobre que cada constelação desenhada nas margens guarda o mapa de uma memória esquecida da cidade.',
            'generos' => 'Fantasia, Aventura, Ficção Juvenil',
            'status_h' => 'publicada',
            'views' => 43,
            'aviso' => '12',
            'status_a' => 'a_venda',
            'revisao_a' => 'aprovada',
            'caps' => [
                [
                    'Capítulo 1 — O sótão dos mapas',
                    '<p>A chuva batia de leve nas janelas altas da biblioteca municipal quando Sofia subiu a escada estreita que levava ao arquivo de mapas antigos. Ela procurava apenas uma referência para o trabalho de geografia, mas encontrou algo muito mais curioso: um caderno de capa azul-marinho, preso por um cordão prateado.</p><p>Ao abrir a primeira página, dezenas de constelações desenhadas a bico de pena pareciam brilhar sob a luz amarelada da luminária. No rodapé, uma caligrafia firme dizia: <em>“Para quem ainda olha para o céu antes de atravessar a ponte.”</em></p>',
                    'publicado', 'automatica', 'aprovada',
                ],
                [
                    'Capítulo 2 — A bússola no mirante',
                    '<p>No dia seguinte, Sofia levou o caderno até o mirante da praça central acompanhada de seu amigo Pedro. Quando alinharam o desenho da segunda folha com a torre do relógio, perceberam que os pontos marcados correspondiam exatamente às antigas lanternas de ferro espalhadas pelo bairro.</p><p>— Isso não é só astronomia — murmurou Pedro, apontando para a margem da folha. — Cada estrela tem uma data e o nome de uma rua.</p>',
                    'publicado', 'automatica', 'aprovada',
                ],
                [
                    'Capítulo 3 — A Rua das Acácias',
                    '<p>Seguindo a primeira coordenada, eles chegaram à pequena livraria fechada na esquina da Rua das Acácias. Sob o batente da porta, uma placa discreta trazia o mesmo símbolo desenhado na contracapa do caderno: uma bússola com três folhas abertas.</p><p>Naquele instante, Sofia entendeu que a investigação estava apenas começando — e que a cidade guardava histórias muito mais antigas do que os livros didáticos contavam.</p>',
                    'publicado', 'automatica', 'aprovada',
                ],
            ],
        ],
        [
            'titulo' => 'Diário do Observatório Escolar',
            'sinopse' => 'Anotações e pequenos contos sobre o clube de astronomia da escola durante uma semana de eclipse lunar.',
            'generos' => 'Mistério, Ficção Juvenil',
            'status_h' => 'rascunho',
            'views' => 0,
            'aviso' => 'livre',
            'status_a' => 'rascunho',
            'revisao_a' => 'nao_solicitada',
            'caps' => [
                [
                    'Capítulo 1 — A noite do eclipse',
                    '<p>Rascunho inicial: na noite de sexta-feira, o terraço da escola foi liberado para o clube de astronomia montar os telescópios antes do eclipse começar.</p>',
                    'rascunho', 'revisao_humana', 'pendente',
                ],
            ],
        ],
    ];

    $chkHist = $pdo->prepare('SELECT id_historias FROM historias WHERE id_usuario = ? AND titulo_historias = ? LIMIT 1');
    $insHist = $pdo->prepare(
        "INSERT INTO historias (id_usuario, titulo_historias, sinopse_historias, capa_historias, generos_historias, status_historias, visualizacoes_historias, licenca_historias, aviso_conteudo_historias, origem_obra_historias, hash_conteudo_historias)
         VALUES (?, ?, ?, NULL, ?, ?, ?, 'direitos_reservados', ?, 'autoral_original', ?)"
    );
    $insCap = $pdo->prepare(
        "INSERT INTO capitulos (id_historias, titulo_capitulos, conteudo_capitulos, hash_conteudo_capitulos, ordem_capitulos, status_capitulos, modo_publicacao_capitulos, revisao_capitulos, data_revisao_capitulos)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
    );
    $insAdl = $pdo->prepare(
        "INSERT INTO autor_dos_livros (id_historias, id_autor, titulo_autor_dos_livros, preco_dos_livros_autor, modalidade_distribuicao, declaracao_autoria, data_declaracao_autoria, revisao_publicacao, status_autor_dos_livros, data_publicacao_autor)
         VALUES (?, ?, ?, 0.00, 'gratuita', 1, CURRENT_TIMESTAMP, ?, ?, IF(? = 'a_venda', CURRENT_TIMESTAMP, NULL))"
    );

    foreach ($obrasAutorais as $ob) {
        $chkHist->execute([$idNatan, $ob['titulo']]);
        if ((int) $chkHist->fetchColumn() > 0) {
            continue;
        }
        $insHist->execute([
            $idNatan, $ob['titulo'], $ob['sinopse'], $ob['generos'],
            $ob['status_h'], $ob['views'], $ob['aviso'],
            hash('sha256', $ob['titulo'] . ' ' . $ob['caps'][0][1]),
        ]);
        $idH = (int) $pdo->lastInsertId();
        foreach ($ob['caps'] as $idx => $cap) {
            $insCap->execute([
                $idH, $cap[0], $cap[1], hash('sha256', $cap[1]),
                $idx + 1, $cap[2], $cap[3], $cap[4],
            ]);
        }
        $insAdl->execute([$idH, $idNatan, $ob['titulo'], $ob['revisao_a'], $ob['status_a'], $ob['status_a']]);
        $idAdl = (int) $pdo->lastInsertId();
        if ($ob['status_a'] === 'a_venda' && $idAdl > 0) {
            folivro_loja_sincronizar_obra_estudio($pdo, $idAdl);
        }
    }
}
