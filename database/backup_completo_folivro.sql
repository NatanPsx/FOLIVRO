-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: folivro
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
INSERT INTO `_folivro_migrations` VALUES ('001_initial_schema','2026-10-16 20:57:24'),('002_openlibrary_catalog','2026-10-16 21:29:57'),('003_copyright_and_moderation','2026-10-19 00:00:37'),('004_chat_security_and_copyright','2026-10-19 01:03:59'),('005_editorial_review_and_chapter_hashes','2026-10-22 00:37:21'),('006_disable_public_default_admin','2026-10-22 00:37:21'),('007_portable_demo_seed','2026-10-25 21:33:36'),('008_seed_normal_test_user','2026-10-26 00:11:14'),('009_age_classification_and_birthdate','2026-11-04 16:27:18'),('010_add_genres_to_historias','2026-11-04 17:00:46'),('011_hybrid_moderation_smart_publishing','2026-11-04 17:08:47'),('012_seed_current_books_and_works','2026-11-04 17:26:33');
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `autor_dos_livros`
--

LOCK TABLES `autor_dos_livros` WRITE;
/*!40000 ALTER TABLE `autor_dos_livros` DISABLE KEYS */;
INSERT INTO `autor_dos_livros` VALUES (15,15,40,'SALVE',0.00,1,'2026-10-26 00:16:02',NULL,'removido',NULL,'2026-10-26 00:18:55','gratuita',0,NULL,'recusada',NULL,NULL,NULL,'Removida pela moderação: loucura'),(16,16,41,'OBSERVÇÕES - FOLIVRO',5.50,1,'2026-11-04 16:14:43',NULL,'a_venda','2026-11-04 17:26:33','2026-11-04 17:26:33','venda_simulada',1,'2026-11-04 16:14:43','aprovada','TA LIBERADO','2026-11-04 16:28:56',1,NULL),(17,17,1,'Crônicas da Biblioteca de Papel',0.00,1,'2026-11-04 16:27:18',NULL,'a_venda','2026-11-04 16:27:18',NULL,'gratuita',1,'2026-11-04 16:27:18','aprovada',NULL,'2026-11-04 16:27:18',1,NULL),(18,18,1,'Sombras da Meia-Noite',19.90,1,'2026-11-04 16:27:18',NULL,'a_venda','2026-11-04 16:27:18',NULL,'venda_simulada',1,'2026-11-04 16:27:18','aprovada',NULL,'2026-11-04 16:27:18',1,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `capitulos`
--

LOCK TABLES `capitulos` WRITE;
/*!40000 ALTER TABLE `capitulos` DISABLE KEYS */;
INSERT INTO `capitulos` VALUES (15,15,'SALVE123','LALAUS FML LALAUS<div><br></div>','08708b65042b1507a1f9209a17b9c9477bf1d5f6efeef45c388673b41b1f4f1e',1,'rascunho','revisao_humana','pendente',NULL,NULL,NULL,'2026-10-26 00:16:02',NULL),(16,16,'A Biblioteca Silenciosa - Demonstração','<p data-pm-slice=\"1 1 []\">A chuva começou pouco antes do anoitecer.</p><p>Kael observava as gotas escorrerem pelas janelas da biblioteca real enquanto organizava os últimos livros devolvidos naquele dia. O lugar estava vazio, exceto por ele e pelo velho bibliotecário, mestre Orven, que insistia em conferir cada registro antes de fechar as portas.</p><p>— Você deveria ir para casa — disse Orven, sem tirar os olhos dos documentos. — Não há nada de interessante aqui depois do pôr do sol.</p><p>Kael sorriu discretamente.</p><p>— É exatamente depois do pôr do sol que as coisas interessantes costumam acontecer.</p><p>O velho ergueu os olhos por um instante, mas não respondeu.</p><p>Havia algo estranho naquela noite. Desde a manhã, os funcionários comentavam sobre uma luz avermelhada que surgira no alto da torre central. Alguns afirmavam ter ouvido vozes vindas das paredes. Outros juravam que seus relógios haviam parado simultaneamente durante alguns segundos.</p><p>Kael não acreditava em histórias sobrenaturais. Para ele, todo mistério possuía uma explicação, mesmo que fosse necessário procurar durante anos para encontrá-la.</p><p>Enquanto guardava um volume antigo na última estante, percebeu uma pequena abertura atrás de uma fileira de livros. A madeira parecia ter sido deslocada recentemente.</p><p>Curioso, afastou os volumes e encontrou uma caixa estreita, coberta por uma camada de poeira. Não havia fechadura, apenas um símbolo gravado na tampa: um círculo atravessado por sete linhas.</p><p>O mesmo símbolo estava presente nas fundações das torres.</p><p>Ao abrir a caixa, Kael encontrou um livro de capa negra. Nenhum título, nenhuma assinatura, nenhuma indicação de autoria. Apenas uma frase escrita em letras prateadas:</p><p><em>Quando a última luz morrer, procure aquele que nunca nasceu.</em></p><p>Ele franziu a testa.</p><p>A frase parecia absurda, mas alguma coisa o impedia de fechar o livro. Ao tocar a primeira página, sentiu uma vibração suave percorrer seus dedos.</p><p>Então as letras começaram a surgir diante de seus olhos.</p><p><strong>Registro número 1.</strong></p><p><strong>Data: 17 de outubro.</strong></p><p><strong>À meia-noite, a cidade de Arvend será destruída.</strong></p><p>Kael afastou a mão imediatamente.</p><p>A data estava correta. Era exatamente aquele dia.</p><p>Ele olhou para o relógio da parede.</p><p>Onze e quarenta e três.</p><p>Sete minutos.</p><p>Um estrondo interrompeu seus pensamentos. As janelas tremeram, os livros caíram das prateleiras e uma luz vermelha atravessou o céu. Ao longe, os sinos da torre central começaram a tocar sem qualquer intervenção humana.</p><p>Orven surgiu no corredor, pálido como papel.</p><p>— Onde você encontrou isso? — perguntou, encarando o livro.</p><p>Kael tentou responder, mas o velho já estava recuando.</p><p>Pela primeira vez desde que o conhecia, mestre Orven parecia verdadeiramente apavorado.</p><p>— Escute com atenção — disse ele. — Se o livro escreveu seu nome, não leia a próxima página.</p><p>Kael sentiu o estômago se contrair.</p><p>Lentamente, voltou os olhos para o manuscrito.</p><p>Uma nova linha acabara de aparecer.</p><p><strong>O leitor já foi identificado.</strong></p><p>Abaixo dela, em letras ainda úmidas de tinta, estava escrito seu nome.</p><p>E, pela primeira vez naquela noite, Kael ouviu uma voz sussurrar diretamente dentro de sua mente:</p><p>— Finalmente encontramos você.</p>','bbedc11f054c51ea75e086d315bcd086c5b6787b52d5eb697caf9460c70da1a5',1,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-04 17:26:33','2026-11-04 16:14:43','2026-11-04 17:26:33'),(17,17,'O Segredo das Estantes','<p>Nas galerias silenciosas da antiga biblioteca, cada volume guardava memórias de leitores de diferentes gerações.</p>','028fe90702d74878d4e7115857f90d5231eae8af36b54f665675a28425bc5c11',1,'publicado','revisao_humana','aprovada',NULL,NULL,NULL,'2026-11-04 16:27:18','2026-11-04 17:08:47'),(18,18,'Noite sem Retorno','<p>A chuva cortava os becos escuros da metrópole enquanto o detetive encarava os segredos mais densos e sombrios da noite.</p>','83ba044b0e0c4d835f5ed2edb1691ebe62844c56fe1a0c516ed03641159c7a91',1,'publicado','revisao_humana','aprovada',NULL,NULL,NULL,'2026-11-04 16:27:18','2026-11-04 17:08:47'),(19,16,'Sete Minutos Até a Meia-Noite','<p data-pm-slice=\"1 1 []\">Kael fechou o livro com tanta força que a poeira da mesa se ergueu em uma pequena nuvem.</p><p>Por alguns segundos, nenhum dos dois homens disse uma palavra.</p><p>Do lado de fora, os sinos continuavam tocando. Cada badalada parecia mais lenta que a anterior, como se o próprio tempo estivesse perdendo o ritmo. A luz avermelhada que cobria a cidade atravessava as janelas da biblioteca, tingindo as estantes de um tom semelhante ao de brasas quase apagadas.</p><p>— Mestre Orven, o que está acontecendo?</p><p>O velho não respondeu imediatamente. Seus olhos permaneciam fixos no livro negro, como se esperasse que ele se abrisse sozinho.</p><p>— Quantas páginas você leu?</p><p>— Apenas a primeira.</p><p>— E seu nome?</p><p>Kael hesitou.</p><p>— Apareceu na página.</p><p>Orven fechou os olhos por um instante.</p><p>— Então não temos muito tempo.</p><p>Ele atravessou o corredor e trancou a porta principal da biblioteca. Em seguida, retirou uma pequena chave de prata do bolso e a colocou sobre a mesa.</p><p>— Existe uma passagem abaixo deste edifício. Ela leva aos arquivos antigos, construídos antes da fundação do reino. Você vai descer por ela e procurar uma sala chamada Câmara dos Fundadores.</p><p>Kael encarou o velho, incrédulo.</p><p>— Você está falando sério? A cidade pode ser destruída em sete minutos e sua solução é me mandar procurar uma sala escondida?</p><p>— Minha solução é fazer você chegar vivo ao amanhecer.</p><p>Uma nova badalada ecoou.</p><p>Kael olhou para o relógio.</p><p>Onze e cinquenta e dois.</p><p>Cinco minutos.</p><p>— Por que eu? — perguntou. — O que esse livro tem a ver comigo?</p><p>Orven se aproximou. Pela primeira vez, Kael percebeu que as mãos do velho tremiam.</p><p>— Porque algumas histórias começam muito antes de seus protagonistas nascerem. E outras começam antes mesmo de eles existirem.</p><p>A resposta não fez sentido, mas Kael já havia desistido de exigir explicações completas naquela noite.</p><p>Pegou a chave e guardou o livro dentro da bolsa.</p><p>Imediatamente, o ar ficou mais frio.</p><p>— Eu não faria isso — advertiu Orven.</p><p>— Você acabou de me mandar salvar minha própria vida. Pretende que eu deixe o único objeto capaz de explicar tudo aqui?</p><p>O velho abriu a boca para responder, mas um estrondo sacudiu o edifício. Uma rachadura atravessou o teto, derrubando pequenos fragmentos de pedra sobre o chão.</p><p>Do lado de fora, alguém gritava.</p><p>Kael correu até a janela.</p><p>A torre central estava escura.</p><p>Não havia mais luz em suas janelas, nem o brilho mágico que costumava envolver sua estrutura. No topo, uma enorme sombra se espalhava pelo céu, cobrindo as nuvens como tinta derramada sobre papel.</p><p>Então a torre começou a tocar novamente.</p><p>Uma vez.</p><p>Duas vezes.</p><p>Três.</p><p>Orven agarrou o braço de Kael e o puxou para longe da janela.</p><p>— Não olhe diretamente para ela!</p><p>— Por quê?</p><p>— Porque aquilo não é uma sombra.</p><p>O velho o conduziu até uma estante no fundo da biblioteca. Atrás dela, havia uma porta estreita que Kael nunca vira durante seus anos de trabalho.</p><p>Orven inseriu a chave de prata na fechadura.</p><p>A porta se abriu com um gemido metálico, revelando uma escadaria que descia até a escuridão.</p><p>— Siga até o final. Você encontrará três portas. Escolha a que não tiver símbolo algum.</p><p>— E você?</p><p>Orven permaneceu imóvel.</p><p>— Preciso fazer uma coisa antes de ir embora.</p><p>Kael percebeu que o velho estava mentindo, mas não teve tempo de insistir. Um som pesado ecoou no andar superior, seguido por passos que pareciam arrastar alguma coisa pelo chão.</p><p>Passos que não pertenciam a nenhum ser humano.</p><p>Orven empurrou Kael para a escadaria.</p><p>— Vá!</p><p>Kael desceu os primeiros degraus. Antes de fechar a porta, ainda conseguiu ver o bibliotecário retirar um pequeno objeto metálico do bolso e posicioná-lo sobre a mesa.</p><p>Parecia um medalhão.</p><p>No instante seguinte, a porta se fechou.</p><p>A escuridão o envolveu.</p><p>Kael acendeu a pequena lanterna que carregava na bolsa e começou a descer. As paredes eram cobertas por inscrições antigas, algumas tão desgastadas que mal podiam ser reconhecidas. Outras pareciam ter sido gravadas naquela mesma noite.</p><p>Uma frase chamou sua atenção:</p><p><strong>O mundo não foi criado para ser salvo. Foi criado para ser repetido.</strong></p><p>Ele continuou descendo.</p><p>Após alguns minutos, chegou a um corredor subterrâneo. Três portas de pedra estavam alinhadas diante dele.</p><p>A primeira exibia o símbolo de um sol.</p><p>A segunda, o de uma lua.</p><p>A terceira não possuía absolutamente nada.</p><p>Kael se aproximou da última porta e tentou girar a maçaneta. Ela cedeu imediatamente.</p><p>Do outro lado havia uma sala circular, iluminada por centenas de pequenos cristais azuis. No centro, uma mesa de pedra sustentava um objeto coberto por um tecido branco.</p><p>Sobre a parede oposta, sete nomes estavam gravados.</p><p>Kael reconheceu seis deles.</p><p>Eram os nomes dos primeiros reis de Eldoria, figuras históricas que haviam fundado o reino séculos antes.</p><p>O sétimo nome, porém, não aparecia em nenhum livro que ele já tivesse lido.</p><p><strong>Kael.</strong></p><p>Ele recuou um passo.</p><p>Abaixo dos nomes havia uma inscrição adicional:</p><p><em>Quando o sétimo retornar, os seis deverão responder pelo que fizeram.</em></p><p>O livro negro começou a vibrar dentro de sua bolsa.</p><p>Kael o retirou lentamente.</p><p>As páginas se abriram sozinhas, revelando uma mensagem diferente daquela que havia lido na biblioteca.</p><p><strong>Registro número 2.</strong></p><p><strong>A cidade não será destruída à meia-noite.</strong></p><p>Por um instante, Kael sentiu um alívio tão intenso que quase deixou o livro cair.</p><p>Então uma segunda frase apareceu.</p><p><strong>A cidade já foi destruída. Você apenas ainda não se lembra.</strong></p><p>Os cristais azuis se apagaram simultaneamente.</p><p>No centro da sala, o tecido branco deslizou sozinho da mesa de pedra.</p><p>Abaixo dele havia um espelho antigo, cuja superfície não refletia o rosto de Kael.</p><p>Refletia uma cidade em ruínas.</p><p>Uma cidade que ele reconheceu imediatamente.</p><p>Arvend.</p><p>No reflexo, o céu estava completamente negro. As torres haviam desabado, as ruas estavam vazias e uma figura permanecia de pé diante dos portões destruídos.</p><p>A figura ergueu a cabeça.</p><p>Tinha o rosto de Kael.</p><p>Mas seus olhos eram inteiramente brancos.</p><p>O reflexo sorriu.</p><p>— Você demorou muito desta vez.</p><p>Atrás de Kael, a porta de pedra se fechou.</p><p>E, em algum lugar acima dele, o último sino da torre central tocou à meia-noite.</p>','eb33014a8f88205ff842ad96069c4660211f8b1c0e0db050f569c01403cda22b',2,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-04 17:26:33','2026-11-04 16:55:08','2026-11-04 17:26:33'),(20,16,'Sete Minutos Até a Meia-Noite','<p data-pm-slice=\"1 1 []\">Kael fechou o livro com tanta força que a poeira da mesa se ergueu em uma pequena nuvem.</p><p>Por alguns segundos, nenhum dos dois homens disse uma palavra.</p><p>Do lado de fora, os sinos continuavam tocando. Cada badalada parecia mais lenta que a anterior, como se o próprio tempo estivesse perdendo o ritmo. A luz avermelhada que cobria a cidade atravessava as janelas da biblioteca, tingindo as estantes de um tom semelhante ao de brasas quase apagadas.</p><p>— Mestre Orven, o que está acontecendo?</p><p>O velho não respondeu imediatamente. Seus olhos permaneciam fixos no livro negro, como se esperasse que ele se abrisse sozinho.</p><p>— Quantas páginas você leu?</p><p>— Apenas a primeira.</p><p>— E seu nome?</p><p>Kael hesitou.</p><p>— Apareceu na página.</p><p>Orven fechou os olhos por um instante.</p><p>— Então não temos muito tempo.</p><p>Ele atravessou o corredor e trancou a porta principal da biblioteca. Em seguida, retirou uma pequena chave de prata do bolso e a colocou sobre a mesa.</p><p>— Existe uma passagem abaixo deste edifício. Ela leva aos arquivos antigos, construídos antes da fundação do reino. Você vai descer por ela e procurar uma sala chamada Câmara dos Fundadores.</p><p>Kael encarou o velho, incrédulo.</p><p>— Você está falando sério? A cidade pode ser destruída em sete minutos e sua solução é me mandar procurar uma sala escondida?</p><p>— Minha solução é fazer você chegar vivo ao amanhecer.</p><p>Uma nova badalada ecoou.</p><p>Kael olhou para o relógio.</p><p>Onze e cinquenta e dois.</p><p>Cinco minutos.</p><p>— Por que eu? — perguntou. — O que esse livro tem a ver comigo?</p><p>Orven se aproximou. Pela primeira vez, Kael percebeu que as mãos do velho tremiam.</p><p>— Porque algumas histórias começam muito antes de seus protagonistas nascerem. E outras começam antes mesmo de eles existirem.</p><p>A resposta não fez sentido, mas Kael já havia desistido de exigir explicações completas naquela noite.</p><p>Pegou a chave e guardou o livro dentro da bolsa.</p><p>Imediatamente, o ar ficou mais frio.</p><p>— Eu não faria isso — advertiu Orven.</p><p>— Você acabou de me mandar salvar minha própria vida. Pretende que eu deixe o único objeto capaz de explicar tudo aqui?</p><p>O velho abriu a boca para responder, mas um estrondo sacudiu o edifício. Uma rachadura atravessou o teto, derrubando pequenos fragmentos de pedra sobre o chão.</p><p>Do lado de fora, alguém gritava.</p><p>Kael correu até a janela.</p><p>A torre central estava escura.</p><p>Não havia mais luz em suas janelas, nem o brilho mágico que costumava envolver sua estrutura. No topo, uma enorme sombra se espalhava pelo céu, cobrindo as nuvens como tinta derramada sobre papel.</p><p>Então a torre começou a tocar novamente.</p><p>Uma vez.</p><p>Duas vezes.</p><p>Três.</p><p>Orven agarrou o braço de Kael e o puxou para longe da janela.</p><p>— Não olhe diretamente para ela!</p><p>— Por quê?</p><p>— Porque aquilo não é uma sombra.</p><p>O velho o conduziu até uma estante no fundo da biblioteca. Atrás dela, havia uma porta estreita que Kael nunca vira durante seus anos de trabalho.</p><p>Orven inseriu a chave de prata na fechadura.</p><p>A porta se abriu com um gemido metálico, revelando uma escadaria que descia até a escuridão.</p><p>— Siga até o final. Você encontrará três portas. Escolha a que não tiver símbolo algum.</p><p>— E você?</p><p>Orven permaneceu imóvel.</p><p>— Preciso fazer uma coisa antes de ir embora.</p><p>Kael percebeu que o velho estava mentindo, mas não teve tempo de insistir. Um som pesado ecoou no andar superior, seguido por passos que pareciam arrastar alguma coisa pelo chão.</p><p>Passos que não pertenciam a nenhum ser humano.</p><p>Orven empurrou Kael para a escadaria.</p><p>— Vá!</p><p>Kael desceu os primeiros degraus. Antes de fechar a porta, ainda conseguiu ver o bibliotecário retirar um pequeno objeto metálico do bolso e posicioná-lo sobre a mesa.</p><p>Parecia um medalhão.</p><p>No instante seguinte, a porta se fechou.</p><p>A escuridão o envolveu.</p><p>Kael acendeu a pequena lanterna que carregava na bolsa e começou a descer. As paredes eram cobertas por inscrições antigas, algumas tão desgastadas que mal podiam ser reconhecidas. Outras pareciam ter sido gravadas naquela mesma noite.</p><p>Uma frase chamou sua atenção:</p><p><strong>O mundo não foi criado para ser salvo. Foi criado para ser repetido.</strong></p><p>Ele continuou descendo.</p><p>Após alguns minutos, chegou a um corredor subterrâneo. Três portas de pedra estavam alinhadas diante dele.</p><p>A primeira exibia o símbolo de um sol.</p><p>A segunda, o de uma lua.</p><p>A terceira não possuía absolutamente nada.</p><p>Kael se aproximou da última porta e tentou girar a maçaneta. Ela cedeu imediatamente.</p><p>Do outro lado havia uma sala circular, iluminada por centenas de pequenos cristais azuis. No centro, uma mesa de pedra sustentava um objeto coberto por um tecido branco.</p><p>Sobre a parede oposta, sete nomes estavam gravados.</p><p>Kael reconheceu seis deles.</p><p>Eram os nomes dos primeiros reis de Eldoria, figuras históricas que haviam fundado o reino séculos antes.</p><p>O sétimo nome, porém, não aparecia em nenhum livro que ele já tivesse lido.</p><p><strong>Kael.</strong></p><p>Ele recuou um passo.</p><p>Abaixo dos nomes havia uma inscrição adicional:</p><p><em>Quando o sétimo retornar, os seis deverão responder pelo que fizeram.</em></p><p>O livro negro começou a vibrar dentro de sua bolsa.</p><p>Kael o retirou lentamente.</p><p>As páginas se abriram sozinhas, revelando uma mensagem diferente daquela que havia lido na biblioteca.</p><p><strong>Registro número 2.</strong></p><p><strong>A cidade não será destruída à meia-noite.</strong></p><p>Por um instante, Kael sentiu um alívio tão intenso que quase deixou o livro cair.</p><p>Então uma segunda frase apareceu.</p><p><strong>A cidade já foi destruída. Você apenas ainda não se lembra.</strong></p><p>Os cristais azuis se apagaram simultaneamente.</p><p>No centro da sala, o tecido branco deslizou sozinho da mesa de pedra.</p><p>Abaixo dele havia um espelho antigo, cuja superfície não refletia o rosto de Kael.</p><p>Refletia uma cidade em ruínas.</p><p>Uma cidade que ele reconheceu imediatamente.</p><p>Arvend.</p><p>No reflexo, o céu estava completamente negro. As torres haviam desabado, as ruas estavam vazias e uma figura permanecia de pé diante dos portões destruídos.</p><p>A figura ergueu a cabeça.</p><p>Tinha o rosto de Kael.</p><p>Mas seus olhos eram inteiramente brancos.</p><p>O reflexo sorriu.</p><p>— Você demorou muito desta vez.</p><p>Atrás de Kael, a porta de pedra se fechou.</p><p>E, em algum lugar acima dele, o último sino da torre central tocou à meia-noite.</p>','eb33014a8f88205ff842ad96069c4660211f8b1c0e0db050f569c01403cda22b',3,'publicado','revisao_humana','aprovada',NULL,NULL,'2026-11-04 17:26:33','2026-11-04 17:02:31','2026-11-04 17:26:33');
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
  `data_criacao_conversa` datetime NOT NULL DEFAULT current_timestamp(),
  `data_ultima_mensagem` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_conversa`),
  UNIQUE KEY `uq_chat_par` (`id_usuario_menor`,`id_usuario_maior`),
  KEY `idx_chat_u_menor` (`id_usuario_menor`),
  KEY `idx_chat_u_maior` (`id_usuario_maior`),
  CONSTRAINT `fk_chat_u_maior` FOREIGN KEY (`id_usuario_maior`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_chat_u_menor` FOREIGN KEY (`id_usuario_menor`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_chat_usuarios_distintos` CHECK (`id_usuario_menor` < `id_usuario_maior`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_conversas`
--

LOCK TABLES `chat_conversas` WRITE;
/*!40000 ALTER TABLE `chat_conversas` DISABLE KEYS */;
INSERT INTO `chat_conversas` VALUES (2,1,40,'2026-10-26 00:13:58','2026-10-26 00:13:58'),(3,1,41,'2026-11-04 16:27:57','2026-11-04 16:28:04');
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
  PRIMARY KEY (`id_mensagem`),
  KEY `idx_chat_msg_conversa` (`id_conversa`,`data_envio_mensagem`),
  KEY `idx_chat_msg_remetente_data` (`id_remetente`,`data_envio_mensagem`),
  KEY `idx_chat_msg_hash` (`hash_conteudo_mensagem`),
  CONSTRAINT `fk_chat_msg_conversa` FOREIGN KEY (`id_conversa`) REFERENCES `chat_conversas` (`id_conversa`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_chat_msg_remetente` FOREIGN KEY (`id_remetente`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_mensagens`
--

LOCK TABLES `chat_mensagens` WRITE;
/*!40000 ALTER TABLE `chat_mensagens` DISABLE KEYS */;
INSERT INTO `chat_mensagens` VALUES (3,3,1,'boa obra','ab02ec5939b6ebc88f4d1d4117fa57b8f4203a64852b01afe7e58fd71af5c98c','ativa',NULL,'2026-11-04 16:28:04');
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compra`
--

LOCK TABLES `compra` WRITE;
/*!40000 ALTER TABLE `compra` DISABLE KEYS */;
INSERT INTO `compra` VALUES (13,1,16,5.50,80.00,4.40,20.00,'concluida','2026-11-04 16:29:04');
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historias`
--

LOCK TABLES `historias` WRITE;
/*!40000 ALTER TABLE `historias` DISABLE KEYS */;
INSERT INTO `historias` VALUES (15,40,'SALVE','',NULL,'Ficção, Literatura Nacional','despublicada',1,'2026-10-26 00:16:02','2026-11-04 17:00:46','direitos_reservados','livre','fanfic_sem_autorizacao','5bd67815cf1b38e897b80f1ba18b33adce37d4af1e8bd04d54e2212c8add4953'),(16,41,'OBSERVÇÕES - FOLIVRO','Durante séculos, o mundo de Eldoria viveu sob a proteção de sete torres mágicas, responsáveis por manter as criaturas das sombras afastadas das cidades humanas. Entretanto, quando a torre da capital se apaga misteriosamente, uma noite interminável começa a avançar sobre o reino.\r\n\r\nKael, um jovem bibliotecário de dezessete anos, descobre um livro escondido entre as páginas de um manuscrito proibido. Suas palavras mudam diariamente, revelando acontecimentos que ainda não ocorreram — incluindo a destruição de sua própria cidade.\r\n\r\nSem poderes especiais ou treinamento para lutar, Kael precisará atravessar terras esquecidas, investigar a origem das torres e descobrir por que seu nome aparece repetidamente nas últimas páginas do livro.\r\n\r\nMas existe um detalhe que ninguém parece disposto a explicar: todas as pessoas que tentaram desvendar o mistério desapareceram antes de completar a jornada.','uploads/capas/capa_41_a6c3d9701c3f80bc.png','Fantasia, Mistério, Ficção','publicada',6,'2026-11-04 16:14:43','2026-11-04 17:26:33','direitos_reservados','16','autoral_original','e12ed122b99dfb2d49391d3ee907ae5ae3d436aa832f760e842e42a92236ea7d'),(17,1,'Crônicas da Biblioteca de Papel','Uma jornada literária acolhedora sobre o poder da leitura e a preservação de memórias em uma biblioteca comunitária.',NULL,'Ficção, Drama, Literatura Nacional','publicada',0,'2026-11-04 16:27:18','2026-11-04 17:00:46','direitos_reservados','livre','autoral_original','1b034658198450de456a46052918bed083d4c7fea5e7093ac9e32cf15c5ba674'),(18,1,'Sombras da Meia-Noite','Suspense psicológico intenso e temas adultos voltados exclusivamente ao público maior de 18 anos.',NULL,'Suspense, Mistério, Drama','publicada',0,'2026-11-04 16:27:18','2026-11-04 17:00:46','direitos_reservados','18','autoral_original','6c0cab5eddfe36c0fedd6d944ad85cea39ca7a6d63960bc54003770689897815');
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historico_moderacao_autor`
--

LOCK TABLES `historico_moderacao_autor` WRITE;
/*!40000 ALTER TABLE `historico_moderacao_autor` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `livraria`
--

LOCK TABLES `livraria` WRITE;
/*!40000 ALTER TABLE `livraria` DISABLE KEYS */;
INSERT INTO `livraria` VALUES (16,1,17,'abandonado','2026-10-22 01:30:06','2026-10-22 01:48:57'),(17,1,15,'lendo','2026-10-22 02:01:44',NULL),(18,1,18,'lendo','2026-10-22 21:46:14','2026-10-22 21:47:09');
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `livraria_digital`
--

LOCK TABLES `livraria_digital` WRITE;
/*!40000 ALTER TABLE `livraria_digital` DISABLE KEYS */;
INSERT INTO `livraria_digital` VALUES (13,1,16,13,'2026-11-04 16:29:04');
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
  `fonte_livro` enum('catalogo','openlibrary') NOT NULL DEFAULT 'catalogo',
  `id_externo_livro` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_livro`),
  UNIQUE KEY `uq_livro_isbn` (`isbn_livro`),
  UNIQUE KEY `uq_livro_externo` (`id_externo_livro`),
  KEY `idx_livro_titulo` (`titulo_livro`),
  KEY `idx_livro_autor` (`autor_livro`),
  KEY `idx_livro_genero` (`genero_livro`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `livro`
--

LOCK TABLES `livro` WRITE;
/*!40000 ALTER TABLE `livro` DISABLE KEYS */;
INSERT INTO `livro` VALUES (15,'Orgulho e Preconceito','Jane Austen',NULL,'Orgulho e Preconceito é um romance de maneiras de 1813 escrito por Jane Austen. O romance segue o desenvolvimento da personagem de Elizabeth Bennet, a protagonista dinâmica do livro que aprende sobre as repercussões de julgamentos precipitados e passa a apreciar a diferença entre bondade superficial e bondade real.\n\nO Sr. Bennet, proprietário da propriedade Longbourn em Hertfordshire, tem cinco filhas, mas sua propriedade está vinculada e só pode ser passada para um herdeiro do sexo masculino. Sua esposa também não tem uma herança, então sua família enfrenta se tornar muito pobre após sua morte. Assim, é imperativo que pelo menos uma das meninas se case bem para apoiar as outras, o que é uma motivação que impulsiona a trama.','https://covers.openlibrary.org/b/id/14348537-M.jpg','Romance, Clássico','livre',351,NULL,'1813-01-01','2026-10-22 00:57:11','openlibrary','OL66554W'),(17,'É Assim que Acaba','Colleen Hoover',NULL,'Lily nem sempre teve uma vida fácil, mas isso nunca a impediu de trabalhar duro pela vida que quer. Ela percorreu um longo caminho desde a pequena cidade onde cresceu - ela se formou na faculdade, mudou-se para Boston e começou seu próprio negócio. E quando ela sente uma faísca com um lindo neurocirurgião chamado Ryle Kincaid, tudo na vida de Lily parece bom demais para ser verdade.\n\nRyle é assertivo, teimoso, talvez até um pouco arrogante. Ele também é sensível, brilhante e tem um fraquinho total por Lily. E a maneira como ele fica de uniforme certamente não dói. Lily não consegue tirá-lo da cabeça. Mas a completa aversão de Ryle aos relacionamentos é perturbadora. Mesmo quando Lily se vê se tornando a exceção à sua regra de \"não namorar\", ela não pode deixar de se perguntar o que o fez assim em primeiro lugar.\n\nÀ medida que as perguntas sobre seu novo relacionamento a dominam, o mesmo acontece com os pensamentos de Atlas Corrigan - seu primeiro amor e uma ligação com o passado que ela deixou para trás. Ele era sua alma gêmea, seu protetor. Quando Atlas reaparece de repente, tudo o que Lily construiu com Ryle é ameaçado.\n\nCom este romance ousado e profundamente pessoal, It Ends With Us é uma história de partir o coração e um conto inesquecível de amor que tem o preço final.','https://covers.openlibrary.org/b/id/10473609-M.jpg','Romance, Drama','18',384,NULL,'2012-01-01','2026-10-22 01:30:06','openlibrary','OL18020194W'),(18,'Alice no País das Maravilhas','Lewis Carroll',NULL,'Alice no País das Maravilhas (geralmente Alice no País das Maravilhas) é um romance infantil inglês de 1865 de Lewis Carroll. Uma jovem chamada Alice cai em um buraco de coelho em um mundo de fantasia de criaturas antropomórficas. É visto como um exemplo do gênero literário sem sentido.\n\nUma das obras mais conhecidas da literatura vitoriana, sua narrativa, estrutura, personagens e imagens tiveram enorme influência na cultura popular e na literatura, especialmente no gênero fantasia.','https://covers.openlibrary.org/b/id/10527843-L.jpg','Fantasia, Aventura','livre',NULL,NULL,'1869-01-01','2026-10-22 21:46:14','openlibrary','OL138052W');
/*!40000 ALTER TABLE `livro` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `progressao_leitura`
--

LOCK TABLES `progressao_leitura` WRITE;
/*!40000 ALTER TABLE `progressao_leitura` DISABLE KEYS */;
INSERT INTO `progressao_leitura` VALUES (16,1,15,68,320,21.25,'2026-10-22 02:01:44'),(18,1,17,0,384,0.00,'2026-10-22 01:30:06'),(20,1,18,15,25,60.00,'2026-10-22 21:47:30');
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
  `data_avaliacao_resenha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_resenha`),
  UNIQUE KEY `uq_resenha_usuario_livro` (`id_usuario`,`id_livro`),
  KEY `idx_resenha_livro` (`id_livro`),
  CONSTRAINT `fk_resenha_livro` FOREIGN KEY (`id_livro`) REFERENCES `livro` (`id_livro`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_resenha_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_resenha_nota` CHECK (`nota_resenha` is null or `nota_resenha` between 1 and 5)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resenha`
--

LOCK TABLES `resenha` WRITE;
/*!40000 ALTER TABLE `resenha` DISABLE KEYS */;
INSERT INTO `resenha` VALUES (13,1,15,5,'Uma obra-prima incontestável','A ironia refinada e a construção de personagens tornam este clássico atemporal.','2026-10-22 01:44:07'),(15,1,18,5,'GOAT ALICE','Lewis Carroll é um genio','2026-10-22 21:48:00');
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
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `termos_aceitos`
--

LOCK TABLES `termos_aceitos` WRITE;
/*!40000 ALTER TABLE `termos_aceitos` DISABLE KEYS */;
INSERT INTO `termos_aceitos` VALUES (22,40,'termos_uso','1.0',NULL,'2026-10-26 00:11:14'),(23,40,'privacidade','1.0',NULL,'2026-10-26 00:11:14'),(24,40,'declaracao_autoria','1.0',15,'2026-10-26 00:16:02'),(25,41,'termos_uso','1.0',NULL,'2026-11-04 16:05:15'),(26,41,'privacidade','1.0',NULL,'2026-11-04 16:05:15'),(27,41,'declaracao_autoria','1.0',16,'2026-11-04 16:14:43'),(28,41,'declaracao_comercializacao','1.0',16,'2026-11-04 16:14:43');
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
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,'Administrador de teste','admin@folivro.local','1990-01-01','$2y$10$oAYBl5moZ8wdZzaUMDhmDuiHooQLqRBtNDrRYfMBpYaPDyaaeNIJ.',NULL,NULL,'admin','ativo','2026-10-16 21:29:57',NULL,0,'avaliacao',0,0,0,NULL,NULL),(40,'Leitor de Teste','leitor@folivro.local',NULL,'$2y$10$s3.HRQrtL3k/2Imbx1eZ7.s3I54bX/gERAafMgCOhHFs30DqrYWRm','Conta de usuário comum (leitor) para testes e demonstração acadêmica do FOLIVRO.',NULL,'autor','ativo','2026-10-26 00:11:14',NULL,1,'avaliacao',0,0,0,NULL,NULL),(41,'natan','pereira2007natan222@gmail.com',NULL,'$2y$10$XSoJRdNqv907.5rSDeA8W.uj2Eww6P4csL57vWWd1hIcTdghboYTW',NULL,NULL,'autor','ativo','2026-11-04 16:05:15',NULL,0,'avaliacao',0,0,0,NULL,NULL);
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

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

-- Dump completed on 2026-11-04 17:26:33
