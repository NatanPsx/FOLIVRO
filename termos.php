<?php
declare(strict_types=1);
require_once __DIR__ . '/config/auth.php';
folivro_start_session();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Termos de uso e privacidade | FOLIVRO</title>
    <link rel="stylesheet" href="layout.css?v=20261123_03">
</head>

<body>
    <main class="conteudo termos">
        <p><a href="<?= empty($_SESSION['id_usuario']) ? 'cadastro.php' : 'usuario.php' ?>">← Voltar</a></p>
        <h1>Termos de uso, direitos autorais e política de privacidade</h1>
        <p><strong>Versão <?= FOLIVRO_TERMOS_VERSAO ?></strong> · FOLIVRO é um projeto acadêmico (TCC do curso Técnico em Desenvolvimento de Sistemas — ETEC Jardim Ângela). As compras da loja são <strong>simuladas</strong> e não envolvem pagamento real nem coleta de dados financeiros.</p>

        <h2 id="termos">1. Termos de uso e responsabilidade autoral (Lei nº 9.610/1998)</h2>
        <ul>
            <li>Para usar a plataforma é preciso criar uma conta com nome, e-mail e senha.</li>
            <li>O usuário é integralmente responsável pelo conteúdo que publica (histórias, capítulos, capas, comentários e resenhas).</li>
            <li><strong>Separação de permissões:</strong> A permissão para publicar gratuitamente é distinta da permissão para comercializar na loja. Para disponibilizar uma obra ao público, o usuário deve declarar titularidade ou autorização para reprodução e comunicação ao público; para comercializá-la (venda simulada), deve registrar declaração específica adicional cobrindo a comercialização.</li>
            <li><strong>Natureza declaratória:</strong> O registro eletrônico da declaração de autoria/comercialização documenta o compromisso do usuário com versão e data, mas <strong>não comprova, por si só, a titularidade jurídica nem elimina o risco de fraude</strong>, não substituindo análise por profissional habilitado.</li>
            <li><strong>Vedação a usos sem base legal verificada:</strong> Citação, resenha, uso educacional, <em>fanfic</em>, conteúdo transformativo, tradução não autorizada ou finalidade acadêmica de TCC <strong>não</strong> constituem autorização automática para publicar ou comercializar obras, capítulos ou capas de terceiros.</li>
            <li><strong>Domínio público e metadados de catálogo:</strong> Não se presume domínio público pela idade da obra ou disponibilidade gratuita na internet sem verificação da legislação e jurisdição aplicáveis, inclusive quanto a traduções e edições recentes. Dados bibliográficos da Open Library (título, autoria, ISBN e ano) servem apenas para organização de leitura e não autorizam reprodução de texto integral ou capas protegidas.</li>
            <li>Na loja acadêmica, o valor simulado é dividido em 80% para o autor e 20% para a plataforma, exclusivamente para fins de demonstração técnica, sem cobrança financeira real.</li>
        </ul>

        <h2 id="denuncia">2. Denúncia, suspensão preventiva, contestação e reincidência</h2>
        <ul>
            <li><strong>Notificação e triagem:</strong> Qualquer usuário pode denunciar suspeita de violação de direitos autorais informando a obra/autor original e as evidências pertinentes. A identidade do denunciante é preservada em sigilo e exibida exclusivamente à equipe de moderação.</li>
            <li><strong>Suspensão preventiva:</strong> Diante de denúncia plausível de violação de direitos autorais ou dúvida material sobre autorização, a venda e a distribuição pública do conteúdo são interrompidas preventivamente enquanto o caso aguarda análise humana.</li>
            <li><strong>Decisão fundamentada e bloqueio de republicação:</strong> A moderação registra justificativa fundamentada ao decidir pela procedência ou improcedente. Obras removidas têm sua impressão digital (hash SHA-256) bloqueada contra republicação automática.</li>
            <li><strong>Direito de contestação:</strong> O autor notificado pode apresentar esclarecimentos e contestação diretamente no painel de gerenciamento da obra para reavaliação humana. Reincidências em violações procedentes podem acarretar o bloqueio definitivo da conta.</li>
        </ul>

        <h2 id="privacidade">3. Política de privacidade e minimização de dados (LGPD — Lei nº 13.709/2018)</h2>
        <ul>
            <li><strong>Dados coletados (minimização):</strong> Nome, e-mail, senha (armazenada apenas como hash seguro), livros salvos, progresso de leitura, obras publicadas, compras simuladas, registros de aceite destes termos e histórico de moderação. Não solicitamos documentos pessoais excessivos nem dados reais de cartão ou pagamento.</li>
            <li><strong>Finalidade:</strong> Permitir o funcionamento da biblioteca pessoal, do painel do autor, da loja simulada e da governança de direitos autorais. Os dados não são vendidos nem compartilhados com terceiros.</li>
            <li><strong>Serviço externo:</strong> Buscas bibliográficas são enviadas ao catálogo público da Open Library, sem envio de dados pessoais do usuário.</li>
            <li><strong>Segurança:</strong> Senhas protegidas com <code>password_hash</code>, consultas preparadas (PDO), proteção contra CSRF e XSS, validação de uploads e controle estrito de acesso no servidor.</li>
            <li><strong>Direitos do titular e exclusão de conta:</strong> O usuário pode consultar seus dados no perfil e excluir a conta a qualquer momento. Na exclusão, nome e e-mail são anonimizados, a biblioteca pessoal e o progresso são apagados e as obras deixam de ser vendidas.</li>
        </ul>

        <h2>4. Marco Civil da Internet (Lei nº 12.965/2014) e limitação de escopo</h2>
        <p>A plataforma respeita a liberdade de expressão, a privacidade e os direitos autorais, atuando mediante triagem e remoção de conteúdos infringentes (<em>Notice and Takedown</em>). Os controles técnicos do FOLIVRO têm finalidade acadêmica e preventiva, não garantindo a conformidade jurídica absoluta de conteúdos submetidos por terceiros.</p>

        <h2 id="chat">5. Chat Literário: segurança, direitos autorais, privacidade e retenção</h2>
        <ul>
            <li><strong>Finalidade e proibição de pirataria:</strong> O chat literário destina-se a conversas sobre leitura e criação autoral. É estritamente proibido enviar, solicitar ou negociar cópias não autorizadas de livros, capítulos, arquivos PDF/EPUB, traduções, capas ou ilustrações protegidas. Nem a declaração de um usuário, nem o caráter privado da conversa, nem a finalidade acadêmica autorizam a reprodução ou o compartilhamento de obras protegidas sem permissão do(a) titular ou base legal verificada.</li>
            <li><strong>Ausência de anexos e proteção técnica:</strong> O envio de arquivos ou anexos não é permitido no chat. Links diretos de download de e-books são bloqueados automaticamente, links externos de compartilhamento são retidos preventivamente para análise humana e conteúdos já removidos pela moderação têm seu hash SHA-256 bloqueado contra reenvio.</li>
            <li><strong>Privacidade e quem pode acessar mensagens denunciadas:</strong> As conversas são privadas entre os dois participantes autenticados. Somente moderadores/administradores autenticados do FOLIVRO podem acessar o trecho específico de uma mensagem quando houver denúncia ou retenção preventiva de segurança.</li>
            <li><strong>Finalidade do acesso e prazo de retenção (180 dias):</strong> O acesso pela moderação ocorre exclusivamente para apurar denúncias de assédio, ameaça, spam ou violação de direitos autorais e aplicar medidas proporcionais (aviso, remoção de mensagem, restrição temporária do chat ou suspensão da conta). Os registros de evidência de moderação são mantidos sob sigilo por <strong>180 dias (6 meses)</strong>, aplicando minimização de dados conforme a LGPD e o Marco Civil da Internet.</li>
        </ul>
    </main>
    <script>
        if (localStorage.getItem('folivroTema') === 'escuro' || localStorage.getItem('folivro_tema') === 'escuro') {
            document.body.classList.add('tema-escuro');
        }
    </script>
</body>

</html>
