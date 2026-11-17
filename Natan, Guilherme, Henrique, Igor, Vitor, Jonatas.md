# 🎓 Relatório de Auditoria Técnica e Mentoria Pedagógica — TCC

**Projeto Auditado:** FOLIVRO — Plataforma Literária e Acadêmica de Leitura, Autoria Independente e Livraria Digital  
**Instituição / Curso:** ETEC Jardim Ângela — Técnico em Desenvolvimento de Sistemas  
**Diretório Analisado:** `C:\xampp\htdocs\FOLIVRO-v6`  
**Avaliador (Persona):** Professor Orientador & Desenvolvedor Full Stack Sênior  
**Data da Auditoria:** 10 de novembro de 2026  

---

## 📋 Sumário Executivo de Avaliação

| Pilar de Auditoria | Conceito Técnico | Status Geral | Resumo da Avaliação |
| :--- | :---: | :---: | :--- |
| **1. Arquitetura e Organização** | **MB** *(Muito Bom)* | 🟢 Excelente | Estrutura modular clara (`/api`, `/config`, `/database`, `/includes`), tipagem estrita (`strict_types=1`), ótimo `.htaccess` e documentação exemplar. Pequenos resíduos de arquivos `.html` de redirecionamento e `.docx` na raiz. |
| **2. UI e Front-end** | **MB** *(Muito Bom)* | 🟢 Excelente | HTML5 altamente semântico, Design System literário coeso com variáveis CSS (`:root`), modo escuro/paletas customizadas e responsividade bem trabalhada. Oportunidade de desacoplar CSS/JS inline de alguns arquivos PHP. |
| **3. UX, Usabilidade e Acessibilidade** | **MB** *(Muito Bom)* | 🟢 Excelente | Fluxo intuitivo, feedbacks visuais ricos, busca com *debounce*, leitor com modo foco/sépia, Classificação Indicativa automática e uso exemplar de atributos `aria-*`, `role` e `.sr-only`. |
| **4. Banco de Dados e Persistência** | **MB** *(Muito Bom)* | 🟢 Excelente | Modelagem relacional robusta (21 tabelas InnoDB em `utf8mb4`), chaves estrangeiras íntegras, restrições `CHECK`, controle transacional (`BEGIN`/`COMMIT`/`FOR UPDATE`) e sistema próprio de *migrations* automáticas. |
| **5. Segurança da Informação** | **B / MB** *(Bom a Muito Bom)* | 🟡 Atenção Pontual | Uso rigoroso de *Prepared Statements* (PDO), `password_hash`, proteção CSRF com `hash_equals`, cabeçalhos HTTP de segurança e proteção de uploads. Requer ajuste pontual na sanitização de HTML de capítulos e na verificação SSL do cURL. |

---

## 1. Visão Geral e Arquitetura do Projeto

### 1.1 Identificação da Stack Tecnológica
O projeto **FOLIVRO** foi construído sobre uma arquitetura cliente-servidor clássica e muito sólida para o ensino técnico, priorizando o domínio dos fundamentos nativos da Web (*Vanilla Stack*) sem dependência de *frameworks* caixa-preta:

* **Back-end:** PHP 8+ utilizando `declare(strict_types=1);` em todos os módulos, programação procedural estruturada e modularizada, extensão **PDO** para acesso a dados, **cURL** / Streams nativos para consumo de APIs externas e manipulação de sessões nativas endurecidas (*hardened sessions*).
* **Banco de Dados:** MySQL / MariaDB (Engine `InnoDB`, charset `utf8mb4` e collation `utf8mb4_unicode_ci`), suportado por esquema declarativo (`database/schema.sql`) e um motor próprio de migrações incrementais (`_folivro_migrations` em `config/database.php`).
* **Front-end:** HTML5 semântico, CSS3 moderno (Custom Properties/Variáveis CSS, Flexbox, CSS Grid, `clamp()`, Media Queries) e JavaScript ES6+ nativo (`livros.js` encapsulado em IIFE, `fetch()` assíncrono, `AbortController`, `localStorage` e manipulação segura do DOM).
* **Integrações Externas e Serviços:**
  * **Open Library API & Internet Archive** (`api/livros.php`, `api/leitura-disponivel.php`, `livro.php`, `leitor.php`) para catálogo bibliográfico mundial, capas e empréstimo digital.
  * **MyMemory Translation API** (`config/traducao.php`) com sistema inteligente de *cache* persistente em arquivo (`cache/traducoes_sinopses.json`) e *fallback* heurístico local para tradução automática de sinopses e títulos ao português.
