<?php
declare(strict_types=1);

function folivro_database(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('FOLIVRO_DB_HOST') ?: '127.0.0.1';
    $port = getenv('FOLIVRO_DB_PORT') ?: '3306';
    $name = getenv('FOLIVRO_DB_NAME') ?: 'folivro';
    $user = getenv('FOLIVRO_DB_USER') ?: 'root';
    $pass = getenv('FOLIVRO_DB_PASS') ?: '';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $safeName = str_replace('`', '``', $name);
    $hostsToTry = ($host === '127.0.0.1') ? ['127.0.0.1', 'localhost'] : [$host];
    $lastException = null;

    foreach ($hostsToTry as $tryHost) {
        try {
            try {
                $pdo = new PDO("mysql:host={$tryHost};port={$port};dbname={$safeName};charset=utf8mb4", $user, $pass, $options);
            } catch (PDOException $e) {
                $server = new PDO("mysql:host={$tryHost};port={$port};charset=utf8mb4", $user, $pass, $options);
                $server->exec("CREATE DATABASE IF NOT EXISTS `{$safeName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo = new PDO("mysql:host={$tryHost};port={$port};dbname={$safeName};charset=utf8mb4", $user, $pass, $options);
            }
            $lastException = null;
            break;
        } catch (PDOException $e) {
            $lastException = $e;
        }
    }

    if (!$pdo instanceof PDO) {
        error_log('[FOLIVRO] Falha de conexão com o banco de dados: ' . ($lastException ? $lastException->getMessage() : 'erro desconhecido'));
        throw new RuntimeException('Serviço de banco de dados temporariamente indisponível.');
    }

    try {
        $appliedCount = (int) $pdo->query("SELECT COUNT(*) FROM _folivro_migrations WHERE version IN ('001_initial_schema', '002_openlibrary_catalog', '003_copyright_and_moderation', '004_chat_security_and_copyright', '005_editorial_review_and_chapter_hashes', '006_disable_public_default_admin', '007_portable_demo_seed', '008_seed_normal_test_user', '009_age_classification_and_birthdate', '010_add_genres_to_historias', '011_hybrid_moderation_smart_publishing', '012_seed_current_books_and_works')")->fetchColumn();
        if ($appliedCount === 12) {
            return $pdo;
        }
    } catch (PDOException $e) {
        // Tabela _folivro_migrations ainda não existe; prossegue com a inicialização abaixo.
    }

    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS _folivro_migrations (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $pdo->exec('ALTER TABLE _folivro_migrations MODIFY COLUMN version VARCHAR(80) NOT NULL');
        $pdo->exec("UPDATE _folivro_migrations SET version = '005_editorial_review_and_chapter_hashes' WHERE version = '005_editorial_review_and_chapter'");
        $migration = $pdo->prepare('SELECT 1 FROM _folivro_migrations WHERE version = ?');
        $migration->execute(['001_initial_schema']);
        if (!$migration->fetchColumn()) {
            $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
            if ($sql === false) {
                throw new RuntimeException('Não foi possível ler o esquema do banco de dados.');
            }
            foreach (preg_split('/;\s*(?:\R|$)/', $sql) as $statement) {
                $statement = trim(preg_replace('/^\s*--.*$/m', '', $statement));
                if ($statement === '' || preg_match('/^(CREATE DATABASE|USE folivro)\b/i', $statement)) {
                    continue;
                }
                $pdo->exec($statement);
            }
            $pdo->exec("INSERT INTO _folivro_migrations (version) VALUES ('001_initial_schema')");
        }

        $migration->execute(['002_openlibrary_catalog']);
        if (!$migration->fetchColumn()) {
            $columns = $pdo->query('SHOW COLUMNS FROM livro')->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('fonte_livro', $columns, true)) {
                $pdo->exec("ALTER TABLE livro ADD COLUMN fonte_livro ENUM('catalogo','openlibrary') NOT NULL DEFAULT 'catalogo'");
            }
            if (!in_array('id_externo_livro', $columns, true)) {
                $pdo->exec('ALTER TABLE livro ADD COLUMN id_externo_livro VARCHAR(50) NULL');
            }
            $index = $pdo->query("SHOW INDEX FROM livro WHERE Key_name = 'uq_livro_externo'")->fetch();
            if (!$index) {
                $pdo->exec('ALTER TABLE livro ADD UNIQUE KEY uq_livro_externo (id_externo_livro)');
            }
            $pdo->exec("INSERT INTO _folivro_migrations (version) VALUES ('002_openlibrary_catalog')");
        }

        $migration->execute(['003_copyright_and_moderation']);
        if (!$migration->fetchColumn()) {
            $histCols = $pdo->query('SHOW COLUMNS FROM historias')->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('licenca_historias', $histCols, true)) {
                $pdo->exec("ALTER TABLE historias ADD COLUMN licenca_historias VARCHAR(60) NOT NULL DEFAULT 'direitos_reservados'");
            }
            if (!in_array('aviso_conteudo_historias', $histCols, true)) {
                $pdo->exec("ALTER TABLE historias ADD COLUMN aviso_conteudo_historias VARCHAR(80) NOT NULL DEFAULT 'livre'");
            }
            if (!in_array('origem_obra_historias', $histCols, true)) {
                $pdo->exec("ALTER TABLE historias ADD COLUMN origem_obra_historias VARCHAR(60) NOT NULL DEFAULT 'autoral_original'");
            }
            if (!in_array('hash_conteudo_historias', $histCols, true)) {
                $pdo->exec('ALTER TABLE historias ADD COLUMN hash_conteudo_historias CHAR(64) NULL, ADD KEY idx_historias_hash (hash_conteudo_historias)');
            }

            $adlCols = $pdo->query('SHOW COLUMNS FROM autor_dos_livros')->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('modalidade_distribuicao', $adlCols, true)) {
                $pdo->exec("ALTER TABLE autor_dos_livros ADD COLUMN modalidade_distribuicao ENUM('gratuita','venda_simulada') NOT NULL DEFAULT 'venda_simulada'");
            }
            if (!in_array('declaracao_comercializacao', $adlCols, true)) {
                $pdo->exec('ALTER TABLE autor_dos_livros ADD COLUMN declaracao_comercializacao TINYINT(1) NOT NULL DEFAULT 0');
            }
            if (!in_array('data_declaracao_comercializacao', $adlCols, true)) {
                $pdo->exec('ALTER TABLE autor_dos_livros ADD COLUMN data_declaracao_comercializacao DATETIME NULL');
            }
            if (!in_array('motivo_bloqueio_autor', $adlCols, true)) {
                $pdo->exec('ALTER TABLE autor_dos_livros ADD COLUMN motivo_bloqueio_autor VARCHAR(255) NULL');
            }

            $denCols = $pdo->query('SHOW COLUMNS FROM denuncias')->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('obra_referencia_denuncia', $denCols, true)) {
                $pdo->exec('ALTER TABLE denuncias ADD COLUMN obra_referencia_denuncia VARCHAR(255) NULL');
            }
            if (!in_array('prioridade_denuncia', $denCols, true)) {
                $pdo->exec("ALTER TABLE denuncias ADD COLUMN prioridade_denuncia ENUM('baixa','media','alta') NOT NULL DEFAULT 'media'");
            }
            if (!in_array('suspensao_preventiva', $denCols, true)) {
                $pdo->exec('ALTER TABLE denuncias ADD COLUMN suspensao_preventiva TINYINT(1) NOT NULL DEFAULT 0');
            }
            if (!in_array('justificativa_moderacao', $denCols, true)) {
                $pdo->exec('ALTER TABLE denuncias ADD COLUMN justificativa_moderacao TEXT NULL');
            }
            if (!in_array('contestacao_autor', $denCols, true)) {
                $pdo->exec('ALTER TABLE denuncias ADD COLUMN contestacao_autor TEXT NULL');
            }
            if (!in_array('data_contestacao', $denCols, true)) {
                $pdo->exec('ALTER TABLE denuncias ADD COLUMN data_contestacao DATETIME NULL');
            }

            $pdo->exec("ALTER TABLE termos_aceitos MODIFY COLUMN tipo_termos ENUM('termos_uso','privacidade','declaracao_autoria','declaracao_comercializacao') NOT NULL");
            $pdo->exec("INSERT INTO _folivro_migrations (version) VALUES ('003_copyright_and_moderation')");
        }

        $migration->execute(['004_chat_security_and_copyright']);
        if (!$migration->fetchColumn()) {
            $uCols = $pdo->query('SHOW COLUMNS FROM usuario')->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('restricao_chat_ate', $uCols, true)) {
                $pdo->exec('ALTER TABLE usuario ADD COLUMN restricao_chat_ate DATETIME NULL');
            }
            if (!in_array('avisos_moderacao_usuario', $uCols, true)) {
                $pdo->exec('ALTER TABLE usuario ADD COLUMN avisos_moderacao_usuario INT UNSIGNED NOT NULL DEFAULT 0');
            }

            $denCols = $pdo->query('SHOW COLUMNS FROM denuncias')->fetchAll(PDO::FETCH_COLUMN, 0);
            $pdo->exec("ALTER TABLE denuncias MODIFY COLUMN tipo_denuncias ENUM('historia','comentario','usuario','obra_autoral','mensagem_chat','conversa_chat') NOT NULL");
            if (!in_array('acao_moderacao_aplicada', $denCols, true)) {
                $pdo->exec('ALTER TABLE denuncias ADD COLUMN acao_moderacao_aplicada VARCHAR(60) NULL');
            }
            if (!in_array('retencao_evidencia_ate', $denCols, true)) {
                $pdo->exec('ALTER TABLE denuncias ADD COLUMN retencao_evidencia_ate DATETIME NULL');
            }

            // CHECKs em colunas de FK removidos (erro 3823 no MySQL 8).
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS chat_conversas (
                  id_conversa           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  id_usuario_menor      INT UNSIGNED NOT NULL,
                  id_usuario_maior      INT UNSIGNED NOT NULL,
                  data_criacao_conversa DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  data_ultima_mensagem  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (id_conversa),
                  UNIQUE KEY uq_chat_par (id_usuario_menor, id_usuario_maior),
                  KEY idx_chat_u_menor (id_usuario_menor),
                  KEY idx_chat_u_maior (id_usuario_maior),
                  CONSTRAINT fk_chat_u_menor FOREIGN KEY (id_usuario_menor) REFERENCES usuario (id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
                  CONSTRAINT fk_chat_u_maior FOREIGN KEY (id_usuario_maior) REFERENCES usuario (id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS chat_mensagens (
                  id_mensagem             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  id_conversa             INT UNSIGNED NOT NULL,
                  id_remetente            INT UNSIGNED NOT NULL,
                  conteudo_mensagem       TEXT NOT NULL,
                  hash_conteudo_mensagem  CHAR(64) NOT NULL,
                  status_mensagem         ENUM('ativa','apagada_pelo_autor','oculta_preventivamente','removida_moderacao') NOT NULL DEFAULT 'ativa',
                  motivo_ocultacao        VARCHAR(255) NULL,
                  data_envio_mensagem     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (id_mensagem),
                  KEY idx_chat_msg_conversa (id_conversa, data_envio_mensagem),
                  KEY idx_chat_msg_remetente_data (id_remetente, data_envio_mensagem),
                  KEY idx_chat_msg_hash (hash_conteudo_mensagem),
                  CONSTRAINT fk_chat_msg_conversa FOREIGN KEY (id_conversa) REFERENCES chat_conversas (id_conversa) ON DELETE CASCADE ON UPDATE CASCADE,
                  CONSTRAINT fk_chat_msg_remetente FOREIGN KEY (id_remetente) REFERENCES usuario (id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS bloqueios_usuario (
                  id_bloqueio       INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  id_bloqueador     INT UNSIGNED NOT NULL,
                  id_bloqueado      INT UNSIGNED NOT NULL,
                  motivo_bloqueio   VARCHAR(150) NULL,
                  data_bloqueio     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (id_bloqueio),
                  UNIQUE KEY uq_bloqueio_par (id_bloqueador, id_bloqueado),
                  KEY idx_bloqueado (id_bloqueado),
                  CONSTRAINT fk_bloqueio_bloqueador FOREIGN KEY (id_bloqueador) REFERENCES usuario (id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
                  CONSTRAINT fk_bloqueio_bloqueado FOREIGN KEY (id_bloqueado) REFERENCES usuario (id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB"
            );

            $pdo->exec("INSERT INTO _folivro_migrations (version) VALUES ('004_chat_security_and_copyright')");
        }

        $migration->execute(['005_editorial_review_and_chapter_hashes']);
        if (!$migration->fetchColumn()) {
            $capCols = $pdo->query('SHOW COLUMNS FROM capitulos')->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('hash_conteudo_capitulos', $capCols, true)) {
                $pdo->exec('ALTER TABLE capitulos ADD COLUMN hash_conteudo_capitulos CHAR(64) NULL AFTER conteudo_capitulos, ADD KEY idx_capitulos_hash (hash_conteudo_capitulos)');
            }

            // Preenche hashes de capítulos existentes para impedir contorno de remoção.
            $existingCaps = $pdo->query('SELECT id_capitulos, conteudo_capitulos FROM capitulos WHERE hash_conteudo_capitulos IS NULL')->fetchAll();
            if ($existingCaps) {
                $updCapHash = $pdo->prepare('UPDATE capitulos SET hash_conteudo_capitulos = ? WHERE id_capitulos = ?');
                foreach ($existingCaps as $capRow) {
                    $norm = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $capRow['conteudo_capitulos'])), 'UTF-8');
                    $updCapHash->execute([hash('sha256', $norm), (int) $capRow['id_capitulos']]);
                }
            }

            $adlCols = $pdo->query('SHOW COLUMNS FROM autor_dos_livros')->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('revisao_publicacao', $adlCols, true)) {
                $pdo->exec("ALTER TABLE autor_dos_livros ADD COLUMN revisao_publicacao ENUM('nao_solicitada','pendente','aprovada','recusada') NOT NULL DEFAULT 'nao_solicitada' AFTER data_declaracao_comercializacao, ADD KEY idx_adl_revisao (revisao_publicacao)");
            }
            if (!in_array('justificativa_revisao', $adlCols, true)) {
                $pdo->exec('ALTER TABLE autor_dos_livros ADD COLUMN justificativa_revisao TEXT NULL AFTER revisao_publicacao');
            }
            if (!in_array('data_revisao_publicacao', $adlCols, true)) {
                $pdo->exec('ALTER TABLE autor_dos_livros ADD COLUMN data_revisao_publicacao DATETIME NULL AFTER justificativa_revisao');
            }
            if (!in_array('id_moderador_revisao', $adlCols, true)) {
                $pdo->exec('ALTER TABLE autor_dos_livros ADD COLUMN id_moderador_revisao INT UNSIGNED NULL AFTER data_revisao_publicacao');
            }

            // Obras que estavam publicadas sem revisão humana prévia passam para revisão pendente e ficam retidas até decisão humana.
            $pdo->exec(
                "UPDATE autor_dos_livros a
                   JOIN historias h ON h.id_historias = a.id_historias
                    SET a.status_autor_dos_livros = 'rascunho',
                        h.status_historias = 'rascunho',
                        a.revisao_publicacao = CASE
                            WHEN h.origem_obra_historias IN ('fanfic_sem_autorizacao', 'dominio_publico_nao_verificado') THEN 'recusada'
                            ELSE 'pendente'
                        END,
                        a.motivo_bloqueio_autor = COALESCE(a.motivo_bloqueio_autor, 'Aguardando revisão humana da moderação antes da disponibilização pública.')
                  WHERE a.status_autor_dos_livros = 'a_venda' AND a.revisao_publicacao = 'nao_solicitada'"
            );

            // Remove URLs de capas externas sem licença verificada importadas anteriormente da Open Library.
            $pdo->exec("UPDATE livro SET capa_livro = NULL WHERE fonte_livro = 'openlibrary' OR capa_livro LIKE 'http%'");

            $pdo->exec("INSERT INTO _folivro_migrations (version) VALUES ('005_editorial_review_and_chapter_hashes')");
        }

        $migration->execute(['006_disable_public_default_admin']);
        if (!$migration->fetchColumn()) {
            $pdo->exec("INSERT INTO _folivro_migrations (version) VALUES ('006_disable_public_default_admin')");
        }

        $migration->execute(['007_portable_demo_seed']);
        if (!$migration->fetchColumn()) {
            $adminEmail = trim((string) (getenv('FOLIVRO_ADMIN_EMAIL') ?: 'admin@folivro.local'));
            $adminPassword = (string) (getenv('FOLIVRO_ADMIN_PASSWORD') ?: 'Admin123!');

            $findAdmin = $pdo->prepare('SELECT id_usuario FROM usuario WHERE email_usuario = ? LIMIT 1');
            $findAdmin->execute([$adminEmail]);
            $adminId = (int) $findAdmin->fetchColumn();

            if ($adminId <= 0) {
                $createAdmin = $pdo->prepare(
                    "INSERT INTO usuario (nome_usuario, email_usuario, senha_usuario, nivel_usuario, status_usuario)
                     VALUES (?, ?, ?, 'admin', 'ativo')"
                );
                $createAdmin->execute(['Administrador de teste', $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);
                $adminId = (int) $pdo->lastInsertId();
            } else {
                $pdo->prepare("UPDATE usuario SET nivel_usuario = 'admin', status_usuario = 'ativo' WHERE id_usuario = ?")
                    ->execute([$adminId]);
            }

            // Semeia os livros, progressos, resenhas e pontos iniciais caso o banco recém-criado em outro PC esteja vazio
            $totalLivros = (int) $pdo->query('SELECT COUNT(*) FROM livro')->fetchColumn();
            if ($totalLivros === 0 && $adminId > 0) {
                $insLivro = $pdo->prepare(
                    "INSERT INTO livro (titulo_livro, autor_livro, isbn_livro, sinopse_livro, capa_livro, genero_livro, paginas_livro, editora_livro, data_publicacao_livro, fonte_livro, id_externo_livro)
                     VALUES (?, ?, NULL, ?, ?, NULL, ?, NULL, ?, 'openlibrary', ?)"
                );

                $insLivro->execute([
                    'Orgulho e Preconceito',
                    'Jane Austen',
                    "Orgulho e Preconceito é um romance de maneiras de 1813 escrito por Jane Austen. O romance segue o desenvolvimento da personagem de Elizabeth Bennet, a protagonista dinâmica do livro que aprende sobre as repercussões de julgamentos precipitados e passa a apreciar a diferença entre bondade superficial e bondade real.\n\nO Sr. Bennet, proprietário da propriedade Longbourn em Hertfordshire, tem cinco filhas, mas sua propriedade está vinculada e só pode ser passada para um herdeiro do sexo masculino. Sua esposa também não tem uma herança, então sua família enfrenta se tornar muito pobre após sua morte. Assim, é imperativo que pelo menos uma das meninas se case bem para apoiar as outras, o que é uma motivação que impulsiona a trama.",
                    'https://covers.openlibrary.org/b/id/14348537-M.jpg',
                    351,
                    '1813-01-01',
                    'OL66554W',
                ]);
                $idLivro1 = (int) $pdo->lastInsertId();

                $insLivro->execute([
                    'É Assim que Acaba',
                    'Colleen Hoover',
                    "Lily nem sempre teve uma vida fácil, mas isso nunca a impediu de trabalhar duro pela vida que quer. Ela percorreu um longo caminho desde a pequena cidade onde cresceu - ela se formou na faculdade, mudou-se para Boston e começou seu próprio negócio. E quando ela sente uma faísca com um lindo neurocirurgião chamado Ryle Kincaid, tudo na vida de Lily parece bom demais para ser verdade.\n\nRyle é assertivo, teimoso, talvez até um pouco arrogante. Ele também é sensível, brilhante e tem um fraquinho total por Lily. E a maneira como ele fica de uniforme certamente não dói. Lily não consegue tirá-lo da cabeça. Mas a completa aversão de Ryle aos relacionamentos é perturbadora. Mesmo quando Lily se vê se tornando a exceção à sua regra de \"não namorar\", ela não pode deixar de se perguntar o que o fez assim em primeiro lugar.\n\nÀ medida que as perguntas sobre seu novo relacionamento a dominam, o mesmo acontece com os pensamentos de Atlas Corrigan - seu primeiro amor e uma ligação com o passado que ela deixou para trás. Ele era sua alma gêmea, seu protetor. Quando Atlas reaparece de repente, tudo o que Lily construiu com Ryle é ameaçado.\n\nCom este romance ousado e profundamente pessoal, It Ends With Us é uma história de partir o coração e um conto inesquecível de amor que tem o preço final.",
                    'https://covers.openlibrary.org/b/id/10473609-M.jpg',
                    384,
                    '2012-01-01',
                    'OL18020194W',
                ]);
                $idLivro2 = (int) $pdo->lastInsertId();

                $insLivro->execute([
                    'Alice no País das Maravilhas',
                    'Lewis Carroll',
                    "Alice no País das Maravilhas (geralmente Alice no País das Maravilhas) é um romance infantil inglês de 1865 de Lewis Carroll. Uma jovem chamada Alice cai em um buraco de coelho em um mundo de fantasia de criaturas antropomórficas. É visto como um exemplo do gênero literário sem sentido.\n\nUma das obras mais conhecidas da literatura vitoriana, sua narrativa, estrutura, personagens e imagens tiveram enorme influência na cultura popular e na literatura, especialmente no gênero fantasia.",
                    'https://covers.openlibrary.org/b/id/10527843-L.jpg',
                    null,
                    '1869-01-01',
                    'OL138052W',
                ]);
                $idLivro3 = (int) $pdo->lastInsertId();

                $insLivraria = $pdo->prepare('INSERT IGNORE INTO livraria (id_usuario, id_livro, status_livraria) VALUES (?, ?, ?)');
                $insLivraria->execute([$adminId, $idLivro2, 'abandonado']);
                $insLivraria->execute([$adminId, $idLivro1, 'lendo']);
                $insLivraria->execute([$adminId, $idLivro3, 'lendo']);

                $insProg = $pdo->prepare('INSERT IGNORE INTO progressao_leitura (id_usuario, id_livro, pagina_atual_progressao, total_paginas_progressao, porcentagem_progressao) VALUES (?, ?, ?, ?, ?)');
                $insProg->execute([$adminId, $idLivro1, 68, 320, 21.25]);
                $insProg->execute([$adminId, $idLivro2, 0, 384, 0.00]);
                $insProg->execute([$adminId, $idLivro3, 15, 25, 60.00]);

                $insResenha = $pdo->prepare('INSERT IGNORE INTO resenha (id_usuario, id_livro, nota_resenha, titulo_resenha, resenha_resenha) VALUES (?, ?, ?, ?, ?)');
                $insResenha->execute([$adminId, $idLivro1, 5, 'Uma obra-prima incontestável', 'A ironia refinada e a construção de personagens tornam este clássico atemporal.']);
                $insResenha->execute([$adminId, $idLivro3, 5, 'GOAT ALICE', 'Lewis Carroll é um genio']);

                $insPontos = $pdo->prepare('INSERT IGNORE INTO contador_pontos (id_usuario, pontos_contador_de_pontos) VALUES (?, ?)');
                $insPontos->execute([$adminId, 83]);
            }

            $pdo->exec("INSERT INTO _folivro_migrations (version) VALUES ('007_portable_demo_seed')");
        }

        $migration->execute(['008_seed_normal_test_user']);
        if (!$migration->fetchColumn()) {
            $leitorEmail = 'leitor@folivro.local';
            $leitorPassword = 'Leitor123!';

            $findLeitor = $pdo->prepare('SELECT id_usuario FROM usuario WHERE email_usuario = ? LIMIT 1');
            $findLeitor->execute([$leitorEmail]);
            $leitorId = (int) $findLeitor->fetchColumn();

            if ($leitorId <= 0) {
                $createLeitor = $pdo->prepare(
                    "INSERT INTO usuario (nome_usuario, email_usuario, senha_usuario, biografia_usuario, nivel_usuario, status_usuario)
                     VALUES (?, ?, ?, ?, 'leitor', 'ativo')"
                );
                $createLeitor->execute([
                    'Leitor de Teste',
                    $leitorEmail,
                    password_hash($leitorPassword, PASSWORD_DEFAULT),
                    'Conta de usuário comum (leitor) para testes e demonstração acadêmica do FOLIVRO.',
                ]);
                $leitorId = (int) $pdo->lastInsertId();
            } else {
                $pdo->prepare("UPDATE usuario SET nivel_usuario = 'leitor', status_usuario = 'ativo' WHERE id_usuario = ?")
                    ->execute([$leitorId]);
            }

            if ($leitorId > 0) {
                $pdo->prepare('INSERT IGNORE INTO contador_pontos (id_usuario, pontos_contador_de_pontos) VALUES (?, 25)')
                    ->execute([$leitorId]);

                $aceiteTermos = $pdo->prepare('INSERT INTO termos_aceitos (id_usuario, tipo_termos, versao_termos) VALUES (?, ?, ?)');
                $chkTermos = $pdo->prepare('SELECT COUNT(*) FROM termos_aceitos WHERE id_usuario = ?');
                $chkTermos->execute([$leitorId]);
                if ((int) $chkTermos->fetchColumn() === 0) {
                    $aceiteTermos->execute([$leitorId, 'termos_uso', '1.0']);
                    $aceiteTermos->execute([$leitorId, 'privacidade', '1.0']);
                }
            }

            $pdo->exec("INSERT INTO _folivro_migrations (version) VALUES ('008_seed_normal_test_user')");
        }

        $migration->execute(['009_age_classification_and_birthdate']);
        if (!$migration->fetchColumn()) {
            $uCols = $pdo->query('SHOW COLUMNS FROM usuario')->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('data_nascimento_usuario', $uCols, true)) {
                $pdo->exec('ALTER TABLE usuario ADD COLUMN data_nascimento_usuario DATE NULL DEFAULT NULL AFTER email_usuario');
            }

            $lCols = $pdo->query('SHOW COLUMNS FROM livro')->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('classificacao_livro', $lCols, true)) {
                $pdo->exec("ALTER TABLE livro ADD COLUMN classificacao_livro VARCHAR(20) NOT NULL DEFAULT 'livre' AFTER genero_livro");
            }

            // Converte avisos antigos para a Classificação Indicativa de idade padrão
            $pdo->exec("UPDATE historias SET aviso_conteudo_historias = '12' WHERE aviso_conteudo_historias = 'temas_sensiveis'");
            $pdo->exec("UPDATE historias SET aviso_conteudo_historias = '16' WHERE aviso_conteudo_historias = 'violencia_moderada'");
            $pdo->exec("UPDATE historias SET aviso_conteudo_historias = '18' WHERE aviso_conteudo_historias = 'linguagem_impropria'");

            // Define data de nascimento maior de 18 anos para o administrador padrão
            $pdo->exec("UPDATE usuario SET data_nascimento_usuario = '1990-01-01' WHERE email_usuario = 'admin@folivro.local' AND data_nascimento_usuario IS NULL");

            // Marca obra adulta no catálogo local para demonstração de classificação indicativa +18
            $pdo->exec("UPDATE livro SET classificacao_livro = '18' WHERE id_externo_livro = 'OL18020194W' OR titulo_livro LIKE '%Assim que Acaba%'");

            // Semeia obras demonstrativas na loja (uma Livre e uma +18) caso ainda não haja obra +18 publicada
            $chkObra18 = (int) $pdo->query(
                "SELECT COUNT(*) FROM autor_dos_livros a
                   JOIN historias h ON h.id_historias = a.id_historias
                  WHERE a.status_autor_dos_livros = 'a_venda'
                    AND h.status_historias = 'publicada'
                    AND a.revisao_publicacao = 'aprovada'
                    AND h.aviso_conteudo_historias = '18'"
            )->fetchColumn();

            $adminIdSeed = (int) $pdo->query("SELECT id_usuario FROM usuario WHERE nivel_usuario = 'admin' ORDER BY id_usuario ASC LIMIT 1")->fetchColumn();
            if ($chkObra18 === 0 && $adminIdSeed > 0) {
                $insHistDemo = $pdo->prepare(
                    "INSERT INTO historias (id_usuario, titulo_historias, sinopse_historias, capa_historias,
                                            licenca_historias, aviso_conteudo_historias, origem_obra_historias,
                                            hash_conteudo_historias, status_historias)
                     VALUES (?, ?, ?, NULL, 'direitos_reservados', ?, 'autoral_original', ?, 'publicada')"
                );
                $insCapDemo = $pdo->prepare(
                    "INSERT INTO capitulos (id_historias, titulo_capitulos, conteudo_capitulos, hash_conteudo_capitulos, ordem_capitulos, status_capitulos)
                     VALUES (?, ?, ?, ?, 1, 'publicado')"
                );
                $insAdlDemo = $pdo->prepare(
                    "INSERT INTO autor_dos_livros (id_historias, id_autor, titulo_autor_dos_livros, preco_dos_livros_autor,
                                                   modalidade_distribuicao, declaracao_autoria, data_declaracao_autoria,
                                                   declaracao_comercializacao, data_declaracao_comercializacao,
                                                   revisao_publicacao, data_revisao_publicacao, id_moderador_revisao,
                                                   status_autor_dos_livros, data_publicacao_autor)
                     VALUES (?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, 1, CURRENT_TIMESTAMP, 'aprovada', CURRENT_TIMESTAMP, ?, 'a_venda', CURRENT_TIMESTAMP)"
                );

                // Obra 1: Livre
                $t1 = 'Crônicas da Biblioteca de Papel';
                $c1 = '<p>Nas galerias silenciosas da antiga biblioteca, cada volume guardava memórias de leitores de diferentes gerações.</p>';
                $insHistDemo->execute([$adminIdSeed, $t1, 'Uma jornada literária acolhedora sobre o poder da leitura e a preservação de memórias em uma biblioteca comunitária.', 'livre', hash('sha256', $t1)]);
                $idH1 = (int) $pdo->lastInsertId();
                $insCapDemo->execute([$idH1, 'O Segredo das Estantes', $c1, hash('sha256', $c1)]);
                $insAdlDemo->execute([$idH1, $adminIdSeed, $t1, 0.00, 'gratuita', $adminIdSeed]);

                // Obra 2: +18 (Para maiores de 18 anos)
                $t2 = 'Sombras da Meia-Noite';
                $c2 = '<p>A chuva cortava os becos escuros da metrópole enquanto o detetive encarava os segredos mais densos e sombrios da noite.</p>';
                $insHistDemo->execute([$adminIdSeed, $t2, 'Suspense psicológico intenso e temas adultos voltados exclusivamente ao público maior de 18 anos.', '18', hash('sha256', $t2)]);
                $idH2 = (int) $pdo->lastInsertId();
                $insCapDemo->execute([$idH2, 'Noite sem Retorno', $c2, hash('sha256', $c2)]);
                $insAdlDemo->execute([$idH2, $adminIdSeed, $t2, 19.90, 'venda_simulada', $adminIdSeed]);
            }

            $pdo->exec("INSERT INTO _folivro_migrations (version) VALUES ('009_age_classification_and_birthdate')");
        }

        // Migração 010: Gêneros literários das obras autorais (historias.generos_historias).
        $mig10 = $pdo->query("SELECT 1 FROM _folivro_migrations WHERE version = '010_add_genres_to_historias' LIMIT 1")->fetchColumn();
        if (!$mig10) {
            $colGenerosHist = $pdo->query("SHOW COLUMNS FROM historias LIKE 'generos_historias'")->fetchColumn();
            if (!$colGenerosHist) {
                $pdo->exec("ALTER TABLE historias ADD COLUMN generos_historias VARCHAR(255) NULL DEFAULT NULL AFTER capa_historias");
            }
            $pdo->exec("UPDATE historias SET generos_historias = 'Ficção, Drama, Literatura Nacional' WHERE titulo_historias = 'Crônicas da Biblioteca de Papel' AND (generos_historias IS NULL OR generos_historias = '')");
            $pdo->exec("UPDATE historias SET generos_historias = 'Suspense, Mistério, Drama' WHERE titulo_historias = 'Sombras da Meia-Noite' AND (generos_historias IS NULL OR generos_historias = '')");
            $pdo->exec("UPDATE historias SET generos_historias = 'Ficção, Literatura Nacional' WHERE generos_historias IS NULL OR TRIM(generos_historias) = ''");

            $pdo->exec("INSERT INTO _folivro_migrations (version) VALUES ('010_add_genres_to_historias')");
        }

        // Migração 011: Sistema de moderação híbrida e publicação inteligente de capítulos.
        $mig11 = $pdo->query("SELECT 1 FROM _folivro_migrations WHERE version = '011_hybrid_moderation_smart_publishing' LIMIT 1")->fetchColumn();
        if (!$mig11) {
            $uCols11 = $pdo->query('SHOW COLUMNS FROM usuario')->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('modalidade_publicacao_usuario', $uCols11, true)) {
                $pdo->exec("ALTER TABLE usuario ADD COLUMN modalidade_publicacao_usuario ENUM('avaliacao','automatica','revisao_obrigatoria','suspensa') NOT NULL DEFAULT 'avaliacao'");
            }
            if (!in_array('capitulos_aprovados_usuario', $uCols11, true)) {
                $pdo->exec("ALTER TABLE usuario ADD COLUMN capitulos_aprovados_usuario INT UNSIGNED NOT NULL DEFAULT 0");
            }
            if (!in_array('violacoes_confirmadas_usuario', $uCols11, true)) {
                $pdo->exec("ALTER TABLE usuario ADD COLUMN violacoes_confirmadas_usuario INT UNSIGNED NOT NULL DEFAULT 0");
            }
            if (!in_array('revisoes_recusadas_usuario', $uCols11, true)) {
                $pdo->exec("ALTER TABLE usuario ADD COLUMN revisoes_recusadas_usuario INT UNSIGNED NOT NULL DEFAULT 0");
            }
            if (!in_array('justificativa_modalidade_usuario', $uCols11, true)) {
                $pdo->exec("ALTER TABLE usuario ADD COLUMN justificativa_modalidade_usuario VARCHAR(255) NULL DEFAULT NULL");
            }
            if (!in_array('data_qualificacao_usuario', $uCols11, true)) {
                $pdo->exec("ALTER TABLE usuario ADD COLUMN data_qualificacao_usuario DATETIME NULL DEFAULT NULL");
            }

            $cCols11 = $pdo->query('SHOW COLUMNS FROM capitulos')->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('modo_publicacao_capitulos', $cCols11, true)) {
                $pdo->exec("ALTER TABLE capitulos ADD COLUMN modo_publicacao_capitulos ENUM('revisao_humana','automatica') NOT NULL DEFAULT 'revisao_humana' AFTER status_capitulos");
            }
            if (!in_array('revisao_capitulos', $cCols11, true)) {
                $pdo->exec("ALTER TABLE capitulos ADD COLUMN revisao_capitulos ENUM('nao_solicitada','pendente','aprovada','publicado_automatico','recusada','oculto_moderacao') NOT NULL DEFAULT 'pendente' AFTER modo_publicacao_capitulos");
            }
            if (!in_array('justificativa_revisao_capitulos', $cCols11, true)) {
                $pdo->exec("ALTER TABLE capitulos ADD COLUMN justificativa_revisao_capitulos TEXT NULL DEFAULT NULL AFTER revisao_capitulos");
            }
            if (!in_array('id_moderador_capitulos', $cCols11, true)) {
                $pdo->exec("ALTER TABLE capitulos ADD COLUMN id_moderador_capitulos INT UNSIGNED NULL DEFAULT NULL AFTER justificativa_revisao_capitulos");
            }
            if (!in_array('data_revisao_capitulos', $cCols11, true)) {
                $pdo->exec("ALTER TABLE capitulos ADD COLUMN data_revisao_capitulos DATETIME NULL DEFAULT NULL AFTER id_moderador_capitulos");
            }

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS historico_moderacao_autor (
                  id_historico_mod    INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  id_autor            INT UNSIGNED NOT NULL,
                  id_moderador        INT UNSIGNED NULL,
                  id_autor_dos_livros INT UNSIGNED NULL,
                  id_capitulos        INT UNSIGNED NULL,
                  tipo_evento         VARCHAR(60) NOT NULL,
                  modalidade_anterior VARCHAR(40) NULL,
                  modalidade_nova     VARCHAR(40) NULL,
                  justificativa       TEXT NOT NULL,
                  data_evento         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (id_historico_mod),
                  KEY idx_hist_mod_autor (id_autor, data_evento),
                  KEY idx_hist_mod_evento (tipo_evento, data_evento),
                  CONSTRAINT fk_hist_mod_autor FOREIGN KEY (id_autor) REFERENCES usuario (id_usuario)
                    ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB"
            );

            // Sincroniza o estado dos capítulos já publicados anteriormente com 'aprovada'.
            $pdo->exec("UPDATE capitulos SET revisao_capitulos = 'aprovada', modo_publicacao_capitulos = 'revisao_humana' WHERE status_capitulos = 'publicado'");

            $pdo->exec("INSERT INTO _folivro_migrations (version) VALUES ('011_hybrid_moderation_smart_publishing')");
        }

        // Migração 012: Garante que todas as obras e livros atuais acompanhem o projeto ao clonar do GitHub.
        $mig12 = $pdo->query("SELECT 1 FROM _folivro_migrations WHERE version = '012_seed_current_books_and_works' LIMIT 1")->fetchColumn();
        if (!$mig12) {
            $adminId12 = (int) $pdo->query("SELECT id_usuario FROM usuario WHERE nivel_usuario = 'admin' ORDER BY id_usuario ASC LIMIT 1")->fetchColumn();

            // Se a obra 'OBSERVÇÕES - FOLIVRO' já existe no banco local, garante que esteja publicada e com os capítulos liberados
            $stmtObs = $pdo->prepare("SELECT id_historias FROM historias WHERE titulo_historias = 'OBSERVÇÕES - FOLIVRO' LIMIT 1");
            $stmtObs->execute();
            $idHistObs = (int) $stmtObs->fetchColumn();

            $sinopseObs = "Durante séculos, o mundo de Eldoria viveu sob a proteção de sete torres mágicas, responsáveis por manter as criaturas das sombras afastadas das cidades humanas. Entretanto, quando a torre da capital se apaga misteriosamente, uma noite interminável começa a avançar sobre o reino.\n\nKael, um jovem bibliotecário de dezessete anos, descobre um livro escondido entre as páginas de um manuscrito proibido. Suas palavras mudam diariamente, revelando acontecimentos que ainda não ocorreram — incluindo a destruição de sua própria cidade.\n\nSem poderes especiais ou treinamento para lutar, Kael precisará atravessar terras esquecidas, investigar a origem das torres e descobrir por que seu nome aparece repetidamente nas últimas páginas do livro.\n\nMas existe um detalhe que ninguém parece disposto a explicar: todas as pessoas que tentaram desvendar o mistério desapareceram antes de completar a jornada.";
            $cap1TextoObs = "<p>A chuva começou pouco antes do anoitecer.</p><p>Kael observava as gotas escorrerem pelas janelas da biblioteca real enquanto organizava os últimos livros devolvidos naquele dia. O lugar estava vazio, exceto por ele e pelo velho bibliotecário, mestre Orven, que insistia em conferir cada registro antes de fechar as portas.</p><p>— Você deveria ir para casa — disse Orven, sem tirar os olhos dos documentos. — Não há nada de interessante aqui depois do pôr do sol.</p><p>Kael sorriu discretamente.</p><p>— É exatamente depois do pôr do sol que as coisas interessantes costumam acontecer.</p><p>Enquanto guardava um volume antigo na última estante, percebeu uma pequena abertura atrás de uma fileira de livros. Ao abrir a caixa, Kael encontrou um livro de capa negra com uma frase escrita em letras prateadas:</p><p><em>Quando a última luz morrer, procure aquele que nunca nasceu.</em></p><p><strong>Registro número 1. Data: 17 de outubro. À meia-noite, a cidade de Arvend será destruída.</strong></p><p><strong>O leitor já foi identificado.</strong> Abaixo dela, em letras ainda úmidas de tinta, estava escrito seu nome. E Kael ouviu uma voz sussurrar diretamente dentro de sua mente: — Finalmente encontramos você.</p>";
            $cap2TextoObs = "<p>Kael fechou o livro com tanta força que a poeira da mesa se ergueu em uma pequena nuvem. Do lado de fora, os sinos continuavam tocando.</p><p>— Existe uma passagem abaixo deste edifício — disse mestre Orven, entregando-lhe uma chave de prata. — Ela leva aos arquivos antigos, construídos antes da fundação do reino. Procure a Câmara dos Fundadores e escolha a porta que não tiver símbolo algum.</p><p>Após descer a escadaria, Kael abriu a terceira porta de pedra e encontrou uma sala circular iluminada por cristais azuis. Sobre a parede estavam gravados os nomes dos seis primeiros reis de Eldoria — e um sétimo nome: <strong>Kael.</strong></p><p><em>Quando o sétimo retornar, os seis deverão responder pelo que fizeram.</em></p><p>No centro da sala, um espelho antigo refletia Arvend em ruínas e uma figura de olhos inteiramente brancos com o rosto de Kael, que sorriu: — Você demorou muito desta vez.</p>";

            if ($idHistObs <= 0 && $adminId12 > 0) {
                $insH12 = $pdo->prepare(
                    "INSERT INTO historias (id_usuario, titulo_historias, sinopse_historias, capa_historias, generos_historias, licenca_historias, aviso_conteudo_historias, origem_obra_historias, hash_conteudo_historias, status_historias)
                     VALUES (?, 'OBSERVÇÕES - FOLIVRO', ?, 'uploads/capas/capa_41_a6c3d9701c3f80bc.png', 'Fantasia, Mistério, Ficção', 'direitos_reservados', '16', 'autoral_original', ?, 'publicada')"
                );
                $insH12->execute([$adminId12, $sinopseObs, hash('sha256', 'OBSERVÇÕES - FOLIVRO')]);
                $idHistObs = (int) $pdo->lastInsertId();

                $insC12 = $pdo->prepare(
                    "INSERT INTO capitulos (id_historias, titulo_capitulos, conteudo_capitulos, hash_conteudo_capitulos, ordem_capitulos, status_capitulos, modo_publicacao_capitulos, revisao_capitulos, data_revisao_capitulos)
                     VALUES (?, ?, ?, ?, ?, 'publicado', 'revisao_humana', 'aprovada', CURRENT_TIMESTAMP)"
                );
                $insC12->execute([$idHistObs, 'A Biblioteca Silenciosa - Demonstração', $cap1TextoObs, hash('sha256', $cap1TextoObs), 1]);
                $insC12->execute([$idHistObs, 'Sete Minutos Até a Meia-Noite', $cap2TextoObs, hash('sha256', $cap2TextoObs), 2]);

                $insA12 = $pdo->prepare(
                    "INSERT INTO autor_dos_livros (id_historias, id_autor, titulo_autor_dos_livros, preco_dos_livros_autor, modalidade_distribuicao, declaracao_autoria, data_declaracao_autoria, declaracao_comercializacao, data_declaracao_comercializacao, revisao_publicacao, data_revisao_publicacao, id_moderador_revisao, status_autor_dos_livros, data_publicacao_autor)
                     VALUES (?, ?, 'OBSERVÇÕES - FOLIVRO', 5.50, 'venda_simulada', 1, CURRENT_TIMESTAMP, 1, CURRENT_TIMESTAMP, 'aprovada', CURRENT_TIMESTAMP, ?, 'a_venda', CURRENT_TIMESTAMP)"
                );
                $insA12->execute([$idHistObs, $adminId12, $adminId12]);
            } elseif ($idHistObs > 0) {
                $pdo->prepare("UPDATE historias SET status_historias = 'publicada', generos_historias = 'Fantasia, Mistério, Ficção' WHERE id_historias = ?")->execute([$idHistObs]);
                $pdo->prepare("UPDATE autor_dos_livros SET status_autor_dos_livros = 'a_venda', revisao_publicacao = 'aprovada', motivo_bloqueio_autor = NULL, data_publicacao_autor = COALESCE(data_publicacao_autor, CURRENT_TIMESTAMP) WHERE id_historias = ?")->execute([$idHistObs]);
                $pdo->prepare("UPDATE capitulos SET status_capitulos = 'publicado', revisao_capitulos = 'aprovada', data_revisao_capitulos = COALESCE(data_revisao_capitulos, CURRENT_TIMESTAMP) WHERE id_historias = ?")->execute([$idHistObs]);
            }

            // Garante gêneros preenchidos também nos livros do catálogo local
            $pdo->exec("UPDATE livro SET genero_livro = 'Romance, Clássico' WHERE id_externo_livro = 'OL66554W' AND (genero_livro IS NULL OR genero_livro = '')");
            $pdo->exec("UPDATE livro SET genero_livro = 'Romance, Drama' WHERE id_externo_livro = 'OL18020194W' AND (genero_livro IS NULL OR genero_livro = '')");
            $pdo->exec("UPDATE livro SET genero_livro = 'Fantasia, Aventura' WHERE id_externo_livro = 'OL138052W' AND (genero_livro IS NULL OR genero_livro = '')");

            $pdo->exec("INSERT INTO _folivro_migrations (version) VALUES ('012_seed_current_books_and_works')");
        }
    } catch (PDOException $e) {
        // Registra o motivo exato no log do PHP para facilitar o diagnóstico.
        error_log('[FOLIVRO] Falha ao inicializar o esquema: ' . $e->getMessage());
        throw new RuntimeException('Serviço de banco de dados temporariamente indisponível.');
    }

    return $pdo;
}