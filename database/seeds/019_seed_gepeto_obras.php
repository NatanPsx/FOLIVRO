<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/loja.php';
require_once __DIR__ . '/../../config/dominio_publico.php';

function folivro_seed_gepeto_obras(PDO $pdo): void
{
    foreach ($pdo->query('SELECT id_produto FROM loja_produtos')->fetchAll(PDO::FETCH_COLUMN) as $idP) {
        folivro_loja_garantir_obra_autoral($pdo, (int) $idP);
    }

    $idGepeto = (int) $pdo->query("SELECT id_usuario FROM usuario WHERE email_usuario = 'autora@folivro.local' OR id_usuario = 44 ORDER BY id_usuario DESC LIMIT 1")->fetchColumn();
    if ($idGepeto <= 0) {
        $idGepeto = (int) $pdo->query('SELECT id_usuario FROM usuario ORDER BY id_usuario ASC LIMIT 1')->fetchColumn();
    } else {
        $pdo->prepare("UPDATE usuario SET nome_usuario = 'GePeTo' WHERE id_usuario = ?")->execute([$idGepeto]);
    }

    $titulosDemo = [
        'Cartas do Observatório Sul' => 'uploads/capas/loja_observatorio_sul.svg',
        'Crônicas da Biblioteca de Papel' => 'uploads/capas/loja_biblioteca_papel.svg',
        'O Arquipélago de Vidro' => 'uploads/capas/loja_arquipelago_vidro.svg',
        'Algoritmo da Neblina' => 'uploads/capas/loja_algoritmo_neblina.svg',
        'Crônicas da Torre Cinzenta' => 'uploads/capas/loja_torre_cinzenta.svg',
        'Sombras da Meia-Noite' => 'uploads/capas/loja_sombras_meia_noite.svg',
    ];

    foreach ($titulosDemo as $t => $capa) {
        if ($idGepeto > 0) {
            $pdo->prepare('UPDATE loja_produtos SET id_autor = ?, capa_produto = ? WHERE titulo_produto = ?')->execute([$idGepeto, $capa, $t]);
            $pdo->prepare('UPDATE historias SET id_usuario = ?, capa_historias = ? WHERE titulo_historias = ?')->execute([$idGepeto, $capa, $t]);
            $pdo->prepare('UPDATE autor_dos_livros a JOIN historias h ON h.id_historias = a.id_historias SET a.id_autor = ? WHERE h.titulo_historias = ?')->execute([$idGepeto, $t]);
        }
        $pdo->prepare("UPDATE livro SET autor_livro = 'GePeTo', capa_livro = ? WHERE titulo_livro = ?")->execute([$capa, $t]);
    }

    foreach (folivro_dp_catalogo() as $wId => $dp) {
        $pdo->prepare('UPDATE livro SET titulo_livro = ?, autor_livro = ?, capa_livro = ?, classificacao_livro = ?, paginas_livro = ? WHERE id_externo_livro = ?')
            ->execute([$dp['title'], $dp['authors'][0], $dp['coverUrl'], $dp['classificacao'], count($dp['capitulos']), $wId]);
    }

    $capsObservatorio = [
        [
            'titulo' => 'A Cúpula sob a Geada',
            'texto' => '<p>Naquela noite de inverno, o telescópio apontava para além das nuvens baixas da serra, onde cada estrela parecia guardar uma carta não enviada. O vento cortante da Mantiqueira assobiava pelas frestas de cobre da velha cúpula do Observatório Sul, fazendo vibrar as lâminas do piso de madeira como se o edifício inteiro respirasse no compasso da madrugada.</p><p>Henrique ajustou o foco da ocular com os dedos enluvados e anotou a declinação no caderno de capa dura. Há doze anos subia aquela montanha todas as semanas, carregando uma garrafa térmica de café amargo, mapas celestes desenhados à mão e a silenciosa esperança de registrar a passagem de um cometa de período longo que seu avô descrevera em 1954.</p><p>Sobre a escrivaninha de mogno, ao lado do cronômetro marítimo, repousava um envelope selado com cera azul. Não trazia selo postal nem carimbo de agência; fora deixado entre as páginas do atlas estelar na véspera, com uma única linha caligrafada em tinta ferrogálica: <em>“Olhe para o setor de Carina antes que a aurora apague o rastro.”</em></p><p>Ele releu o bilhete três vezes antes de girar a manivela da fenda zenital. Quando as placas metálicas se abriram para o céu austral, a faixa leitosa da Via Láctea inundou o salão escuro com um brilho pálido e ancestral.</p>'
        ],
        [
            'titulo' => 'O Atlas das Constelações Esquecidas',
            'texto' => '<p>Ao puxar o terceiro volume do arquivo do observatório, Henrique encontrou anotações que não constavam em nenhum catálogo oficial da União Astronômica. Eram cartas traçadas por antigos navegadores e astrônomos amadores que cruzaram o Atlântico Sul no final do século XIX, registrando estrelas variáveis que pareciam pulsar em intervalos quase musicais.</p><p>Nas margens amareladas do papel de linho, uma caligrafia feminina — firme, inclinada e precisa — havia acrescentado comentários datados de três invernos atrás. Chamava-se Helena, ex-cartógrafa celeste que trabalhara na restauração das lentes refratoras antes da reforma do prédio principal.</p><p>“Quem observa o céu do hemisfério sul aprende cedo que a luz viaja como uma memória”, dizia uma das notas. “Quando vemos o brilho de Eta Carinae esta noite, estamos lendo uma carta escrita há sete mil e quinhentos anos. Nada do que brilha lá em cima pertence ao presente; e, ainda assim, é a única bússola que temos para o amanhã.”</p><p>Henrique sentiu um arrepio percorrer-lhe a nuca. As coordenadas anotadas por Helena coincidiam, segundo por segundo, com o ponto indicado no envelope azul deixado sobre sua mesa.</p>'
        ],
        [
            'titulo' => 'A Segunda Carta no Astrolábio',
            'texto' => '<p>Na noite seguinte, a neblina subiu do vale mais cedo, envolvendo os pinheiros em um véu prateado que refletia a luz da lua minguante. Henrique chegou ao observatório duas horas antes da meia-noite e encontrou a porta lateral destrancada, exatamente como costumava ficar quando a equipe de manutenção subia a serra.</p><p>Mas não havia ninguém nos corredores. Apenas o tique-taque compassado do pêndulo de quartzo e, preso à base do antigo astrolábio de latão em exposição na antessala, um segundo envelope azul.</p><p>Desta vez, a carta era mais longa. Relatava a história de dois observadores separados por latitudes opostas que, durante décadas, trocavam mensagens usando as efemérides de ocultação lunar como código. Cada data correspondia a uma página dos cadernos guardados no sótão da cúpula menor.</p><p>Com uma lanterna de luz vermelha — para não ofuscar a visão adaptada ao escuro —, Henrique subiu a escada helicoidal até o arquivo superior. Entre caixas de chapas fotográficas de vidro, encontrou o diário de observação número sete, intacto sob uma fina camada de poeira.</p>'
        ],
        [
            'titulo' => 'As Chapas de Vidro do Cruzeiro',
            'texto' => '<p>As chapas fotográficas revelavam o céu da serra tal como fora registrado em junho de 1987. Contra o visor iluminado da mesa de inspeção, os pontos negros sobre o negativo de vidro formavam o desenho inconfundível do Cruzeiro do Sul e do Saco de Carvão.</p><p>No verso da última lâmina, presa com fita de papel-arroz, havia uma fotografia em preto e branco: dois jovens astrônomos sorriam diante do grande refrator de vinte polegadas. Um deles era o próprio avô de Henrique; ao seu lado, segurando uma régua de cálculo astronômico, estava a mãe de Helena.</p><p>Tudo se encaixou com a clareza silenciosa de uma equação resolvida. As cartas não eram um enigma lançado ao acaso, mas a continuação de uma promessa feita entre duas famílias que haviam dedicado a vida a manter acesa aquela vigília no topo da montanha, mesmo quando o financiamento minguara e a cidade lá embaixo esquecera o caminho da serra.</p><p>Do lado de fora, o vento cessou de repente, limpando o horizonte sul como um pano puxado sobre um espelho escuro.</p>'
        ],
        [
            'titulo' => 'Antes que a Aurora Apague o Rastro',
            'texto' => '<p>Eram quatro e vinte da madrugada quando passos leves soaram nos degraus de ferro da plataforma de observação. Henrique não precisou se virar para saber quem subia. Helena trazia nos ombros um casaco de lã escuro e, nas mãos, o caderno original de 1954 que faltava na estante do avô.</p><p>— Achei que você só abriria a terceira carta no solstício — disse ela, com um sorriso sereno, aproximando-se do parapeito da cúpula.</p><p>— Você subestimou a curiosidade de quem passa o inverno inteiro olhando para o escuro — respondeu Henrique, cedendo espaço junto à ocular do telescópio. — Ele voltou, não foi?</p><p>Helena inclinou-se sobre a lente. Lá, exatamente na borda austral de Carina, uma tênue cabeleira esverdeada riscava a escuridão entre duas estrelas de quinta magnitude: o cometa de longo período retornava ao periélio após setenta e dois anos de viagem pelo frio interestelar.</p><p>Permaneceram em silêncio enquanto o primeiro fio dourado da aurora tingia as cristas da serra. Algumas cartas levam uma vida inteira para atravessar a noite, mas chegam sempre a tempo para quem não deixa de olhar para o céu.</p>'
        ],
    ];

    $capsBiblioteca = [
        [
            'titulo' => 'O Segredo das Estantes',
            'texto' => '<p>Nas galerias silenciosas da antiga biblioteca, cada volume guardava memórias de leitores de diferentes gerações. Fundada em 1928 num casarão de esquina com vitrais cor de âmbar, a Biblioteca Comunitária de Santa Teresa resistira ao tempo graças ao hábito peculiar de seus frequentadores: ninguém devolvia um livro sem deixar, entre suas folhas, um pequeno vestígio de quem o lera.</p><p>Clara, a jovem bibliotecária responsável pelo acervo de obras raras, conhecia cada estante pelo perfume da madeira de cedro e pelo estalido dos degraus da escada corredora. Todas as manhãs, antes de abrir os portões de ferro batido, ela percorria os corredores do segundo pavimento para recolher marcadores esquecidos, bilhetes dobrados e flores secas prensadas entre capítulos.</p><p>Foi numa terça-feira chuvosa de abril que ela encontrou, dentro de uma edição desgastada de contos brasileiros, um mapa desenhado no verso de uma ficha de empréstimo datada de 1974. O traçado indicava uma estante que não aparecia na planta baixa oficial do edifício.</p><p>Intrigada, Clara caminhou até a ala norte, onde os dicionários enciclopédicos repousavam lado a lado como sentinelas imóveis.</p>'
        ],
        [
            'titulo' => 'A Ficha de Empréstimo nº 408',
            'texto' => '<p>Atrás da fileira de enciclopédias do século passado, um painel de lambril cedia levemente à pressão dos dedos. Ao deslizar a trava de latão oxidado, Clara revelou um pequeno gabinete de leitura iluminado por uma claraboia circular que dava para o jardim interno do casarão.</p><p>No centro da saleta, sobre uma mesa de carvalho, havia um fichário de madeira com centenas de cartões catalográficos preenchidos à mão. Não eram registros comuns de catálogo: cada ficha trazia o número de um leitor e a história de como determinado livro havia mudado o rumo de sua vida.</p><p>A ficha número 408 pertencia a Seu Vicente, o relojoeiro da rua de baixo, que em 1974 conhecera sua esposa ao disputar o último exemplar de um romance de Machado de Assis numa tarde de tempestade. Ambos haviam assinado o cartão e prometido manter aquele gabinete secreto como um arquivo vivo da memória afetiva do bairro.</p><p>Clara passou as pontas dos dedos pelas dezenas de cartões seguintes. Ali estavam professores, estudantes, tipógrafos e poetas anônimos que, ao longo de cinquenta anos, haviam transformado aquela biblioteca num refúgio contra o esquecimento.</p>'
        ],
        [
            'titulo' => 'O Clube das Margens Anotadas',
            'texto' => '<p>À medida que mergulhava nos registros do gabinete da claraboia, Clara descobriu que um grupo de leitores se reunia ali todas as quintas-feiras até o início dos anos noventa. Chamavam a si mesmos de <em>O Clube das Margens Anotadas</em>.</p><p>A regra do grupo era simples e encantadora: um mesmo livro circulava entre sete moradores do bairro durante sete semanas. Cada leitor lia a obra usando um lápis de cor diferente para sublinhar trechos e responder, no rodapé das páginas, às reflexões deixadas pelo leitor anterior.</p><p>Ao abrir um dos volumes guardados na estante do gabinete, Clara viu páginas inteiras transformadas num diálogo vivo através das décadas. Onde uma mão jovem escrevera em 1982 uma dúvida sobre a coragem de recomeçar, outra caligrafia mais madura respondera, cinco anos depois, com um relato sincero de superação.</p><p>Aquele livro não era apenas papel e tinta impressa; era uma conversa que vencera o próprio tempo.</p>'
        ],
        [
            'titulo' => 'A Noite da Chuva de Outono',
            'texto' => '<p>Na semana seguinte, um forte temporal de outono ameaçou o telhado antigo da ala oeste do casarão. Quando a energia elétrica do quarteirão caiu por volta das sete da noite, Clara pensou que estaria sozinha para proteger as caixas de periódicos históricos das goteiras que começavam a surgir perto das janelas.</p><p>Para sua surpresa, em menos de vinte minutos, lanternas começaram a brilhar na calçada de pedra. Seu Vicente, agora com cabelos brancos e passos calmos, foi o primeiro a entrar, trazendo lonas impermeáveis e velas dentro de lampiões de vidro. Atrás dele vieram ex-alunos da escola vizinha, uma professora aposentada e dois jovens escritores que passavam as tardes estudando no salão principal.</p><p>Durante três horas, trabalharam ombro a ombro à luz dos lampiões, transferindo coleções inteiras para as galerias superiores. Quando a chuva finalmente amainou, sentaram-se em círculo no tapete do salão central, compartilhando chá quente e lendo em voz alta trechos escolhidos ao acaso nas estantes.</p><p>Clara compreendeu, naquela noite, que uma biblioteca nunca é feita apenas de paredes: ela existe enquanto houver pessoas dispostas a proteger as histórias umas das outras.</p>'
        ],
        [
            'titulo' => 'Novas Páginas em Branco',
            'texto' => '<p>Com a chegada da primavera, o gabinete da claraboia foi oficialmente integrado ao roteiro aberto da Biblioteca de Papel. A mesa de carvalho ganhou um novo fichário, com centenas de cartões em branco esperando pelas próximas gerações de leitores.</p><p>Clara retomou também a tradição do Clube das Margens Anotadas, destinando uma estante inteira a obras independentes escritas pelos próprios moradores e jovens autores da comunidade. Ao lado de cada exemplar, uma caixa de lápis de cor convidava o visitante a deixar sua impressão para o próximo leitor.</p><p>No primeiro cartão do novo fichário — o de número 1.001 —, Clara escreveu com letra firme, sob a luz dourada que descia pelos vitrais cor de âmbar:</p><p><em>“Todo livro começa quando o autor escreve a última linha, mas só se completa quando encontra alguém disposto a habitar suas páginas.”</em></p>'
        ],
    ];

    $todasObrasCaps = [
        'Cartas do Observatório Sul' => $capsObservatorio,
        'Crônicas da Biblioteca de Papel' => $capsBiblioteca,
    ];

    foreach ($todasObrasCaps as $tituloObra => $capitulos) {
        $st = $pdo->prepare('SELECT id_historias FROM historias WHERE titulo_historias = ? ORDER BY id_historias ASC');
        $st->execute([$tituloObra]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $idH) {
            $idH = (int) $idH;
            $pdo->prepare('DELETE FROM capitulos WHERE id_historias = ?')->execute([$idH]);
            $ins = $pdo->prepare(
                "INSERT INTO capitulos (id_historias, titulo_capitulos, conteudo_capitulos, hash_conteudo_capitulos,
                                        ordem_capitulos, status_capitulos, modo_publicacao_capitulos, revisao_capitulos, data_revisao_capitulos)
                 VALUES (?, ?, ?, ?, ?, 'publicado', 'revisao_humana', 'aprovada', CURRENT_TIMESTAMP)"
            );
            foreach ($capitulos as $idx => $cap) {
                $ordem = $idx + 1;
                $ins->execute([$idH, $cap['titulo'], $cap['texto'], hash('sha256', $tituloObra . '|' . $ordem . '|' . $cap['texto']), $ordem]);
            }
        }
        $pdo->prepare('UPDATE livro SET paginas_livro = ? WHERE titulo_livro = ?')->execute([count($capitulos), $tituloObra]);
    }
}