* **Servidor Web:** Apache HTTP Server configurado com regras de segurança e redirecionamento via `.htaccess`.

### 1.2 Separação de Responsabilidades e Organização de Pastas
A árvore de diretórios demonstra maturidade e organização limpa:

```text
FOLIVRO-v6/
├── api/                # Endpoints REST/JSON assíncronos (biblioteca, chat, compra, livros, progresso)
├── cache/              # Armazenamento local de traduções de sinopses (protegido via .htaccess)
├── config/             # Núcleo da aplicação: conexão PDO + migrations (database.php), autenticação/segurança/CSRF (auth.php) e tradução (traducao.php)
├── database/           # Script DDL completo com seeds (schema.sql) e dump de backup
├── img/                # Ativos visuais estáticos e ícones (img/icones/)
├── includes/           # Componentes de layout reutilizáveis (menu-lateral.php, menu-autor.php, configuracoes-modal.php)
├── uploads/            # Arquivos enviados por usuários (capas/ e perfis/) isolados com .htaccess próprio
└── *.php / *.css / *.js # Páginas de visualização/controladores de página e folhas de estilo modulares
```

* **Ponto de destaque arquitetural:** A centralização da infraestrutura em `config/database.php` e `config/auth.php` evitou a repetição de código de conexão e validação de sessão nas mais de 20 páginas do sistema. Além disso, separar as ações assíncronas na pasta `api/` (`api/biblioteca.php`, `api/progresso.php`, `api/compra.php`, `api/chat.php`) permitiu que o front-end atualize estados sem recarregar a página inteira.

### 1.3 Nomenclatura, Clareza de Código e Arquivos Legados ("Código Morto")
* **Padronização e Nomenclatura:** Os nomes de arquivos e funções seguem o idioma português de forma consistente e autoexplicativa, utilizando o prefixo de *namespace* lógico `folivro_` em todas as funções globais (ex.: `folivro_require_login()`, `folivro_verify_csrf()`, `folivro_avaliar_modalidade_publicacao()`), o que evita colisões de nomes no PHP.
* **Arquivos Legados / Redundantes Identificados:**
  1. **Arquivos `.html` na raiz (`login.html`, `cadastro.html`, `pesquisa.html`, `usuario.html`, `estatisticas.html`):** Esses 5 arquivos possuem apenas 14 linhas cada com um `<meta http-equiv="refresh">` redirecionando para os respectivos `.php`. Como o arquivo `.htaccess` (linhas 19–22) já faz o redirecionamento `302` automático das rotas `.html` para `.php`, esses arquivos estáticos na raiz tornaram-se redundantes.
  2. **Redirecionadores PHP legados (`autor.php` e `biblioteca.php`):** Atuam apenas como *stubs* de `header('Location: ...')` para `escrever.php` e `minha-biblioteca.php`. É uma boa prática de retrocompatibilidade, mas poderiam ser consolidados diretamente nas regras do `.htaccess`.
  3. **Documentos Word na raiz (`TCC_ESCRITO_FOLIVRO-v22.docx` e `TCC_ESCRITO_FOLIVRO-v23.docx`):** Apesar de o `.gitignore` prever `*.docx`, esses arquivos pesados estão na pasta de publicação do servidor web. Recomenda-se movê-los para uma pasta `docs/` fora da raiz pública ou removê-los do pacote final de deploy.

---

## 2. UI (Interface do Usuário) e Front-end

