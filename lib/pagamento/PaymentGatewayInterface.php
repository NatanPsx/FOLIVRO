<?php
declare(strict_types=1);

/**
 * Exceção de gateway com mensagem segura para exibir ao usuário
 * (nunca contém tokens, payloads ou detalhes internos).
 */
class PaymentGatewayException extends RuntimeException
{
}

/**
 * Contrato comum dos meios de pagamento da loja FOLIVRO.
 *
 * Todos os status retornados são NORMALIZADOS para o vocabulário do pedido:
 *   'pendente' | 'pago' | 'falhou' | 'cancelado' | 'reembolsado'
 *
 * Valores monetários sempre em centavos (int). O valor cobrado é SEMPRE
 * o do pedido gravado no servidor — nunca um valor enviado pelo navegador.
 */
interface PaymentGatewayInterface
{
    /** Identificador gravado em loja_pedidos.gateway (ex.: 'simulado', 'mercadopago'). */
    public function nome(): string;

    /** 'simulado' ou 'real' (gravado em loja_pedidos.modo_pagamento). */
    public function modo(): string;

    /**
     * Cria a cobrança no gateway.
     *
     * @param array $pedido Linha de loja_pedidos + 'comprador_email'.
     * @param array $dados  Dados do método (ex.: cenário simulado ou token do cartão gerado no navegador).
     * @return array{id_externo:string,status:string,status_detalhe:string,valor_centavos:?int,
     *               pix:?array{copia_cola:string,qr_base64:?string,ticket_url:?string,expira_em:?string},
     *               payload:array}
     */
    public function criarCobranca(array $pedido, array $dados): array;

    /**
     * Consulta o status atual diretamente no gateway (fonte de verdade).
     *
     * @return array{id_externo:string,status:string,status_detalhe:string,valor_centavos:?int,referencia:?string,payload:array}
     */
    public function consultarStatus(string $idExterno): array;

    /**
     * Solicita o reembolso total (ou parcial, quando $valorCentavos é informado).
     *
     * @return array{ok:bool,status:string,status_detalhe:string,payload:array}
     */
    public function reembolsar(string $idExterno, ?int $valorCentavos = null): array;

    /**
     * Valida a autenticidade da notificação recebida no webhook.
     *
     * @param array<string,string> $headers Cabeçalhos com nomes em minúsculas.
     * @param array<string,string> $query   Parâmetros da query string (nomes preservados, ex.: 'data.id').
     */
    public function validarWebhook(array $headers, string $body, array $query): bool;

    /** Extrai o id do pagamento referenciado pela notificação (ou null se não for evento de pagamento). */
    public function idRecursoWebhook(array $headers, string $body, array $query): ?string;
}

/**
 * Assinatura de webhooks no formato do Mercado Pago (cabeçalho x-signature):
 *   x-signature: ts=<timestamp>,v1=<hmac_sha256_hex>
 *   manifesto:   id:<data.id>;request-id:<x-request-id>;ts:<ts>;
 * O gateway simulado reutiliza o mesmo esquema para que os testes e a
 * demonstração percorram exatamente o mesmo caminho do modo real.
 */
final class WebhookSignature
{
    public static function manifesto(string $dataId, string $requestId, string $ts): string
    {
        // Pela documentação do Mercado Pago, ids alfanuméricos entram em minúsculas no manifesto.
        $id = ctype_alnum($dataId) ? strtolower($dataId) : $dataId;
        $m = '';
        if ($id !== '') {
            $m .= 'id:' . $id . ';';
        }
        if ($requestId !== '') {
            $m .= 'request-id:' . $requestId . ';';
        }
        if ($ts !== '') {
            $m .= 'ts:' . $ts . ';';
        }
        return $m;
    }

    public static function gerar(string $segredo, string $dataId, string $requestId, string $ts): string
    {
        $v1 = hash_hmac('sha256', self::manifesto($dataId, $requestId, $ts), $segredo);
        return 'ts=' . $ts . ',v1=' . $v1;
    }

    public static function validar(string $segredo, string $xSignature, string $requestId, string $dataId): bool
    {
        if ($segredo === '' || $xSignature === '' || $dataId === '') {
            return false; // falha fechada: sem segredo configurado, nenhuma notificação é aceita
        }
        $ts = '';
        $v1 = '';
        foreach (explode(',', $xSignature) as $parte) {
            $kv = explode('=', trim($parte), 2);
            if (count($kv) !== 2) {
                continue;
            }
            if ($kv[0] === 'ts') {
                $ts = trim($kv[1]);
            } elseif ($kv[0] === 'v1') {
                $v1 = strtolower(trim($kv[1]));
            }
        }
        if ($ts === '' || !ctype_digit($ts) || !preg_match('/^[a-f0-9]{64}$/', $v1)) {
            return false;
        }
        $esperado = hash_hmac('sha256', self::manifesto($dataId, $requestId, $ts), $segredo);
        return hash_equals($esperado, $v1);
    }

    /** Lê data.id da query (preservando o ponto) ou do corpo JSON. */
    public static function dataIdDaNotificacao(string $body, array $query): string
    {
        $id = $query['data.id'] ?? $query['id'] ?? '';
        if (!is_string($id) || $id === '') {
            $json = json_decode($body, true);
            $id = is_array($json) ? (string) ($json['data']['id'] ?? '') : '';
        }
        $id = trim((string) $id);
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) ? $id : '';
    }
}
