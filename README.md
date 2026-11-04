# FOLIVRO

Plataforma acadêmica de leitura, escrita, comunidade literária, autopublicação e loja com compras simuladas.

## Como abrir em outro PC (Apresentação / Demonstração)

1. Copie a pasta `FOLIVRO-v6` para dentro de `C:\xampp\htdocs\FOLIVRO-v6` (no XAMPP) ou `C:\laragon\www\FOLIVRO-v6` (no Laragon) do outro computador.
2. Abra o **XAMPP Control Panel** (ou Laragon) e inicie **Apache** e **MySQL**.
3. Acesse no navegador: `http://localhost/FOLIVRO-v6`
   - **Inicialização 100% automática:** Ao abrir a página pela primeira vez em outro PC, o sistema cria sozinho o banco `folivro`, todas as tabelas, os livros/resenhas de demonstração e a conta administrativa inicial. Não é obrigatório importar nada manualmente no phpMyAdmin.
   - **Backup SQL opcional:** Caso queira importar manualmente no phpMyAdmin em outro ambiente, utilize o arquivo completo [`database/backup_completo_folivro.sql`](database/backup_completo_folivro.sql).

### Credenciais de acesso para apresentação
- **Conta Admin (com painel de moderação):**
  - **E-mail:** `admin@folivro.local`
  - **Senha:** `Admin123!`
- **Conta de Usuário Comum (Leitor):**
  - **E-mail:** `leitor@folivro.local`
  - **Senha:** `Leitor123!`

## Banco de dados

O projeto usa PHP com PDO MySQL e cria/aplica as migrações ao conectar pela primeira vez. Por padrão, a conexão local usa o banco `folivro` em `127.0.0.1:3306` (com fallback automático para `localhost`), usuário `root` sem senha. Para outra instalação com credenciais diferentes, configure `FOLIVRO_DB_HOST`, `FOLIVRO_DB_PORT`, `FOLIVRO_DB_NAME`, `FOLIVRO_DB_USER` e `FOLIVRO_DB_PASS` no ambiente do PHP/Apache.

## Publicação e direitos autorais

Declarações de autoria ou comercialização não liberam uma obra automaticamente. Solicitações de publicação ficam privadas até uma decisão humana registrada no painel de moderação. Obras com origem declarada como fanfic/adaptação sem autorização ou domínio público não verificado não podem ser aprovadas enquanto a base de direitos permanecer incerta. Alterações em obras já publicadas suspendem sua exibição e exigem nova revisão.

A busca da Open Library importa apenas metadados bibliográficos e conta com cache local embutido na pasta `cache/` para garantir carregamento rápido mesmo em redes lentas ou instáveis. Compras na loja são simulações acadêmicas e não solicitam dados reais de pagamento.
