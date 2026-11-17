<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentGatewayInterface.php';

/**
 * Gateway SIMULADO (demonstração acadêmica — nenhum dinheiro real).
 *
 * A tabela loja_gateway_simulado faz o papel do "servidor do PSP": a loja cria a
 * cobrança, consulta o status e recebe webhooks exatamente como faria com o
 * Mercado Pago. Cenários disponíveis:
 *   - pix              : Pix pendente (QR/código fictício) até "Confirmar pagamento simulado"
 *   - pix_expira       : Pix pendente com expiração curta (demonstra o fluxo de expiração)
 *   - cartao_aprovado  : cartão aprovado na hora
 *   - cartao_recusado  : cartão recusado (saldo insuficiente simulado)
 * Nenhum dado de cartão é solicitado ou recebido no modo simulado.
 */
final class SimulatedGateway implements PaymentGatewayInterface
{
    public const CENARIOS = [
        'pix' => 'Pix — aguardando confirmação',
        'pix_expira' => 'Pix — expira em instantes (demonstração de expiração)',
        'cartao_aprovado' => 'Cartão — aprovado',
        'cartao_recusado' => 'Cartão — recusado',
    ];

    private PDO $pdo;
    private string $segredoWebhook;
    private int $pixExpiracaoMin;
    private int $pixExpiraCurtaSeg;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->segredoWebhook = (string) ($config['webhook_secret'] ?? '');
        $this->pixExpiracaoMin = max(1, (int) ($config['pix_expiracao_min'] ?? 15));
        $this->pixExpiraCurtaSeg = max(5, (int) ($config['pix_expira_curta_seg'] ?? 60));
    }

    public function nome(): string
    {
        return 'simulado';
    }

    public function modo(): string
    {
        return 'simulado';
    }

    public static function metodoDoCenario(string $cenario): ?string
    {
        if (in_array($cenario, ['pix', 'pix_expira'], true)) {
            return 'pix';
        }
        if (in_array($cenario, ['cartao_aprovado', 'cartao_recusado'], true)) {
            return 'cartao';
        }
        return null;
    }

    public function criarCobranca(array $pedido, array $dados): array
    {
        $cenario = (string) ($dados['cenario'] ?? '');
        $metodo = self::metodoDoCenario($cenario);
        if ($metodo === null || $metodo !== ($pedido['metodo_pagamento'] ?? '')) {
            throw new PaymentGatewayException('Cenário de pagamento simulado inválido para este método.');
        }
        $valor = (int) $pedido['valor_bruto_centavos'];
        $ref = (string) $pedido['codigo_pedido'];
        $idExterno = 'SIM-' . strtoupper(bin2hex(random_bytes(8)));

        $statusRemoto = 'pending';
        $detalhe = 'pending_waiting_transfer';
        $expira = null;
        if ($cenario === 'pix') {
            $expira = date('Y-m-d H:i:s', time() + $this->pixExpiracaoMin * 60);
        } elseif ($cenario === 'pix_expira') {
            $expira = date('Y-m-d H:i:s', time() + $this->pixExpiraCurtaSeg);
        } elseif ($cenario === 'cartao_aprovado') {
            $statusRemoto = 'approved';
            $detalhe = 'accredited';
        } else {
            $statusRemoto = 'rejected';
            $detalhe = 'cc_rejected_insufficient_amount';
        }

        $this->pdo->prepare(
            'INSERT INTO loja_gateway_simulado (id_externo, referencia, metodo, valor_centavos, status_remoto, status_detalhe, expira_em)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$idExterno, $ref, $metodo, $valor, $statusRemoto, $detalhe, $expira]);

        $pix = null;
        if ($metodo === 'pix') {
            $pix = [
                'copia_cola' => self::pixCopiaColaFicticio($valor, $ref),
                'qr_base64' => null,
                'ticket_url' => null,
                'expira_em' => $expira,
            ];
        }

        return [
            'id_externo' => $idExterno,
            'status' => self::normalizar($statusRemoto),
            'status_detalhe' => $detalhe,
            'valor_centavos' => $valor,
            'pix' => $pix,
            'payload' => ['id' => $idExterno, 'status' => $statusRemoto, 'status_detail' => $detalhe, 'cenario' => $cenario, 'external_reference' => $ref, 'transaction_amount_cents' => $valor],
        ];
    }

    public function consultarStatus(string $idExterno): array
    {
        $row = $this->buscar($idExterno);
        if (!$row) {
            throw new PaymentGatewayException('Pagamento simulado não encontrado.');
        }
        // A expiração é decidida pelo "PSP": Pix pendente vencido passa a cancelado.
        if ($row['status_remoto'] === 'pending' && $row['expira_em'] !== null && strtotime((string) $row['expira_em']) <= time()) {
            $this->pdo->prepare("UPDATE loja_gateway_simulado SET status_remoto = 'cancelled', status_detalhe = 'expired' WHERE id_externo = ? AND status_remoto = 'pending'")
                ->execute([$idExterno]);
            $row = $this->buscar($idExterno);
        }
        return [
            'id_externo' => $idExterno,
            'status' => self::normalizar((string) $row['status_remoto']),
            'status_detalhe' => (string) $row['status_detalhe'],
            'valor_centavos' => (int) $row['valor_centavos'],
            'referencia' => (string) $row['referencia'],
            'payload' => ['id' => $idExterno, 'status' => $row['status_remoto'], 'status_detail' => $row['status_detalhe'], 'external_reference' => $row['referencia']],
        ];
    }

    /** Ação do botão "Confirmar pagamento simulado" (equivale ao leitor pagar o Pix no app do banco). */
    public function simularPagamentoPix(string $idExterno): bool
    {
        $this->consultarStatus($idExterno); // aplica expiração antes de aceitar
        $st = $this->pdo->prepare(
            "UPDATE loja_gateway_simulado SET status_remoto = 'approved', status_detalhe = 'accredited'
              WHERE id_externo = ? AND status_remoto = 'pending' AND metodo = 'pix'"
        );
        $st->execute([$idExterno]);
        return $st->rowCount() === 1;
    }

    public function reembolsar(string $idExterno, ?int $valorCentavos = null): array
    {
        $row = $this->buscar($idExterno);
        if (!$row) {
            throw new PaymentGatewayException('Pagamento simulado não encontrado.');
        }
        if ($row['status_remoto'] === 'refunded') {
            return ['ok' => true, 'status' => 'reembolsado', 'status_detalhe' => 'refunded', 'payload' => ['id' => $idExterno, 'status' => 'refunded']];
        }
        if ($row['status_remoto'] !== 'approved') {
            return ['ok' => false, 'status' => self::normalizar((string) $row['status_remoto']), 'status_detalhe' => 'not_refundable', 'payload' => ['id' => $idExterno, 'status' => $row['status_remoto']]];
        }
        $this->pdo->prepare("UPDATE loja_gateway_simulado SET status_remoto = 'refunded', status_detalhe = 'refunded' WHERE id_externo = ?")
            ->execute([$idExterno]);
        return ['ok' => true, 'status' => 'reembolsado', 'status_detalhe' => 'refunded', 'payload' => ['id' => $idExterno, 'status' => 'refunded']];
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
        $id = WebhookSignature::dataIdDaNotificacao($body, $query);
        return ($id !== '' && str_starts_with($id, 'SIM-')) ? $id : null;
    }

    private function buscar(string $idExterno): ?array
    {
        if (!preg_match('/^SIM-[A-F0-9]{16}$/', $idExterno)) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM loja_gateway_simulado WHERE id_externo = ? LIMIT 1');
        $st->execute([$idExterno]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function normalizar(string $statusRemoto): string
    {
        switch ($statusRemoto) {
            case 'approved':
                return 'pago';
            case 'rejected':
                return 'falhou';
            case 'cancelled':
                return 'cancelado';
            case 'refunded':
                return 'reembolsado';
            default:
                return 'pendente';
        }
    }

    /**
     * Gera um código "copia e cola" no formato EMV do Pix, porém FICTÍCIO
     * (chave .invalid): serve só para ilustrar a interface; não é pagável.
     */
    public static function pixCopiaColaFicticio(int $valorCentavos, string $referencia): string
    {
        $campo = static function (string $id, string $valor): string {
            return $id . str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT) . $valor;
        };
        $conta = $campo('00', 'BR.GOV.BCB.PIX') . $campo('01', 'pix-simulado@folivro.invalid');
        $ref = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($referencia)) ?: 'SIMULADO', 0, 25);
        $payload = $campo('00', '01')
            . $campo('26', $conta)
            . $campo('52', '0000')
            . $campo('53', '986')
            . $campo('54', number_format($valorCentavos / 100, 2, '.', ''))
            . $campo('58', 'BR')
            . $campo('59', 'FOLIVRO SIMULADO')
            . $campo('60', 'SAO PAULO')
            . $campo('62', $campo('05', $ref))
            . '6304';
        return $payload . self::crc16($payload);
    }

    private static function crc16(string $dados): string
    {
        $crc = 0xFFFF;
        $len = strlen($dados);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($dados[$i]) << 8;
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
