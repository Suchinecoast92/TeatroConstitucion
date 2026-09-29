<?php
/**
 * Gateway mock para desarrollo local sin credenciales MP.
 * Simula preferencia y permite marcar pagos vía API de prueba.
 */

require_once __DIR__ . '/PaymentGatewayInterface.php';

class MockPaymentGateway implements PaymentGatewayInterface
{
    public function nombreProveedor(): string
    {
        return 'mock';
    }

    public function crearPreferencia(array $orden, array $urls): array
    {
        $ref = 'MOCK-PREF-' . strtoupper(bin2hex(random_bytes(6)));
        $success = $urls['success'] ?? '';
        $sep = (strpos($success, '?') === false) ? '?' : '&';
        $init = $success . $sep . 'mock_pay=1&preference_id=' . rawurlencode($ref);

        return [
            'success' => true,
            'ref_externa' => $ref,
            'init_point' => $init,
            'sandbox_init_point' => $init,
            'raw' => ['mock' => true, 'id' => $ref],
        ];
    }

    /**
     * Simula POST /v1/payments. Misma clave de idempotencia → mismo pago (como MP).
     * Solo para pruebas: datosPago puede traer mock_result (approved|rejected|pending|in_process),
     * mock_monto y mock_external_reference para simular respuestas inconsistentes del proveedor.
     */
    public function crearPagoDirecto(array $orden, array $datosPago, string $idempotencyKey, array $urls): array
    {
        $metodo = trim((string) ($datosPago['payment_method_id'] ?? ''));
        if ($metodo === '') {
            return ['success' => false, 'error' => 'Método de pago inválido', 'rechazo_definitivo' => true];
        }
        $result = strtolower((string) ($datosPago['mock_result'] ?? 'approved'));
        $mapa = [
            'approved' => ['PAID', 'accredited'],
            'rejected' => ['FAILED', 'cc_rejected_other_reason'],
            'pending' => ['PENDING', 'pending_waiting_payment'],
            'in_process' => ['PENDING', 'pending_contingency'],
        ];
        [$estado, $detalle] = $mapa[$result] ?? $mapa['approved'];

        $refPago = 'MOCK-PAY-' . strtoupper(substr(hash('sha256', $idempotencyKey), 0, 12));
        $monto = isset($datosPago['mock_monto']) ? (float) $datosPago['mock_monto'] : round((float) $orden['total'], 2);
        $extRef = isset($datosPago['mock_external_reference'])
            ? (string) $datosPago['mock_external_reference']
            : (string) $orden['codigo_publico'];

        return [
            'success' => true,
            'estado_interno' => $estado,
            'status_detail' => $detalle,
            'ref_pago' => $refPago,
            'monto' => $monto,
            'moneda' => 'MXN',
            'external_reference' => $extRef,
            'raw' => ['mock' => true, 'id' => $refPago, 'status' => $result, 'payment_method_id' => $metodo],
        ];
    }

    public function consultarPago(string $paymentId): array
    {
        // En mock, paymentId puede ser MOCK-PAY-xxx; el estado lo decide PaymentService
        return [
            'success' => true,
            'estado_interno' => 'PAID',
            'ref_pago' => $paymentId,
            'monto' => null,
            'external_reference' => null,
            'raw' => ['mock' => true, 'id' => $paymentId],
        ];
    }

    public function reembolsarPago(string $paymentId, ?float $monto = null, ?string $motivo = null): array
    {
        return [
            'success' => true,
            'ref_reembolso' => 'MOCK-REF-' . strtoupper(bin2hex(random_bytes(4))),
            'raw' => [
                'mock' => true,
                'payment_id' => $paymentId,
                'amount' => $monto,
                'motivo' => $motivo,
            ],
        ];
    }
}
