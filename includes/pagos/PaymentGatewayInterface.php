<?php
/**
 * Contrato de pasarela de pago (Mercado Pago, Stripe, etc.).
 *
 * Estados internos que devuelve cualquier gateway: PENDING | PAID | FAILED | REFUNDED.
 * El resto del sistema nunca depende de los estados propios del proveedor.
 */

interface PaymentGatewayInterface
{
    /**
     * Checkout con redirección (Checkout Pro / preferencia).
     * @param array $orden Orden con items y totales
     * @param array $urls  success, failure, pending, notification
     * @return array{success:bool,ref_externa?:string,init_point?:string,sandbox_init_point?:string,raw?:array,error?:string}
     */
    public function crearPreferencia(array $orden, array $urls): array;

    /**
     * Pago directo desde un formulario embebido (Checkout Bricks).
     * El navegador solo aporta datos tokenizados por el proveedor; el monto sale de $orden.
     *
     * @param array  $orden          Orden validada por backend (total, codigo_publico, email…)
     * @param array  $datosPago      Datos tokenizados del Brick (token, payment_method_id, installments…)
     * @param string $idempotencyKey Clave única de la operación (X-Idempotency-Key)
     * @param array  $urls           notification
     * @return array{success:bool,ref_pago?:string,estado_interno?:string,status_detail?:string,monto?:float,moneda?:string,external_reference?:string,raw?:array,error?:string,rechazo_definitivo?:bool}
     */
    public function crearPagoDirecto(array $orden, array $datosPago, string $idempotencyKey, array $urls): array;

    /**
     * Consulta un pago en el proveedor.
     * @return array{success:bool,estado_interno?:string,ref_pago?:string,monto?:float,moneda?:string,external_reference?:string,raw?:array,error?:string}
     */
    public function consultarPago(string $paymentId): array;

    /**
     * Reembolso total (o parcial si monto > 0) de un pago del proveedor.
     * @return array{success:bool,ref_reembolso?:string,raw?:array,error?:string}
     */
    public function reembolsarPago(string $paymentId, ?float $monto = null, ?string $motivo = null): array;

    public function nombreProveedor(): string;
}
