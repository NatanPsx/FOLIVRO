-- =====================================================================
-- FOLIVRO - Migração 013: Loja de autores (marketplace leitor <-> autor)
-- MySQL 8 / MariaDB 10.4+ | InnoDB | utf8mb4
-- Executada automaticamente por config/database.php (comando a comando).
-- Não altera nem remove tabelas existentes: apenas cria as tabelas da loja.
-- Valores monetários em CENTAVOS (INT) para evitar erros de arredondamento.
-- =====================================================================

-- ---------------------------------------------------------------------
-- PRODUTOS (obras digitais à venda: PDF/EPUB)
-- id_livro liga a obra ao catálogo/biblioteca/avaliações existentes.
-- id_autor_dos_livros (opcional) liga a uma obra escrita no estúdio.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS loja_produtos (
  id_produto              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_autor                INT UNSIGNED NOT NULL,
  id_livro                INT UNSIGNED NULL,
  id_autor_dos_livros     INT UNSIGNED NULL,
  titulo_produto          VARCHAR(200) NOT NULL,
  descricao_produto       TEXT NULL,
  preview_produto         TEXT NULL,
  capa_produto            VARCHAR(255) NULL,
  generos_produto         VARCHAR(255) NULL,
  classificacao_produto   VARCHAR(20) NOT NULL DEFAULT 'livre',
  preco_centavos          INT UNSIGNED NOT NULL DEFAULT 0,
  arquivo_produto         VARCHAR(120) NULL,
  arquivo_nome_original   VARCHAR(200) NULL,
  arquivo_tipo            ENUM('pdf','epub') NULL,
  arquivo_tamanho         INT UNSIGNED NULL,
  arquivo_sha256          CHAR(64) NULL,
  status_produto          ENUM('rascunho','publicado','arquivado') NOT NULL DEFAULT 'rascunho',
  aceite_termo_direitos   TINYINT(1) NOT NULL DEFAULT 0,
  data_aceite_termo       DATETIME NULL,
  versao_termo            VARCHAR(20) NULL,
  data_criacao            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  data_publicacao         DATETIME NULL,
  PRIMARY KEY (id_produto),
  UNIQUE KEY uq_loja_produto_livro (id_livro),
  KEY idx_loja_produto_autor (id_autor, status_produto),
  KEY idx_loja_produto_vitrine (status_produto, data_publicacao),
  KEY idx_loja_produto_preco (preco_centavos),
  KEY idx_loja_produto_titulo (titulo_produto),
  CONSTRAINT ck_loja_produto_preco CHECK (preco_centavos <= 100000000),
  CONSTRAINT fk_loja_produto_autor FOREIGN KEY (id_autor) REFERENCES usuario (id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_loja_produto_livro FOREIGN KEY (id_livro) REFERENCES livro (id_livro)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_loja_produto_adl FOREIGN KEY (id_autor_dos_livros) REFERENCES autor_dos_livros (id_autor_dos_livros)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PEDIDOS (comprador x autor/vendedor x produto)
-- compra_paga_unica: coluna gerada = 1 somente quando status = 'pago'.
-- O UNIQUE (comprador, produto, compra_paga_unica) impede duas compras
-- pagas da mesma obra pelo mesmo leitor (NULL não conflita no índice).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS loja_pedidos (
  id_pedido               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo_pedido           CHAR(20) NOT NULL,
  id_comprador            INT UNSIGNED NOT NULL,
  id_vendedor             INT UNSIGNED NOT NULL,
  id_produto              INT UNSIGNED NOT NULL,
  titulo_produto_snapshot VARCHAR(200) NOT NULL,
  valor_bruto_centavos    INT UNSIGNED NOT NULL,
  taxa_percentual         DECIMAL(5,2) NOT NULL,
  valor_taxa_centavos     INT UNSIGNED NOT NULL,
  valor_liquido_centavos  INT UNSIGNED NOT NULL,
  metodo_pagamento        ENUM('pix','cartao','gratuito') NOT NULL,
  modo_pagamento          ENUM('simulado','real') NOT NULL,
  gateway                 VARCHAR(30) NOT NULL,
  id_externo_gateway      VARCHAR(100) NULL,
  status_pedido           ENUM('pendente','pago','falhou','cancelado','reembolsado') NOT NULL DEFAULT 'pendente',
  motivo_status           VARCHAR(255) NULL,
  pix_copia_cola          TEXT NULL,
  pix_qr_base64           MEDIUMTEXT NULL,
  pix_ticket_url          VARCHAR(500) NULL,
  expira_em               DATETIME NULL,
  pago_em                 DATETIME NULL,
  reembolsado_em          DATETIME NULL,
  ultima_consulta_em      DATETIME NULL,
  compra_paga_unica       TINYINT(1) AS (IF(status_pedido = 'pago', 1, NULL)) STORED,
  criado_em               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pedido),
  UNIQUE KEY uq_loja_pedido_codigo (codigo_pedido),
  UNIQUE KEY uq_loja_pedido_pago_unico (id_comprador, id_produto, compra_paga_unica),
  KEY idx_loja_pedido_comprador (id_comprador, criado_em),
  KEY idx_loja_pedido_vendedor (id_vendedor, status_pedido, criado_em),
  KEY idx_loja_pedido_produto (id_produto, status_pedido),
  KEY idx_loja_pedido_externo (gateway, id_externo_gateway),
  KEY idx_loja_pedido_status (status_pedido, expira_em),
  CONSTRAINT ck_loja_pedido_valores CHECK (valor_taxa_centavos + valor_liquido_centavos = valor_bruto_centavos),
  CONSTRAINT fk_loja_pedido_comprador FOREIGN KEY (id_comprador) REFERENCES usuario (id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_loja_pedido_vendedor FOREIGN KEY (id_vendedor) REFERENCES usuario (id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_loja_pedido_produto FOREIGN KEY (id_produto) REFERENCES loja_produtos (id_produto)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PAGAMENTOS / TRANSAÇÕES (histórico de cada interação com o gateway)
-- payload_bruto guarda o corpo recebido/retornado SEM dados de cartão
-- (o cartão é tokenizado no navegador pelo SDK do Mercado Pago).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS loja_pagamentos (
  id_pagamento            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pedido               INT UNSIGNED NOT NULL,
  gateway                 VARCHAR(30) NOT NULL,
  id_externo              VARCHAR(100) NULL,
  origem_evento           ENUM('criacao','consulta','webhook','simulacao','reembolso','expiracao','gratuito') NOT NULL,
  status_pagamento        VARCHAR(30) NOT NULL,
  status_detalhe          VARCHAR(120) NULL,
  valor_centavos          INT UNSIGNED NULL,
  payload_bruto           MEDIUMTEXT NULL,
  criado_em               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pagamento),
  KEY idx_loja_pag_pedido (id_pedido, criado_em),
  KEY idx_loja_pag_externo (gateway, id_externo),
  CONSTRAINT fk_loja_pag_pedido FOREIGN KEY (id_pedido) REFERENCES loja_pedidos (id_pedido)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- EVENTOS DE WEBHOOK (idempotência: o mesmo evento não é processado 2x)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS loja_webhook_eventos (
  id_evento               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  gateway                 VARCHAR(30) NOT NULL,
  chave_idempotencia      CHAR(64) NOT NULL,
  id_recurso              VARCHAR(100) NULL,
  tipo_evento             VARCHAR(60) NULL,
  assinatura_valida       TINYINT(1) NOT NULL DEFAULT 0,
  status_processamento    ENUM('recebido','processado','ignorado','erro') NOT NULL DEFAULT 'recebido',
  resultado               VARCHAR(255) NULL,
  payload_bruto           MEDIUMTEXT NULL,
  tentativas              INT UNSIGNED NOT NULL DEFAULT 1,
  recebido_em             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processado_em           DATETIME NULL,
  PRIMARY KEY (id_evento),
  UNIQUE KEY uq_loja_webhook_evento (gateway, chave_idempotencia),
  KEY idx_loja_webhook_recurso (gateway, id_recurso)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- REPASSES AO AUTOR (saques do saldo)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS loja_repasses (
  id_repasse              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_autor                INT UNSIGNED NOT NULL,
  valor_centavos          INT UNSIGNED NOT NULL,
  status_repasse          ENUM('solicitado','processando','pago','cancelado') NOT NULL DEFAULT 'solicitado',
  modo_pagamento          ENUM('simulado','real') NOT NULL,
  observacao              VARCHAR(255) NULL,
  solicitado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processado_em           DATETIME NULL,
  PRIMARY KEY (id_repasse),
  KEY idx_loja_repasse_autor (id_autor, solicitado_em),
  CONSTRAINT ck_loja_repasse_valor CHECK (valor_centavos > 0),
  CONSTRAINT fk_loja_repasse_autor FOREIGN KEY (id_autor) REFERENCES usuario (id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- LEDGER (livro-razão) DO AUTOR: crédito por venda, estorno e repasse.
-- Saldo disponível = SUM(valor_centavos). UNIQUE(id_pedido, tipo)
-- garante que a mesma venda nunca credita/estorna duas vezes.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS loja_ledger_autor (
  id_lancamento           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_autor                INT UNSIGNED NOT NULL,
  id_pedido               INT UNSIGNED NULL,
  id_repasse              INT UNSIGNED NULL,
  tipo_lancamento         ENUM('credito_venda','estorno_venda','repasse','repasse_cancelado') NOT NULL,
  valor_centavos          INT NOT NULL,
  descricao               VARCHAR(255) NOT NULL,
  criado_em               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_lancamento),
  UNIQUE KEY uq_loja_ledger_pedido_tipo (id_pedido, tipo_lancamento),
  UNIQUE KEY uq_loja_ledger_repasse_tipo (id_repasse, tipo_lancamento),
  KEY idx_loja_ledger_autor (id_autor, criado_em),
  CONSTRAINT fk_loja_ledger_autor FOREIGN KEY (id_autor) REFERENCES usuario (id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_loja_ledger_pedido FOREIGN KEY (id_pedido) REFERENCES loja_pedidos (id_pedido)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_loja_ledger_repasse FOREIGN KEY (id_repasse) REFERENCES loja_repasses (id_repasse)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- GATEWAY SIMULADO: faz o papel do "servidor do PSP" no modo simulado,
-- para que o fluxo (cobrança -> consulta de status -> webhook) seja o
-- mesmo do modo real, sem dinheiro de verdade.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS loja_gateway_simulado (
  id_externo              VARCHAR(40) NOT NULL,
  referencia              CHAR(20) NOT NULL,
  metodo                  ENUM('pix','cartao') NOT NULL,
  valor_centavos          INT UNSIGNED NOT NULL,
  status_remoto           ENUM('pending','approved','rejected','cancelled','refunded') NOT NULL DEFAULT 'pending',
  status_detalhe          VARCHAR(120) NULL,
  expira_em               DATETIME NULL,
  criado_em               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_externo),
  KEY idx_loja_gwsim_ref (referencia)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- RATE LIMIT simples por janela de tempo (checkout, webhook, download)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS loja_rate_limit (
  chave                   CHAR(64) NOT NULL,
  janela_inicio           INT UNSIGNED NOT NULL,
  contador                INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (chave, janela_inicio),
  KEY idx_loja_rate_janela (janela_inicio)
) ENGINE=InnoDB
