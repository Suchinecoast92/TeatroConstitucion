<?php
/**
 * Gateway Mercado Pago (Checkout Pro) vía API REST.
 * No guarda ni solicita datos de tarjeta.
 */

require_once __DIR__ . '/PaymentGatewayInterface.php';

class MercadoPagoGateway implements PaymentGatewayInterface
{
    private string $accessToken;

    public function __construct(?string $accessToken = null)
    {
        require_once dirname(__DIR__, 2) . '/config/env.php';
        $this->accessToken = $accessToken ?: (string) teatro_env('MP_ACCESS_TOKEN', '');
    }

    public function nombreProveedor(): string
    {
        return 'mercadopago';
    }

    public function crearPreferencia(array $orden, array $urls): array
    {
        if ($this->accessToken === '') {
            return ['success' => false, 'error' => 'MP_ACCESS_TOKEN no configurado'];
        }

        $items = [];
        foreach ($orden['items'] as $it) {
            $items[] = [
                'id' => $it['codigo_asiento'],
                'title' => 'Boleto ' . $it['codigo_asiento'] . ' · ' . ($orden['titulo_evento'] ?? 'Teatro'),
                'quantity' => 1,
                'currency_id' => 'MXN',
                'unit_price' => round((float) $it['precio_final'], 2),
            ];
        }
        if (!$items) {
            // Un solo ítem con el total de la orden
            $items[] = [
                'id' => $orden['codigo_publico'],
                'title' => 'Boletos Teatro Constitución',
                'quantity' => 1,
                'currency_id' => 'MXN',
                'unit_price' => round((float) $orden['total'], 2),
            ];
        }

        $body = [
            'items' => $items,
            'payer' => [
                'name' => $orden['nombre'] ?? '',
                'email' => $orden['email'] ?? '',
            ],
            'external_reference' => $orden['codigo_publico'],
            'notification_url' => $urls['notification'] ?? null,
            'back_urls' => [
                'success' => $urls['success'] ?? '',
                'failure' => $urls['failure'] ?? '',
                'pending' => $urls['pending'] ?? '',
            ],
            'auto_return' => 'approved',
            'statement_descriptor' => 'TEATRO CONST',
            'metadata' => [
                'id_orden' => (int) ($orden['id_orden'] ?? 0),
                'codigo_publico' => $orden['codigo_publico'] ?? '',
            ],
        ];

        // Quitar notification_url nula (MP a veces rechaza)
        if (empty($body['notification_url'])) {
            unset($body['notification_url']);
        }

        $res = $this->request('POST', '/checkout/preferences', $body);
        if (!$res['success']) {
            return $res;
        }
        $data = $res['data'];
        return [
            'success' => true,
            'ref_externa' => (string) ($data['id'] ?? ''),
            'init_point' => $data['init_point'] ?? null,
            'sandbox_init_point' => $data['sandbox_init_point'] ?? null,
            'raw' => [
                'id' => $data['id'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
            ],
        ];
    }

    public function consultarPago(string $paymentId): array
    {
        if ($this->accessToken === '') {
            return ['success' => false, 'error' => 'MP_ACCESS_TOKEN no configurado'];
        }
        $res = $this->request('GET', '/v1/payments/' . rawurlencode($paymentId));
        if (!$res['success']) {
            return $res;
        }
        $data = $res['data'];
        $status = strtolower((string) ($data['status'] ?? ''));
        $interno = 'PENDING';
        if ($status === 'approved') {
            $interno = 'PAID';
        } elseif (in_array($status, ['rejected', 'cancelled', 'charged_back'], true)) {
            $interno = 'FAILED';
        } elseif ($status === 'refunded') {
            $interno = 'REFUNDED';
        }

        return [
            'success' => true,
            'estado_interno' => $interno,
            'ref_pago' => (string) ($data['id'] ?? $paymentId),
            'monto' => isset($data['transaction_amount']) ? (float) $data['transaction_amount'] : null,
            'external_reference' => $data['external_reference'] ?? null,
            'raw' => [
                'id' => $data['id'] ?? null,
                'status' => $data['status'] ?? null,
                'status_detail' => $data['status_detail'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
            ],
        ];
    }

    public function reembolsarPago(string $paymentId, ?float $monto = null, ?string $motivo = null): array
    {
        if ($this->accessToken === '') {
            return ['success' => false, 'error' => 'MP_ACCESS_TOKEN no configurado'];
        }
        $body = null;
        if ($monto !== null && $monto > 0) {
            $body = ['amount' => round($monto, 2)];
        } else {
            $body = []; // reembolso total
        }
        $res = $this->request('POST', '/v1/payments/' . rawurlencode($paymentId) . '/refunds', $body);
        if (!$res['success']) {
            return $res;
        }
        $data = $res['data'];
        return [
            'success' => true,
            'ref_reembolso' => (string) ($data['id'] ?? ''),
            'raw' => [
                'id' => $data['id'] ?? null,
                'status' => $data['status'] ?? null,
                'amount' => $data['amount'] ?? null,
                'motivo' => $motivo,
            ],
        ];
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = 'https://api.mercadopago.com' . $path;
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return ['success' => false, 'error' => 'cURL: ' . $err];
        }
        $data = json_decode((string) $raw, true);
        if ($code < 200 || $code >= 300) {
            $msg = is_array($data) ? ($data['message'] ?? $data['error'] ?? $raw) : $raw;
            return ['success' => false, 'error' => 'MP HTTP ' . $code . ': ' . $msg, 'data' => $data];
        }
        return ['success' => true, 'data' => is_array($data) ? $data : []];
    }
}