### 2.1 Semântica HTML5
O código HTML gerado pelos templates PHP apresenta um nível de semântica **acima da média** para projetos de nível técnico:
* Uso correto de *landmarks* estruturais: `<aside class="menu-lateral">`, `<main class="conteudo">`, `<header>`, `<section>`, `<article>`, `<nav>` e `<footer>`.
* Uso de elementos semânticos especializados:
  * `<dl>`, `<dt>` e `<dd>` para metadados técnicos dos livros em `livro.php` e `obra.php`.
  * `<details>` e `<summary>` para painéis expansíveis (como o formulário de recuperação de senha em `login.php`, denúncias e histórico de moderação em `moderacao.php`).
  * `<figure>` e `<figcaption>` para capas de livros e `<time>` / atributos semânticos em listagens.

### 2.2 Estilização (CSS) e Responsividade
* **Design System Literário (`layout.css`):** A definição de variáveis CSS no `:root` (`--cor-vinho`, `--cor-marrom`, `--cor-dourado-foco`, `--fundo-pagina`, `--fonte-literaria`, etc.) cria uma identidade visual acolhedora, acadêmica e perfeitamente alinhada ao tema de leitura.
* **Suporte Nativo a Múltiplos Temas:** O sistema suporta Tema Claro, Tema Escuro (`body.tema-escuro`) e paletas personalizadas (Sépia, Floresta, Oceano, Noturno) com transição suave e sincronização entre `localStorage` e banco de dados (`usuario.tema_preferido`).
* **Responsividade Moderna:** Uso competente de `CSS Grid` (`grid-template-columns: repeat(auto-fill, minmax(195px, 1fr))`), `Flexbox` e funções fluidas como `clamp(24px, 3.5vw, 40px)`. As *media queries* (`@media (max-width: 1300px)`, `760px`, `680px`) adaptam a grade de 6 colunas do catálogo (`home.css`) para 3 e 2 colunas em tablets e celulares, além de transformar a barra lateral em navegação compacta no mobile.

### 2.3 Componentização e Limpeza de Código Front-end
* **Pontos Positivos:** O reaproveitamento de `includes/menu-lateral.php`, `includes/menu-autor.php` e `includes/configuracoes-modal.php` garante consistência visual em todas as páginas. O arquivo `livros.js` é muito bem escrito, utilizando `IIFE` (`(function () { 'use strict'; ... })()`) para não poluir o escopo global `window`.
* **Oportunidades de Melhoria no Front-end:**
  * **Excesso de `!important` e CSS Inline em Componentes:** O arquivo `includes/configuracoes-modal.php` possui mais de **700 linhas de `<style>` e `<script>` embutidas diretamente no PHP**, com dezenas de declarações `!important` para forçar cores de temas. Da mesma forma, páginas como `obra.php`, `escrever.php`, `loja.php` e `leitor.php` possuem blocos `<script>` inline extensos no final do arquivo. Extrair esses estilos para `layout.css` (usando especificidade de variáveis CSS em vez de `!important`) e os scripts para arquivos `.js` dedicados deixará o código muito mais limpo e fácil de dar manutenção.

---

## 3. UX (Experiência do Usuário) e Usabilidade

### 3.1 Fluxo de Navegação e Prevenção de Erros
A jornada do usuário é fluida, lógica e pensada nos mínimos detalhes:
* **Navegação Contextual:** O menu lateral (`includes/menu-lateral.php`) destaca automaticamente a página ativa (`.item-menu.ativo` e `aria-current="page"`), exibe contador de mensagens não lidas no ícone do Chat com atualização periódica em tempo real e só exibe o atalho de **Moderação** para usuários com perfil `admin`.
* **Migalhas de Pão (*Breadcrumbs*):** Telas internas profundas (`livro.php`, `obra.php`, `leitor.php`, `gerenciar-obra.php`) contam com `<nav class="migalhas-navegacao" aria-label="Navegação estrutural">` e botões claros de retorno, impedindo que o leitor se perca.
* **Classificação Indicativa Automatizada (ECA / ClassInd):** Em `cadastro.php`, ao digitar a data de nascimento, um script calcula em tempo real a idade do usuário e exibe um selo visual informando qual faixa etária (`L`, `+10`, `+12`, `+14`, `+16`, `+18`) será liberada. No catálogo e nas obras, conteúdos acima da idade do usuário recebem filtro visual borrado (*blur*) com ícone de restrição e bloqueio no back-end.

