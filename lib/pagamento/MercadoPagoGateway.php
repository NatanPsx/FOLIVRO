<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentGatewayInterface.php';

/**
 * Integração REAL com o Mercado Pago (API REST v1 via cURL — o projeto não usa Composer).
 *
 *  - Pix: POST /v1/payments com payment_method_id = 'pix' → QR Code (base64) + copia e cola.
 *  - Cartão: o navegador tokeniza o cartão com o SDK JS oficial (Card Payment Brick).
 *    O servidor recebe APENAS o token de uso único — número, validade e CVV nunca
 *    trafegam pelo FOLIVRO nem são gravados.
 *  - Consulta: GET /v1/payments/{id}  |  Reembolso: POST /v1/payments/{id}/refunds
 *  - Webhook: assinatura HMAC-SHA256 do cabeçalho x-signature (segredo do painel do MP).
 *
 * Por segurança, apenas credenciais de TESTE (prefixo TEST-) são aceitas, a menos que
 * MP_ALLOW_PRODUCTION=true esteja explicitamente configurado no .env.
 */
final class MercadoPagoGateway implements PaymentGatewayInterface
{
    private const API = 'https://api.mercadopago.com';

    private string $accessToken;
    private string $segredoWebhook;
    private string $urlNotificacao;
    private int $pixExpiracaoMin;
    /** @var callable|null fn(string $metodo, string $url, array $headers, ?string $corpo): array{0:int,1:string} */
    private $http;

    public function __construct(array $config, ?callable $http = null)
    {
        $this->accessToken = trim((string) ($config['access_token'] ?? ''));
        $this->segredoWebhook = (string) ($config['webhook_secret'] ?? '');
        $this->urlNotificacao = (string) ($config['notification_url'] ?? '');
        $this->pixExpiracaoMin = max(30, (int) ($config['pix_expiracao_min'] ?? 30)); // MP exige no mínimo 30 min
        $this->http = $http;

        if ($this->accessToken === '') {
            throw new PaymentGatewayException('Pagamento real indisponível: MP_ACCESS_TOKEN não configurado no .env.');
        }
        if (!self::ehCredencialTeste($this->accessToken) && empty($config['permitir_producao'])) {
            throw new PaymentGatewayException('Pagamento real bloqueado: use credenciais de TESTE (TEST-...) ou defina MP_ALLOW_PRODUCTION=true conscientemente.');
        }
    }

    public static function ehCredencialTeste(string $token): bool
    {
        return str_starts_with(trim($token), 'TEST-');
    }

    public function nome(): string
    {
        return 'mercadopago';
    }

    public function modo(): string
    {
        return 'real';
    }

