<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

/*
 * Exclusão de conta por anonimização (LGPD, art. 18, VI).
 * Não usa DELETE em usuario porque compra/autor_dos_livros têm FK RESTRICT:
 * quem comprou uma obra continua com acesso a ela.
 */
$pdo = folivro_database();
folivro_require_login();
$uid = (int) $_SESSION['id_usuario'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !folivro_verify_csrf()) {
    header('Location: usuario.php?conta=erro');
    exit;
}

$senha = (string) ($_POST['senha_confirmacao'] ?? '');
$stmt = $pdo->prepare('SELECT senha_usuario, foto_usuario FROM usuario WHERE id_usuario = ?');
$stmt->execute([$uid]);
$userRow = $stmt->fetch() ?: [];
$hash = (string) ($userRow['senha_usuario'] ?? '');
if ($hash === '' || !password_verify($senha, $hash) || !isset($_POST['confirmo_exclusao'])) {
    header('Location: usuario.php?conta=senha');
    exit;
}

$pdo->beginTransaction();
try {
    // 1) Dados pessoais de leitura, bloqueios e mensagens ativas do chat não vinculadas a denúncias (minimização LGPD).
    foreach (['livraria', 'progressao_leitura', 'metas_leitura', 'curtidas', 'contador_pontos'] as $tabela) {
        $pdo->prepare("DELETE FROM {$tabela} WHERE id_usuario = ?")->execute([$uid]);
    }
    $pdo->prepare('DELETE FROM seguidores WHERE id_seguidor = ? OR id_seguido = ?')->execute([$uid, $uid]);
    $pdo->prepare('DELETE FROM bloqueios_usuario WHERE id_bloqueador = ? OR id_bloqueado = ?')->execute([$uid, $uid]);
    $pdo->prepare(
        "UPDATE chat_mensagens
            SET conteudo_mensagem = '', status_mensagem = 'apagada_pelo_autor'
          WHERE id_remetente = ?
            AND status_mensagem = 'ativa'
            AND id_mensagem NOT IN (
                SELECT item_denuncia FROM denuncias WHERE tipo_denuncias = 'mensagem_chat'
            )"
    )->execute([$uid]);

    // 2) Rascunhos privados nunca publicados são apagados; obras vendidas saem da loja mantendo acesso aos compradores.
    $pdo->prepare(
        "DELETE c FROM capitulos c
         JOIN historias h ON h.id_historias = c.id_historias
        WHERE h.id_usuario = ? AND c.status_capitulos = 'rascunho'"
    )->execute([$uid]);
    $pdo->prepare("UPDATE historias SET status_historias = 'despublicada' WHERE id_usuario = ?")
        ->execute([$uid]);
    $pdo->prepare("UPDATE autor_dos_livros SET status_autor_dos_livros = 'suspenso' WHERE id_autor = ? AND status_autor_dos_livros = 'a_venda'")
        ->execute([$uid]);
    $pdo->prepare("UPDATE autor_dos_livros SET status_autor_dos_livros = 'removido' WHERE id_autor = ? AND status_autor_dos_livros = 'rascunho'")
        ->execute([$uid]);

    // 3) Identificação substituída por dados anônimos; senha aleatória impede novo login.
    $pdo->prepare(
        "UPDATE usuario
            SET nome_usuario = 'Usuário removido',
                email_usuario = ?,
                senha_usuario = ?,
                biografia_usuario = NULL,
                foto_usuario = NULL,
                status_usuario = 'inativo'
          WHERE id_usuario = ?"
    )->execute(['removido-' . $uid . '@folivro.invalid', password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $uid]);

    $pdo->commit();

    // Remove do disco a foto de perfil enviada anteriormente (se houver).
    $fotoAntiga = (string) ($userRow['foto_usuario'] ?? '');
    if ($fotoAntiga !== '' && str_starts_with($fotoAntiga, 'uploads/perfis/')) {
        $caminhoArquivo = __DIR__ . '/' . $fotoAntiga;
        if (is_file($caminhoArquivo)) {
            @unlink($caminhoArquivo);
        }
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    header('Location: usuario.php?conta=erro');
    exit;
}

$_SESSION = [];
session_destroy();
header('Location: login.php?conta=excluida');
exit;