### 3.2 Feedbacks Visuais e Estados da Interface
O sistema conversa muito bem com o usuário em todos os momentos:
* **Estados de Carregamento (*Loading*):** Ao buscar livros na Open Library ou traduzir sinopses, o usuário vê indicadores claros (`.estado-carregando`, textos dinâmicos nos botões como *"Salvando..."*, *"Processando compra..."* e desabilitação de botões `.disabled = true` para evitar duplo clique).
* **Estados Vazios (*Empty States*):** Quando uma estante ou busca não retorna itens (`minha-biblioteca.php`, `pesquisa.php`, `loja.php`), o sistema apresenta cartões amigáveis explicando o motivo e oferecendo um botão de ação (*Call to Action*) para explorar o catálogo.
* **Conforto de Leitura (`obra.php` e `leitor.php`):** O leitor de capítulos autorais inclui controles ergonômicos de tamanho de fonte (`A-` / `A+`), alternância entre fonte serifada e sem serifa, cor do papel ( Padrão, Sépia, Noturno), barra de progresso de rolagem no topo da tela e **Modo Foco (Leitura Zen)** que recolhe os menus laterais.

### 3.3 Acessibilidade Básica (A11y)
O cuidado com acessibilidade neste projeto merece elogios especiais:
* **Skip Link:** Todas as páginas com menu lateral possuem `<a href="#conteudo-principal" class="link-pular-conteudo">Pular para o conteúdo principal</a>`, essencial para navegação via teclado e leitores de tela.
* **Atributos ARIA e Roles:** Uso correto de `role="alert"` para mensagens de erro, `role="status"` e `aria-live="polite"` para atualizações dinâmicas de busca e progresso, `role="progressbar"` com `aria-valuenow`/`aria-valuemin`/`aria-valuemax` nas metas de leitura (`estatisticas.php`) e `aria-expanded` / `aria-modal="true"` nos modais.
* **Formulários e Imagens:** Todos os campos de entrada possuem `<label for="...">` (visíveis ou com a classe `.sr-only` para leitores de tela) e todas as imagens possuem atributo `alt` descritivo (ou `alt=""` com `aria-hidden="true"` quando puramente decorativas).

---

## 4. Banco de Dados e Persistência

### 4.1 Modelagem Relacional e Estrutura (`.sql`)
O esquema de banco de dados (`database/schema.sql` aliado às migrações em `config/database.php`) é **extremamente completo**, composto por **21 tabelas** bem estruturadas utilizando o motor transacional `InnoDB` e codificação `utf8mb4`:

1. **Núcleo de Usuários e Governança:** `usuario`, `termos_aceitos`, `seguidores`, `bloqueios_usuario`, `historico_moderacao_autor`, `denuncias`.
2. **Acervo Literário e Leitura:** `livro`, `livraria` (estante do usuário), `progressao_leitura`, `resenha`, `metas_leitura`, `contador_pontos` (gamificação).
3. **Estúdio de Autoria Independente:** `historias`, `capitulos`, `curtidas`, `comentarios`.
4. **Comércio Digital (Loja Simulada):** `autor_dos_livros`, `compra`, `livraria_digital`.
5. **Comunicação:** `chat_conversas`, `chat_mensagens`.

### 4.2 Chaves, Tipos de Dados e Integridade Referencial
* **Chaves Primárias e Estrangeiras:** Todas as tabelas utilizam chaves primárias `INT UNSIGNED AUTO_INCREMENT` e chaves estrangeiras explícitas (`CONSTRAINT fk_... FOREIGN KEY`).
* **Políticas de Exclusão (`ON DELETE`) Inteligentes:**
  * Tabelas dependentes de sessão/atividade usam `ON DELETE CASCADE` (ex.: `livraria`, `progressao_leitura`, `capitulos`, `curtidas`).
  * Tabelas financeiras/históricas como `compra` usam `ON DELETE RESTRICT` para preservar o registro de livros comprados. Inclusive, em `excluir-conta.php`, o grupo implementou uma rotina brilhante inspirada na **LGPD**: se o autor que está excluindo a conta já vendeu livros para outros leitores, o sistema **anonimiza** os dados pessoais do autor em vez de apagar o registro físico, evitando que os compradores percam acesso às obras adquiridas!