    public function criarCobranca(array $pedido, array $dados): array
    {
        $metodo = (string) ($pedido['metodo_pagamento'] ?? '');
        $email = (string) ($pedido['comprador_email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new PaymentGatewayException('E-mail do comprador inválido para emissão da cobrança.');
        }
        $corpo = [
            // Valor SEMPRE do pedido gravado no servidor.
            'transaction_amount' => round(((int) $pedido['valor_bruto_centavos']) / 100, 2),
            'description' => mb_substr('FOLIVRO - ' . (string) $pedido['titulo_produto_snapshot'], 0, 120, 'UTF-8'),
            'external_reference' => (string) $pedido['codigo_pedido'],
            'metadata' => ['codigo_pedido' => (string) $pedido['codigo_pedido']],
        ];
        if ($this->urlNotificacao !== '' && str_starts_with($this->urlNotificacao, 'https://')) {
            $corpo['notification_url'] = $this->urlNotificacao;
        }

        if ($metodo === 'pix') {
            $expira = new DateTimeImmutable('+' . $this->pixExpiracaoMin . ' minutes');
            $corpo['payment_method_id'] = 'pix';
            $corpo['date_of_expiration'] = $expira->format('Y-m-d\TH:i:s.vP');
            $corpo['payer'] = ['email' => $email];
        } elseif ($metodo === 'cartao') {
            $token = (string) ($dados['token'] ?? '');
            $pmId = (string) ($dados['payment_method_id'] ?? '');
            $parcelas = (int) ($dados['installments'] ?? 1);
            if (!preg_match('/^[A-Za-z0-9]{16,64}$/', $token) || !preg_match('/^[a-z_]{2,30}$/', $pmId) || $parcelas < 1 || $parcelas > 12) {
                throw new PaymentGatewayException('Dados do cartão tokenizado inválidos. Recarregue a página e tente novamente.');
            }
            $corpo['token'] = $token;
            $corpo['installments'] = $parcelas;
            $corpo['payment_method_id'] = $pmId;
            if (!empty($dados['issuer_id']) && ctype_digit((string) $dados['issuer_id'])) {
                $corpo['issuer_id'] = (int) $dados['issuer_id'];
            }
            $payer = ['email' => $email];
            $tipoDoc = strtoupper((string) ($dados['identification_type'] ?? ''));
            $numDoc = preg_replace('/\D/', '', (string) ($dados['identification_number'] ?? ''));
            if (in_array($tipoDoc, ['CPF', 'CNPJ'], true) && $numDoc !== '' && strlen($numDoc) <= 14) {
                $payer['identification'] = ['type' => $tipoDoc, 'number' => $numDoc];
            }
            $corpo['payer'] = $payer;
            $corpo['statement_descriptor'] = 'FOLIVRO';
        } else {
            throw new PaymentGatewayException('Método de pagamento não suportado.');
        }

        [$status, $resp] = $this->requisicao('POST', '/v1/payments', $corpo, 'pedido-' . $pedido['codigo_pedido'] . '-' . $metodo);
        if ($status < 200 || $status >= 300 || !isset($resp['id'])) {
            throw new PaymentGatewayException('O Mercado Pago não aceitou a cobrança (' . $status . '). Verifique os dados e tente novamente.');
        }

        $pix = null;
        if ($metodo === 'pix') {
            $td = $resp['point_of_interaction']['transaction_data'] ?? [];
            $expiraResp = isset($resp['date_of_expiration']) ? strtotime((string) $resp['date_of_expiration']) : false;
            $pix = [
                'copia_cola' => (string) ($td['qr_code'] ?? ''),
                'qr_base64' => isset($td['qr_code_base64']) ? (string) $td['qr_code_base64'] : null,
                'ticket_url' => isset($td['ticket_url']) ? (string) $td['ticket_url'] : null,
                'expira_em' => $expiraResp ? date('Y-m-d H:i:s', $expiraResp) : date('Y-m-d H:i:s', time() + $this->pixExpiracaoMin * 60),
            ];
        }

        return [
            'id_externo' => (string) $resp['id'],
            'status' => self::normalizar((string) ($resp['status'] ?? '')),
            'status_detalhe' => (string) ($resp['status_detail'] ?? ''),
            'valor_centavos' => isset($resp['transaction_amount']) ? (int) round(((float) $resp['transaction_amount']) * 100) : null,
            'pix' => $pix,
            'payload' => self::sanitizar($resp),
        ];
    }

    public function consultarStatus(string $idExterno): array
    {
        if (!ctype_digit($idExterno)) {
            throw new PaymentGatewayException('Identificador de pagamento inválido.');
        }
        [$status, $resp] = $this->requisicao('GET', '/v1/payments/' . $idExterno, null, null);
        if ($status !== 200 || !isset($resp['id'])) {
            throw new PaymentGatewayException('Não foi possível consultar o pagamento no Mercado Pago (' . $status . ').');
        }
        return [
            'id_externo' => (string) $resp['id'],
            'status' => self::normalizar((string) ($resp['status'] ?? '')),
            'status_detalhe' => (string) ($resp['status_detail'] ?? ''),
            'valor_centavos' => isset($resp['transaction_amount']) ? (int) round(((float) $resp['transaction_amount']) * 100) : null,
            'referencia' => isset($resp['external_reference']) ? (string) $resp['external_reference'] : null,
            'payload' => self::sanitizar($resp),
        ];
    }

    public function reembolsar(string $idExterno, ?int $valorCentavos = null): array
    {
        if (!ctype_digit($idExterno)) {
            throw new PaymentGatewayException('Identificador de pagamento inválido.');
        }
        $corpo = $valorCentavos !== null ? ['amount' => round($valorCentavos / 100, 2)] : new stdClass();
        [$status, $resp] = $this->requisicao('POST', '/v1/payments/' . $idExterno . '/refunds', $corpo, 'reembolso-' . $idExterno);
        $ok = $status >= 200 && $status < 300;
        return [
            'ok' => $ok,
            'status' => $ok ? 'reembolsado' : 'pago',
            'status_detalhe' => $ok ? (string) ($resp['status'] ?? 'approved') : 'refund_error_' . $status,
            'payload' => ['refund_id' => $resp['id'] ?? null, 'status' => $resp['status'] ?? null, 'http' => $status],
        ];
    }

    public function validarWebhook(array $headers, string $body, array $query): bool
    {
        return WebhookSignature::validar(
            $this->segredoWebhook,
            (string) ($headers['x-signature'] ?? ''),
            (string) ($headers['x-request-id'] ?? ''),
            WebhookSignature::dataIdDaNotificacao($body, $query)
        );
    }

    public function idRecursoWebhook(array $headers, string $body, array $query): ?string
    {
        $json = json_decode($body, true);
        $tipo = (string) ($query['type'] ?? $query['topic'] ?? (is_array($json) ? ($json['type'] ?? $json['topic'] ?? '') : ''));
        if ($tipo !== '' && $tipo !== 'payment') {
            return null;
        }
        $id = WebhookSignature::dataIdDaNotificacao($body, $query);
        return ($id !== '' && ctype_digit($id)) ? $id : null;
    }

    public static function normalizar(string $statusMp): string
    {
        switch ($statusMp) {
            case 'approved':
                return 'pago';
            case 'rejected':
                return 'falhou';
            case 'cancelled':
                return 'cancelado';
            case 'refunded':
            case 'charged_back':
                return 'reembolsado';
            default: // pending, in_process, authorized, in_mediation
                return 'pendente';
        }
    }

    /** Mantém apenas campos não sensíveis (sem payer, card, documentos ou QR em base64). */
    public static function sanitizar(array $resp): array
    {
        $permitidos = ['id', 'status', 'status_detail', 'transaction_amount', 'currency_id', 'payment_method_id',
            'payment_type_id', 'external_reference', 'date_created', 'date_approved', 'date_last_updated',
            'date_of_expiration', 'live_mode', 'installments'];
        return array_intersect_key($resp, array_flip($permitidos));
    }

    /** @return array{0:int,1:array} */
    private function requisicao(string $metodo, string $caminho, $corpo, ?string $chaveIdempotencia): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($chaveIdempotencia !== null) {
            $headers[] = 'X-Idempotency-Key: ' . substr(hash('sha256', $chaveIdempotencia), 0, 64);
        }
        $json = $corpo === null ? null : (string) json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $url = self::API . $caminho;

        if ($this->http !== null) {
            [$status, $raw] = ($this->http)($metodo, $url, $headers, $json);
        } else {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $metodo,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            if ($json !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            }
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $erro = curl_errno($ch);
            curl_close($ch);
            if ($raw === false || $erro !== 0) {
                throw new PaymentGatewayException('Falha de comunicação com o Mercado Pago. Tente novamente em instantes.');
            }
        }
        $dados = json_decode((string) $raw, true);
        return [(int) $status, is_array($dados) ? $dados : []];
    }
}
