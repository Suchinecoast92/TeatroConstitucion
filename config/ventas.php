<?php
/**
 * Configuración de ventana de ventas post-función.
 * Las ventas permanecen abiertas hasta N horas después de cada función.
 */
if (defined('VENTAS_CONFIG_INCLUDED')) {
    return;
}
define('VENTAS_CONFIG_INCLUDED', true);

/** Horas después de la función en que aún se permite vender */
define('HORAS_CIERRE_VENTAS_POST_FUNCION', 24);

define('SEGUNDOS_CIERRE_VENTAS_POST_FUNCION', HORAS_CIERRE_VENTAS_POST_FUNCION * 3600);
define('MS_CIERRE_VENTAS_POST_FUNCION', SEGUNDOS_CIERRE_VENTAS_POST_FUNCION * 1000);