* **Restrições de Domínio (`CHECK` e `UNIQUE`):** Uso exemplar de `CHECK` constraints no próprio SGBD (`ck_progressao_pagina`, `ck_progressao_pct`, `ck_resenha_nota`, `ck_adl_preco`, `ck_compra_percentuais` garantindo que `percentual_autor + percentual_plataforma = 100.00`) e índices `UNIQUE` compostos (ex.: `uq_curtida_usuario_historia`, `uq_livraria_digital_compra`) que impedem duplicidades mesmo sob requisições concorrentes.

### 4.3 Consultas CRUD, Transações e Oportunidades na Modelagem
* **Controle Transacional (`ACID`):** Em operações críticas que alteram múltiplas tabelas — como registrar uma compra (`api/compra.php`), publicar um capítulo numerado (`gerenciar-obra.php` com `SELECT ... FOR UPDATE`), moderar uma obra (`moderacao.php`) ou excluir a conta (`excluir-conta.php`) — o código utiliza `beginTransaction()`, `commit()` e `rollBack()` dentro de blocos `try/catch`.
* **Sistema Próprio de Migrations (`config/database.php`):** A função `folivro_ensure_schema()` cria o banco automaticamente se não existir e executa 12 migrações versionadas na tabela `_folivro_migrations`. Isso facilita enormemente a avaliação da banca e o trabalho em equipe no XAMPP.
* **Observação Didática de Normalização (3FN):**
  * Na tabela `historias`, a coluna `generos_historias VARCHAR(255)` armazena múltiplos gêneros separados por vírgula (ex.: `"Fantasia, Aventura"`), sendo filtrada via `LIKE` em `pesquisa.php`. Para o escopo de TCC técnico isso funciona perfeitamente, mas em um banco estritamente normalizado na **1ª/3ª Forma Normal**, o ideal futuro seria uma tabela associativa `historia_genero (id_historia, id_genero)`.
  * Alguns nomes de colunas herdados da modelagem inicial possuem redundância semântica (ex.: `resenha_resenha` na tabela `resenha`, `comentarios_comentario` na tabela `comentarios`, `historias_historia` em `capitulos`). Padronizar para `texto_resenha`, `texto_comentario` e `resumo_historia` em projetos futuros tornará o SQL ainda mais legível.

---

## 5. Segurança da Informação (Foco Web/Aplicação)

### 5.1 Prevenção contra SQL Injection
* **Status:** 🟢 **100% Protegido.**
* **Análise:** A conexão PDO em `config/database.php` desativa a emulação de *prepared statements* (`PDO::ATTR_EMULATE_PREPARES => false`) e ativa exceções (`PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`). Todas as consultas que recebem entrada do usuário utilizam ` $pdo->prepare(...)` com *placeholders* `?` ou nomeados (`:id`, `:q`). Não há concatenação direta de variáveis de usuário em strings SQL.

### 5.2 Autenticação, Senhas, Sessões e Controle de Acesso (RBAC)
* **Status:** 🟢 **Excelente.**
* **Senhas:** Armazenadas exclusivamente com `password_hash($senha, PASSWORD_DEFAULT)` (Bcrypt) e verificadas com `password_verify()`. Nunca trafegam ou são salvas em texto puro.
* **Sessões e Cookies:** `config/auth.php` configura `session.use_strict_mode = 1`, cookies com `httponly => true` (impede roubo de sessão via `document.cookie` em caso de XSS) e `samesite => 'Lax'`. No login (`login.php`), `session_regenerate_id(true)` é chamado para prevenir ataques de *Session Fixation*.
* **Revalidação Contínua de Banimento/Suspensão:** A função `folivro_require_login()` consulta o status atualizado do usuário no banco a cada requisição; se um administrador suspender ou banir uma conta em `moderacao.php`, o usuário é desconectado imediatamente na próxima ação.
* **Proteção contra CSRF (*Cross-Site Request Forgery*):** Todos os formulários `POST` e chamadas `fetch()` das APIs exigem um token criptográfico gerado com `bin2hex(random_bytes(32))` e validado em tempo constante com `hash_equals()` (`folivro_verify_csrf()`).

