-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: folivro
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `folivro`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `folivro` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `folivro`;

--
-- Table structure for table `_folivro_migrations`
--

DROP TABLE IF EXISTS `_folivro_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `_folivro_migrations` (
  `version` varchar(80) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `_folivro_migrations`
--

LOCK TABLES `_folivro_migrations` WRITE;
/*!40000 ALTER TABLE `_folivro_migrations` DISABLE KEYS */;
INSERT INTO `_folivro_migrations` VALUES ('001_initial_schema','2026-10-16 20:57:24'),('002_openlibrary_catalog','2026-10-16 21:29:57'),('003_copyright_and_moderation','2026-10-19 00:00:37'),('004_chat_security_and_copyright','2026-10-19 01:03:59'),('005_editorial_review_and_chapter_hashes','2026-10-22 00:37:21'),('006_disable_public_default_admin','2026-10-22 00:37:21'),('007_portable_demo_seed','2026-10-25 21:33:36'),('008_seed_normal_test_user','2026-10-26 00:11:14'),('009_age_classification_and_birthdate','2026-11-04 16:27:18'),('010_add_genres_to_historias','2026-11-04 17:00:46'),('011_hybrid_moderation_smart_publishing','2026-11-04 17:08:47'),('012_seed_current_books_and_works','2026-11-04 17:26:33'),('013_loja_marketplace','2026-11-17 10:59:44'),('014_seed_loja_marketplace','2026-11-17 11:00:38'),('015_sistema_config','2026-11-17 11:19:06'),('016_sincronizar_obras_estudio_loja','2026-11-17 11:26:12'),('017_chat_solicitacoes','2026-11-17 12:28:09'),('018_chat_notificacoes','2026-11-22 01:00:08');
/*!40000 ALTER TABLE `_folivro_migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `autor_dos_livros`
--

