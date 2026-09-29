<?php
/**
 * Gateway Mercado Pago vía API REST.
 *  - Checkout Pro (preferencia + redirección): crearPreferencia()
 *  - Checkout Bricks (Payment Brick embebido): crearPagoDirecto()
 * Solo recibe tokens de tarjeta generados por el SDK de MP; nunca PAN/CVV.
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

    public function crearPagoDirecto(array $orden, array $datosPago, string $idempotencyKey, array $urls): array
    {
        if ($this->accessToken === '') {
            return ['success' => false, 'error' => 'MP_ACCESS_TOKEN no configurado'];
        }
        $metodo = trim((string) ($datosPago['payment_method_id'] ?? ''));
        if ($metodo === '' || !preg_match('/^[a-z0-9_]{2,40}$/i', $metodo)) {
            return ['success' => false, 'error' => 'Método de pago inválido', 'rechazo_definitivo' => true];
        }

        $email = trim((string) ($datosPago['payer']['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = (string) ($orden['email'] ?? '');
        }

        // Lista blanca: nada del navegador pasa sin revisar; el monto siempre es el de la orden.
        $body = [
            'transaction_amount' => round((float) $orden['total'], 2),
            'payment_method_id' => $metodo,
            'description' => 'Boletos · ' . mb_substr((string) ($orden['titulo_evento'] ?? 'Teatro Constitución'), 0, 200),
            'external_reference' => (string) $orden['codigo_publico'],
            'statement_descriptor' => 'TEATRO CONST',
            'payer' => ['email' => $email],
            'metadata' => [
                'id_orden' => (int) ($orden['id_orden'] ?? 0),
                'codigo_publico' => (string) $orden['codigo_publico'],
            ],
        ];

        $token = trim((string) ($datosPago['token'] ?? ''));
        if ($token !== '') {
            if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/', $token)) {
                return ['success' => false, 'error' => 'Token de pago inválido', 'rechazo_definitivo' => true];
            }
            $body['token'] = $token;
            $body['installments'] = max(1, min(24, (int) ($datosPago['installments'] ?? 1)));
            if (!empty($datosPago['issuer_id']) && ctype_digit((string) $datosPago['issuer_id'])) {
                $body['issuer_id'] = (int) $datosPago['issuer_id'];
            }
        }

        $ident = $datosPago['payer']['identification'] ?? null;
        if (is_array($ident) && !empty($ident['type']) && !empty($ident['number'])) {
            $body['payer']['identification'] = [
                'type' => mb_substr((string) $ident['type'], 0, 20),
                'number' => preg_replace('/[^A-Za-z0-9]/', '', (string) $ident['number']),
            ];
        }

        if (!empty($urls['notification'])) {
            $body['notification_url'] = $urls['notification'];
        }

        $res = $this->request('POST', '/v1/payments', $body, ['X-Idempotency-Key: ' . $idempotencyKey]);
        if (!$res['success']) {
            // 4xx = rechazo de validación del proveedor (no se creó cobro); 5xx/red = resultado incierto
            $code = (int) ($res['http_code'] ?? 0);
            $res['rechazo_definitivo'] = $code >= 400 && $code < 500;
            return $res;
        }
        return $this->normalizarPago($res['data'], '');
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
        return $this->normalizarPago($res['data'], $paymentId);
    }

    /**
     * Traduce estados de MP a estados internos.
     */
    public static function mapearEstado(string $status): string
    {
        $status = strtolower($status);
        if ($status === 'approved') {
            return 'PAID';
        }
        if (in_array($status, ['rejected', 'cancelled', 'charged_back'], true)) {
            return 'FAILED';
        }
        if ($status === 'refunded') {
            return 'REFUNDED';
        }
        return 'PENDING';
    }

    private function normalizarPago(array $data, string $paymentIdFallback): array
    {
        return [
            'success' => true,
            'estado_interno' => self::mapearEstado((string) ($data['status'] ?? '')),
            'status_detail' => (string) ($data['status_detail'] ?? ''),
            'ref_pago' => (string) ($data['id'] ?? $paymentIdFallback),
            'monto' => isset($data['transaction_amount']) ? (float) $data['transaction_amount'] : null,
            'moneda' => isset($data['currency_id']) ? (string) $data['currency_id'] : null,
            'external_reference' => $data['external_reference'] ?? null,
            'raw' => [
                'id' => $data['id'] ?? null,
                'status' => $data['status'] ?? null,
                'status_detail' => $data['status_detail'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'payment_type_id' => $data['payment_type_id'] ?? null,
                'transaction_amount' => $data['transaction_amount'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
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

    private function request(string $method, string $path, ?array $body = null, array $extraHeaders = []): array
    {
        $url = 'https://api.mercadopago.com' . $path;
        $ch = curl_init($url);
        $headers = array_merge([
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ], $extraHeaders);
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
            return ['success' => false, 'error' => 'cURL: ' . $err, 'http_code' => 0];
        }
        $data = json_decode((string) $raw, true);
        if ($code < 200 || $code >= 300) {
            $msg = is_array($data) ? ($data['message'] ?? $data['error'] ?? 'error') : 'respuesta no JSON';
            return ['success' => false, 'error' => 'MP HTTP ' . $code . ': ' . mb_substr((string) $msg, 0, 200), 'http_code' => $code];
        }
        return ['success' => true, 'data' => is_array($data) ? $data : []];
    }
}