### 5.3 Validação de Dados, Uploads e Sanitização contra XSS
* **Escape de Saída:** A função `folivro_escape()` (`htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`) é aplicada de forma disciplinada na renderização de nomes, títulos, resenhas, comentários e mensagens de chat.
* **Uploads Seguros (`usuario.php`, `escrever.php`, `gerenciar-obra.php`):**
  * Validação real de imagem via `getimagesize()` (não confia apenas na extensão enviada pelo navegador).
  * Limite de 2 MB e *whitelist* estrita de MIME types (`image/jpeg`, `image/png`, `image/webp`).
  * Renomeação do arquivo com hash aleatório (`bin2hex(random_bytes(8))`) prevenindo *Path Traversal* e sobrescrita.
  * Criação automática de arquivo `.htaccess` dentro de `uploads/perfis/` e `uploads/capas/` desativando a execução de scripts PHP (`php_flag engine off`).
* **⚠️ Ponto Crítico Identificado #1 — Sanitização de HTML de Capítulos (`config/auth.php`):**
  * Para permitir formatação rica nos capítulos (`<b>`, `<i>`, `<p>`, etc.), a função `folivro_sanitizar_html_capitulo()` (linhas 404–418 de `config/auth.php`) usa `strip_tags()` combinada com `preg_replace` para remover atributos `on*` (como `onload`, `onclick`). Na segurança web, filtrar atributos HTML apenas com expressões regulares em `strip_tags` pode ser contornado por vetores de XSS malformados (ex.: atributos separados por barras `<i/onmouseover=...>`, quebras de linha ou atributos `style` maliciosos). Veja a solução prática na **Seção 6.2**.

### 5.4 Exposição de Dados, `.htaccess` e Comunicação Externa
* **Proteção de Diretórios (`.htaccess`):** Excelente configuração na raiz! Bloqueia listagem de diretórios (`Options -Indexes`), barra acesso HTTP direto às pastas internas (`config/`, `database/`, `includes/`, `cache/`) e bloqueia o download de arquivos `.sql`, `.log`, `.ini`, `.sh` e `.md`.
* **⚠️ Ponto de Atenção Identificado #2 — Fallback cURL sem Verificação SSL:**
  * Nos arquivos `config/traducao.php` (linha 572), `api/livros.php` (linha 141), `api/leitura-disponivel.php` (linha 23) e `livro.php` (linha 188), quando a primeira requisição HTTPS falha por falta de certificado CA configurado no XAMPP local, o sistema faz uma segunda tentativa com `CURLOPT_SSL_VERIFYPEER => false` e `CURLOPT_SSL_VERIFYHOST => 0`. Embora seja compreensível para funcionar de imediato nos laboratórios da escola, desativar a verificação SSL em produção abre brecha para ataques *Man-in-the-Middle (MitM)*. Veja como parametrizar isso por ambiente na **Seção 6.2**.

---

## 6. Parecer Pedagógico e Feedback Final (Mentoria)

### 🌟 6.1 Pontos Fortes do Projeto (O que vocês fizeram de brilhante!)

1. **Engenharia de Banco de Dados e Auto-Instalação (`config/database.php` e `database/schema.sql`):**
   * Pouquíssimos grupos de nível técnico implementam um sistema de *migrations* versionadas (`_folivro_migrations`) que cria o banco, aplica 12 evoluções estruturais de forma idempotente e já popula dados de teste. Isso demonstra um carinho enorme com quem vai testar e avaliar o projeto.
2. **Segurança Ofensiva e Defensiva levada a sério (`config/auth.php` e `.htaccess`):**
   * O uso combinado de `declare(strict_types=1)`, PDO sem emulação, CSRF token em todas as rotas (inclusive via header `X-CSRF-Token` nas APIs JSON), regeneração de ID de sessão no login e bloqueio de execução de PHP na pasta `uploads/` mostra que o grupo realmente estudou segurança de aplicações web.
