#!/usr/bin/env bash
# Arranque en DigitalOcean App Platform (run_command). El disco se borra en cada despliegue:
# antes de servir, restaura los carteles de eventos desde la BD. Si falla, la app arranca igual
# y evt_interfaz/imagen_evento.php los sirve bajo demanda.
timeout 120 php sql/sincronizar_imagenes_eventos.php || echo "[inicio] sincronización de imágenes incompleta"
exec heroku-php-apache2
