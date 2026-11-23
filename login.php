<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
$pdo = folivro_database();
folivro_start_session();
if (!empty($_SESSION['id_usuario'])) {
    header('Location: home.php');
    exit;
}
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!folivro_verify_csrf()) {
        $erro = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $senha = (string) ($_POST['senha'] ?? '');
        if ($email !== '' && strlen($email) <= 150 && $senha !== '' && strlen($senha) <= 255) {
            $consulta = $pdo->prepare('SELECT id_usuario, nome_usuario, email_usuario, data_nascimento_usuario, senha_usuario, nivel_usuario, status_usuario FROM usuario WHERE email_usuario = ? LIMIT 1');
            $consulta->execute([$email]);
            $usuario = $consulta->fetch();
            if ($usuario && $usuario['status_usuario'] === 'ativo' && password_verify($senha, $usuario['senha_usuario'])) {
                session_regenerate_id(true);
                $_SESSION['id_usuario'] = (int) $usuario['id_usuario'];
                $_SESSION['nome_usuario'] = $usuario['nome_usuario'];
                $_SESSION['email_usuario'] = $usuario['email_usuario'];
                $_SESSION['nivel_usuario'] = $usuario['nivel_usuario'];
                $_SESSION['data_nascimento_usuario'] = $usuario['data_nascimento_usuario'] ?? null;
                header('Location: home.php');
                exit;
            }
        }
        $erro = 'E-mail ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — FOLIVRO</title>
    <link rel="stylesheet" href="login.css?v=20261123_01">
</head>
<body>
    <main class="container">
        <div class="logo"><?= folivro_icone('livro-fechado-f', 'folivro-icon-xl', 'FOLIVRO') ?></div>
        <h1>FOLIVRO</h1>
        <p>Leia, organize e compartilhe livros em um só lugar.</p>
        <?php if ($erro !== ''): ?><p class="mensagem-erro" role="alert"><?= folivro_escape($erro) ?></p><?php endif; ?>
        <?php if (($_GET['conta'] ?? '') === 'excluida'): ?><p class="mensagem-sucesso" role="status">Sua conta foi excluída e seus dados pessoais foram anonimizados.</p><?php endif; ?>
        <form action="login.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= folivro_escape(folivro_csrf_token()) ?>">
            <label class="sr-only" for="email">E-mail</label>
            <div class="campo">
                <span aria-hidden="true"><img src="img/icones/email.png" alt="" class="icone-input-png"></span>
                <input id="email" name="email" type="email" placeholder="E-mail" maxlength="150" autocomplete="username" required value="<?= folivro_escape($_POST['email'] ?? '') ?>">
            </div>
            <label class="sr-only" for="senha">Senha</label>
            <div class="campo">
                <span aria-hidden="true"><img src="img/icones/lock.png" alt="" class="icone-input-png"></span>
                <input id="senha" name="senha" type="password" placeholder="Senha" maxlength="255" autocomplete="current-password" required>
            </div>
            <div class="botoes">
                <a href="cadastro.php">Criar conta</a>
                <button type="submit">Entrar</button>
            </div>
        </form>
        <p class="links-rodape-login"><a href="termos.php">Termos de Uso, Direitos Autorais e Privacidade (LGPD)</a></p>
    </main>
</body>
</html>
