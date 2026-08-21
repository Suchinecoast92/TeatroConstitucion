# Fase 5 — Administración online

## Completado

- Columna `boletos.origen` (`local` | `online`) — auto-migración vía `asegurar_origen_boletos()`
- Emisión online marca `origen=online`
- Buscador de boletos muestra badge Local / Online
- Panel **Ajustes → Órdenes online** (`admin_interfaz/ordenes_online/`)
  - Listar / filtrar órdenes y pagos
  - Alerta si `pagada` sin boletos + botón reemitir
  - Reembolsar: gateway + cancelar boletos + estado `reembolsada`
- Gateways: `reembolsarPago()` (Mock + Mercado Pago)

## Prueba

```bash
php sql/test_fase5_admin.php
```

## Uso

1. Entra como admin → Ajustes → Órdenes online
2. Abre una orden pagada → Reembolsar / cancelar boletos
3. En Buscador de boletos verifica el origen Online

## Notas

- Cancelar un boleto solo desde el Buscador **no** reembolsa MP; para venta online usa el panel de órdenes.
- Mock: el reembolso siempre “pasa”. Con token real, MP ejecuta `/v1/payments/{id}/refunds`.
