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