DROP TABLE IF EXISTS `autor_dos_livros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `autor_dos_livros` (
  `id_autor_dos_livros` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_historias` int(10) unsigned NOT NULL,
  `id_autor` int(10) unsigned NOT NULL,
  `titulo_autor_dos_livros` varchar(200) NOT NULL,
  `preco_dos_livros_autor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `declaracao_autoria` tinyint(1) NOT NULL DEFAULT 0,
  `data_declaracao_autoria` datetime DEFAULT NULL,
  `data_lancamento_autor` date DEFAULT NULL,
  `status_autor_dos_livros` enum('rascunho','a_venda','suspenso','removido') NOT NULL DEFAULT 'rascunho',
  `data_publicacao_autor` datetime DEFAULT NULL,
  `data_atualizacao_autor` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modalidade_distribuicao` enum('gratuita','venda_simulada') NOT NULL DEFAULT 'venda_simulada',
  `declaracao_comercializacao` tinyint(1) NOT NULL DEFAULT 0,
  `data_declaracao_comercializacao` datetime DEFAULT NULL,
  `revisao_publicacao` enum('nao_solicitada','pendente','aprovada','recusada') NOT NULL DEFAULT 'nao_solicitada',
  `justificativa_revisao` text DEFAULT NULL,
  `data_revisao_publicacao` datetime DEFAULT NULL,
  `id_moderador_revisao` int(10) unsigned DEFAULT NULL,
  `motivo_bloqueio_autor` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_autor_dos_livros`),
  KEY `idx_adl_historias` (`id_historias`),
  KEY `idx_adl_autor` (`id_autor`),
  KEY `idx_adl_status` (`status_autor_dos_livros`),
  KEY `idx_adl_revisao` (`revisao_publicacao`),
  CONSTRAINT `fk_adl_autor` FOREIGN KEY (`id_autor`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `fk_adl_historias` FOREIGN KEY (`id_historias`) REFERENCES `historias` (`id_historias`) ON UPDATE CASCADE,
  CONSTRAINT `ck_adl_preco` CHECK (`preco_dos_livros_autor` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `autor_dos_livros`
--

LOCK TABLES `autor_dos_livros` WRITE;
/*!40000 ALTER TABLE `autor_dos_livros` DISABLE KEYS */;
INSERT INTO `autor_dos_livros` VALUES (17,17,44,'Crônicas da Biblioteca de Papel',0.00,1,'2026-11-04 16:27:18',NULL,'a_venda','2026-11-04 16:27:18','2026-11-22 14:36:57','gratuita',1,'2026-11-04 16:27:18','aprovada',NULL,'2026-11-04 16:27:18',1,NULL),(18,18,44,'Sombras da Meia-Noite',19.90,1,'2026-11-04 16:27:18',NULL,'a_venda','2026-11-04 16:27:18','2026-11-22 14:36:57','venda_simulada',1,'2026-11-04 16:27:18','aprovada',NULL,'2026-11-04 16:27:18',1,NULL),(24,24,44,'Cartas do Observatório Sul',0.00,1,'2026-11-22 13:11:45',NULL,'a_venda','2026-11-22 13:11:45',NULL,'gratuita',1,'2026-11-22 13:11:45','aprovada',NULL,'2026-11-22 13:11:45',44,NULL),(25,25,44,'Crônicas da Torre Cinzenta',19.90,1,'2026-11-22 13:11:45',NULL,'a_venda','2026-11-22 13:11:45',NULL,'venda_simulada',1,'2026-11-22 13:11:45','aprovada',NULL,'2026-11-22 13:11:45',44,NULL),(26,26,44,'O Arquipélago de Vidro',24.90,1,'2026-11-22 13:11:45',NULL,'a_venda','2026-11-22 13:11:45',NULL,'venda_simulada',1,'2026-11-22 13:11:45','aprovada',NULL,'2026-11-22 13:11:45',44,NULL),(27,27,44,'Algoritmo da Neblina',34.90,1,'2026-11-22 13:11:45',NULL,'a_venda','2026-11-22 13:11:45',NULL,'venda_simulada',1,'2026-11-22 13:11:45','aprovada',NULL,'2026-11-22 13:11:45',44,NULL);
/*!40000 ALTER TABLE `autor_dos_livros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bloqueios_usuario`
--

DROP TABLE IF EXISTS `bloqueios_usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bloqueios_usuario` (
  `id_bloqueio` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_bloqueador` int(10) unsigned NOT NULL,
  `id_bloqueado` int(10) unsigned NOT NULL,
  `motivo_bloqueio` varchar(150) DEFAULT NULL,
  `data_bloqueio` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_bloqueio`),
  UNIQUE KEY `uq_bloqueio_par` (`id_bloqueador`,`id_bloqueado`),
  KEY `idx_bloqueado` (`id_bloqueado`),
  CONSTRAINT `fk_bloqueio_bloqueado` FOREIGN KEY (`id_bloqueado`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bloqueio_bloqueador` FOREIGN KEY (`id_bloqueador`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_bloqueio_distintos` CHECK (`id_bloqueador` <> `id_bloqueado`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bloqueios_usuario`
--

LOCK TABLES `bloqueios_usuario` WRITE;
/*!40000 ALTER TABLE `bloqueios_usuario` DISABLE KEYS */;
/*!40000 ALTER TABLE `bloqueios_usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `capitulos`
--

DROP TABLE IF EXISTS `capitulos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `capitulos` (
  `id_capitulos` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_historias` int(10) unsigned NOT NULL,
  `titulo_capitulos` varchar(200) NOT NULL,
  `conteudo_capitulos` mediumtext NOT NULL,
  `hash_conteudo_capitulos` char(64) DEFAULT NULL,
  `ordem_capitulos` int(10) unsigned NOT NULL,
  `status_capitulos` enum('rascunho','publicado') NOT NULL DEFAULT 'rascunho',
  `modo_publicacao_capitulos` enum('revisao_humana','automatica') NOT NULL DEFAULT 'revisao_humana',
  `revisao_capitulos` enum('nao_solicitada','pendente','aprovada','publicado_automatico','recusada','oculto_moderacao') NOT NULL DEFAULT 'pendente',
  `justificativa_revisao_capitulos` text DEFAULT NULL,
  `id_moderador_capitulos` int(10) unsigned DEFAULT NULL,
  `data_revisao_capitulos` datetime DEFAULT NULL,
  `data_criacao_capitulos` datetime NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao_capitulos` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_capitulos`),
  UNIQUE KEY `uq_capitulos_historia_ordem` (`id_historias`,`ordem_capitulos`),
  KEY `idx_capitulos_hash` (`hash_conteudo_capitulos`),
  CONSTRAINT `fk_capitulos_historias` FOREIGN KEY (`id_historias`) REFERENCES `historias` (`id_historias`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `capitulos`
--

LOCK TABLES `capitulos` WRITE;
/*!40000 ALTER TABLE `capitulos` DISABLE KEYS */;
INSERT INTO `capitulos` VALUES (37,24,'A Cúpula sob a Geada','<p>Naquela noite de inverno, o telescópio apontava para além das nuvens baixas da serra, onde cada estrela parecia guardar uma carta não enviada. O vento cortante da Mantiqueira assobiava pelas frestas de cobre da velha cúpula do Observatório Sul, fazendo vibrar as lâminas do piso de madeira como se o edifício inteiro respirasse no compasso da madrugada.</p>\n<p>Henrique ajustou o foco da ocular com os dedos enluvados e anotou a declinação no caderno de capa dura. Há doze anos subia aquela montanha todas as semanas, carregando uma garrafa térmica de café amargo, mapas celestes desenhados à mão e a silenciosa esperança de registrar a passagem de um cometa de período longo que seu avô descrevera em 1954.</p>\n<p>Sobre a escrivaninha de mogno, ao lado do cronômetro marítimo, repousava um envelope selado com cera azul. Não trazia selo postal nem carimbo de agência; fora deixado entre as páginas do atlas estelar na véspera, com uma única linha caligrafada em tinta ferrogálica: <em>“Olhe para o setor de Carina antes que a aurora apague o rastro.”</em></p>\n<p>Ele releu o bilhete três vezes antes de girar a manivela da fenda zenital. Quando as placas metálicas se abriram para o céu austral, a faixa leitosa da Via Láctea inundou o salão escuro com um brilho pálido e ancestral.</p>','f0083314581898a879495231b0e7aa5da6700923c591304a9fd500061ac0e98b',1,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(38,24,'O Atlas das Constelações Esquecidas','<p>Ao puxar o terceiro volume do arquivo do observatório, Henrique encontrou anotações que não constavam em nenhum catálogo oficial da União Astronômica. Eram cartas traçadas por antigos navegadores e astrônomos amadores que cruzaram o Atlântico Sul no final do século XIX, registrando estrelas variáveis que pareciam pulsar em intervalos quase musicais.</p>\n<p>Nas margens amareladas do papel de linho, uma caligrafia feminina — firme, inclinada e precisa — havia acrescentado comentários datados de três invernos atrás. Chamava-se Helena, ex-cartógrafa celeste que trabalhara na restauração das lentes refratoras antes da reforma do prédio principal.</p>\n<p>“Quem observa o céu do hemisfério sul aprende cedo que a luz viaja como uma memória”, dizia uma das notas. “Quando vemos o brilho de Eta Carinae esta noite, estamos lendo uma carta escrita há sete mil e quinhentos anos. Nada do que brilha lá em cima pertence ao presente; e, ainda assim, é a única bússola que temos para o amanhã.”</p>\n<p>Henrique sentiu um arrepio percorrer-lhe a nuca. As coordenadas anotadas por Helena coincidiam, segundo por segundo, com o ponto indicado no envelope azul deixado sobre sua mesa.</p>','310def4021d1c0950794cda6dcb4bba61ae4c687cd695f67f3b5e4c5e6fd72db',2,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(39,24,'A Segunda Carta no Astrolábio','<p>Na noite seguinte, a neblina subiu do vale mais cedo, envolvendo os pinheiros em um véu prateado que refletia a luz da lua minguante. Henrique chegou ao observatório duas horas antes da meia-noite e encontrou a porta lateral destrancada, exatamente como costumava ficar quando a equipe de manutenção subia a serra.</p>\n<p>Mas não havia ninguém nos corredores. Apenas o tique-taque compassado do pêndulo de quartzo e, preso à base do antigo astrolábio de latão em exposição na antessala, um segundo envelope azul.</p>\n<p>Desta vez, a carta era mais longa. Relatava a história de dois observadores separados por latitudes opostas que, durante décadas, trocavam mensagens usando as efemérides de ocultação lunar como código. Cada data correspondia a uma página dos cadernos guardados no sótão da cúpula menor.</p>\n<p>Com uma lanterna de luz vermelha — para não ofuscar a visão adaptada ao escuro —, Henrique subiu a escada helicoidal até o arquivo superior. Entre caixas de chapas fotográficas de vidro, encontrou o diário de observação número sete, intacto sob uma fina camada de poeira.</p>','c37b8306aeac59e9cde7a4b903223c97e32e7308aaf1fc5f07882647456f9279',3,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(40,24,'As Chapas de Vidro do Cruzeiro','<p>As chapas fotográficas revelavam o céu da serra tal como fora registrado em junho de 1987. Contra o visor iluminado da mesa de inspeção, os pontos negros sobre o negativo de vidro formavam o desenho inconfundível do Cruzeiro do Sul e do Saco de Carvão.</p>\n<p>No verso da última lâmina, presa com fita de papel-arroz, havia uma fotografia em preto e branco: dois jovens astrônomos sorriam diante do grande refrator de vinte polegadas. Um deles era o próprio avô de Henrique; ao seu lado, segurando uma régua de cálculo astronômico, estava a mãe de Helena.</p>\n<p>Tudo se encaixou com a clareza silenciosa de uma equação resolvida. As cartas não eram um enigma lançado ao acaso, mas a continuação de uma promessa feita entre duas famílias que haviam dedicado a vida a manter acesa aquela vigília no topo da montanha, mesmo quando o financiamento minguara e a cidade lá embaixo esquecera o caminho da serra.</p>\n<p>Do lado de fora, o vento cessou de repente, limpando o horizonte sul como um pano puxado sobre um espelho escuro.</p>','614baa780fc2c1d0b6ec529e26434d266185f74956f550eb8219dad50f2aa60c',4,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(41,24,'Antes que a Aurora Apague o Rastro','<p>Eram quatro e vinte da madrugada quando passos leves soaram nos degraus de ferro da plataforma de observação. Henrique não precisou se virar para saber quem subia. Helena trazia nos ombros um casaco de lã escuro e, nas mãos, o caderno original de 1954 que faltava na estante do avô.</p>\n<p>— Achei que você só abriria a terceira carta no solstício — disse ela, com um sorriso sereno, aproximando-se do parapeito da cúpula.</p>\n<p>— Você subestimou a curiosidade de quem passa o inverno inteiro olhando para o escuro — respondeu Henrique, cedendo espaço junto à ocular do telescópio. — Ele voltou, não foi?</p>\n<p>Helena inclinou-se sobre a lente. Lá, exatamente na borda austral de Carina, uma tênue cabeleira esverdeada riscava a escuridão entre duas estrelas de quinta magnitude: o cometa de longo período retornava ao periélio após setenta e dois anos de viagem pelo frio interestelar.</p>\n<p>Permaneceram em silêncio enquanto o primeiro fio dourado da aurora tingia as cristas da serra. Algumas cartas levam uma vida inteira para atravessar a noite, mas chegam sempre a tempo para quem não deixa de olhar para o céu.</p>','adadca8620cab68dd66df8ca9fa5f3e18054dd2b3d39aa44f5b2d4101f98d912',5,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(42,17,'O Segredo das Estantes','<p>Nas galerias silenciosas da antiga biblioteca, cada volume guardava memórias de leitores de diferentes gerações. Fundada em 1928 num casarão de esquina com vitrais cor de âmbar, a Biblioteca Comunitária de Santa Teresa resistira ao tempo graças ao hábito peculiar de seus frequentadores: ninguém devolvia um livro sem deixar, entre suas folhas, um pequeno vestígio de quem o lera.</p>\n<p>Clara, a jovem bibliotecária responsável pelo acervo de obras raras, conhecia cada estante pelo perfume da madeira de cedro e pelo estalido dos degraus da escada corredora. Todas as manhãs, antes de abrir os portões de ferro batido, ela percorria os corredores do segundo pavimento para recolher marcadores esquecidos, bilhetes dobrados e flores secas prensadas entre capítulos.</p>\n<p>Foi numa terça-feira chuvosa de abril que ela encontrou, dentro de uma edição desgastada de contos brasileiros, um mapa desenhado no verso de uma ficha de empréstimo datada de 1974. O traçado indicava uma estante que não aparecia na planta baixa oficial do edifício.</p>\n<p>Intrigada, Clara caminhou até a ala norte, onde os dicionários enciclopédicos repousavam lado a lado como sentinelas imóveis.</p>','c9891fa4a4b24dec2aba8bf78fd86b731486859b68bb0782a5a3637268691e54',1,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(43,17,'A Ficha de Empréstimo nº 408','<p>Atrás da fileira de enciclopédias do século passado, um painel de lambril cedia levemente à pressão dos dedos. Ao deslizar a trava de latão oxidado, Clara revelou um pequeno gabinete de leitura iluminado por uma claraboia circular que dava para o jardim interno do casarão.</p>\n<p>No centro da saleta, sobre uma mesa de carvalho, havia um fichário de madeira com centenas de cartões catalográficos preenchidos à mão. Não eram registros comuns de catálogo: cada ficha trazia o número de um leitor e a história de como determinado livro havia mudado o rumo de sua vida.</p>\n<p>A ficha número 408 pertencia a Seu Vicente, o relojoeiro da rua de baixo, que em 1974 conhecera sua esposa ao disputar o último exemplar de um romance de Machado de Assis numa tarde de tempestade. Ambos haviam assinado o cartão e prometido manter aquele gabinete secreto como um arquivo vivo da memória afetiva do bairro.</p>\n<p>Clara passou as pontas dos dedos pelas dezenas de cartões seguintes. Ali estavam professores, estudantes, tipógrafos e poetas anônimos que, ao longo de cinquenta anos, haviam transformado aquela biblioteca num refúgio contra o esquecimento.</p>','85563d51cd331294ff7bd24471343285d761adf513422ba47d72e77469eee932',2,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(44,17,'O Clube das Margens Anotadas','<p>À medida que mergulhava nos registros do gabinete da claraboia, Clara descobriu que um grupo de leitores se reunia ali todas as quintas-feiras até o início dos anos noventa. Chamavam a si mesmos de <em>O Clube das Margens Anotadas</em>.</p>\n<p>A regra do grupo era simples e encantadora: um mesmo livro circulava entre sete moradores do bairro durante sete semanas. Cada leitor lia a obra usando um lápis de cor diferente para sublinhar trechos e responder, no rodapé das páginas, às reflexões deixadas pelo leitor anterior.</p>\n<p>Ao abrir um dos volumes guardados na estante do gabinete, Clara viu páginas inteiras transformadas num diálogo vivo através das décadas. Onde uma mão jovem escrevera em 1982 uma dúvida sobre a coragem de recomeçar, outra caligrafia mais madura respondera, cinco anos depois, com um relato sincero de superação.</p>\n<p>Aquele livro não era apenas papel e tinta impressa; era uma conversa que vencera o próprio tempo.</p>','421ffb4a026d7859d8bcee77c4c96887a771bf6757801727d036f446a8d772db',3,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(45,17,'A Noite da Chuva de Outono','<p>Na semana seguinte, um forte temporal de outono ameaçou o telhado antigo da ala oeste do casarão. Quando a energia elétrica do quarteirão caiu por volta das sete da noite, Clara pensou que estaria sozinha para proteger as caixas de periódicos históricos das goteiras que começavam a surgir perto das janelas.</p>\n<p>Para sua surpresa, em menos de vinte minutos, lanternas começaram a brilhar na calçada de pedra. Seu Vicente, agora com cabelos brancos e passos calmos, foi o primeiro a entrar, trazendo lonas impermeáveis e velas dentro de lampiões de vidro. Atrás dele vieram ex-alunos da escola vizinha, uma professora aposentada e dois jovens escritores que passavam as tardes estudando no salão principal.</p>\n<p>Durante três horas, trabalharam ombro a ombro à luz dos lampiões, transferindo coleções inteiras para as galerias superiores. Quando a chuva finalmente amainou, sentaram-se em círculo no tapete do salão central, compartilhando chá quente e lendo em voz alta trechos escolhidos ao acaso nas estantes.</p>\n<p>Clara compreendeu, naquela noite, que uma biblioteca nunca é feita apenas de paredes: ela existe enquanto houver pessoas dispostas a proteger as histórias umas das outras.</p>','3b4c59ebef458dacb604e0b9757e75879f1e53102f65b0860ca7ce4643d1fb55',4,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(46,17,'Novas Páginas em Branco','<p>Com a chegada da primavera, o gabinete da claraboia foi oficialmente integrado ao roteiro aberto da Biblioteca de Papel. A mesa de carvalho ganhou um novo fichário, com centenas de cartões em branco esperando pelas próximas gerações de leitores.</p>\n<p>Clara retomou também a tradição do Clube das Margens Anotadas, destinando uma estante inteira a obras independentes escritas pelos próprios moradores e jovens autores da comunidade. Ao lado de cada exemplar, uma caixa de lápis de cor convidava o visitante a deixar sua impressão para o próximo leitor.</p>\n<p>No primeiro cartão do novo fichário — o de número 1.001 —, Clara escreveu com letra firme, sob a luz dourada que descia pelos vitrais cor de âmbar:</p>\n<p><em>“Todo livro começa quando o autor escreve a última linha, mas só se completa quando encontra alguém disposto a habitar suas páginas.”</em></p>','cdc455683d4512a9e1560602b3b4bd892eb9976b5a0fec2484b308a1e6a6eccb',5,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(47,26,'A Bússola de Quartzo','<p>Lira ajustou o sextante enquanto a costa de vidro refletia o amanhecer em sete tons de âmbar. As ilhas translúcidas do Mar de Sândalo mudavam de posição a cada maré equinocial, exigindo que os cartógrafos da Guilda recalculassem as rotas antes que as correntes submersas fechassem o estreito.</p><p>No convés da caravela <em>Alvorada</em>, a agulha de quartzo girou três vezes para o norte magnético e estacou apontando para uma formação cristalina que não constava em nenhum pergaminho real.</p>','95cfb9e25d093f75253a2f008e5a71e6f09512f07cfa7fd6f192fa870d286826',1,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(48,26,'As Marés de Âmbar','<p>À medida que a embarcação adentrava o canal entre os recifes transparentes, era possível enxergar, a vinte metros de profundidade, as torres submersas da antiga capital dos vidreiros. Cardumes prateados atravessavam os arcos de cristal como flechas de luz líquida.</p><p>Lira desceu à cabine de navegação e abriu o astrolábio herdado de seu mestre. As inscrições gravadas no anel interno indicavam que a ilha central só emergia por completo uma vez a cada dezenove anos.</p>','e9dae15d6d9c6ba02567f3057e190fd3c0fc4593da10a62a48ddc8a23eabcad2',2,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(49,26,'O Mapa do Horizonte Móvel','<p>Ao pisar no cais de sílica polida, a tripulação descobriu o farol prismático ainda intacto. Quando o sol atingiu o zênite, o feixe refratado projetou sobre as águas do arquipélago o mapa completo das rotas perdidas do sul.</p><p>Lira registrou cada ângulo em seu diário de bordo, sabendo que a verdadeira arte da cartografia não era prender o mundo no papel, mas aprender a navegar com a sua constante transformação.</p>','6621623b5e1e9d390be18771c65702c76ab714fd4b657f27f3d63b13c35d0bc1',3,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(50,27,'Terminal Submerso','<p>Os servidores da zona leste ainda zumbiam sob a garoa fina quando o último arquivo criptografado chegou ao terminal. Na São Paulo de 2084, a neblina sintética condensava os sinais ópticos das torres de transmissão, criando fantasmas luminosos sobre os viadutos.</p><p>Davi decodificou o cabeçalho do pacote e reconheceu a assinatura digital da antiga rede meteorológica municipal, desativada havia mais de duas décadas.</p>','9e5cb14480c4b35aa42eaf7e2fe02fe5bbebb4b0a8703378eb7f6b3a1266ca23',1,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(51,27,'Frequência Fantasma','<p>Seguindo o rastreamento de pacotes pelos túneis da antiga linha ferroviária, Davi encontrou uma estação repetidora operando com baterias térmicas subterrâneas. Nos monitores de fósforo verde, linhas de código reconstruíam a memória urbana de bairros inteiros antes da grande reforma climática.</p><p>Cada arquivo preservava relatos, fotografias e vozes de moradores que haviam construído uma biblioteca descentralizada impossível de ser apagada.</p>','1ee4c519ffb3135123cd01c804eb97c6c40cb6e7a9391af1b0908c3da988e080',2,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(52,27,'Céu Limpo sobre a Metrópole','<p>Ao sincronizar o nó central com a antena da Serra do Mar, o protocolo público foi finalmente liberado para todos os terminais da cidade. Pela primeira vez em anos, os painéis comunitários exibiram o arquivo histórico completo da metrópole, enquanto a brisa do Atlântico abria uma clareira real entre as nuvens.</p>','3c7816879d14daad8d26b0bc5b5d130c6e6233c523a51c3b8ba4e0d48875b942',3,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(53,25,'O Códice de 1740','<p>Havia tinta fresca nas margens do códice de 1740, como se alguém tivesse continuado o relato minutos antes da minha chegada. Como restaurador de manuscritos antigos, eu já havia lidado com pergaminhos frágeis e encadernações de couro gravado, mas nunca com um livro cujo texto parecia avançar durante a noite.</p><p>A Torre Cinzenta do antigo mosteiro da serra guardava mais de quatro mil volumes, e o silêncio de seus corredores de pedra amplificava cada virar de página.</p>','6096a2af7498ab5db85551073744bb55f0bf97da3754b7ed64ad96c6903eb0c6',1,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(54,25,'A Escadaria do Scriptorium','<p>Na segunda noite de trabalho, segui o leve aroma de cera de abelha e óleo de linhaça que vinha do andar superior da torre. No antigo scriptorium, encontrei uma escrivaninha inclinada onde penas de metal e tinteiros de vidro permaneciam organizados segundo a tradição dos copistas do século XVIII.</p><p>Ao lado do tinteiro, uma carta explicava que o guardião anterior do acervo mantivera viva a crônica anual da vila por quarenta invernos consecutivos.</p>','ab0ab4894b62ae17a0c45b667094d043698a3aaaea69921868b8e6efc2a5ee03',2,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(55,25,'O Novo Guardião','<p>Ao concluir o restauro da encadernação do códice, compreendi o convite deixado nas entrelinhas do manuscrito. Mergulhei a pena na tinta ferrogálica e registrei, na folha de guarda seguinte, a data daquela manhã luminosa na serra, assumindo meu lugar na longa corrente de cronistas da Torre Cinzenta.</p>','46f5604010a795eeea0791cccffcd0dfdf3dbe08b1a77e2e49b88830c973ab40',3,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(56,18,'Noite sem Retorno','<p>A chuva cortava os becos escuros da metrópole enquanto o detetive encarava os segredos mais densos e sombrios da noite. O relógio da estação central acabara de bater doze badaladas quando o telefone de disco do escritório tocou pela terceira vez.</p><p>Do outro lado da linha, uma testemunha chave do caso Valadares pedia um encontro urgente no cais antigo antes que o último navio cargueiro deixasse a baía.</p>','2eab8c44a220409e035e08e0727d75eefe6571ec3cfaefa214300ce5a8b5a2b1',1,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(57,18,'O Dossiê do Cais','<p>Sob a luz amarelada dos postes portuários, o envelope entregue continha negativos fotográficos e registros contábeis que ligavam o desaparecimento das obras de arte da galeria central a um leilão clandestino marcado para aquela mesma madrugada.</p><p>Cada pista exigia frieza e precisão: qualquer passo em falso nos armazéns abandonados colocaria toda a investigação a perder.</p>','04240f7e6557d1225abf837fd23ad6d3d0b04952439328a0ac31a36570d2a9df',2,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL),(58,18,'Antes do Amanhecer','<p>Com o apoio da inspetoria convocada às pressas, o cerco ao armazém sete foi fechado pouco antes das cinco da manhã. As telas roubadas foram recuperadas intactas e os responsáveis conduzidos sob custódia enquanto o sol começava a dissipar as sombras sobre o porto.</p>','f4be670cab366a7874e517a2daf4cfb52c4ec5f5496bea6a63c31d6ed0253cbe',3,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-22 14:36:57','2026-11-22 14:36:57',NULL);
/*!40000 ALTER TABLE `capitulos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_conversas`
--

DROP TABLE IF EXISTS `chat_conversas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_conversas` (
  `id_conversa` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario_menor` int(10) unsigned NOT NULL,
  `id_usuario_maior` int(10) unsigned NOT NULL,
  `status_conversa` enum('pendente','aceita','recusada') NOT NULL DEFAULT 'aceita',
  `id_solicitante` int(10) unsigned DEFAULT NULL,
  `data_criacao_conversa` datetime NOT NULL DEFAULT current_timestamp(),
  `data_ultima_mensagem` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_conversa`),
  UNIQUE KEY `uq_chat_par` (`id_usuario_menor`,`id_usuario_maior`),
  KEY `idx_chat_u_menor` (`id_usuario_menor`),
  KEY `idx_chat_u_maior` (`id_usuario_maior`),
  CONSTRAINT `fk_chat_u_maior` FOREIGN KEY (`id_usuario_maior`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_chat_u_menor` FOREIGN KEY (`id_usuario_menor`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_chat_usuarios_distintos` CHECK (`id_usuario_menor` < `id_usuario_maior`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_conversas`
--

LOCK TABLES `chat_conversas` WRITE;
/*!40000 ALTER TABLE `chat_conversas` DISABLE KEYS */;
INSERT INTO `chat_conversas` VALUES (2,1,40,'aceita',NULL,'2026-10-26 00:13:58','2026-10-26 00:13:58'),(3,1,41,'aceita',NULL,'2026-11-04 16:27:57','2026-11-22 13:10:22'),(4,41,51,'pendente',51,'2026-11-22 13:26:46','2026-11-22 13:26:46');
/*!40000 ALTER TABLE `chat_conversas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_mensagens`
--

DROP TABLE IF EXISTS `chat_mensagens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_mensagens` (
  `id_mensagem` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_conversa` int(10) unsigned NOT NULL,
  `id_remetente` int(10) unsigned NOT NULL,
  `conteudo_mensagem` text NOT NULL,
  `hash_conteudo_mensagem` char(64) NOT NULL,
  `status_mensagem` enum('ativa','apagada_pelo_autor','oculta_preventivamente','removida_moderacao') NOT NULL DEFAULT 'ativa',
  `motivo_ocultacao` varchar(255) DEFAULT NULL,
  `data_envio_mensagem` datetime NOT NULL DEFAULT current_timestamp(),
  `lida_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id_mensagem`),
  KEY `idx_chat_msg_conversa` (`id_conversa`,`data_envio_mensagem`),
  KEY `idx_chat_msg_remetente_data` (`id_remetente`,`data_envio_mensagem`),
  KEY `idx_chat_msg_hash` (`hash_conteudo_mensagem`),
  CONSTRAINT `fk_chat_msg_conversa` FOREIGN KEY (`id_conversa`) REFERENCES `chat_conversas` (`id_conversa`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_chat_msg_remetente` FOREIGN KEY (`id_remetente`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_mensagens`
--

LOCK TABLES `chat_mensagens` WRITE;
/*!40000 ALTER TABLE `chat_mensagens` DISABLE KEYS */;
INSERT INTO `chat_mensagens` VALUES (3,3,1,'boa obra','ab02ec5939b6ebc88f4d1d4117fa57b8f4203a64852b01afe7e58fd71af5c98c','ativa',NULL,'2026-11-04 16:28:04','2026-11-04 16:28:04'),(4,3,1,'opa','1fbe8e4f4059ee0e7e8ac840aefd2ac3224c51bb038c09f80ebb767600b9378a','ativa',NULL,'2026-11-22 13:10:22',NULL);
/*!40000 ALTER TABLE `chat_mensagens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comentarios`
--

DROP TABLE IF EXISTS `comentarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comentarios` (
  `id_comentarios` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_historias` int(10) unsigned NOT NULL,
  `comentarios_comentario` text NOT NULL,
  `data_comentario` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_comentarios`),
  KEY `idx_comentarios_usuario` (`id_usuario`),
  KEY `idx_comentarios_historia` (`id_historias`),
  CONSTRAINT `fk_comentarios_historias` FOREIGN KEY (`id_historias`) REFERENCES `historias` (`id_historias`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comentarios_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comentarios`
--

LOCK TABLES `comentarios` WRITE;
/*!40000 ALTER TABLE `comentarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `comentarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compra`
--

DROP TABLE IF EXISTS `compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compra` (
  `id_compra` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_autor_dos_livros` int(10) unsigned NOT NULL,
  `valor_pago_compra` decimal(10,2) NOT NULL,
  `percentual_autor_compra` decimal(5,2) NOT NULL DEFAULT 80.00,
  `ingresso_autor_compra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `percentual_plataforma_compra` decimal(5,2) NOT NULL DEFAULT 20.00,
  `status_compra` enum('pendente','concluida','cancelada','reembolsada') NOT NULL DEFAULT 'pendente',
  `data_compra` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_compra`),
  KEY `idx_compra_usuario` (`id_usuario`),
  KEY `idx_compra_obra` (`id_autor_dos_livros`),
  KEY `idx_compra_status` (`status_compra`),
  CONSTRAINT `fk_compra_obra` FOREIGN KEY (`id_autor_dos_livros`) REFERENCES `autor_dos_livros` (`id_autor_dos_livros`) ON UPDATE CASCADE,
  CONSTRAINT `fk_compra_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `ck_compra_valor` CHECK (`valor_pago_compra` >= 0),
  CONSTRAINT `ck_compra_percentuais` CHECK (`percentual_autor_compra` + `percentual_plataforma_compra` = 100)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compra`
--

LOCK TABLES `compra` WRITE;
/*!40000 ALTER TABLE `compra` DISABLE KEYS */;
INSERT INTO `compra` VALUES (15,1,24,0.00,90.00,0.00,10.00,'concluida','2026-11-22 13:11:45'),(16,40,26,24.90,90.00,22.41,10.00,'concluida','2026-11-22 13:11:45'),(17,51,17,0.00,90.00,0.00,10.00,'concluida','2026-11-22 13:24:37'),(18,51,24,0.00,90.00,0.00,10.00,'concluida','2026-11-22 13:54:18'),(19,1,17,0.00,90.00,0.00,10.00,'concluida','2026-11-23 15:31:48');
/*!40000 ALTER TABLE `compra` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contador_pontos`
--

DROP TABLE IF EXISTS `contador_pontos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contador_pontos` (
  `id_contador_de_pontos` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `pontos_contador_de_pontos` int(10) unsigned NOT NULL DEFAULT 0,
  `data_atualizacao_contador` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_contador_de_pontos`),
  UNIQUE KEY `uq_contador_usuario` (`id_usuario`),
  CONSTRAINT `fk_contador_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contador_pontos`
--

LOCK TABLES `contador_pontos` WRITE;
/*!40000 ALTER TABLE `contador_pontos` DISABLE KEYS */;
INSERT INTO `contador_pontos` VALUES (12,1,83,'2026-10-22 21:47:30'),(14,40,25,'2026-10-26 00:11:14');
/*!40000 ALTER TABLE `contador_pontos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curtidas`
--

DROP TABLE IF EXISTS `curtidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `curtidas` (
  `id_curtidas` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_historias` int(10) unsigned NOT NULL,
  `data_criacao_curtidas` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_curtidas`),
  UNIQUE KEY `uq_curtidas_usuario_historia` (`id_usuario`,`id_historias`),
  KEY `idx_curtidas_historia` (`id_historias`),
  CONSTRAINT `fk_curtidas_historias` FOREIGN KEY (`id_historias`) REFERENCES `historias` (`id_historias`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_curtidas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curtidas`
--

LOCK TABLES `curtidas` WRITE;
/*!40000 ALTER TABLE `curtidas` DISABLE KEYS */;
/*!40000 ALTER TABLE `curtidas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `denuncias`
--

DROP TABLE IF EXISTS `denuncias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `denuncias` (
  `id_denuncias` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `tipo_denuncias` enum('historia','comentario','usuario','obra_autoral','mensagem_chat','conversa_chat') NOT NULL,
  `item_denuncia` int(10) unsigned NOT NULL,
  `motivo_denuncia` varchar(150) NOT NULL,
  `descricao_denuncia` text DEFAULT NULL,
  `status_denuncias` enum('aberta','em_analise','procedente','improcedente') NOT NULL DEFAULT 'aberta',
  `data_denuncia` datetime NOT NULL DEFAULT current_timestamp(),
  `data_resposta_denuncia` datetime DEFAULT NULL,
  `obra_referencia_denuncia` varchar(255) DEFAULT NULL,
  `prioridade_denuncia` enum('baixa','media','alta') NOT NULL DEFAULT 'media',
  `suspensao_preventiva` tinyint(1) NOT NULL DEFAULT 0,
  `justificativa_moderacao` text DEFAULT NULL,
  `contestacao_autor` text DEFAULT NULL,
  `data_contestacao` datetime DEFAULT NULL,
  `acao_moderacao_aplicada` varchar(60) DEFAULT NULL,
  `retencao_evidencia_ate` datetime DEFAULT NULL,
  PRIMARY KEY (`id_denuncias`),
  KEY `idx_denuncias_usuario` (`id_usuario`),
  KEY `idx_denuncias_tipo_item` (`tipo_denuncias`,`item_denuncia`),
  KEY `idx_denuncias_status` (`status_denuncias`),
  CONSTRAINT `fk_denuncias_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `denuncias`
--

LOCK TABLES `denuncias` WRITE;
/*!40000 ALTER TABLE `denuncias` DISABLE KEYS */;
INSERT INTO `denuncias` VALUES (14,1,'obra_autoral',15,'Violação de direitos autorais (plágio)','SINIXTRO O BAGUIO ALI','procedente','2026-10-26 00:18:19','2026-10-26 00:18:55','ta feio','alta',0,'loucura',NULL,NULL,'aviso_e_remover_mensagem','2027-04-24 00:18:55');
/*!40000 ALTER TABLE `denuncias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historias`
--

DROP TABLE IF EXISTS `historias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historias` (
  `id_historias` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `titulo_historias` varchar(200) NOT NULL,
  `sinopse_historias` text DEFAULT NULL,
  `capa_historias` varchar(255) DEFAULT NULL,
  `generos_historias` varchar(255) DEFAULT NULL,
  `status_historias` enum('rascunho','publicada','despublicada') NOT NULL DEFAULT 'rascunho',
  `visualizacoes_historias` int(10) unsigned NOT NULL DEFAULT 0,
  `data_criacao_historias` datetime NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao_historias` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `licenca_historias` varchar(60) NOT NULL DEFAULT 'direitos_reservados',
  `aviso_conteudo_historias` varchar(80) NOT NULL DEFAULT 'livre',
  `origem_obra_historias` varchar(60) NOT NULL DEFAULT 'autoral_original',
  `hash_conteudo_historias` char(64) DEFAULT NULL,
  PRIMARY KEY (`id_historias`),
  KEY `idx_historias_usuario` (`id_usuario`),
  KEY `idx_historias_status` (`status_historias`),
  KEY `idx_historias_titulo` (`titulo_historias`),
  KEY `idx_historias_hash` (`hash_conteudo_historias`),
  CONSTRAINT `fk_historias_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historias`
--

LOCK TABLES `historias` WRITE;
/*!40000 ALTER TABLE `historias` DISABLE KEYS */;
INSERT INTO `historias` VALUES (17,44,'Crônicas da Biblioteca de Papel','Uma jornada literária acolhedora sobre o poder da leitura e a preservação de memórias em uma biblioteca comunitária.','uploads/capas/loja_biblioteca_papel.svg','Ficção, Drama, Literatura Nacional','publicada',8,'2026-11-04 16:27:18','2026-11-23 15:32:08','direitos_reservados','livre','autoral_original','1b034658198450de456a46052918bed083d4c7fea5e7093ac9e32cf15c5ba674'),(18,44,'Sombras da Meia-Noite','Suspense psicológico intenso e temas adultos voltados exclusivamente ao público maior de 18 anos.','uploads/capas/loja_sombras_meia_noite.svg','Suspense, Mistério, Drama','publicada',0,'2026-11-04 16:27:18','2026-11-22 14:36:57','direitos_reservados','18','autoral_original','6c0cab5eddfe36c0fedd6d944ad85cea39ca7a6d63960bc54003770689897815'),(24,44,'Cartas do Observatório Sul','Coletânea de crônicas astronômicas e reflexões sobre o céu do hemisfério sul. Edição digital gratuita para novos leitores.','uploads/capas/loja_observatorio_sul.svg','Crônica, Literatura Nacional','publicada',8,'2026-11-22 13:11:45','2026-11-23 15:30:35','direitos_reservados','livre','autoral_original','d78d4e9938333935c7a3e32b6da0e214ad0d746122a17d70bb5afd71c921f049'),(25,44,'Crônicas da Torre Cinzenta','Suspense gótico sobre um restaurador de manuscritos que descobre anotações cifradas em um convento isolado.','uploads/capas/loja_torre_cinzenta.svg','Mistério, Terror, Histórico','publicada',0,'2026-11-22 13:11:45','2026-11-22 14:07:59','direitos_reservados','16','autoral_original','33fff4b5fcf3a4c64e6b0d92a81830f72f316cbfcca402c503cd9118eeb03ff7'),(26,44,'O Arquipélago de Vidro','Fantasia épica sobre cartógrafos que navegam entre ilhas translúcidas cujos mapas mudam a cada maré.','uploads/capas/loja_arquipelago_vidro.svg','Fantasia, Aventura','publicada',0,'2026-11-22 13:11:45','2026-11-22 14:07:59','direitos_reservados','12','autoral_original','c2179b4dccfeb52c3c31c2c67a542bf05828d5c5cba1aa1ad0609c46672dc035'),(27,44,'Algoritmo da Neblina','Ficção científica investigativa em uma São Paulo submersa por dados e neblina sintética.','uploads/capas/loja_algoritmo_neblina.svg','Ficção Científica, Suspense, Distopia','publicada',0,'2026-11-22 13:11:45','2026-11-22 14:07:59','direitos_reservados','14','autoral_original','24f619dbc6461187485ac8ada9d80f6c3e432d248f0b7543e971e2b6e4329758');
/*!40000 ALTER TABLE `historias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historico_moderacao_autor`
--

DROP TABLE IF EXISTS `historico_moderacao_autor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historico_moderacao_autor` (
  `id_historico_mod` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_autor` int(10) unsigned NOT NULL,
  `id_moderador` int(10) unsigned DEFAULT NULL,
  `id_autor_dos_livros` int(10) unsigned DEFAULT NULL,
  `id_capitulos` int(10) unsigned DEFAULT NULL,
  `tipo_evento` varchar(60) NOT NULL,
  `modalidade_anterior` varchar(40) DEFAULT NULL,
  `modalidade_nova` varchar(40) DEFAULT NULL,
  `justificativa` text NOT NULL,
  `data_evento` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_historico_mod`),
  KEY `idx_hist_mod_autor` (`id_autor`,`data_evento`),
  KEY `idx_hist_mod_evento` (`tipo_evento`,`data_evento`),
  CONSTRAINT `fk_hist_mod_autor` FOREIGN KEY (`id_autor`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historico_moderacao_autor`
--

LOCK TABLES `historico_moderacao_autor` WRITE;
/*!40000 ALTER TABLE `historico_moderacao_autor` DISABLE KEYS */;
INSERT INTO `historico_moderacao_autor` VALUES (6,41,NULL,NULL,NULL,'qualificacao_automatica','avaliacao','automatica','Autor qualificado para publicação automática após cumprir os critérios objetivos de histórico positivo e ausência de violações.','2026-11-04 17:45:06'),(7,1,NULL,20,26,'publicacao_automatica_capitulo','avaliacao','avaliacao','Obra \'TESTE 002\' e Capítulo 1 (TESTANDO) publicados automaticamente (sujeitos à moderação posterior).','2026-11-17 11:21:14'),(11,44,NULL,NULL,NULL,'qualificacao_automatica','avaliacao','automatica','Autor qualificado para publicação automática após cumprir os critérios objetivos de histórico positivo e ausência de violações.','2026-11-22 13:35:09');
/*!40000 ALTER TABLE `historico_moderacao_autor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `livraria`
--

DROP TABLE IF EXISTS `livraria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `livraria` (
  `id_livraria` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_livro` int(10) unsigned NOT NULL,
  `status_livraria` enum('quero_ler','lendo','lido','abandonado') NOT NULL DEFAULT 'quero_ler',
  `data_solicitacao_livraria` datetime NOT NULL DEFAULT current_timestamp(),
  `data_modificacao_livraria` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_livraria`),
  UNIQUE KEY `uq_livraria_usuario_livro` (`id_usuario`,`id_livro`),
  KEY `idx_livraria_livro` (`id_livro`),
  CONSTRAINT `fk_livraria_livro` FOREIGN KEY (`id_livro`) REFERENCES `livro` (`id_livro`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_livraria_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `livraria`
--

LOCK TABLES `livraria` WRITE;
/*!40000 ALTER TABLE `livraria` DISABLE KEYS */;
INSERT INTO `livraria` VALUES (16,1,17,'abandonado','2026-10-22 01:30:06','2026-10-22 01:48:57'),(17,1,15,'lendo','2026-10-22 02:01:44',NULL),(18,1,18,'lendo','2026-10-22 21:46:14','2026-10-22 21:47:09'),(19,40,20,'quero_ler','2026-11-17 11:00:38',NULL),(20,1,19,'quero_ler','2026-11-17 11:05:36',NULL),(23,40,26,'quero_ler','2026-11-18 11:11:49',NULL),(25,51,24,'quero_ler','2026-11-22 13:24:37',NULL),(29,51,33,'quero_ler','2026-11-22 13:50:15',NULL),(30,51,34,'lendo','2026-11-22 13:53:40',NULL),(31,51,19,'quero_ler','2026-11-22 13:54:18',NULL),(32,51,35,'lendo','2026-11-22 14:15:02',NULL),(33,1,24,'quero_ler','2026-11-23 15:31:48',NULL),(34,1,33,'lendo','2026-11-23 15:45:55',NULL);
/*!40000 ALTER TABLE `livraria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `livraria_digital`
--

DROP TABLE IF EXISTS `livraria_digital`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `livraria_digital` (
  `id_livraria_digital` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_autor_dos_livros` int(10) unsigned NOT NULL,
  `id_compra` int(10) unsigned NOT NULL,
  `data_liberacao_livraria_digital` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_livraria_digital`),
  UNIQUE KEY `uq_ld_usuario_obra` (`id_usuario`,`id_autor_dos_livros`),
  UNIQUE KEY `uq_ld_compra` (`id_compra`),
  KEY `idx_ld_obra` (`id_autor_dos_livros`),
  CONSTRAINT `fk_ld_compra` FOREIGN KEY (`id_compra`) REFERENCES `compra` (`id_compra`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ld_obra` FOREIGN KEY (`id_autor_dos_livros`) REFERENCES `autor_dos_livros` (`id_autor_dos_livros`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ld_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `livraria_digital`
--

LOCK TABLES `livraria_digital` WRITE;
/*!40000 ALTER TABLE `livraria_digital` DISABLE KEYS */;
INSERT INTO `livraria_digital` VALUES (15,1,24,15,'2026-11-22 13:11:45'),(16,40,26,16,'2026-11-22 13:11:45'),(17,51,17,17,'2026-11-22 13:24:37'),(18,51,24,18,'2026-11-22 13:54:18'),(19,1,17,19,'2026-11-23 15:31:48');
/*!40000 ALTER TABLE `livraria_digital` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `livro`
--

DROP TABLE IF EXISTS `livro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `livro` (
  `id_livro` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo_livro` varchar(200) NOT NULL,
  `autor_livro` varchar(150) NOT NULL,
  `isbn_livro` varchar(20) DEFAULT NULL,
  `sinopse_livro` text DEFAULT NULL,
  `capa_livro` varchar(255) DEFAULT NULL,
  `genero_livro` varchar(80) DEFAULT NULL,
  `classificacao_livro` varchar(20) NOT NULL DEFAULT 'livre',
  `paginas_livro` int(10) unsigned DEFAULT NULL,
  `editora_livro` varchar(120) DEFAULT NULL,
  `data_publicacao_livro` date DEFAULT NULL,
  `data_cadastro_livro` datetime NOT NULL DEFAULT current_timestamp(),
  `fonte_livro` enum('catalogo','openlibrary','loja') NOT NULL DEFAULT 'catalogo',
  `id_externo_livro` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_livro`),
  UNIQUE KEY `uq_livro_isbn` (`isbn_livro`),
  UNIQUE KEY `uq_livro_externo` (`id_externo_livro`),
  KEY `idx_livro_titulo` (`titulo_livro`),
  KEY `idx_livro_autor` (`autor_livro`),
  KEY `idx_livro_genero` (`genero_livro`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `livro`
--

LOCK TABLES `livro` WRITE;
/*!40000 ALTER TABLE `livro` DISABLE KEYS */;
INSERT INTO `livro` VALUES (15,'Orgulho e Preconceito','Jane Austen',NULL,'Orgulho e Preconceito é um romance de maneiras de 1813 escrito por Jane Austen. O romance segue o desenvolvimento da personagem de Elizabeth Bennet, a protagonista dinâmica do livro que aprende sobre as repercussões de julgamentos precipitados e passa a apreciar a diferença entre bondade superficial e bondade real.\n\nO Sr. Bennet, proprietário da propriedade Longbourn em Hertfordshire, tem cinco filhas, mas sua propriedade está vinculada e só pode ser passada para um herdeiro do sexo masculino. Sua esposa também não tem uma herança, então sua família enfrenta se tornar muito pobre após sua morte. Assim, é imperativo que pelo menos uma das meninas se case bem para apoiar as outras, o que é uma motivação que impulsiona a trama.','uploads/capas/cache_ol/14348537-M.jpg','Romance, Clássico','12',351,NULL,'1813-01-01','2026-10-22 00:57:11','openlibrary','OL66554W'),(17,'É Assim que Acaba','Colleen Hoover',NULL,'Lily nem sempre teve uma vida fácil, mas isso nunca a impediu de trabalhar duro pela vida que quer. Ela percorreu um longo caminho desde a pequena cidade onde cresceu - ela se formou na faculdade, mudou-se para Boston e começou seu próprio negócio. E quando ela sente uma faísca com um lindo neurocirurgião chamado Ryle Kincaid, tudo na vida de Lily parece bom demais para ser verdade.\n\nRyle é assertivo, teimoso, talvez até um pouco arrogante. Ele também é sensível, brilhante e tem um fraquinho total por Lily. E a maneira como ele fica de uniforme certamente não dói. Lily não consegue tirá-lo da cabeça. Mas a completa aversão de Ryle aos relacionamentos é perturbadora. Mesmo quando Lily se vê se tornando a exceção à sua regra de \"não namorar\", ela não pode deixar de se perguntar o que o fez assim em primeiro lugar.\n\nÀ medida que as perguntas sobre seu novo relacionamento a dominam, o mesmo acontece com os pensamentos de Atlas Corrigan - seu primeiro amor e uma ligação com o passado que ela deixou para trás. Ele era sua alma gêmea, seu protetor. Quando Atlas reaparece de repente, tudo o que Lily construiu com Ryle é ameaçado.\n\nCom este romance ousado e profundamente pessoal, It Ends With Us é uma história de partir o coração e um conto inesquecível de amor que tem o preço final.','uploads/capas/cache_ol/10473609-M.jpg','Romance, Drama','18',384,NULL,'2012-01-01','2026-10-22 01:30:06','openlibrary','OL18020194W'),(18,'Alice no País das Maravilhas','Lewis Carroll',NULL,'Alice no País das Maravilhas (geralmente Alice no País das Maravilhas) é um romance infantil inglês de 1865 de Lewis Carroll. Uma jovem chamada Alice cai em um buraco de coelho em um mundo de fantasia de criaturas antropomórficas. É visto como um exemplo do gênero literário sem sentido.\n\nUma das obras mais conhecidas da literatura vitoriana, sua narrativa, estrutura, personagens e imagens tiveram enorme influência na cultura popular e na literatura, especialmente no gênero fantasia.','uploads/capas/cache_ol/10527843-L.jpg','Fantasia, Aventura','livre',NULL,NULL,'1869-01-01','2026-10-22 21:46:14','openlibrary','OL138052W'),(19,'Cartas do Observatório Sul','GePeTo',NULL,'Coletânea de crônicas astronômicas e reflexões sobre o céu do hemisfério sul. Edição digital gratuita para novos leitores.','uploads/capas/loja_observatorio_sul.svg','Crônica, Literatura Nacional','10',5,NULL,NULL,'2026-11-17 11:00:38','loja','LOJA-1'),(20,'O Arquipélago de Vidro','GePeTo',NULL,'Fantasia épica sobre cartógrafos que navegam entre ilhas translúcidas cujos mapas mudam a cada maré.','uploads/capas/loja_arquipelago_vidro.svg','Fantasia, Aventura','12',3,NULL,NULL,'2026-11-17 11:00:38','loja','LOJA-2'),(21,'Algoritmo da Neblina','GePeTo',NULL,'Ficção científica investigativa em uma São Paulo submersa por dados e neblina sintética.','uploads/capas/loja_algoritmo_neblina.svg','Ficção Científica, Suspense, Distopia','14',3,NULL,NULL,'2026-11-17 11:00:38','loja','LOJA-3'),(22,'Crônicas da Torre Cinzenta','GePeTo',NULL,'Suspense gótico sobre um restaurador de manuscritos que descobre anotações cifradas em um convento isolado.','uploads/capas/loja_torre_cinzenta.svg','Mistério, Terror, Histórico','16',3,NULL,NULL,'2026-11-17 11:00:38','loja','LOJA-4'),(23,'OBSERVÇÕES - FOLIVRO','natan',NULL,'Durante séculos, o mundo de Eldoria viveu sob a proteção de sete torres mágicas, responsáveis por manter as criaturas das sombras afastadas das cidades humanas. Entretanto, quando a torre da capital se apaga misteriosamente, uma noite interminável começa a avançar sobre o reino.\r\n\r\nKael, um jovem bibliotecário de dezessete anos, descobre um livro escondido entre as páginas de um manuscrito proibido. Suas palavras mudam diariamente, revelando acontecimentos que ainda não ocorreram — incluindo a destruição de sua própria cidade.\r\n\r\nSem poderes especiais ou treinamento para lutar, Kael precisará atravessar terras esquecidas, investigar a origem das torres e descobrir por que seu nome aparece repetidamente nas últimas páginas do livro.\r\n\r\nMas existe um detalhe que ninguém parece disposto a explicar: todas as pessoas que tentaram desvendar o mistério desapareceram antes de completar a jornada.','uploads/capas/capa_41_a6c3d9701c3f80bc.png','Fantasia, Mistério, Ficção','16',NULL,NULL,NULL,'2026-11-17 11:26:12','loja','LOJA-5'),(24,'Crônicas da Biblioteca de Papel','GePeTo',NULL,'Uma jornada literária acolhedora sobre o poder da leitura e a preservação de memórias em uma biblioteca comunitária.','uploads/capas/loja_biblioteca_papel.svg','Ficção, Drama, Literatura Nacional','livre',5,NULL,NULL,'2026-11-17 11:26:12','loja','LOJA-6'),(25,'Sombras da Meia-Noite','GePeTo',NULL,'Suspense psicológico intenso e temas adultos voltados exclusivamente ao público maior de 18 anos.','uploads/capas/loja_sombras_meia_noite.svg','Suspense, Mistério, Drama','18',3,NULL,NULL,'2026-11-17 11:26:12','loja','LOJA-7'),(26,'TESTE 002','Administrador de teste',NULL,'TESTE002','api/capa.php?t=TESTE%20002&a=Administrador%20de%20teste','Romance, Suspense, Terror, Ficção, Histórico','18',NULL,NULL,NULL,'2026-11-17 11:26:12','loja','LOJA-8'),(33,'Dom Casmurro','Machado de Assis',NULL,'Clássico absoluto de Machado de Assis (1899), narrado por Bento Santiago (Bentinho), que revisita sua juventude no Rio de Janeiro e seu relacionamento com Capitu. — Rio de Janeiro: H. Garnier, Livreiro-Editor, 1899 (Domínio Público — Art. 41 da Lei nº 9.610/1998).','uploads/capas/dp_dom_casmurro.svg',NULL,'livre',15,NULL,'1899-01-01','2026-11-22 13:50:15','openlibrary','OL263058W'),(34,'Memórias Póstumas de Brás Cubas','Machado de Assis',NULL,'Marco inaugural do Realismo no Brasil (1881), em que o defunto-autor Brás Cubas narra com ironia e liberdade filosófica sua própria trajetória. — Rio de Janeiro: Tipografia Nacional, 1881 (Domínio Público — Art. 41 da Lei nº 9.610/1998).','uploads/capas/dp_bras_cubas.svg',NULL,'14',10,NULL,'1881-01-01','2026-11-22 13:53:40','openlibrary','OL262884W'),(35,'O Alienista','Machado de Assis',NULL,NULL,'uploads/capas/dp_o_alienista.svg',NULL,'livre',13,NULL,'1882-01-01','2026-11-22 14:15:02','openlibrary','OL262969W');
/*!40000 ALTER TABLE `livro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loja_gateway_simulado`
--

DROP TABLE IF EXISTS `loja_gateway_simulado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loja_gateway_simulado` (
  `id_externo` varchar(40) NOT NULL,
  `referencia` char(20) NOT NULL,
  `metodo` enum('pix','cartao') NOT NULL,
  `valor_centavos` int(10) unsigned NOT NULL,
  `status_remoto` enum('pending','approved','rejected','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `status_detalhe` varchar(120) DEFAULT NULL,
  `expira_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_externo`),
  KEY `idx_loja_gwsim_ref` (`referencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loja_gateway_simulado`
--

LOCK TABLES `loja_gateway_simulado` WRITE;
/*!40000 ALTER TABLE `loja_gateway_simulado` DISABLE KEYS */;
INSERT INTO `loja_gateway_simulado` VALUES ('SIM-5759690AA57DF6B7','FLV8AB88BB166BF9778B','pix',2550,'approved','accredited','2026-11-18 15:26:48','2026-11-18 11:11:48','2026-11-18 11:11:49'),('SIM-6F923609EE3617C0','FLV4CF50F92DDAEB7724','pix',1990,'pending','pending_waiting_transfer','2026-11-17 16:17:35','2026-11-17 12:02:35','2026-11-17 12:02:35'),('SIM-A2241B1CBF894188','FLV152590226C55CD861','cartao',2490,'approved','accredited',NULL,'2026-11-17 11:00:38','2026-11-17 11:00:38');
/*!40000 ALTER TABLE `loja_gateway_simulado` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loja_ledger_autor`
--

DROP TABLE IF EXISTS `loja_ledger_autor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loja_ledger_autor` (
  `id_lancamento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_autor` int(10) unsigned NOT NULL,
  `id_pedido` int(10) unsigned DEFAULT NULL,
  `id_repasse` int(10) unsigned DEFAULT NULL,
  `tipo_lancamento` enum('credito_venda','estorno_venda','repasse','repasse_cancelado') NOT NULL,
  `valor_centavos` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_lancamento`),
  UNIQUE KEY `uq_loja_ledger_pedido_tipo` (`id_pedido`,`tipo_lancamento`),
  UNIQUE KEY `uq_loja_ledger_repasse_tipo` (`id_repasse`,`tipo_lancamento`),
  KEY `idx_loja_ledger_autor` (`id_autor`,`criado_em`),
  CONSTRAINT `fk_loja_ledger_autor` FOREIGN KEY (`id_autor`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `fk_loja_ledger_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `loja_pedidos` (`id_pedido`) ON UPDATE CASCADE,
  CONSTRAINT `fk_loja_ledger_repasse` FOREIGN KEY (`id_repasse`) REFERENCES `loja_repasses` (`id_repasse`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loja_ledger_autor`
--

LOCK TABLES `loja_ledger_autor` WRITE;
/*!40000 ALTER TABLE `loja_ledger_autor` DISABLE KEYS */;
INSERT INTO `loja_ledger_autor` VALUES (1,44,1,NULL,'credito_venda',2241,'Venda FLV152590226C55CD861 — O Arquipélago de Vidro','2026-11-17 11:00:38');
/*!40000 ALTER TABLE `loja_ledger_autor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loja_pagamentos`
--

DROP TABLE IF EXISTS `loja_pagamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loja_pagamentos` (
  `id_pagamento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_pedido` int(10) unsigned NOT NULL,
  `gateway` varchar(30) NOT NULL,
  `id_externo` varchar(100) DEFAULT NULL,
  `origem_evento` enum('criacao','consulta','webhook','simulacao','reembolso','expiracao','gratuito') NOT NULL,
  `status_pagamento` varchar(30) NOT NULL,
  `status_detalhe` varchar(120) DEFAULT NULL,
  `valor_centavos` int(10) unsigned DEFAULT NULL,
  `payload_bruto` mediumtext DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_pagamento`),
  KEY `idx_loja_pag_pedido` (`id_pedido`,`criado_em`),
  KEY `idx_loja_pag_externo` (`gateway`,`id_externo`),
  CONSTRAINT `fk_loja_pag_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `loja_pedidos` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loja_pagamentos`
--

LOCK TABLES `loja_pagamentos` WRITE;
/*!40000 ALTER TABLE `loja_pagamentos` DISABLE KEYS */;
INSERT INTO `loja_pagamentos` VALUES (1,1,'simulado','SIM-A2241B1CBF894188','criacao','pago','accredited',2490,'{\"id\":\"SIM-A2241B1CBF894188\",\"status\":\"approved\",\"status_detail\":\"accredited\",\"cenario\":\"cartao_aprovado\",\"external_reference\":\"FLV152590226C55CD861\",\"transaction_amount_cents\":2490}','2026-11-17 11:00:38','2026-11-17 11:00:38'),(2,1,'simulado','SIM-A2241B1CBF894188','criacao','pago','accredited',2490,'{\"origem\":\"criacao\",\"status_detail\":\"accredited\"}','2026-11-17 11:00:38','2026-11-17 11:00:38'),(3,2,'nenhum',NULL,'gratuito','pago','free',0,'{\"origem\":\"gratuito\",\"status_detail\":\"free\"}','2026-11-17 11:05:36','2026-11-17 11:05:36'),(4,3,'simulado','SIM-6F923609EE3617C0','criacao','pendente','pending_waiting_transfer',1990,'{\"id\":\"SIM-6F923609EE3617C0\",\"status\":\"pending\",\"status_detail\":\"pending_waiting_transfer\",\"cenario\":\"pix\",\"external_reference\":\"FLV4CF50F92DDAEB7724\",\"transaction_amount_cents\":1990}','2026-11-17 12:02:35','2026-11-17 12:02:35'),(5,3,'simulado','SIM-6F923609EE3617C0','consulta','cancelado','cancelled_by_buyer',NULL,'{\"origem\":\"comprador\"}','2026-11-17 12:02:46','2026-11-17 12:02:46'),(8,10,'nenhum',NULL,'gratuito','pago','free',0,'{\"origem\":\"gratuito\",\"status_detail\":\"free\"}','2026-11-22 13:24:37','2026-11-22 13:24:37'),(9,14,'nenhum',NULL,'gratuito','pago','free',0,'{\"origem\":\"gratuito\",\"status_detail\":\"free\"}','2026-11-22 13:54:18','2026-11-22 13:54:18'),(10,15,'nenhum',NULL,'gratuito','pago','free',0,'{\"origem\":\"gratuito\",\"status_detail\":\"free\"}','2026-11-23 15:31:48','2026-11-23 15:31:48');
/*!40000 ALTER TABLE `loja_pagamentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loja_pedidos`
--

DROP TABLE IF EXISTS `loja_pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loja_pedidos` (
  `id_pedido` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo_pedido` char(20) NOT NULL,
  `id_comprador` int(10) unsigned NOT NULL,
  `id_vendedor` int(10) unsigned NOT NULL,
  `id_produto` int(10) unsigned NOT NULL,
  `titulo_produto_snapshot` varchar(200) NOT NULL,
  `valor_bruto_centavos` int(10) unsigned NOT NULL,
  `taxa_percentual` decimal(5,2) NOT NULL,
  `valor_taxa_centavos` int(10) unsigned NOT NULL,
  `valor_liquido_centavos` int(10) unsigned NOT NULL,
  `metodo_pagamento` enum('pix','cartao','gratuito') NOT NULL,
  `modo_pagamento` enum('simulado','real') NOT NULL,
  `gateway` varchar(30) NOT NULL,
  `id_externo_gateway` varchar(100) DEFAULT NULL,
  `status_pedido` enum('pendente','pago','falhou','cancelado','reembolsado') NOT NULL DEFAULT 'pendente',
  `motivo_status` varchar(255) DEFAULT NULL,
  `pix_copia_cola` text DEFAULT NULL,
  `pix_qr_base64` mediumtext DEFAULT NULL,
  `pix_ticket_url` varchar(500) DEFAULT NULL,
  `expira_em` datetime DEFAULT NULL,
  `pago_em` datetime DEFAULT NULL,
  `reembolsado_em` datetime DEFAULT NULL,
  `ultima_consulta_em` datetime DEFAULT NULL,
  `compra_paga_unica` tinyint(1) GENERATED ALWAYS AS (if(`status_pedido` = 'pago',1,NULL)) STORED,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_pedido`),
  UNIQUE KEY `uq_loja_pedido_codigo` (`codigo_pedido`),
  UNIQUE KEY `uq_loja_pedido_pago_unico` (`id_comprador`,`id_produto`,`compra_paga_unica`),
  KEY `idx_loja_pedido_comprador` (`id_comprador`,`criado_em`),
  KEY `idx_loja_pedido_vendedor` (`id_vendedor`,`status_pedido`,`criado_em`),
  KEY `idx_loja_pedido_produto` (`id_produto`,`status_pedido`),
  KEY `idx_loja_pedido_externo` (`gateway`,`id_externo_gateway`),
  KEY `idx_loja_pedido_status` (`status_pedido`,`expira_em`),
  CONSTRAINT `fk_loja_pedido_comprador` FOREIGN KEY (`id_comprador`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `fk_loja_pedido_produto` FOREIGN KEY (`id_produto`) REFERENCES `loja_produtos` (`id_produto`) ON UPDATE CASCADE,
  CONSTRAINT `fk_loja_pedido_vendedor` FOREIGN KEY (`id_vendedor`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `ck_loja_pedido_valores` CHECK (`valor_taxa_centavos` + `valor_liquido_centavos` = `valor_bruto_centavos`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loja_pedidos`
--

LOCK TABLES `loja_pedidos` WRITE;
/*!40000 ALTER TABLE `loja_pedidos` DISABLE KEYS */;
INSERT INTO `loja_pedidos` VALUES (1,'FLV152590226C55CD861',40,44,2,'O Arquipélago de Vidro',2490,10.00,249,2241,'cartao','simulado','simulado','SIM-A2241B1CBF894188','pago',NULL,NULL,NULL,NULL,NULL,'2026-11-17 15:00:38',NULL,NULL,1,'2026-11-17 11:00:38','2026-11-17 11:00:38'),(2,'FLVDD0A174653CF02448',1,44,1,'Cartas do Observatório Sul',0,10.00,0,0,'gratuito','simulado','nenhum',NULL,'pago',NULL,NULL,NULL,NULL,NULL,'2026-11-17 15:05:36',NULL,NULL,1,'2026-11-17 11:05:35','2026-11-17 11:05:36'),(3,'FLV4CF50F92DDAEB7724',1,44,4,'Crônicas da Torre Cinzenta',1990,10.00,199,1791,'pix','simulado','simulado','SIM-6F923609EE3617C0','cancelado','Cancelado pelo comprador','00020126500014BR.GOV.BCB.PIX0128pix-simulado@folivro.invalid520400005303986540519.905802BR5916FOLIVRO SIMULADO6009SAO PAULO62240520FLV4CF50F92DDAEB77246304D948',NULL,NULL,'2026-11-17 16:17:35',NULL,NULL,'2026-11-17 16:02:41',NULL,'2026-11-17 12:02:35','2026-11-17 12:02:46'),(10,'FLV7CF93ECB70B311D9C',51,1,6,'Crônicas da Biblioteca de Papel',0,10.00,0,0,'gratuito','simulado','nenhum',NULL,'pago',NULL,NULL,NULL,NULL,NULL,'2026-11-22 17:24:37',NULL,NULL,1,'2026-11-22 13:24:37','2026-11-22 13:24:37'),(14,'FLV865406556B4942902',51,44,1,'Cartas do Observatório Sul',0,10.00,0,0,'gratuito','simulado','nenhum',NULL,'pago',NULL,NULL,NULL,NULL,NULL,'2026-11-22 17:54:17',NULL,NULL,1,'2026-11-22 13:54:17','2026-11-22 13:54:17'),(15,'FLVF7673BB8B1D72C124',1,44,6,'Crônicas da Biblioteca de Papel',0,10.00,0,0,'gratuito','simulado','nenhum',NULL,'pago',NULL,NULL,NULL,NULL,NULL,'2026-11-23 19:31:48',NULL,NULL,1,'2026-11-23 15:31:48','2026-11-23 15:31:48');
/*!40000 ALTER TABLE `loja_pedidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loja_produtos`
--

DROP TABLE IF EXISTS `loja_produtos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loja_produtos` (
  `id_produto` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_autor` int(10) unsigned NOT NULL,
  `id_livro` int(10) unsigned DEFAULT NULL,
  `id_autor_dos_livros` int(10) unsigned DEFAULT NULL,
  `titulo_produto` varchar(200) NOT NULL,
  `descricao_produto` text DEFAULT NULL,
  `preview_produto` text DEFAULT NULL,
  `capa_produto` varchar(255) DEFAULT NULL,
  `generos_produto` varchar(255) DEFAULT NULL,
  `classificacao_produto` varchar(20) NOT NULL DEFAULT 'livre',
  `preco_centavos` int(10) unsigned NOT NULL DEFAULT 0,
  `arquivo_produto` varchar(120) DEFAULT NULL,
  `arquivo_nome_original` varchar(200) DEFAULT NULL,
  `arquivo_tipo` enum('pdf','epub') DEFAULT NULL,
  `arquivo_tamanho` int(10) unsigned DEFAULT NULL,
  `arquivo_sha256` char(64) DEFAULT NULL,
  `status_produto` enum('rascunho','publicado','arquivado') NOT NULL DEFAULT 'rascunho',
  `aceite_termo_direitos` tinyint(1) NOT NULL DEFAULT 0,
  `data_aceite_termo` datetime DEFAULT NULL,
  `versao_termo` varchar(20) DEFAULT NULL,
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `data_publicacao` datetime DEFAULT NULL,
  PRIMARY KEY (`id_produto`),
  UNIQUE KEY `uq_loja_produto_livro` (`id_livro`),
  KEY `idx_loja_produto_autor` (`id_autor`,`status_produto`),
  KEY `idx_loja_produto_vitrine` (`status_produto`,`data_publicacao`),
  KEY `idx_loja_produto_preco` (`preco_centavos`),
  KEY `idx_loja_produto_titulo` (`titulo_produto`),
  KEY `fk_loja_produto_adl` (`id_autor_dos_livros`),
  CONSTRAINT `fk_loja_produto_adl` FOREIGN KEY (`id_autor_dos_livros`) REFERENCES `autor_dos_livros` (`id_autor_dos_livros`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_loja_produto_autor` FOREIGN KEY (`id_autor`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `fk_loja_produto_livro` FOREIGN KEY (`id_livro`) REFERENCES `livro` (`id_livro`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `ck_loja_produto_preco` CHECK (`preco_centavos` <= 100000000)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loja_produtos`
--

LOCK TABLES `loja_produtos` WRITE;
/*!40000 ALTER TABLE `loja_produtos` DISABLE KEYS */;
INSERT INTO `loja_produtos` VALUES (1,44,19,24,'Cartas do Observatório Sul','Coletânea de crônicas astronômicas e reflexões sobre o céu do hemisfério sul. Edição digital gratuita para novos leitores.','Naquela noite de inverno, o telescópio apontava para além das nuvens baixas da serra, onde cada estrela parecia guardar uma carta não enviada.','uploads/capas/loja_observatorio_sul.svg','Crônica, Literatura Nacional','livre',0,'obra_44_42f8347fa1edffbc92a4270c.pdf','Cartas-do-Observat-orio-Sul.pdf','pdf',784,'7b4f7a862dc80ee28a6efd0f0a077b31395797965631514cd84e67f01ae1e723','publicado',1,'2026-11-17 11:00:38','loja-1.0','2026-11-17 11:00:38','2026-11-22 14:07:59','2026-11-05 11:00:38'),(2,44,20,26,'O Arquipélago de Vidro','Fantasia épica sobre cartógrafos que navegam entre ilhas translúcidas cujos mapas mudam a cada maré.','Capítulo 1 — A bússola de quartzo: Lira ajustou o sextante enquanto a costa de vidro refletia o amanhecer em sete tons de âmbar.','uploads/capas/loja_arquipelago_vidro.svg','Fantasia, Aventura','12',2490,'obra_44_27ebfa32aa025314c170e2dd.pdf','O-Arquip-elago-de-Vidro.pdf','pdf',780,'2cc4948685f1f389c717ada76f7e63c36109ceedd2343c2830cf0c0108d1ba7d','publicado',1,'2026-11-17 11:00:38','loja-1.0','2026-11-17 11:00:38','2026-11-22 14:07:59','2026-11-08 11:00:38'),(3,44,21,27,'Algoritmo da Neblina','Ficção científica investigativa em uma São Paulo submersa por dados e neblina sintética.','Os servidores da zona leste ainda zumbiam sob a garoa fina quando o último arquivo criptografado chegou ao terminal.','uploads/capas/loja_algoritmo_neblina.svg','Ficção Científica, Suspense, Distopia','14',3490,'obra_44_0b5dbd03b80a986e6dae5fd4.epub','Algoritmo-da-Neblina.epub','epub',1160,'a4474ac3ec6c6dda510a9d4ed523d3f22b727c286e6211fdbfd987ddc23b279f','publicado',1,'2026-11-17 11:00:38','loja-1.0','2026-11-17 11:00:38','2026-11-22 14:07:59','2026-11-11 11:00:38'),(4,44,22,25,'Crônicas da Torre Cinzenta','Suspense gótico sobre um restaurador de manuscritos que descobre anotações cifradas em um convento isolado.','Havia tinta fresca nas margens do códice de 1740, como se alguém tivesse continuado o relato minutos antes da minha chegada.','uploads/capas/loja_torre_cinzenta.svg','Mistério, Terror, Histórico','16',1990,'obra_44_aeec1fe7f48056706bf7941e.pdf','Cr-onicas-da-Torre-Cinzenta.pdf','pdf',784,'6d5f9a30a39a3442316b1300b7d954aeebad826cf09034847517a1b770089426','publicado',1,'2026-11-17 11:00:38','loja-1.0','2026-11-17 11:00:38','2026-11-22 14:07:59','2026-11-14 11:00:38'),(6,44,24,17,'Crônicas da Biblioteca de Papel','Uma jornada literária acolhedora sobre o poder da leitura e a preservação de memórias em uma biblioteca comunitária.','Nas galerias silenciosas da antiga biblioteca, cada volume guardava memórias de leitores de diferentes gerações.','uploads/capas/loja_biblioteca_papel.svg','Ficção, Drama, Literatura Nacional','livre',0,'obra_1_9336ed8a2e683ae51b11cf49.pdf','Cr-onicas-da-Biblioteca-de-Papel.pdf','pdf',1039,'7a4fd646ed45a2c31e19f3d4b964c081425660f74d062ea7b91377559b073a15','publicado',1,'2026-11-17 11:26:12','loja-1.0','2026-11-17 11:26:12','2026-11-22 14:36:57','2026-11-04 16:27:18'),(7,44,25,18,'Sombras da Meia-Noite','Suspense psicológico intenso e temas adultos voltados exclusivamente ao público maior de 18 anos.','A chuva cortava os becos escuros da metrópole enquanto o detetive encarava os segredos mais densos e sombrios da noite.','uploads/capas/loja_sombras_meia_noite.svg','Suspense, Mistério, Drama','18',1990,'obra_1_2a2c400b569e6df114443009.pdf','Sombras-da-Meia-Noite.pdf','pdf',1008,'98b962a558274805b0c6ed430b4eeb92c1c3b5d33f9ff9940f74349fc2ab56ac','publicado',1,'2026-11-17 11:26:12','loja-1.0','2026-11-17 11:26:12','2026-11-22 14:36:57','2026-11-04 16:27:18');
/*!40000 ALTER TABLE `loja_produtos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loja_rate_limit`
--

DROP TABLE IF EXISTS `loja_rate_limit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loja_rate_limit` (
  `chave` char(64) NOT NULL,
  `janela_inicio` int(10) unsigned NOT NULL,
  `contador` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`chave`,`janela_inicio`),
  KEY `idx_loja_rate_janela` (`janela_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loja_rate_limit`
--

LOCK TABLES `loja_rate_limit` WRITE;
/*!40000 ALTER TABLE `loja_rate_limit` DISABLE KEYS */;
INSERT INTO `loja_rate_limit` VALUES ('4f40011d744b87453d0381fc07f44d64d4cd389fe42c507fbc90601eb39c5820',1794924360,1),('4f40011d744b87453d0381fc07f44d64d4cd389fe42c507fbc90601eb39c5820',1795363140,1),('4f40011d744b87453d0381fc07f44d64d4cd389fe42c507fbc90601eb39c5820',1795363260,1),('4f40011d744b87453d0381fc07f44d64d4cd389fe42c507fbc90601eb39c5820',1795363320,4),('4f40011d744b87453d0381fc07f44d64d4cd389fe42c507fbc90601eb39c5820',1795363440,6),('55ad3d6b119bc8f7eab7e61bf7a0005937a3bc2838eb7827bb80ab014f273f50',1795013460,1),('74ee0ab8038efbf13ecb496a09bf2cd499f4c93d82aee935b587d2128e240f92',1795010940,1),('8f87356e758d9369662b8c842e419094dfbfdbba4cd1e6f8c7602fde57595b96',1795365300,1),('a68c0cbabf6548819748258869f418ade22f4b033756905aff04ad9fc28a9e5c',1794924300,1),('a68c0cbabf6548819748258869f418ade22f4b033756905aff04ad9fc28a9e5c',1794927720,1),('a68c0cbabf6548819748258869f418ade22f4b033756905aff04ad9fc28a9e5c',1794927840,2),('bdebea4c1e1ce6b1202126381cb603b28716816e71b0bba9c93bac95e1ddf21b',1795365360,1),('cb74d8c9a0c81a9abd686a6ae373f20afcd0a698f6709b2653eefa95a6d801d7',1795011060,1);
/*!40000 ALTER TABLE `loja_rate_limit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loja_repasses`
--

DROP TABLE IF EXISTS `loja_repasses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loja_repasses` (
  `id_repasse` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_autor` int(10) unsigned NOT NULL,
  `valor_centavos` int(10) unsigned NOT NULL,
  `status_repasse` enum('solicitado','processando','pago','cancelado') NOT NULL DEFAULT 'solicitado',
  `modo_pagamento` enum('simulado','real') NOT NULL,
  `observacao` varchar(255) DEFAULT NULL,
  `solicitado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `processado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id_repasse`),
  KEY `idx_loja_repasse_autor` (`id_autor`,`solicitado_em`),
  CONSTRAINT `fk_loja_repasse_autor` FOREIGN KEY (`id_autor`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `ck_loja_repasse_valor` CHECK (`valor_centavos` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loja_repasses`
--

LOCK TABLES `loja_repasses` WRITE;
/*!40000 ALTER TABLE `loja_repasses` DISABLE KEYS */;
/*!40000 ALTER TABLE `loja_repasses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loja_webhook_eventos`
--

DROP TABLE IF EXISTS `loja_webhook_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loja_webhook_eventos` (
  `id_evento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gateway` varchar(30) NOT NULL,
  `chave_idempotencia` char(64) NOT NULL,
  `id_recurso` varchar(100) DEFAULT NULL,
  `tipo_evento` varchar(60) DEFAULT NULL,
  `assinatura_valida` tinyint(1) NOT NULL DEFAULT 0,
  `status_processamento` enum('recebido','processado','ignorado','erro') NOT NULL DEFAULT 'recebido',
  `resultado` varchar(255) DEFAULT NULL,
  `payload_bruto` mediumtext DEFAULT NULL,
  `tentativas` int(10) unsigned NOT NULL DEFAULT 1,
  `recebido_em` datetime NOT NULL DEFAULT current_timestamp(),
  `processado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id_evento`),
  UNIQUE KEY `uq_loja_webhook_evento` (`gateway`,`chave_idempotencia`),
  KEY `idx_loja_webhook_recurso` (`gateway`,`id_recurso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loja_webhook_eventos`
--

LOCK TABLES `loja_webhook_eventos` WRITE;
/*!40000 ALTER TABLE `loja_webhook_eventos` DISABLE KEYS */;
/*!40000 ALTER TABLE `loja_webhook_eventos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `metas_leitura`
--

DROP TABLE IF EXISTS `metas_leitura`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `metas_leitura` (
  `id_metas_leitura` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `titulo_metas` varchar(120) NOT NULL,
  `tipo_metas` enum('livros','paginas') NOT NULL DEFAULT 'livros',
  `quantidade_metas` int(10) unsigned NOT NULL,
  `progresso_metas` int(10) unsigned NOT NULL DEFAULT 0,
  `data_inicio_metas` date NOT NULL,
  `data_fim_metas` date NOT NULL,
  `status_metas` enum('em_andamento','concluida','cancelada') NOT NULL DEFAULT 'em_andamento',
  PRIMARY KEY (`id_metas_leitura`),
  KEY `idx_metas_usuario` (`id_usuario`),
  CONSTRAINT `fk_metas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_metas_datas` CHECK (`data_fim_metas` >= `data_inicio_metas`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `metas_leitura`
--

LOCK TABLES `metas_leitura` WRITE;
/*!40000 ALTER TABLE `metas_leitura` DISABLE KEYS */;
/*!40000 ALTER TABLE `metas_leitura` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `progressao_leitura`
--

DROP TABLE IF EXISTS `progressao_leitura`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `progressao_leitura` (
  `id_progressao_leitura` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_livro` int(10) unsigned NOT NULL,
  `pagina_atual_progressao` int(10) unsigned NOT NULL DEFAULT 0,
  `total_paginas_progressao` int(10) unsigned NOT NULL DEFAULT 0,
  `porcentagem_progressao` decimal(5,2) NOT NULL DEFAULT 0.00,
  `data_modificacao_progressao` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_progressao_leitura`),
  UNIQUE KEY `uq_progressao_usuario_livro` (`id_usuario`,`id_livro`),
  KEY `idx_progressao_livro` (`id_livro`),
  CONSTRAINT `fk_progressao_livro` FOREIGN KEY (`id_livro`) REFERENCES `livro` (`id_livro`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_progressao_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_progressao_pagina` CHECK (`total_paginas_progressao` = 0 or `pagina_atual_progressao` <= `total_paginas_progressao`),
  CONSTRAINT `ck_progressao_pct` CHECK (`porcentagem_progressao` between 0 and 100)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `progressao_leitura`
--

LOCK TABLES `progressao_leitura` WRITE;
/*!40000 ALTER TABLE `progressao_leitura` DISABLE KEYS */;
INSERT INTO `progressao_leitura` VALUES (16,1,15,68,320,21.25,'2026-10-22 02:01:44'),(18,1,17,0,384,0.00,'2026-10-22 01:30:06'),(20,1,18,15,25,60.00,'2026-10-22 21:47:30'),(22,51,33,0,0,0.00,'2026-11-22 13:50:15'),(23,51,34,0,0,0.00,'2026-11-22 13:53:40'),(24,51,35,0,13,0.00,'2026-11-22 14:15:02'),(25,1,33,0,15,0.00,'2026-11-23 15:45:55');
/*!40000 ALTER TABLE `progressao_leitura` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resenha`
--

DROP TABLE IF EXISTS `resenha`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resenha` (
  `id_resenha` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_livro` int(10) unsigned NOT NULL,
  `nota_resenha` tinyint(3) unsigned DEFAULT NULL,
  `titulo_resenha` varchar(150) DEFAULT NULL,
  `resenha_resenha` text NOT NULL,
  `compra_verificada_resenha` tinyint(1) NOT NULL DEFAULT 0,
  `data_avaliacao_resenha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_resenha`),
  UNIQUE KEY `uq_resenha_usuario_livro` (`id_usuario`,`id_livro`),
  KEY `idx_resenha_livro` (`id_livro`),
  CONSTRAINT `fk_resenha_livro` FOREIGN KEY (`id_livro`) REFERENCES `livro` (`id_livro`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_resenha_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_resenha_nota` CHECK (`nota_resenha` is null or `nota_resenha` between 1 and 5)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resenha`
--

LOCK TABLES `resenha` WRITE;
/*!40000 ALTER TABLE `resenha` DISABLE KEYS */;
INSERT INTO `resenha` VALUES (13,1,15,5,'Uma obra-prima incontestável','A ironia refinada e a construção de personagens tornam este clássico atemporal.',0,'2026-10-22 01:44:07'),(15,1,18,5,'GOAT ALICE','Lewis Carroll é um genio',0,'2026-10-22 21:48:00'),(16,40,20,5,'Construção de mundo envolvente','A leitura flui muito bem e a edição em PDF está impecável. Recomendo para quem gosta de fantasia de exploração.',1,'2026-11-17 11:00:38');
/*!40000 ALTER TABLE `resenha` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seguidores`
--

DROP TABLE IF EXISTS `seguidores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seguidores` (
  `id_seguidores` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_seguidor` int(10) unsigned NOT NULL,
  `id_seguido` int(10) unsigned NOT NULL,
  `data_seguidores` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_seguidores`),
  UNIQUE KEY `uq_seguidores_par` (`id_seguidor`,`id_seguido`),
  KEY `idx_seguidores_seguido` (`id_seguido`),
  CONSTRAINT `fk_seguidores_seguido` FOREIGN KEY (`id_seguido`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_seguidores_seguidor` FOREIGN KEY (`id_seguidor`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_seguidores_diferentes` CHECK (`id_seguidor` <> `id_seguido`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seguidores`
--

LOCK TABLES `seguidores` WRITE;
/*!40000 ALTER TABLE `seguidores` DISABLE KEYS */;
/*!40000 ALTER TABLE `seguidores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sistema_config`
--

DROP TABLE IF EXISTS `sistema_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sistema_config` (
  `chave` varchar(80) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `atualizado_por` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sistema_config`
--

LOCK TABLES `sistema_config` WRITE;
/*!40000 ALTER TABLE `sistema_config` DISABLE KEYS */;
INSERT INTO `sistema_config` VALUES ('aprovacao_automatica_obras','1','2026-11-17 11:19:13',1),('payment_mode','simulated','2026-11-17 12:56:07',1);
/*!40000 ALTER TABLE `sistema_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `termos_aceitos`
--

DROP TABLE IF EXISTS `termos_aceitos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `termos_aceitos` (
  `id_termos_aceitos` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `tipo_termos` enum('termos_uso','privacidade','declaracao_autoria','declaracao_comercializacao') NOT NULL,
  `versao_termos` varchar(20) NOT NULL,
  `id_autor_dos_livros` int(10) unsigned DEFAULT NULL,
  `data_aceite_termos` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_termos_aceitos`),
  KEY `idx_termos_usuario` (`id_usuario`,`tipo_termos`),
  KEY `fk_termos_obra` (`id_autor_dos_livros`),
  CONSTRAINT `fk_termos_obra` FOREIGN KEY (`id_autor_dos_livros`) REFERENCES `autor_dos_livros` (`id_autor_dos_livros`) ON UPDATE CASCADE,
  CONSTRAINT `fk_termos_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `termos_aceitos`
--

LOCK TABLES `termos_aceitos` WRITE;
/*!40000 ALTER TABLE `termos_aceitos` DISABLE KEYS */;
INSERT INTO `termos_aceitos` VALUES (22,40,'termos_uso','1.0',NULL,'2026-10-26 00:11:14'),(23,40,'privacidade','1.0',NULL,'2026-10-26 00:11:14'),(25,41,'termos_uso','1.0',NULL,'2026-11-04 16:05:15'),(26,41,'privacidade','1.0',NULL,'2026-11-04 16:05:15'),(31,51,'termos_uso','1.0',NULL,'2026-11-22 13:23:45'),(32,51,'privacidade','1.0',NULL,'2026-11-22 13:23:45');
/*!40000 ALTER TABLE `termos_aceitos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario` (
  `id_usuario` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome_usuario` varchar(120) NOT NULL,
  `email_usuario` varchar(150) NOT NULL,
  `data_nascimento_usuario` date DEFAULT NULL,
  `senha_usuario` varchar(255) NOT NULL,
  `biografia_usuario` text DEFAULT NULL,
  `foto_usuario` varchar(255) DEFAULT NULL,
  `nivel_usuario` enum('leitor','autor','admin') NOT NULL DEFAULT 'leitor',
  `status_usuario` enum('ativo','inativo','banido') NOT NULL DEFAULT 'ativo',
  `data_cadastro_usuario` datetime NOT NULL DEFAULT current_timestamp(),
  `restricao_chat_ate` datetime DEFAULT NULL,
  `avisos_moderacao_usuario` int(10) unsigned NOT NULL DEFAULT 0,
  `modalidade_publicacao_usuario` enum('avaliacao','automatica','revisao_obrigatoria','suspensa') NOT NULL DEFAULT 'avaliacao',
  `capitulos_aprovados_usuario` int(10) unsigned NOT NULL DEFAULT 0,
  `violacoes_confirmadas_usuario` int(10) unsigned NOT NULL DEFAULT 0,
  `revisoes_recusadas_usuario` int(10) unsigned NOT NULL DEFAULT 0,
  `justificativa_modalidade_usuario` varchar(255) DEFAULT NULL,
  `data_qualificacao_usuario` datetime DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_usuario_email` (`email_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,'Administrador de teste','admin@folivro.local','2007-09-11','$2y$10$oAYBl5moZ8wdZzaUMDhmDuiHooQLqRBtNDrRYfMBpYaPDyaaeNIJ.',NULL,NULL,'admin','ativo','2026-10-16 21:29:57',NULL,0,'avaliacao',0,0,0,NULL,NULL),(40,'Leitor de Teste','leitor@folivro.local','1998-03-20','$2y$10$s3.HRQrtL3k/2Imbx1eZ7.s3I54bX/gERAafMgCOhHFs30DqrYWRm','Conta de usuário comum (leitor) para testes e demonstração acadêmica do FOLIVRO.',NULL,'autor','ativo','2026-10-26 00:11:14',NULL,1,'avaliacao',0,0,0,NULL,NULL),(41,'natan','pereira2007natan222@gmail.com','2007-07-19','$2y$10$XSoJRdNqv907.5rSDeA8W.uj2Eww6P4csL57vWWd1hIcTdghboYTW',NULL,NULL,'autor','ativo','2026-11-04 16:05:15',NULL,0,'automatica',3,0,0,'Autor qualificado para publicação automática após cumprir os critérios objetivos de histórico positivo e ausência de violações.','2026-11-04 17:45:06'),(44,'GePeTo','autora@folivro.local','1993-05-14','$2y$10$jihhmgxj9UQOVbJuEN.2bu2Eff4yQGAK24AH/RNV8bzjpc5QHqXRe','Escritora independente de fantasia, ficção científica e crônicas no FOLIVRO.',NULL,'autor','ativo','2026-11-17 11:00:38',NULL,0,'automatica',4,0,0,'Autor qualificado para publicação automática após cumprir os critérios objetivos de histórico positivo e ausência de violações.','2026-11-22 13:35:09'),(51,'Natan','natan.folivro@gmail.com','2007-07-19','$2y$10$bsY0YbQAmTo66KLVCdhxC.1RqLKRd3PJIsE3LyQswkn/4bMSj9U/W',NULL,NULL,'leitor','ativo','2026-11-22 13:23:45',NULL,0,'avaliacao',0,0,0,NULL,NULL);
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'folivro'
--

--
-- Dumping routines for database 'folivro'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-23 16:53:58