3. **Governança de Direitos Autorais, Anti-Pirataria e LGPD (`config/auth.php`, `moderacao.php`, `excluir-conta.php`):**
   * O sistema de hash `SHA-256` normalizado (`folivro_hash_conteudo_normalizado`) para impedir que obras removidas por plágio sejam republicadas, o filtro de links suspeitos no chat (`folivro_chat_avaliar_conteudo`) e a anonimização de conta em conformidade com a LGPD (`excluir-conta.php`) são diferenciais dignos de produtos reais de mercado.
4. **Experiência de Leitura e Acessibilidade (`obra.php`, `leitor.php`, `pesquisa.php`):**
   * A preocupação em traduzir automaticamente sinopses em inglês da Open Library com *cache* local (`config/traducao.php`), somada aos controles de tipografia, modo foco e suporte completo a leitores de tela (`aria-*`), prova que vocês pensaram no usuário final em cada linha de código.

---

### 🚀 6.2 Oportunidades de Melhoria (*Quick Wins*)

Abaixo estão listadas as melhorias recomendadas, ordenadas da **mais importante (segurança)** até **organização e estética**, com exemplos práticos de refatoração para vocês aplicarem ou citarem como *Trabalhos Futuros* na apresentação da banca:

---

#### 🔴 1. [Segurança] Substituir sanitização via Regex por `DOMDocument` Whitelist nos Capítulos
* **Onde:** `config/auth.php` (função `folivro_sanitizar_html_capitulo`, linhas 404–418).
* **Por que melhorar (Explicação Didática):** `strip_tags()` mantém todos os atributos das tags permitidas (ex.: `<p>`, `<b>`, `<i>`), e a expressão regular atual pode deixar passar variações de atributos HTML malformados que executam JavaScript (XSS Armazenado) quando outro leitor abre o capítulo em `obra.php`. Usar a classe nativa `DOMDocument` do PHP permite remover **qualquer atributo** das tags permitidas, garantindo 100% de segurança.

**Como está hoje (`config/auth.php`):**
```php
function folivro_sanitizar_html_capitulo(string $html): string
{
    $permitidas = '<p><br><strong><b><em><i><u><s><h2><h3><blockquote><ul><ol><li><hr>';
    $limpo = strip_tags($html, $permitidas);
    $limpo = preg_replace('/\s*(on[a-z]+|javascript:|data:)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $limpo) ?? $limpo;
    return trim($limpo);
}
```

**Sugestão de Refatoração Segura (usando `DOMDocument` nativo do PHP):**
```php
function folivro_sanitizar_html_capitulo(string $html): string
{
    $permitidas = '<p><br><strong><b><em><i><u><s><h2><h3><blockquote><ul><ol><li><hr>';
    $limpo = strip_tags($html, $permitidas);
    if (trim($limpo) === '') {
        return '';
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $limpo . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    // Remove TODOS os atributos (onclick, onmouseover, style, etc.) de todas as tags permitidas
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//*[@*]') as $node) {
        if ($node instanceof DOMElement && $node->getAttribute('id') !== 'root') {
            while ($node->attributes->length > 0) {
                $node->removeAttributeNode($node->attributes->item(0));
            }
        }
    }

    $root = $dom->getElementById('root');
    if (!$root) {
        return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
    }

    $saida = '';
    foreach ($root->childNodes as $child) {
        $saida .= $dom->saveHTML($child);
    }
    return trim($saida);
}
```

---

#### 🟠 2. [Segurança / Infra] Condicionar o Fallback de SSL (`CURLOPT_SSL_VERIFYPEER`) ao Ambiente Local
* **Onde:** `config/traducao.php` (linha 572), `api/livros.php` (linha 141), `api/leitura-disponivel.php` (linha 23) e `livro.php` (linha 188).
* **Por que melhorar (Explicação Didática):** Desabilitar a verificação de certificado SSL (`CURLOPT_SSL_VERIFYPEER => false`) resolve o problema do XAMPP no Windows sem pacote `cacert.pem`, mas em um servidor na internet permitiria que um invasor interceptasse a comunicação com a API. A boa prática é permitir esse *fallback* **somente** quando o servidor estiver rodando em `localhost` (`127.0.0.1`).

**Sugestão de Refatoração:**
```php
$isLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    || ($_SERVER['HTTP_HOST'] ?? '') === 'localhost';

if ($raw === false && $isLocalhost) {
    // Só desativa a verificação SSL se estiver rodando em ambiente local de desenvolvimento (XAMPP)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $raw = curl_exec($ch);
}
```

