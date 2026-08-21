# QA pre–Fase 6 — Venta online

Fecha de revisión: 2026-08-21

## Resultado tests automáticos

```bash
php sql/test_doble_apartado.php   # PASS
php sql/test_fase1_holds.php      # PASS
php sql/test_fase2_ordenes.php    # PASS
php sql/test_fase3_pagos.php      # PASS
php sql/test_fase4_emision.php    # PASS
php sql/test_fase5_admin.php      # PASS
php sql/reparar_holds_timezone.php # PASS
```

## Correcciones aplicadas en esta revisión

| ID | Severidad | Qué se corrigió |
|----|-----------|-----------------|
| C1 | Crítico | Mock webhook / `mock_pay` solo si `payment_mock_permitido()` (no con token live / prod) |
| C2 | Crítico | PAID también desde orden `expirada`/`fallida`; se extiende `expira_en` + holds al iniciar/reusar pago |
| C3 | Crítico | Pago en curso: no liberar holds ni expirar orden con `PENDING` activo (45 min); timer no libera al clic en Pagar |
| H1 | Alto | Checkout exige `renovados > 0`; si no, re-aparte asientos |
| H2 | Alto | Webhook `REFUNDED` cancela boletos (`cancelar_boletos_de_orden`) |
| H3 | Alto | `notification_url` incluye `?secret=` si hay `MP_WEBHOOK_SECRET`; webhook lo exige |

## Checklist manual (navegador)

### Camino feliz
- [ ] Cartelera → Comprar boletos → apartar 1–2 asientos
- [ ] Continuar → aparece timer ~05:00 en datos/pago
- [ ] Completar datos → Pagar (mock) → orden `pagada` + QR
- [ ] Buscador admin: boleto con origen **Online**
- [ ] Entrada / escanear código funciona

### Fallos / bordes
- [ ] Dos navegadores, mismo asiento → el segundo no puede apartarlo
- [ ] Esperar a que expire el timer en checkout (sin pagar) → “sesión expiró” + asientos libres
- [ ] Clic en Pagar cerca del final del timer → inicia pasarela; asientos NO se liberan; al aprobar llegan boletos
- [ ] Recargar checkout dentro de los 5 min → timer y asientos siguen
- [ ] Admin → Órdenes online → Reembolsar → boletos cancelados, asiento libre
- [ ] Taquilla ve asientos vendidos/apartados online en el mapa

### Seguridad (antes de prod)
- [ ] Con `MP_ACCESS_TOKEN` real: `webhook_pagos.php?mock=1&codigo=…` debe fallar
- [ ] `APP_URL` apunta al dominio HTTPS real
- [ ] Definir `MP_WEBHOOK_SECRET` y verificar que MP llama con `?secret=`

## Riesgos residuales (aceptables / Fase 6+)

- Pago MP OK pero asiento ya vendido por taquilla (solo si el hold se perdió fuera de la ventana de 45 min o por bug) → orden pagada sin boleto (alerta admin; reembolso)
- Envío de correo con QR aún no implementado
- No hay cron dedicado; expiración de órdenes es oportunista (holds sí se limpian; PENDING caducado se marca FAILED al expirar)

## Cómo re-correr QA rápido

```bash
php sql/test_doble_apartado.php
php sql/test_fase1_holds.php
php sql/test_fase2_ordenes.php
php sql/test_fase3_pagos.php
php sql/test_fase4_emision.php
php sql/test_fase5_admin.php
```
