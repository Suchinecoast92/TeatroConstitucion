// js/sync-sender.js - Sistema de sincronización POS -> Visor Cliente
const canalSender = new BroadcastChannel('pos_sync_channel');

// 1. Enviar Evento Inicial con toda la información
function enviarEventoInicial() {
    const urlParams = new URLSearchParams(window.location.search);
    const idEvento = urlParams.get('id_evento');

    if (idEvento) {
        // Obtener información adicional del evento desde el DOM
        const tituloEvento = document.querySelector('.panel-header h5')?.textContent?.trim() ||
            document.querySelector('.evento-badge')?.textContent?.trim() ||
            'Evento';

        canalSender.postMessage({
            accion: 'INIT',
            id_evento: idEvento,
            titulo: tituloEvento.replace(/[\u{1F3AB}\u{1F39F}]/gu, '').trim()
        });

        console.log('📤 Enviando INIT al visor:', { id_evento: idEvento, titulo: tituloEvento });
    }
}

// 2. Enviar Función/Horario Seleccionado con animación
function enviarFuncion() {
    const select = document.getElementById('selectFuncion');
    if (select && select.value && select.selectedIndex > 0) {
        const texto = select.options[select.selectedIndex].text;
        const idFuncion = select.value;

        canalSender.postMessage({
            accion: 'UPDATE_FUNCION',
            texto: texto,
            id_funcion: idFuncion,
            timestamp: Date.now()
        });

        console.log('📤 Enviando función al visor:', { texto, id_funcion: idFuncion });
    }
}

// 3. Enviar Carrito con información completa incluyendo descuentos
function enviarCarrito(carritoArray) {
    let total = 0;
    let totalDescuento = 0;

    const arraySimple = carritoArray.map(item => {
        const precioBase = parseFloat(item.precio || 0);
        const descuento = parseFloat(item.descuento_aplicado || 0);
        const tipoBoleto = item.tipo_boleto || 'adulto';
        const esCortesia = tipoBoleto === 'cortesia';
        const precioFinal = esCortesia ? 0 : Math.max(0, precioBase - descuento);

        total += precioFinal;
        totalDescuento += descuento;
        if (esCortesia) totalDescuento += precioBase; // Cortesía es 100% descuento

        return {
            id: item.asiento,
            precio: precioBase,
            precio_final: precioFinal,
            descuento_aplicado: esCortesia ? precioBase : descuento,
            tipo_boleto: tipoBoleto,
            categoria: item.categoria,
            color: item.color || '#2563eb'
        };
    });

    canalSender.postMessage({
        accion: 'UPDATE_CARRITO',
        carrito: arraySimple,
        total: total,
        totalDescuento: totalDescuento,
        cantidad: arraySimple.length
    });

    console.log('📤 Enviando carrito al visor:', { items: arraySimple.length, total, descuento: totalDescuento });
}

// 4. Enviar Vendidos
function enviarVendidos(listaIds) {
    canalSender.postMessage({
        accion: 'UPDATE_VENDIDOS',
        asientos: listaIds,
        cantidad: listaIds.length
    });

    console.log('📤 Enviando vendidos al visor:', listaIds.length);
}

// 5. Enviar compra exitosa (muestra animación de gracias)
function enviarCompraExitosa(total, cantidadBoletos) {
    canalSender.postMessage({
        accion: 'COMPRA_EXITOSA',
        total: total,
        cantidad: cantidadBoletos,
        timestamp: Date.now()
    });

    console.log('📤 Enviando compra exitosa al visor:', { total, cantidad: cantidadBoletos });
}

// 6. Enviar a cliente de regreso a cartelera
function enviarRegresarCartelera() {
    canalSender.postMessage({
        accion: 'REGRESAR_CARTELERA',
        timestamp: Date.now()
    });

    console.log('📤 Enviando regreso a cartelera al visor');
}

// 6b. Enviar a cliente a la vista de horarios (después de una venta)
function enviarMostrarHorarios() {
    const urlParams = new URLSearchParams(window.location.search);
    const idEvento = urlParams.get('id_evento');

    if (idEvento) {
        const tituloEvento = document.querySelector('.panel-header h5')?.textContent?.trim() ||
            document.querySelector('.evento-badge')?.textContent?.trim() ||
            'Evento';

        canalSender.postMessage({
            accion: 'MOSTRAR_HORARIOS',
            id_evento: idEvento,
            titulo: tituloEvento.replace(/[\u{1F3AB}\u{1F39F}]/gu, '').trim(),
            timestamp: Date.now()
        });

        console.log('📤 Enviando mostrar horarios al visor');
    }
}

// 7. Nueva función: Enviar selección de evento (desde cartelera)
function enviarSeleccionEvento(idEvento, titulo) {
    canalSender.postMessage({
        accion: 'SELECCION_EVENTO',
        id_evento: idEvento,
        titulo: titulo,
        timestamp: Date.now()
    });

    console.log('📤 Enviando selección de evento al visor:', { id_evento: idEvento, titulo });
}

// 8. Nueva función: Enviar señal de NUEVA VENTA (limpiar pantalla gracias)
function enviarNuevaVenta() {
    canalSender.postMessage({
        accion: 'NUEVA_VENTA',
        timestamp: Date.now()
    });
    console.log('📤 Enviando señal de NUEVA VENTA al visor');
}


// Inicializadores
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const idEvento = urlParams.get('id_evento');

    // Si hay evento, enviar INIT; si no hay, enviar al cliente a cartelera
    if (idEvento) {
        // Pequeño delay para asegurar que el DOM esté listo
        setTimeout(() => {
            enviarEventoInicial();
        }, 200);
    } else {
        // Vendedor está en cartelera, cliente también debe ir a cartelera
        enviarRegresarCartelera();
    }

    // Escuchar cambios en el selector de función
    const selectFun = document.getElementById('selectFuncion');
    if (selectFun) {
        selectFun.addEventListener('change', () => {
            if (selectFun.value) {
                enviarFuncion();
            }
        });
    }

    // LISTENER PARA SOLICITUDES DE SINCRONIZACIÓN (HANDSHAKE)
    canalSender.onmessage = (event) => {
        const data = event.data;
        if (data && data.accion === 'REQUEST_SYNC') {
            console.log('🔄 Solicitud de sincronización recibida del visor');

            // 1. Enviar Evento
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('id_evento')) {
                enviarEventoInicial();
            } else {
                enviarRegresarCartelera();
                return; // Si no hay evento, no tiene caso enviar lo demás
            }

            // 2. Enviar Función
            setTimeout(enviarFuncion, 100);

            // 3. Enviar Carrito
            if (typeof carrito !== 'undefined' && Array.isArray(carrito)) {
                setTimeout(() => enviarCarrito(carrito), 200);
            }

            // 4. Enviar Vendidos
            if (typeof asientosVendidos !== 'undefined') {
                setTimeout(() => enviarVendidos(Array.from(asientosVendidos)), 300);
            }

            console.log('✅ Estado completo enviado al visor');
        }
    };

    console.log('🔄 Sync-sender inicializado');
});

// Exponer funciones globalmente
window.enviarEventoInicial = enviarEventoInicial;
window.enviarFuncion = enviarFuncion;
window.enviarCarrito = enviarCarrito;
window.enviarVendidos = enviarVendidos;
window.enviarCompraExitosa = enviarCompraExitosa;
window.enviarRegresarCartelera = enviarRegresarCartelera;
window.enviarMostrarHorarios = enviarMostrarHorarios;
window.enviarSeleccionEvento = enviarSeleccionEvento;
window.enviarNuevaVenta = enviarNuevaVenta;