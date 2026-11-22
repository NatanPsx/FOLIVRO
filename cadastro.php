<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = folivro_database();
folivro_start_session();

/* Usuário já autenticado — redireciona */
if (!empty($_SESSION['id_usuario'])) {
    header('Location: home.php');
    exit;
}

$erro   = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $erro = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $nome           = trim((string) ($_POST['nome']            ?? ''));
        $email          = trim((string) ($_POST['email']           ?? ''));
        $dataNascimento = trim((string) ($_POST['data_nascimento'] ?? ''));
        $senha          = (string) ($_POST['senha']                ?? '');
        $confirmacao    = (string) ($_POST['confirmacao']          ?? '');

        $nomeLen = function_exists('mb_strlen') ? mb_strlen($nome, 'UTF-8') : strlen($nome);
        $idadeCadastro = folivro_calcular_idade($dataNascimento);

        if ($nome === '' || $email === '' || $dataNascimento === '' || $senha === '') {
            $erro = 'Preencha todos os campos obrigatórios, incluindo a data de nascimento.';
        } elseif ($nomeLen < 2 || $nomeLen > 120) {
            $erro = 'O nome deve ter entre 2 e 120 caracteres.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $erro = 'Informe um e-mail válido.';
        } elseif ($idadeCadastro === null || $idadeCadastro < 5 || $idadeCadastro > 120) {
            $erro = 'Informe uma data de nascimento válida.';
        } elseif (strlen($senha) < 8 || strlen($senha) > 255) {
            $erro = 'A senha deve ter no mínimo 8 caracteres.';
        } elseif ($senha !== $confirmacao) {
            $erro = 'As senhas não coincidem.';
        } elseif (!isset($_POST['aceite_termos'])) {
            $erro = 'Para criar a conta, aceite os termos de uso e a política de privacidade.';
        } else {
            /* Verifica e-mail duplicado */
            $check = $pdo->prepare('SELECT 1 FROM usuario WHERE email_usuario = ? LIMIT 1');
            $check->execute([$email]);
            if ($check->fetchColumn()) {
                $erro = 'Este e-mail já está cadastrado.';
            } else {
                /* Usuário e registro de aceite (LGPD) gravados juntos */
                $pdo->beginTransaction();
                try {
                    $insert = $pdo->prepare(
                        "INSERT INTO usuario (nome_usuario, email_usuario, data_nascimento_usuario, senha_usuario, nivel_usuario, status_usuario)
                         VALUES (?, ?, ?, ?, 'leitor', 'ativo')"
                    );
                    $insert->execute([$nome, $email, $dataNascimento, password_hash($senha, PASSWORD_DEFAULT)]);
                    $novoId = (int) $pdo->lastInsertId();
                    $aceite = $pdo->prepare('INSERT INTO termos_aceitos (id_usuario, tipo_termos, versao_termos) VALUES (?, ?, ?)');
                    $aceite->execute([$novoId, 'termos_uso', FOLIVRO_TERMOS_VERSAO]);
                    $aceite->execute([$novoId, 'privacidade', FOLIVRO_TERMOS_VERSAO]);
                    $pdo->commit();
                    $sucesso = 'Conta criada com sucesso!';
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $erro = 'Não foi possível criar a conta agora. Tente novamente.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar conta — FOLIVRO</title>
    <link rel="stylesheet" href="cadastro.css?v=20261022_11">
</head>
<body>
    <main class="container">
        <div class="logo"><img src="img/livro icon.png" height="60" alt="FOLIVRO"></div>
        <h1>Criar conta</h1>
        <p>Cadastre-se para ler, organizar e publicar obras.</p>

        <?php if ($sucesso !== ''): ?>
            <p class="mensagem-sucesso" role="status"><?= folivro_escape($sucesso) ?></p>
            <p><a href="login.php">Ir para o login</a></p>
        <?php else: ?>
            <?php if ($erro !== ''): ?>
                <p class="mensagem-erro" role="alert"><?= folivro_escape($erro) ?></p>
            <?php endif; ?>

            <form action="cadastro.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= folivro_escape(folivro_csrf_token()) ?>">

                <div class="campo">
                    <span aria-hidden="true"><img src="img/icones/person.png" alt="" class="icone-input-png"></span>
                    <label class="sr-only" for="nome">Nome completo</label>
                    <input id="nome" name="nome" type="text" placeholder="Nome completo"
                           autocomplete="name" maxlength="120" required
                           value="<?= folivro_escape($_POST['nome'] ?? '') ?>">
                </div>

                <div class="campo">
                    <span aria-hidden="true"><img src="img/icones/email.png" alt="" class="icone-input-png"></span>
                    <label class="sr-only" for="email">E-mail</label>
                    <input id="email" name="email" type="email" placeholder="E-mail"
                           autocomplete="email" maxlength="150" required
                           value="<?= folivro_escape($_POST['email'] ?? '') ?>">
                </div>

                <div class="bloco-data-nascimento">
                    <label class="rotulo-data-nascimento" for="data_nascimento">Data de nascimento (classificação indicativa)</label>
                    <div class="campo">
                        <span aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </span>
                        <input id="data_nascimento" name="data_nascimento" type="date"
                               autocomplete="bday" min="1905-01-01" max="<?= date('Y-m-d') ?>"
                               data-hoje="<?= date('Y-m-d') ?>" required
                               value="<?= folivro_escape($_POST['data_nascimento'] ?? '') ?>">
                    </div>
                    <small id="feedback-idade-cadastro" class="dica-idade-cadastro" aria-live="polite">
                        Informe sua data de nascimento. Caso tenha menos de 18 anos, obras +18 ficarão ocultas e bloqueadas.
                    </small>
                </div>

                <div class="campo">
                    <span aria-hidden="true"><img src="img/icones/lock.png" alt="" class="icone-input-png"></span>
                    <label class="sr-only" for="senha">Senha (8 a 255 caracteres)</label>
                    <input id="senha" name="senha" type="password" placeholder="Senha (mín. 8 caracteres)"
                           autocomplete="new-password" minlength="8" maxlength="255" required>
                </div>

                <div class="campo">
                    <span aria-hidden="true"><img src="img/icones/lock.png" alt="" class="icone-input-png"></span>
                    <label class="sr-only" for="confirmacao">Confirmar senha</label>
                    <input id="confirmacao" name="confirmacao" type="password" placeholder="Confirmar senha"
                           autocomplete="new-password" minlength="8" maxlength="255" required>
                </div>

                <label class="aceite-termos">
                    <input type="checkbox" name="aceite_termos" value="1" required <?= isset($_POST['aceite_termos']) ? 'checked' : '' ?>>
                    Li e aceito os <a href="termos.php#termos" target="_blank">termos de uso</a> e a <a href="termos.php#privacidade" target="_blank">política de privacidade</a>.
                </label>

                <div class="botoes">
                    <a href="login.php" class="voltar">← Voltar</a>
                    <button type="submit">Criar conta</button>
                </div>
            </form>
            <script>
                (() => {
                    const inputData = document.getElementById('data_nascimento');
                    const feedback = document.getElementById('feedback-idade-cadastro');
                    if (!inputData || !feedback) return;

                    function atualizarFeedbackIdade() {
                        const val = inputData.value;
                        if (!val) {
                            feedback.className = 'dica-idade-cadastro';
                            feedback.textContent = 'Informe sua data de nascimento. Caso tenha menos de 18 anos, obras +18 ficarão ocultas e bloqueadas.';
                            return;
                        }
                        const hojeStr = inputData.getAttribute('data-hoje') || new Date().toISOString().slice(0, 10);
                        const [anoH, mesH, diaH] = hojeStr.split('-').map(Number);
                        const [anoN, mesN, diaN] = val.split('-').map(Number);
                        if (!anoN || !mesN || !diaN) return;

                        let idade = anoH - anoN;
                        if (mesH < mesN || (mesH === mesN && diaH < diaN)) {
                            idade--;
                        }

                        if (idade < 0 || idade > 120) {
                            feedback.className = 'dica-idade-cadastro dica-idade-alerta';
                            feedback.textContent = 'Informe uma data de nascimento válida.';
                        } else if (idade < 18) {
                            feedback.className = 'dica-idade-cadastro dica-idade-menor';
                            feedback.textContent = `Idade: ${idade} anos (menor de 18 anos) — Livros com classificação +18 terão capa embaçada com olhinho bloqueado e título oculto.`;
                        } else {
                            feedback.className = 'dica-idade-cadastro dica-idade-maior';
                            feedback.textContent = `Idade: ${idade} anos (maior de 18 anos) — Acesso liberado a todas as classificações indicativas.`;
                        }
                    }

                    inputData.addEventListener('change', atualizarFeedbackIdade);
                    inputData.addEventListener('input', atualizarFeedbackIdade);
                    atualizarFeedbackIdade();
                })();
            </script>
        <?php endif; ?>
    </main>
</body>
</html>
