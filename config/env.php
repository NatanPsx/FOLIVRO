<?php
declare(strict_types=1);

/**
 * Leitor mínimo de variáveis de ambiente para o FOLIVRO.
 *
 * Ordem de precedência:
 *   1) valores sobrescritos em tempo de execução (folivro_env_set — usado pelos testes);
 *   2) variáveis reais do ambiente do servidor (getenv / SetEnv do Apache);
 *   3) arquivo .env na raiz do projeto (nunca versionado — ver .env.example).
 *
 * Nenhuma chave/segredo deve ser escrita no código-fonte.
 */
function folivro_env_arquivo(): array
{
    static $valores = null;
    if (is_array($valores)) {
        return $valores;
    }
    $valores = [];
    $arquivo = getenv('FOLIVRO_ENV_FILE') ?: dirname(__DIR__) . '/.env';
    if (!is_file($arquivo) || !is_readable($arquivo)) {
        return $valores;
    }
    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) {
            continue;
        }
        [$chave, $valor] = explode('=', $linha, 2);
        $chave = trim($chave);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $chave)) {
            continue;
        }
        $valor = trim($valor);
        // Remove comentário no fim da linha quando o valor não está entre aspas.
        if ($valor !== '' && $valor[0] !== '"' && $valor[0] !== "'" && str_contains($valor, ' #')) {
            $valor = trim(substr($valor, 0, (int) strpos($valor, ' #')));
        }
        if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && substr($valor, -1) === $valor[0]) {
            $valor = substr($valor, 1, -1);
        }
        $valores[$chave] = $valor;
    }
    return $valores;
}

/** @var array<string, string|null> Sobrescritas em memória (testes automatizados). */
$GLOBALS['__folivro_env_override'] = $GLOBALS['__folivro_env_override'] ?? [];

function folivro_env(string $chave, ?string $padrao = null): ?string
{
    if (array_key_exists($chave, $GLOBALS['__folivro_env_override'])) {
        $v = $GLOBALS['__folivro_env_override'][$chave];
        return ($v === null || $v === '') ? $padrao : $v;
    }
    $real = getenv($chave);
    if ($real !== false && $real !== '') {
        return $real;
    }
    $arquivo = folivro_env_arquivo();
    if (isset($arquivo[$chave]) && $arquivo[$chave] !== '') {
        return $arquivo[$chave];
    }
    return $padrao;
}

/** Sobrescreve uma variável apenas na requisição/processo atual (uso em testes). */
function folivro_env_set(string $chave, ?string $valor): void
{
    $GLOBALS['__folivro_env_override'][$chave] = $valor;
}
