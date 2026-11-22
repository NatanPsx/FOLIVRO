-- =====================================================================
-- FOLIVRO - Script de criacao do banco de dados
-- MySQL 8 / MariaDB | InnoDB | utf8mb4
-- Compativel com o Laragon (MySQL 8) e com o XAMPP (MariaDB)
-- Sem DELIMITER e sem triggers: o PHP executa este arquivo comando a comando
-- =====================================================================

CREATE DATABASE IF NOT EXISTS folivro
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE folivro;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- USUARIO
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuario (
  id_usuario            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome_usuario          VARCHAR(120) NOT NULL,
  email_usuario         VARCHAR(150) NOT NULL,
  data_nascimento_usuario DATE NULL,
  senha_usuario         VARCHAR(255) NOT NULL,
  biografia_usuario     TEXT NULL,
  foto_usuario          VARCHAR(255) NULL,
  nivel_usuario         ENUM('leitor','autor','admin') NOT NULL DEFAULT 'leitor',
  status_usuario        ENUM('ativo','inativo','banido') NOT NULL DEFAULT 'ativo',
  restricao_chat_ate    DATETIME NULL,
  avisos_moderacao_usuario INT UNSIGNED NOT NULL DEFAULT 0,
  modalidade_publicacao_usuario ENUM('avaliacao','automatica','revisao_obrigatoria','suspensa') NOT NULL DEFAULT 'avaliacao',
  capitulos_aprovados_usuario INT UNSIGNED NOT NULL DEFAULT 0,
  violacoes_confirmadas_usuario INT UNSIGNED NOT NULL DEFAULT 0,
  revisoes_recusadas_usuario INT UNSIGNED NOT NULL DEFAULT 0,
  justificativa_modalidade_usuario VARCHAR(255) NULL DEFAULT NULL,
  data_qualificacao_usuario DATETIME NULL DEFAULT NULL,
  data_cadastro_usuario DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuario_email (email_usuario)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- LIVRO
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS livro (
  id_livro              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo_livro          VARCHAR(200) NOT NULL,
  autor_livro           VARCHAR(150) NOT NULL,
  isbn_livro            VARCHAR(20) NULL,
  fonte_livro           ENUM('catalogo','openlibrary') NOT NULL DEFAULT 'catalogo',
  id_externo_livro      VARCHAR(50) NULL,
  sinopse_livro         TEXT NULL,
  capa_livro            VARCHAR(255) NULL,
  genero_livro          VARCHAR(80) NULL,
  classificacao_livro   VARCHAR(20) NOT NULL DEFAULT 'livre',
  paginas_livro         INT UNSIGNED NULL,
  editora_livro         VARCHAR(120) NULL,
  data_publicacao_livro DATE NULL,
  data_cadastro_livro   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_livro),
  UNIQUE KEY uq_livro_isbn (isbn_livro),
  UNIQUE KEY uq_livro_externo (id_externo_livro),
  KEY idx_livro_titulo (titulo_livro),
  KEY idx_livro_autor (autor_livro),
  KEY idx_livro_genero (genero_livro)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- LIVRARIA
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS livraria (
  id_livraria               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario                INT UNSIGNED NOT NULL,
  id_livro                  INT UNSIGNED NOT NULL,
  status_livraria           ENUM('quero_ler','lendo','lido','abandonado') NOT NULL DEFAULT 'quero_ler',
  data_solicitacao_livraria DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_modificacao_livraria DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_livraria),
  UNIQUE KEY uq_livraria_usuario_livro (id_usuario, id_livro),
  KEY idx_livraria_livro (id_livro),
  CONSTRAINT fk_livraria_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_livraria_livro FOREIGN KEY (id_livro) REFERENCES livro (id_livro)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PROGRESSAO DE LEITURA
-- CHECKs ficam aqui dentro (colunas de FK sao id_usuario e id_livro, que nao entram nos CHECKs)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS progressao_leitura (
  id_progressao_leitura        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario                   INT UNSIGNED NOT NULL,
  id_livro                     INT UNSIGNED NOT NULL,
  pagina_atual_progressao      INT UNSIGNED NOT NULL DEFAULT 0,
  total_paginas_progressao     INT UNSIGNED NOT NULL DEFAULT 0,
  porcentagem_progressao       DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  data_modificacao_progressao  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_progressao_leitura),
  UNIQUE KEY uq_progressao_usuario_livro (id_usuario, id_livro),
  KEY idx_progressao_livro (id_livro),
  CONSTRAINT ck_progressao_pagina CHECK (total_paginas_progressao = 0 OR pagina_atual_progressao <= total_paginas_progressao),
  CONSTRAINT ck_progressao_pct CHECK (porcentagem_progressao BETWEEN 0 AND 100),
  CONSTRAINT fk_progressao_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_progressao_livro FOREIGN KEY (id_livro) REFERENCES livro (id_livro)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- RESENHA
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS resenha (
  id_resenha             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario             INT UNSIGNED NOT NULL,
  id_livro               INT UNSIGNED NOT NULL,
  nota_resenha           TINYINT UNSIGNED NULL,
  titulo_resenha         VARCHAR(150) NULL,
  resenha_resenha        TEXT NOT NULL,
  data_avaliacao_resenha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_resenha),
  UNIQUE KEY uq_resenha_usuario_livro (id_usuario, id_livro),
  KEY idx_resenha_livro (id_livro),
  CONSTRAINT ck_resenha_nota CHECK (nota_resenha IS NULL OR nota_resenha BETWEEN 1 AND 5),
  CONSTRAINT fk_resenha_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_resenha_livro FOREIGN KEY (id_livro) REFERENCES livro (id_livro)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CONTADOR DE PONTOS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contador_pontos (
  id_contador_de_pontos      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario                 INT UNSIGNED NOT NULL,
  pontos_contador_de_pontos  INT UNSIGNED NOT NULL DEFAULT 0,
  data_atualizacao_contador  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_contador_de_pontos),
  UNIQUE KEY uq_contador_usuario (id_usuario),
  CONSTRAINT fk_contador_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- HISTORIAS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS historias (
  id_historias               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario                 INT UNSIGNED NOT NULL,
  titulo_historias           VARCHAR(200) NOT NULL,
  sinopse_historias          TEXT NULL,
  capa_historias             VARCHAR(255) NULL,
  generos_historias          VARCHAR(255) NULL DEFAULT NULL,
  licenca_historias          VARCHAR(60) NOT NULL DEFAULT 'direitos_reservados',
  aviso_conteudo_historias   VARCHAR(80) NOT NULL DEFAULT 'livre',
  origem_obra_historias      VARCHAR(60) NOT NULL DEFAULT 'autoral_original',
  hash_conteudo_historias    CHAR(64) NULL,
  status_historias           ENUM('rascunho','publicada','despublicada') NOT NULL DEFAULT 'rascunho',
  visualizacoes_historias    INT UNSIGNED NOT NULL DEFAULT 0,
  data_criacao_historias     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao_historias DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_historias),
  KEY idx_historias_usuario (id_usuario),
  KEY idx_historias_status (status_historias),
  KEY idx_historias_titulo (titulo_historias),
  KEY idx_historias_hash (hash_conteudo_historias),
  CONSTRAINT fk_historias_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CAPITULOS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS capitulos (
  id_capitulos               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_historias               INT UNSIGNED NOT NULL,
  titulo_capitulos           VARCHAR(200) NOT NULL,
  conteudo_capitulos         MEDIUMTEXT NOT NULL,
  hash_conteudo_capitulos    CHAR(64) NULL,
  ordem_capitulos            INT UNSIGNED NOT NULL,
  status_capitulos           ENUM('rascunho','publicado') NOT NULL DEFAULT 'rascunho',
  modo_publicacao_capitulos  ENUM('revisao_humana','automatica') NOT NULL DEFAULT 'revisao_humana',
  revisao_capitulos          ENUM('nao_solicitada','pendente','aprovada','publicado_automatico','recusada','oculto_moderacao') NOT NULL DEFAULT 'pendente',
  justificativa_revisao_capitulos TEXT NULL DEFAULT NULL,
  id_moderador_capitulos     INT UNSIGNED NULL DEFAULT NULL,
  data_revisao_capitulos     DATETIME NULL DEFAULT NULL,
  data_criacao_capitulos     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao_capitulos DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_capitulos),
  UNIQUE KEY uq_capitulos_historia_ordem (id_historias, ordem_capitulos),
  KEY idx_capitulos_hash (hash_conteudo_capitulos),
  CONSTRAINT fk_capitulos_historias FOREIGN KEY (id_historias) REFERENCES historias (id_historias)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CURTIDAS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS curtidas (
  id_curtidas           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario            INT UNSIGNED NOT NULL,
  id_historias          INT UNSIGNED NOT NULL,
  data_criacao_curtidas DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_curtidas),
  UNIQUE KEY uq_curtidas_usuario_historia (id_usuario, id_historias),
  KEY idx_curtidas_historia (id_historias),
  CONSTRAINT fk_curtidas_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_curtidas_historias FOREIGN KEY (id_historias) REFERENCES historias (id_historias)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- COMENTARIOS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comentarios (
  id_comentarios         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario             INT UNSIGNED NOT NULL,
  id_historias           INT UNSIGNED NOT NULL,
  comentarios_comentario TEXT NOT NULL,
  data_comentario        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_comentarios),
  KEY idx_comentarios_usuario (id_usuario),
  KEY idx_comentarios_historia (id_historias),
  CONSTRAINT fk_comentarios_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_comentarios_historias FOREIGN KEY (id_historias) REFERENCES historias (id_historias)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- AUTOR DOS LIVROS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS autor_dos_livros (
  id_autor_dos_livros             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_historias                    INT UNSIGNED NOT NULL,
  id_autor                        INT UNSIGNED NOT NULL,
  titulo_autor_dos_livros         VARCHAR(200) NOT NULL,
  preco_dos_livros_autor          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  modalidade_distribuicao         ENUM('gratuita','venda_simulada') NOT NULL DEFAULT 'venda_simulada',
  declaracao_autoria              TINYINT(1) NOT NULL DEFAULT 0,
  data_declaracao_autoria         DATETIME NULL,
  declaracao_comercializacao      TINYINT(1) NOT NULL DEFAULT 0,
  data_declaracao_comercializacao DATETIME NULL,
  revisao_publicacao              ENUM('nao_solicitada','pendente','aprovada','recusada') NOT NULL DEFAULT 'nao_solicitada',
  justificativa_revisao           TEXT NULL,
  data_revisao_publicacao         DATETIME NULL,
  id_moderador_revisao            INT UNSIGNED NULL,
  motivo_bloqueio_autor           VARCHAR(255) NULL,
  data_lancamento_autor           DATE NULL,
  status_autor_dos_livros         ENUM('rascunho','a_venda','suspenso','removido') NOT NULL DEFAULT 'rascunho',
  data_publicacao_autor           DATETIME NULL,
  data_atualizacao_autor          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_autor_dos_livros),
  KEY idx_adl_historias (id_historias),
  KEY idx_adl_autor (id_autor),
  KEY idx_adl_status (status_autor_dos_livros),
  KEY idx_adl_revisao (revisao_publicacao),
  CONSTRAINT ck_adl_preco CHECK (preco_dos_livros_autor >= 0),
  CONSTRAINT fk_adl_historias FOREIGN KEY (id_historias) REFERENCES historias (id_historias)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_adl_autor FOREIGN KEY (id_autor) REFERENCES usuario (id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- COMPRA
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS compra (
  id_compra                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario                   INT UNSIGNED NOT NULL,
  id_autor_dos_livros          INT UNSIGNED NOT NULL,
  valor_pago_compra            DECIMAL(10,2) NOT NULL,
  percentual_autor_compra      DECIMAL(5,2) NOT NULL DEFAULT 80.00,
  ingresso_autor_compra        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  percentual_plataforma_compra DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  status_compra                ENUM('pendente','concluida','cancelada','reembolsada') NOT NULL DEFAULT 'pendente',
  data_compra                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_compra),
  KEY idx_compra_usuario (id_usuario),
  KEY idx_compra_obra (id_autor_dos_livros),
  KEY idx_compra_status (status_compra),
  CONSTRAINT ck_compra_valor CHECK (valor_pago_compra >= 0),
  CONSTRAINT ck_compra_percentuais CHECK (percentual_autor_compra + percentual_plataforma_compra = 100),
  CONSTRAINT fk_compra_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_compra_obra FOREIGN KEY (id_autor_dos_livros) REFERENCES autor_dos_livros (id_autor_dos_livros)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- LIVRARIA DIGITAL
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS livraria_digital (
  id_livraria_digital             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario                      INT UNSIGNED NOT NULL,
  id_autor_dos_livros             INT UNSIGNED NOT NULL,
  id_compra                       INT UNSIGNED NOT NULL,
  data_liberacao_livraria_digital DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_livraria_digital),
  UNIQUE KEY uq_ld_usuario_obra (id_usuario, id_autor_dos_livros),
  UNIQUE KEY uq_ld_compra (id_compra),
  KEY idx_ld_obra (id_autor_dos_livros),
  CONSTRAINT fk_ld_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ld_obra FOREIGN KEY (id_autor_dos_livros) REFERENCES autor_dos_livros (id_autor_dos_livros)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ld_compra FOREIGN KEY (id_compra) REFERENCES compra (id_compra)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- DENUNCIAS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS denuncias (
  id_denuncias             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario               INT UNSIGNED NOT NULL,
  tipo_denuncias           ENUM('historia','comentario','usuario','obra_autoral','mensagem_chat','conversa_chat') NOT NULL,
  item_denuncia            INT UNSIGNED NOT NULL,
  motivo_denuncia          VARCHAR(150) NOT NULL,
  obra_referencia_denuncia VARCHAR(255) NULL,
  descricao_denuncia       TEXT NULL,
  prioridade_denuncia      ENUM('baixa','media','alta') NOT NULL DEFAULT 'media',
  suspensao_preventiva     TINYINT(1) NOT NULL DEFAULT 0,
  acao_moderacao_aplicada  VARCHAR(60) NULL,
  justificativa_moderacao  TEXT NULL,
  contestacao_autor        TEXT NULL,
  data_contestacao         DATETIME NULL,
  status_denuncias         ENUM('aberta','em_analise','procedente','improcedente') NOT NULL DEFAULT 'aberta',
  data_denuncia            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_resposta_denuncia   DATETIME NULL,
  retencao_evidencia_ate   DATETIME NULL,
  PRIMARY KEY (id_denuncias),
  KEY idx_denuncias_usuario (id_usuario),
  KEY idx_denuncias_tipo_item (tipo_denuncias, item_denuncia),
  KEY idx_denuncias_status (status_denuncias),
  CONSTRAINT fk_denuncias_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- TABELAS COMPLEMENTARES
-- =====================================================================

-- SEGUIDORES (sem CHECK: colunas de FK nao podem entrar em CHECK no MySQL 8. Validar no PHP)
CREATE TABLE IF NOT EXISTS seguidores (
  id_seguidores   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_seguidor     INT UNSIGNED NOT NULL,
  id_seguido      INT UNSIGNED NOT NULL,
  data_seguidores DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_seguidores),
  UNIQUE KEY uq_seguidores_par (id_seguidor, id_seguido),
  KEY idx_seguidores_seguido (id_seguido),
  CONSTRAINT fk_seguidores_seguidor FOREIGN KEY (id_seguidor) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_seguidores_seguido FOREIGN KEY (id_seguido) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- METAS DE LEITURA
CREATE TABLE IF NOT EXISTS metas_leitura (
  id_metas_leitura  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario        INT UNSIGNED NOT NULL,
  titulo_metas      VARCHAR(120) NOT NULL,
  tipo_metas        ENUM('livros','paginas') NOT NULL DEFAULT 'livros',
  quantidade_metas  INT UNSIGNED NOT NULL,
  progresso_metas   INT UNSIGNED NOT NULL DEFAULT 0,
  data_inicio_metas DATE NOT NULL,
  data_fim_metas    DATE NOT NULL,
  status_metas      ENUM('em_andamento','concluida','cancelada') NOT NULL DEFAULT 'em_andamento',
  PRIMARY KEY (id_metas_leitura),
  KEY idx_metas_usuario (id_usuario),
  CONSTRAINT ck_metas_datas CHECK (data_fim_metas >= data_inicio_metas),
  CONSTRAINT fk_metas_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- TERMOS ACEITOS
CREATE TABLE IF NOT EXISTS termos_aceitos (
  id_termos_aceitos   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario          INT UNSIGNED NOT NULL,
  tipo_termos         ENUM('termos_uso','privacidade','declaracao_autoria','declaracao_comercializacao') NOT NULL,
  versao_termos       VARCHAR(20) NOT NULL,
  id_autor_dos_livros INT UNSIGNED NULL,
  data_aceite_termos  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_termos_aceitos),
  KEY idx_termos_usuario (id_usuario, tipo_termos),
  CONSTRAINT fk_termos_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_termos_obra FOREIGN KEY (id_autor_dos_livros) REFERENCES autor_dos_livros (id_autor_dos_livros)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- CHAT (sem CHECK: validar id_usuario_menor < id_usuario_maior no PHP)
CREATE TABLE IF NOT EXISTS chat_conversas (
  id_conversa           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario_menor      INT UNSIGNED NOT NULL,
  id_usuario_maior      INT UNSIGNED NOT NULL,
  data_criacao_conversa DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_ultima_mensagem  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_conversa),
  UNIQUE KEY uq_chat_par (id_usuario_menor, id_usuario_maior),
  KEY idx_chat_u_menor (id_usuario_menor),
  KEY idx_chat_u_maior (id_usuario_maior),
  CONSTRAINT fk_chat_u_menor FOREIGN KEY (id_usuario_menor) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_chat_u_maior FOREIGN KEY (id_usuario_maior) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_mensagens (
  id_mensagem            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_conversa            INT UNSIGNED NOT NULL,
  id_remetente           INT UNSIGNED NOT NULL,
  conteudo_mensagem      TEXT NOT NULL,
  hash_conteudo_mensagem CHAR(64) NOT NULL,
  status_mensagem        ENUM('ativa','apagada_pelo_autor','oculta_preventivamente','removida_moderacao') NOT NULL DEFAULT 'ativa',
  motivo_ocultacao       VARCHAR(255) NULL,
  data_envio_mensagem    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lida_em                DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id_mensagem),
  KEY idx_chat_msg_conversa (id_conversa, data_envio_mensagem),
  KEY idx_chat_msg_remetente_data (id_remetente, data_envio_mensagem),
  KEY idx_chat_msg_hash (hash_conteudo_mensagem),
  CONSTRAINT fk_chat_msg_conversa FOREIGN KEY (id_conversa) REFERENCES chat_conversas (id_conversa)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_chat_msg_remetente FOREIGN KEY (id_remetente) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- BLOQUEIOS (sem CHECK: validar id_bloqueador <> id_bloqueado no PHP)
CREATE TABLE IF NOT EXISTS bloqueios_usuario (
  id_bloqueio     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_bloqueador   INT UNSIGNED NOT NULL,
  id_bloqueado    INT UNSIGNED NOT NULL,
  motivo_bloqueio VARCHAR(150) NULL,
  data_bloqueio   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_bloqueio),
  UNIQUE KEY uq_bloqueio_par (id_bloqueador, id_bloqueado),
  KEY idx_bloqueado (id_bloqueado),
  CONSTRAINT fk_bloqueio_bloqueador FOREIGN KEY (id_bloqueador) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_bloqueio_bloqueado FOREIGN KEY (id_bloqueado) REFERENCES usuario (id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- HISTORICO DE MODERACAO E QUALIFICACAO DE AUTORES
CREATE TABLE IF NOT EXISTS historico_moderacao_autor (
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
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;