---

#### 🟡 3. [Arquitetura / DRY] Unificar Funções Utilitárias HTTP e de Texto Duplicadas
* **Onde:** `api/livros.php` e `config/traducao.php` (ambos declaram `folivro_remover_acentos` e funções cURL próprias: `folivro_http_get_json` em `api/livros.php`, `folivro_http_get_json_livro` em `livro.php` e `folivro_http_get` em `api/leitura-disponivel.php`).
* **Por que melhorar (Explicação Didática):** Seguindo o princípio **DRY (*Don't Repeat Yourself*)**, ter uma única função `folivro_http_get_json()` centralizada em um arquivo `config/http.php` (ou dentro de `config/traducao.php`) evita ter que dar manutenção na lógica de cURL em 4 arquivos diferentes.

---

#### 🔵 4. [Front-end / Manutenibilidade] Extrair o CSS e JS Embutidos em `includes/configuracoes-modal.php`
* **Onde:** `includes/configuracoes-modal.php` (linhas 160–860) e blocos `<script>` ao final de `obra.php`, `escrever.php` e `loja.php`.
* **Por que melhorar (Explicação Didática):** Atualmente, `includes/configuracoes-modal.php` injeta cerca de 700 linhas de `<style>` e `<script>` diretamente no HTML de todas as páginas onde o menu lateral é carregado. Mover o CSS para `layout.css` e o JavaScript para um arquivo `configuracoes.js` permite que o navegador faça **cache** desses recursos estáticos, diminuindo o tamanho do HTML transferido a cada clique e eliminando a necessidade de tantos `!important`.

---

#### 🟢 5. [Organização / Limpeza] Remover Arquivos `.html` Redundantes e `.docx` da Raiz
* **Onde:** Raiz do projeto (`login.html`, `cadastro.html`, `pesquisa.html`, `usuario.html`, `estatisticas.html`, `TCC_ESCRITO_FOLIVRO-v22.docx` e `TCC_ESCRITO_FOLIVRO-v23.docx`).
* **Por que melhorar (Explicação Didática):** Como o `.htaccess` já redireciona qualquer chamada `.html` para `.php` automaticamente, remover os 5 arquivos `.html` legados e mover os arquivos `.docx` da monografia para uma pasta separada fora do diretório público deixará o repositório impecável para entrega e apresentação.

---

### 🏆 6.3 Mensagem de Encerramento e Mentoria

Parabéns a todos os integrantes do grupo pelo excelente trabalho desenvolvido no **FOLIVRO**! 👏📚

Analisando cada arquivo deste projeto, fica evidente que vocês não fizeram apenas um "sistema básico de CRUD para cumprir tabela". Vocês construíram uma **plataforma literária viva, completa e funcional**, que integra catálogo global via API, tradução automática com cache, estúdio de escrita autoral, moderação com fluxo de revisão humana e qualificação automática de autores, loja digital com divisão transparente de *royalties*, chat entre leitores e um cuidado admirável com acessibilidade e segurança.

Para formandos do curso **Técnico em Desenvolvimento de Sistemas**, o domínio demonstrado aqui sobre PHP 8, PDO com transações ACID, modelagem SQL com 21 tabelas, proteção CSRF/XSS e JavaScript assíncrono coloca vocês em um patamar de maturidade técnica muito acima do esperado para o nível médio/técnico. Na apresentação para a banca examinadora, **valorizem e mostrem com orgulho**:
1. A arquitetura de auto-instalação e migrações do banco de dados (`config/database.php`);
2. O fluxo de governança de direitos autorais e publicação de obras (`escrever.php` e `moderacao.php`);
3. A experiência de leitura acessível com modo foco, temas e classificação indicativa (`obra.php` e `leitor.php`);
4. O respeito à LGPD na exclusão/anonimização de contas com compras vinculadas (`excluir-conta.php`).

Vocês estão mais do que prontos para o mercado de tecnologia e para o ensino superior. Desejo uma apresentação brilhante na banca de TCC e muito sucesso na caminhada como desenvolvedores! 🚀🎓
