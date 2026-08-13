/**

 * Reservas en Tiempo Real - Lado LOCAL (taquilla)

 * ================================================

 * Hace monkey-patch a `agregarAlCarrito` y `removerDelCarrito` para que cada

 * acción del vendedor reserve / libere el asiento en la BD compartida.

 *

 * También expone `window.TeatroReservas.aplicarHolds(arr)` que es invocado por

 * teatro-sync.js cuando llega un evento SSE `reservas` (asientos apartados por

 * el punto de venta online o por otro vendedor).

 */

(function () {

    'use strict';

    /** Raíz de la app según la URL (p. ej. /teatro) — no depende del nombre fijo de carpeta */
    function getAppRoot() {
        const seg = window.location.pathname.split('/').filter(Boolean);
        return seg.length ? '/' + seg[0] : '';
    }

    const API_BASE = getAppRoot() + '/api/reservas.php';



    function genSessionId() {

        let s = sessionStorage.getItem('teatro_local_session_id');

        if (!s) {

            s = 'taq_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);

            sessionStorage.setItem('teatro_local_session_id', s);

        }

        return s;

    }



    const SESSION_ID = genSessionId();

    let asientosReservadosOtros = new Set();



    function getIds() {

        const idEvento  = (typeof obtenerIdEvento === 'function') ? obtenerIdEvento() : null;

        const idFuncion = (typeof obtenerIdFuncion === 'function') ? obtenerIdFuncion() : null;

        return { idEvento, idFuncion };

    }



    function reservar(codigo) {

        const { idEvento, idFuncion } = getIds();

        if (!idEvento) return Promise.resolve({ success: false });

        return fetch(API_BASE + '?action=reservar', {

            method: 'POST',

            headers: { 'Content-Type': 'application/json' },

            body: JSON.stringify({

                session_id: SESSION_ID,

                id_evento: idEvento,

                id_funcion: idFuncion,

                asientos: [codigo],

            }),

        }).then((r) => r.json()).catch(() => ({ success: false }));

    }



    function liberar(codigos, ctx) {

        const idEvento = ctx?.idEvento ?? getIds().idEvento;

        const idFuncion = ctx?.idFuncion !== undefined ? ctx.idFuncion : getIds().idFuncion;

        if (!idEvento) return Promise.resolve();

        const lista = codigos === undefined || codigos === null ? [] : codigos;

        const body = JSON.stringify({

            session_id: SESSION_ID,

            id_evento: idEvento,

            id_funcion: idFuncion,

            asientos: lista,

        });

        try {

            return fetch(API_BASE + '?action=liberar', {

                method: 'POST',

                headers: { 'Content-Type': 'application/json' },

                body,

                keepalive: true,

            });

        } catch (_) {

            return Promise.resolve();

        }

    }



    function enviarLiberarSesionBeacon() {

        try {

            const url = API_BASE + '?action=liberar_sesion';

            const payload = JSON.stringify({ session_id: SESSION_ID });

            if (navigator.sendBeacon) {

                navigator.sendBeacon(url, new Blob([payload], { type: 'application/json' }));

            } else {

                fetch(url, {

                    method: 'POST',

                    body: payload,

                    keepalive: true,

                    headers: { 'Content-Type': 'application/json' },

                });

            }

        } catch (_) {}

    }



    function aplicarHolds(asientos) {

        // Excluir asientos que YO tengo en mi carrito

        const enMiCarrito = new Set(

            (typeof carrito !== 'undefined' && Array.isArray(carrito))

                ? carrito.map((c) => c.asiento)

                : []

        );

        const ajenos = (asientos || []).filter((a) => !enMiCarrito.has(a));

        const nuevoSet = new Set(ajenos);



        // Marcar nuevos

        ajenos.forEach((codigo) => {

            const seat = document.querySelector(`.seat[data-asiento-id="${codigo}"]`);

            if (!seat) return;

            if (seat.classList.contains('vendido')) return;

            seat.classList.add('reservado-otro');

            seat.dataset.reservaTitleAntes = seat.dataset.reservaTitleAntes ?? (seat.title || '');

            seat.title = '🟠 APARTADO temporalmente';

        });



        // Quitar los que ya no están

        asientosReservadosOtros.forEach((codigo) => {

            if (!nuevoSet.has(codigo)) {

                const seat = document.querySelector(`.seat[data-asiento-id="${codigo}"]`);

                if (seat) {

                    seat.classList.remove('reservado-otro');

                    if (seat.dataset.reservaTitleAntes !== undefined) {

                        seat.title = seat.dataset.reservaTitleAntes;

                        delete seat.dataset.reservaTitleAntes;

                    }

                }

            }

        });



        asientosReservadosOtros = nuevoSet;

    }



    async function cargarReservasActivas() {

        const { idEvento, idFuncion } = getIds();

        if (!idEvento) return;

        try {

            const url = API_BASE + `?action=activas&id_evento=${idEvento}` +

                (idFuncion ? `&id_funcion=${idFuncion}` : '') +

                `&session_id=${encodeURIComponent(SESSION_ID)}`;

            const r = await fetch(url);

            const data = await r.json();

            if (data.success) aplicarHolds(data.reservados || []);

        } catch (_) {}

    }



    function liberarTodos() {

        if (typeof carrito !== 'undefined' && Array.isArray(carrito) && carrito.length) {

            const codigos = carrito.map((c) => c.asiento);

            return liberar(codigos);

        }

        return liberar([]);

    }



    // Monkey-patch para que cualquier llamada a agregarAlCarrito o removerDelCarrito

    // reserve/libere el asiento en el servidor.

    function instalarHooks() {

        if (typeof window.agregarAlCarrito !== 'function' ||

            typeof window.removerDelCarrito !== 'function') {

            return false;

        }

        const _agregar  = window.agregarAlCarrito;

        const _remover  = window.removerDelCarrito;



        window.agregarAlCarrito = function (asientoId, categoriaId) {

            // Bloquear si está apartado por otro

            if (asientosReservadosOtros.has(asientoId)) {

                if (typeof notify !== 'undefined' && notify.warning) {

                    notify.warning(`El asiento ${asientoId} está apartado por otro vendedor / cliente online.`);

                }

                return false;

            }

            const ok = _agregar.apply(this, arguments);

            if (ok) {

                // Reservar en background (no bloqueante para UX)

                reservar(asientoId).then((r) => {

                    if (!r) return;

                    if (r.success) return;

                    const conflict = r.conflictos && r.conflictos[asientoId];

                    if (conflict) {

                        if (typeof notify !== 'undefined') {

                            notify.error(`El asiento ${asientoId} ${conflict === 'vendido' ? 'acaba de venderse' : 'fue apartado por otra persona'}.`);

                        }

                        // Solo quitar del carrito si hay conflicto real con este asiento

                        try { window.removerDelCarrito && window.removerDelCarrito(asientoId); } catch (_) {}

                        return;

                    }

                    // Error de red o infraestructura: mantener en carrito

                    console.warn('[Reservas] No se pudo apartar', asientoId, '- se mantiene en carrito:', r.error || 'sin respuesta');

                });

            }

            return ok;

        };



        window.removerDelCarrito = function (asientoId) {

            const r = _remover.apply(this, arguments);

            liberar([asientoId]);

            return r;

        };

        return true;

    }



    // Cierre, recarga o navegación fuera

    window.addEventListener('beforeunload', enviarLiberarSesionBeacon);

    window.addEventListener('pagehide', (e) => {

        if (!e.persisted) enviarLiberarSesionBeacon();

    });



    // Reintentar instalar los hooks por si carrito.js se carga después

    function tryInstall() {

        if (instalarHooks()) {

            cargarReservasActivas();

            return;

        }

        setTimeout(tryInstall, 200);

    }



    document.addEventListener('DOMContentLoaded', () => {

        // Apartados huérfanos de recarga/cierre anterior

        fetch(API_BASE + '?action=liberar_sesion', {

            method: 'POST',

            headers: { 'Content-Type': 'application/json' },

            body: JSON.stringify({ session_id: SESSION_ID }),

        }).catch(() => {});

        tryInstall();

    });



    // Refresco periódico de apartados mientras hay función activa

    setInterval(() => {

        const { idFuncion } = getIds();

        if (idFuncion) cargarReservasActivas();

    }, 3000);



    // Recargar reservas activas cuando cambie la función seleccionada

    let lastFunc = null;

    setInterval(() => {

        const { idFuncion } = getIds();

        if (idFuncion !== lastFunc) {

            lastFunc = idFuncion;

            cargarReservasActivas();

        }

    }, 2500);



    window.TeatroReservas = {

        sessionId: SESSION_ID,

        aplicarHolds,

        cargarReservasActivas,

        liberar,

        liberarTodos,

        liberarSesion: enviarLiberarSesionBeacon,

    };

})();


