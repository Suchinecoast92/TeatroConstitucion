<?php
/**
 * Contrato de pasarela de pago (Mercado Pago, Stripe, etc.).
 */

interface PaymentGatewayInterface
{
    /**
     * @param array $orden Orden con items y totales
     * @param array $urls  success, failure, pending, notification
     * @return array{success:bool,ref_externa?:string,init_point?:string,sandbox_init_point?:string,raw?:array,error?:string}
     */
    public function crearPreferencia(array $orden, array $urls): array;

    /**
     * Consulta un pago en el proveedor.
     * @return array{success:bool,estado_interno?:string,ref_pago?:string,monto?:float,raw?:array,error?:string}
     */
    public function consultarPago(string $paymentId): array;

    /**
     * Reembolso total (o parcial si monto > 0) de un pago del proveedor.
     * @return array{success:bool,ref_reembolso?:string,raw?:array,error?:string}
     */
    public function reembolsarPago(string $paymentId, ?float $monto = null, ?string $motivo = null): array;

    public function nombreProveedor(): string;
}
