// Sistema de carrito de compras
// Variables dinámicas que se obtienen de la URL o constantes globales
function obtenerIdEvento() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('id_evento') || (typeof EVENTO_SELECCIONADO !== 'undefined' ? EVENTO_SELECCIONADO : null);
}

function obtenerIdFuncion() {
    // Primero intentar del input hidden (se actualiza con AJAX)
    const inputFuncion = document.getElementById('inputIdFuncion');
    if (inputFuncion && inputFuncion.value) {
        return inputFuncion.value;
    }
    // Luego de la URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('id_funcion')) {
        return urlParams.get('id_funcion');
    }
    // Finalmente, de la constante global
    return typeof FUNCION_SELECCIONADA !== 'undefined' ? FUNCION_SELECCIONADA : null;
}

// Variables que se re-evalúan dinámicamente
let ID_EVENTO = obtenerIdEvento();
let ID_FUNCION = obtenerIdFuncion();
let carrito = [];
let asientosVendidos = new Set();
let descuentos = [];
let descuentoSeleccionado = null;

function getConfigTiposBoleto() {
    return (typeof window.CONFIG_TIPOS_BOLETO !== 'undefined' && window.CONFIG_TIPOS_BOLETO)
        ? window.CONFIG_TIPOS_BOLETO
        : { tipos: ['adulto', 'nino', 'adulto_mayor', 'discapacitado', 'cortesia'], evento_gratuito: false, etiquetas: {} };
}

function getTiposBoletoDisponibles() {
    const cfg = getConfigTiposBoleto();
    return Array.isArray(cfg.tipos) && cfg.tipos.length ? cfg.tipos : ['adulto', 'cortesia'];
}

function etiquetaTipoBoleto(tipo) {
    const cfg = getConfigTiposBoleto();
    const mapa = { adulto: 'Adulto', nino: 'Niño', adulto_mayor: '3ra Edad', discapacitado: 'Discapacitado', cortesia: 'Cortesía' };
    return (cfg.etiquetas && cfg.etiquetas[tipo]) || mapa[tipo] || tipo;
}

function iconoTipoBoleto(tipo) {
    const mapa = {
        adulto: 'bi-person', nino: 'bi-emoji-smile', adulto_mayor: 'bi-person-heart',
        discapacitado: 'bi-universal-access', cortesia: 'bi-gift'
    };
    return mapa[tipo] || 'bi-ticket';
}

function colorBootstrapTipo(tipo) {
    const mapa = { adulto: 'primary', nino: 'info', adulto_mayor: 'warning', discapacitado: 'success', cortesia: 'danger' };
    return mapa[tipo] || 'secondary';
}

function generarBotonesTipoClienteHTML() {
    const tipos = getTiposBoletoDisponibles();
    return tipos.map((tipo, i) => {
        const color = colorBootstrapTipo(tipo);
        const outline = i === 0 ? '' : 'outline-';
        const active = i === 0 ? ' active' : '';
        return `<button type="button" class="btn btn-${outline}${color} btn-sm tipo-cliente-btn${active}" data-tipo="${tipo}" onclick="aplicarTipoATodos('${tipo}')">
            <i class="bi ${iconoTipoBoleto(tipo)}"></i> ${etiquetaTipoBoleto(tipo)}
        </button>`;
    }).join('');
}

function generarOpcionesTipoSelect(tipoActual) {
    return getTiposBoletoDisponibles().map(tipo =>
        `<option value="${tipo}" ${tipoActual === tipo ? 'selected' : ''}>${etiquetaTipoBoleto(tipo)}</option>`
    ).join('');
}

function normalizarTipoBoletoItem(item) {
    const tipos = getTiposBoletoDisponibles();
    if (!item.tipo_boleto || !tipos.includes(item.tipo_boleto)) {
        item.tipo_boleto = tipos[0] || 'adulto';
    }
}

// Variables para selección múltiple
let ultimoAsientoSeleccionado = null;
let modoSeleccionMultiple = false;

// Cargar asientos vendidos al inicio
async function cargarAsientosVendidos() {
    // Actualizar variables globales dinámicamente
    ID_EVENTO = obtenerIdEvento();
    ID_FUNCION = obtenerIdFuncion();

    const idEvento = ID_EVENTO;
    const idFuncion = ID_FUNCION;

    if (!idEvento) return;

    try {
        const params = new URLSearchParams({ id_evento: idEvento });
        if (idFuncion) {
            params.append('id_funcion', idFuncion);
        }

        const response = await fetch(`obtener_asientos_vendidos.php?${params.toString()}`);
        const data = await response.json();

        if (data.success) {
            const prev = new Set(asientosVendidos);
            asientosVendidos = new Set(data.asientos || data.vendidos || []);
            marcarAsientosVendidos();

            // Holds ajenos desde la misma respuesta unificada (además del poll de reservas)
            if (Array.isArray(data.reservados)) {
                if (window.TeatroReservas && typeof window.TeatroReservas.aplicarHolds === 'function') {
                    window.TeatroReservas.aplicarHolds(data.reservados);
                }
            }

            // Quitar del carrito asientos que acaban de venderse (otro canal)
            if (typeof carrito !== 'undefined' && Array.isArray(carrito)) {
                let cambio = false;
                for (let i = carrito.length - 1; i >= 0; i--) {
                    const codigo = carrito[i].asiento;
                    if (asientosVendidos.has(codigo) && !prev.has(codigo)) {
                        carrito.splice(i, 1);
                        cambio = true;
                        const seat = document.querySelector(`.seat[data-asiento-id="${codigo}"]`);
                        if (seat) seat.classList.remove('selected');
                    }
                }
                if (cambio && typeof actualizarCarrito === 'function') actualizarCarrito();
            }

            // <--- SINCRONIZACIÓN: ENVIAR VENDIDOS AL VISOR CLIENTE --->
            if (typeof enviarVendidos === 'function') {
                enviarVendidos(Array.from(asientosVendidos));
            }
        }
    } catch (error) {
        console.error('Error al cargar asientos vendidos:', error);
    }
}

// Cargar descuentos disponibles
async function cargarDescuentos() {
    // Actualizar ID_EVENTO dinámicamente
    ID_EVENTO = obtenerIdEvento();
    const idEvento = ID_EVENTO;

    console.log('Cargando descuentos para evento:', idEvento);

    if (!idEvento) {
        console.log('No hay evento seleccionado');
        return;
    }

    try {
        const response = await fetch(`obtener_descuentos.php?id_evento=${idEvento}`);
        console.log('Response status:', response.status);

        const data = await response.json();
        console.log('Datos recibidos:', data);

        if (data.success) {
            descuentos = data.descuentos;
            // Guardar en variable global para el modal de venta
            window.DESCUENTOS = descuentos;
            console.log('Descuentos cargados y guardados en DESCUENTOS:', descuentos.length);
            actualizarSelectDescuentos();
        } else {
            console.error('Error en respuesta:', data.message);
        }
    } catch (error) {
        console.error('Error al cargar descuentos:', error);
    }
}

// Actualizar el select de descuentos
function actualizarSelectDescuentos() {
    const select = document.getElementById('selectDescuento');
    if (!select) {
        // El selector ya no existe en el panel lateral (ahora está en el modal de venta)
        console.log('Descuentos cargados, disponibles para el modal de venta');
        return;
    }

    console.log('Actualizando select con', descuentos.length, 'descuentos');

    select.innerHTML = '<option value="">-- Sin descuento --</option>';

    descuentos.forEach(desc => {
        const option = document.createElement('option');
        option.value = desc.id_promocion;

        let texto = desc.nombre;
        if (desc.modo_calculo === 'porcentaje') {
            texto += ` (-${desc.valor}%)`;
        } else {
            texto += ` (-$${parseFloat(desc.valor).toFixed(2)})`;
        }

        if (desc.nombre_categoria) {
            texto += ` [${desc.nombre_categoria}]`;
        }

        if (desc.tipo_regla === 'codigo' && desc.codigo) {
            texto += ` (Código: ${desc.codigo})`;
        }

        if (desc.min_cantidad > 1) {
            texto += ` (Mín. ${desc.min_cantidad} boletos)`;
        }

        option.textContent = texto;
        select.appendChild(option);
    });
}

// Aplicar descuento seleccionado
function aplicarDescuento() {
    const select = document.getElementById('selectDescuento');
    const infoElement = document.getElementById('descuentoInfo');

    if (!select.value) {
        descuentoSeleccionado = null;
        infoElement.textContent = '';
        actualizarCarrito();
        return;
    }

    descuentoSeleccionado = descuentos.find(d => d.id_promocion == select.value);

    if (descuentoSeleccionado) {
        // Verificar cantidad mínima
        if (carrito.length < descuentoSeleccionado.min_cantidad) {
            notify.warning(`Este descuento requiere al menos ${descuentoSeleccionado.min_cantidad} boleto(s). Actualmente tienes ${carrito.length}.`);
            select.value = '';
            descuentoSeleccionado = null;
            infoElement.textContent = '';
            actualizarCarrito();
            return;
        }

        let infoTexto = '';
        if (descuentoSeleccionado.modo_calculo === 'porcentaje') {
            infoTexto = `Descuento del ${descuentoSeleccionado.valor}% aplicado`;
        } else {
            infoTexto = `Descuento de $${parseFloat(descuentoSeleccionado.valor).toFixed(2)} aplicado`;
        }

        if (descuentoSeleccionado.nombre_categoria) {
            infoTexto += ` (solo ${descuentoSeleccionado.nombre_categoria})`;
        }

        infoElement.textContent = infoTexto;
    }

    actualizarCarrito();
}

// Calcular descuento para un item
// IMPORTANTE: Para descuentos FIJOS, el valor se divide entre todos los boletos aplicables
// Por ejemplo: $40 de descuento con 2 boletos = $20 de descuento por boleto
function calcularDescuentoItem(item) {
    if (!descuentoSeleccionado || !item.descuentoAplicado) return 0;

    // Verificar cantidad mínima de boletos
    const cantidadMinima = parseInt(descuentoSeleccionado.min_cantidad) || 1;
    if (carrito.length < cantidadMinima) {
        return 0; // No cumple requisito mínimo
    }

    // Si el descuento es para una categoría específica, verificar
    if (descuentoSeleccionado.id_categoria &&
        descuentoSeleccionado.id_categoria != item.categoriaId) {
        return 0;
    }

    // Contar boletos que pueden recibir el descuento
    const boletosConDescuento = carrito.filter(i => {
        if (!i.descuentoAplicado) return false;
        if (descuentoSeleccionado.id_categoria &&
            descuentoSeleccionado.id_categoria != i.categoriaId) return false;
        return true;
    });

    let descuento = 0;
    if (descuentoSeleccionado.modo_calculo === 'porcentaje') {
        // Porcentaje: se aplica a cada boleto individualmente
        descuento = item.precio * (parseFloat(descuentoSeleccionado.valor) / 100);
    } else {
        // FIJO: El descuento total se DIVIDE entre todos los boletos aplicables
        // Ej: $40 de descuento con 2 boletos = $20 por boleto
        const descuentoTotal = parseFloat(descuentoSeleccionado.valor);
        const cantidadBoletosPorDescuento = boletosConDescuento.length;
        descuento = descuentoTotal / cantidadBoletosPorDescuento;
    }

    // No permitir que el descuento sea mayor al precio del boleto
    return Math.min(descuento, item.precio);
}

// Marcar visualmente los asientos vendidos
function marcarAsientosVendidos() {
    document.querySelectorAll('.seat').forEach(seat => {
        const asientoId = seat.dataset.asientoId;
        if (asientosVendidos.has(asientoId)) {
            seat.classList.add('vendido');
            seat.title = '🚫 VENDIDO - ' + asientoId;

            // Guardar color original si no se ha guardado
            if (!seat.dataset.colorOriginal) {
                seat.dataset.colorOriginal = seat.style.backgroundColor || '';
            }

            // Aplicar estilo inline para sobrescribir el color de categoría
            seat.style.background = 'repeating-linear-gradient(45deg, #ef4444, #ef4444 10px, #dc2626 10px, #dc2626 20px)';
            seat.style.color = 'white';
            seat.style.opacity = '0.7';
        } else {
            // Remover clase vendido si ya no está en la lista
            seat.classList.remove('vendido');
            if (seat.title && seat.title.includes('VENDIDO')) {
                seat.title = '';
            }

            // Restaurar color original si existe
            if (seat.dataset.colorOriginal) {
                seat.style.background = '';
                seat.style.backgroundColor = seat.dataset.colorOriginal;
                seat.style.color = '';
                seat.style.opacity = '';
            }
        }
    });

    // Actualizar resumen de asientos después de marcar vendidos
    actualizarResumenAsientos();
}

// Marcar asientos con categoría "No Venta"
function marcarAsientosNoVenta() {
    document.querySelectorAll('.seat').forEach(seat => {
        const categoriaId = seat.dataset.categoriaId;
        const categoriaInfo = CATEGORIAS_INFO[categoriaId];

        // Verificar si la categoría es "No Venta" (por nombre o precio 0 con nombre específico)
        if (categoriaInfo) {
            const nombreLower = (categoriaInfo.nombre || '').toLowerCase();
            if (nombreLower.includes('no venta') ||
                nombreLower.includes('noventa') ||
                nombreLower.includes('bloqueado') ||
                nombreLower === 'no disponible') {
                seat.classList.add('no-venta');
                seat.title = '🚫 NO DISPONIBLE - Este asiento no está a la venta';
            }
        }
    });

    // Actualizar resumen de asientos después de marcar "No Venta"
    actualizarResumenAsientos();
}

// Verificar si un asiento es de categoría "No Venta"
function esNoVenta(categoriaId) {
    const categoriaInfo = CATEGORIAS_INFO[categoriaId];
    if (!categoriaInfo) return false;

    const nombreLower = (categoriaInfo.nombre || '').toLowerCase();
    return nombreLower.includes('no venta') ||
        nombreLower.includes('noventa') ||
        nombreLower.includes('bloqueado') ||
        nombreLower === 'no disponible';
}

// Agregar asiento al carrito
function agregarAlCarrito(asientoId, categoriaId) {
    // Verificar si hay horario seleccionado
    const idFuncion = obtenerIdFuncion();
    if (!idFuncion) {
        notify.warning('Primero debe seleccionar un horario de función');
        return false;
    }

    // Verificar si ya está vendido
    if (asientosVendidos.has(asientoId)) {
        notify.error('Este asiento ya está vendido');
        return false;
    }

    // Verificar si es categoría "No Venta"
    if (esNoVenta(categoriaId)) {
        notify.error('Este asiento no está disponible para venta');
        return false;
    }

    // Verificar si ya está en el carrito
    if (carrito.find(item => item.asiento === asientoId)) {
        notify.warning('Este asiento ya está en tu carrito');
        return false;
    }

    const categoriaInfo = CATEGORIAS_INFO[categoriaId] || CATEGORIAS_INFO[DEFAULT_CAT_ID];

    // Obtener color del asiento o de la categoría
    let colorAsiento = categoriaInfo.color || '#2563eb';
    const seatElem = document.querySelector(`[data-asiento-id="${asientoId}"]`);
    if (seatElem && seatElem.style.backgroundColor) {
        colorAsiento = seatElem.style.backgroundColor;
    }

    carrito.push({
        asiento: asientoId,
        categoria: categoriaInfo.nombre,
        precio: parseFloat(categoriaInfo.precio),
        categoriaId: categoriaId,
        color: colorAsiento,
        descuentoAplicado: true
    });

    actualizarCarrito();
    return true;
}

// Remover asiento del carrito
function removerDelCarrito(asientoId) {
    carrito = carrito.filter(item => item.asiento !== asientoId);

    // Remover clase selected del asiento
    const seatElement = document.querySelector(`[data-asiento-id="${asientoId}"]`);
    if (seatElement) {
        seatElement.classList.remove('selected');
    }

    // Si el carrito queda vacío, resetear descuento
    if (carrito.length === 0) {
        descuentoSeleccionado = null;
        const selectDescuento = document.getElementById('selectDescuento');
        if (selectDescuento) selectDescuento.value = '';
        const descuentoInfo = document.getElementById('descuentoInfo');
        if (descuentoInfo) descuentoInfo.textContent = '';
    }

    actualizarCarrito();
}

// Actualizar vista del carrito
function actualizarCarrito() {
    const carritoContainer = document.getElementById('carritoItems');
    const totalElement = document.getElementById('totalCompra');
    const btnPagar = document.getElementById('btnPagar');

    if (carrito.length === 0) {
        carritoContainer.innerHTML = '<div class="carrito-vacio">No hay asientos seleccionados</div>';
        totalElement.textContent = '$0.00';
        btnPagar.disabled = true;

        // Resetear descuento cuando el carrito está vacío
        descuentoSeleccionado = null;
        const selectDescuento = document.getElementById('selectDescuento');
        if (selectDescuento) selectDescuento.value = '';
        const descuentoInfo = document.getElementById('descuentoInfo');
        if (descuentoInfo) descuentoInfo.textContent = '';

        // Mostrar botones de acciones cuando no hay asientos seleccionados
        mostrarBotonesAcciones(true);

        // <--- SINCRONIZACIÓN: CARRITO VACÍO AL VISOR CLIENTE --->
        if (typeof enviarCarrito === 'function') {
            enviarCarrito([]);
        }
        return;
    }

    // Ocultar botones de acciones cuando hay asientos seleccionados
    mostrarBotonesAcciones(false);

    let html = '';
    let subtotal = 0;
    let totalDescuento = 0;

    carrito.forEach(item => {
        const descuentoItem = calcularDescuentoItem(item);
        const precioFinal = item.precio - descuentoItem;

        subtotal += item.precio;
        totalDescuento += descuentoItem;

        html += `
            <div class="carrito-item">
                <div class="asiento-info">
                    <strong>${item.asiento}</strong>
                    <small style="display: block;">${item.categoria}</small>
                    <span class="text-success" style="font-size: 0.9rem;">$${item.precio.toFixed(2)}</span>
                    ${descuentoItem > 0 ? `<small class="text-danger" style="display: block;">-$${descuentoItem.toFixed(2)}</small>` : ''}
                    ${descuentoItem > 0 ? `<strong class="text-primary" style="font-size: 0.9rem;">$${precioFinal.toFixed(2)}</strong>` : ''}
                </div>
                <button class="btn-remove" onclick="removerDelCarrito('${item.asiento}')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `;
    });

    const total = subtotal - totalDescuento;

    // Agregar resumen si hay descuento
    if (totalDescuento > 0) {
        html += `
            <div class="mt-2 pt-2" style="border-top: 1px solid #dee2e6;">
                <div class="d-flex justify-content-between">
                    <small>Subtotal:</small>
                    <small>$${subtotal.toFixed(2)}</small>
                </div>
                <div class="d-flex justify-content-between text-danger">
                    <small>Descuento:</small>
                    <small>-$${totalDescuento.toFixed(2)}</small>
                </div>
            </div>
        `;
    }

    carritoContainer.innerHTML = html;
    totalElement.textContent = `$${total.toFixed(2)}`;
    btnPagar.disabled = false;

    // Actualizar estadísticas
    actualizarEstadisticas();

    // <--- SINCRONIZACIÓN: ENVIAR CARRITO AL VISOR CLIENTE --->
    if (typeof enviarCarrito === 'function') {
        enviarCarrito(carrito);
    }
}

// Procesar pago - Abre modal para seleccionar tipo de boleto
function procesarPago() {
    if (carrito.length === 0) {
        notify.warning('No hay asientos en el carrito');
        return;
    }

    // Abrir modal para seleccionar tipo de boleto
    abrirModalTipoBoleto();
}

// ============================================
// GENERAR BOTONES DE DESCUENTO CON VALIDACIÓN
// Esta función se puede llamar para actualizar
// los botones cuando cambien las condiciones
// ============================================
function generarBotonesDescuento() {
    const descuentosDisponibles = typeof DESCUENTOS !== 'undefined' ? DESCUENTOS : [];

    console.log('=== GENERANDO BOTONES DE DESCUENTO ===');
    console.log('Boletos en carrito:', carrito.length);

    let botonesDescuento = '';
    if (descuentosDisponibles.length > 0) {
        descuentosDisponibles.forEach(d => {
            const esPorcentaje = d.modo_calculo === 'porcentaje';
            const valorTexto = esPorcentaje ? `${d.valor}%` : `$${parseFloat(d.valor || 0).toFixed(0)}`;
            const esActivo = descuentoSeleccionado && descuentoSeleccionado.id_promocion == d.id_promocion;

            // Mostrar tipo de boleto aplicable
            let tipoIcono = '🎫';
            let tipoTexto = 'Todos';
            if (d.tipo_boleto_aplicable) {
                const tipoInfo = {
                    'adulto': { icono: '👤', texto: 'Adulto' },
                    'nino': { icono: '👶', texto: 'Niño' },
                    'adulto_mayor': { icono: '👴', texto: '3ra Edad' },
                    'discapacitado': { icono: '♿', texto: 'Discap.' }
                };
                const info = tipoInfo[d.tipo_boleto_aplicable] || { icono: '🎫', texto: d.tipo_boleto_aplicable };
                tipoIcono = info.icono;
                tipoTexto = info.texto;
            }

            // Determinar si es descuento específico del evento o global
            const esGlobal = d.tipo_descuento === 'global' || !d.id_evento;
            const tipoDescuentoIcono = esGlobal ? '🌐' : '🎯';
            const tipoDescuentoLabel = esGlobal ? 'Global' : 'Este evento';

            // ============================================
            // VALIDAR SI EL DESCUENTO CUMPLE REQUISITOS
            // ============================================
            let puedeAplicar = true;
            let razonNoAplicable = '';

            // 1. Verificar cantidad mínima de boletos
            const cantidadMinima = parseInt(d.min_cantidad) || 1;
            console.log(`Descuento "${d.nombre}": min_cantidad=${cantidadMinima}, carrito.length=${carrito.length}`);

            if (carrito.length < cantidadMinima) {
                puedeAplicar = false;
                razonNoAplicable = `Mín. ${cantidadMinima} boletos (tienes ${carrito.length})`;
                console.log(`  ❌ NO PUEDE APLICAR: ${razonNoAplicable}`);
            }

            // 2. Verificar si hay cortesías (no se puede aplicar descuento a cortesías)
            const hayCortesias = carrito.some(item => item.tipo_boleto === 'cortesia');
            if (hayCortesias && puedeAplicar) {
                puedeAplicar = false;
                razonNoAplicable = 'No aplica con cortesías';
                console.log(`  ❌ NO PUEDE APLICAR: ${razonNoAplicable}`);
            }

            // 3. Verificar tipo de boleto requerido
            if (d.tipo_boleto_aplicable && puedeAplicar) {
                const tipoRequerido = d.tipo_boleto_aplicable;
                const boletosIncorrectos = carrito.filter(item =>
                    item.tipo_boleto && item.tipo_boleto !== tipoRequerido
                );
                if (boletosIncorrectos.length > 0) {
                    puedeAplicar = false;
                    const tipoNombres = {
                        'adulto': 'Adultos',
                        'nino': 'Niños',
                        'adulto_mayor': '3ra Edad',
                        'discapacitado': 'Discap.'
                    };
                    razonNoAplicable = `Solo para ${tipoNombres[tipoRequerido] || tipoRequerido}`;
                    console.log(`  ❌ NO PUEDE APLICAR: ${razonNoAplicable}`);
                }
            }

            // 4. Verificar categoría de asiento específica
            if (d.id_categoria && puedeAplicar) {
                const boletosOtraCategoria = carrito.filter(item =>
                    item.categoriaId && item.categoriaId != d.id_categoria
                );
                if (boletosOtraCategoria.length > 0) {
                    puedeAplicar = false;
                    razonNoAplicable = `Solo para ${d.nombre_categoria || 'categoría específica'}`;
                    console.log(`  ❌ NO PUEDE APLICAR: ${razonNoAplicable}`);
                }
            }

            // Si ya está activo pero ya no cumple requisitos, desactivarlo
            if (esActivo && !puedeAplicar) {
                console.log(`  ⚠️ Descuento activo pero ya no cumple requisitos, desactivando...`);
                descuentoSeleccionado = null;
                carrito.forEach(item => item.descuentoAplicado = false);
            }

            if (puedeAplicar) {
                console.log(`  ✅ PUEDE APLICAR`);
            }

            // Clases y estilos según si puede aplicar
            const claseBoton = esActivo
                ? 'btn-success'
                : (puedeAplicar ? 'btn-outline-success' : 'btn-outline-secondary');
            const estiloDisabled = !puedeAplicar ? 'opacity: 0.5; cursor: not-allowed; pointer-events: none;' : '';

            // IMPORTANTE: Si no puede aplicar, el onclick no debe hacer nada
            const onClickAction = puedeAplicar
                ? `toggleDescuento(${d.id_promocion})`
                : `event.preventDefault(); event.stopPropagation(); notify.error('Este descuento requiere mínimo ${cantidadMinima} boleto(s). Tienes ${carrito.length}.'); return false;`;

            botonesDescuento += `
                <button type="button" 
                        class="btn ${claseBoton} descuento-btn" 
                        data-id="${d.id_promocion}"
                        data-min-cantidad="${cantidadMinima}"
                        onclick="${onClickAction}"
                        ${!puedeAplicar ? 'disabled aria-disabled="true"' : ''}
                        style="${estiloDisabled}"
                        title="${puedeAplicar ? `${tipoDescuentoLabel} - Aplica a: ${tipoTexto}` : `❌ ${razonNoAplicable}`}">
                    <span class="d-block">${tipoIcono} ${valorTexto}</span>
                    <small class="d-block">${d.nombre}</small>
                    <div class="d-flex gap-1 justify-content-center flex-wrap" style="font-size: 0.55rem;">
                        ${!puedeAplicar ? `<span class="badge bg-danger">❌ ${razonNoAplicable}</span>` : ''}
                        ${puedeAplicar && d.tipo_boleto_aplicable ? `<span class="badge bg-info">${tipoTexto}</span>` : ''}
                        ${puedeAplicar ? `<span class="badge ${esGlobal ? 'bg-secondary' : 'bg-primary'}">${tipoDescuentoIcono} ${tipoDescuentoLabel}</span>` : ''}
                    </div>
                </button>
            `;
        });
    }

    return botonesDescuento;
}

// Actualizar botones de descuento en el modal (llamar cuando cambie tipo de boleto)
function actualizarBotonesDescuentoEnModal() {
    const contenedor = document.getElementById('contenedorDescuentos');
    if (!contenedor) return;

    // Regenerar botones con validación actualizada
    const nuevosBotones = generarBotonesDescuento();

    // Mantener el botón "Sin descuento" y actualizar los demás
    const botonSinDescuento = `
        <button type="button" 
                class="btn ${!descuentoSeleccionado ? 'btn-success' : 'btn-outline-success'} btn-sm descuento-btn" 
                data-id=""
                onclick="toggleDescuento(null)">
            <i class="bi bi-x-circle"></i> Sin descuento
        </button>
    `;

    const descuentosDisponibles = typeof DESCUENTOS !== 'undefined' ? DESCUENTOS : [];
    const sinDescuentosMsg = descuentosDisponibles.length === 0 ? `
        <span class="text-muted small align-self-center ms-2">
            <i class="bi bi-info-circle"></i> No hay promociones activas para este evento
        </span>
    ` : '';

    contenedor.innerHTML = botonSinDescuento + nuevosBotones + sinDescuentosMsg;

    // Actualizar info de descuento aplicado
    const infoDiv = document.getElementById('descuentoAplicadoInfo');
    const textoSpan = document.getElementById('textoDescuentoAplicado');

    if (descuentoSeleccionado) {
        // Verificar si cumple los requisitos
        const cantidadMinima = parseInt(descuentoSeleccionado.min_cantidad) || 1;
        if (carrito.length >= cantidadMinima) {
            const esPorcentaje = descuentoSeleccionado.modo_calculo === 'porcentaje';
            const textoDesc = esPorcentaje
                ? `${descuentoSeleccionado.valor}% de descuento`
                : `$${descuentoSeleccionado.valor} de descuento en total`;

            if (infoDiv && textoSpan) {
                textoSpan.textContent = textoDesc;
                infoDiv.style.display = 'block';
            }
        } else {
            // No cumple requisitos, ocultar y quitar descuento
            descuentoSeleccionado = null;
            carrito.forEach(item => item.descuentoAplicado = false);
            if (infoDiv) infoDiv.style.display = 'none';
        }
    } else {
        if (infoDiv) infoDiv.style.display = 'none';
    }

    // Actualizar totales
    actualizarTotalModal();
}

// Abrir modal para seleccionar tipo de boleto
function abrirModalTipoBoleto() {
    // Obtener descuentos disponibles
    const descuentosDisponibles = typeof DESCUENTOS !== 'undefined' ? DESCUENTOS : [];

    console.log('Descuentos disponibles:', descuentosDisponibles);

    // Usar la función centralizada para generar botones con validación
    const botonesDescuento = generarBotonesDescuento();

    const modalHTML = `
        <div class="modal fade" id="modalTipoBoleto" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content" style="border-radius: 16px; overflow: hidden;">
                    <div class="modal-header bg-primary text-white py-3">
                        <h5 class="modal-title">
                            <i class="bi bi-cash-coin"></i> Confirmar Venta
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-3">
                        
                        <!-- Tipo de Cliente - Botones compactos -->
                        <div class="mb-3">
                            <label class="form-label fw-bold mb-2">
                                <i class="bi bi-people"></i> Tipo de Cliente:
                            </label>
                            <div class="d-flex flex-wrap gap-2" id="contenedorTiposCliente">
                                ${generarBotonesTipoClienteHTML()}
                            </div>
                        </div>
                        
                        <!-- Descuentos - Siempre visible -->
                        <div class="mb-3 p-3 rounded" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #86efac;">
                            <label class="form-label fw-bold mb-2" style="color: #166534;">
                                <i class="bi bi-percent"></i> Aplicar Descuento:
                            </label>
                            <div class="d-flex flex-wrap gap-2" id="contenedorDescuentos">
                                <button type="button" 
                                        class="btn ${!descuentoSeleccionado ? 'btn-success' : 'btn-outline-success'} btn-sm descuento-btn" 
                                        data-id=""
                                        onclick="toggleDescuento(null)">
                                    <i class="bi bi-x-circle"></i> Sin descuento
                                </button>
                                ${botonesDescuento}
                                ${descuentosDisponibles.length === 0 ? `
                                    <span class="text-muted small align-self-center ms-2">
                                        <i class="bi bi-info-circle"></i> No hay promociones activas para este evento
                                    </span>
                                ` : ''}
                            </div>
                            <div id="descuentoAplicadoInfo" class="mt-2 small text-success fw-bold" style="display: ${descuentoSeleccionado ? 'block' : 'none'};">
                                <i class="bi bi-check-circle-fill"></i> 
                                <span id="textoDescuentoAplicado">${descuentoSeleccionado ? 'Descuento aplicado' : ''}</span>
                            </div>
                        </div>
                        
                        <!-- Resumen compacto -->
                        <div class="bg-light rounded p-2 mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-ticket"></i> <strong>${carrito.length}</strong> boleto(s)</span>
                                <span id="resumenDescuentoTotal" class="text-danger fw-bold" style="display: none;">
                                </span>
                            </div>
                        </div>
                        
                        <!-- Lista de boletos (visible, max 3) -->
                        <div class="mb-3">
                            <label class="form-label fw-bold mb-2">
                                <i class="bi bi-list-check"></i> Boletos (${carrito.length}):
                            </label>
                            <div id="listaBoletosTipo" class="lista-boletos-tipo rounded border" style="max-height: 180px; overflow-y: auto; background: #f8f9fa;">
                            </div>
                            ${carrito.length > 3 ? `<small class="text-muted"><i class="bi bi-arrow-down-short"></i> Desliza para ver más</small>` : ''}
                        </div>
                        
                        <div class="alert alert-warning py-2 mt-2" id="alertCortesia" style="display: none; font-size: 0.85rem;">
                            <i class="bi bi-exclamation-triangle"></i> Cortesía = $0.00
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">Total:</small>
                                <h4 class="mb-0 text-success" id="totalModalPago">$0.00</h4>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    Cancelar
                                </button>
                                <button type="button" class="btn btn-success btn-lg px-4" onclick="confirmarYProcesarPago()">
                                    <i class="bi bi-check-circle"></i> Cobrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remover modal anterior si existe
    const modalAnterior = document.getElementById('modalTipoBoleto');
    if (modalAnterior) {
        modalAnterior.remove();
    }

    // Agregar modal al DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Llenar lista de boletos
    llenarListaBoletosTipo();

    // Seleccionar descuento actual si existe
    if (descuentoSeleccionado) {
        const selectDescuento = document.getElementById('selectDescuentoModal');
        if (selectDescuento) {
            selectDescuento.value = descuentoSeleccionado.id_promocion;
            aplicarDescuentoDesdeModal();
        }
    }

    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('modalTipoBoleto'));
    modal.show();

    // Limpiar al cerrar
    document.getElementById('modalTipoBoleto').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}

// Toggle descuento (activar/desactivar con botones)
function toggleDescuento(idPromocion) {
    const contenedor = document.getElementById('contenedorDescuentos');
    const infoDiv = document.getElementById('descuentoAplicadoInfo');
    const textoSpan = document.getElementById('textoDescuentoAplicado');
    const resumenSpan = document.getElementById('descuentoResumen');

    if (!idPromocion) {
        // Sin descuento
        descuentoSeleccionado = null;
        carrito.forEach(item => item.descuentoAplicado = false);

        // Actualizar botones
        if (contenedor) {
            contenedor.querySelectorAll('.descuento-btn').forEach(btn => {
                btn.classList.remove('btn-success', 'btn-secondary');
                btn.classList.add(btn.dataset.id === '' ? 'btn-success' : 'btn-outline-success');
            });
        }

        if (infoDiv) infoDiv.style.display = 'none';
        if (resumenSpan) resumenSpan.style.display = 'none';

        notify.info('Descuento removido');
    } else {
        // Buscar el descuento seleccionado
        const descuentos = typeof DESCUENTOS !== 'undefined' ? DESCUENTOS : [];
        const descuento = descuentos.find(d => d.id_promocion == idPromocion);

        if (descuento) {
            // ============================================
            // VALIDACIÓN 0: Cantidad mínima de boletos
            // ============================================
            const cantidadMinima = parseInt(descuento.min_cantidad) || 1;
            if (carrito.length < cantidadMinima) {
                notify.error(`Este descuento requiere mínimo ${cantidadMinima} boleto(s). Tienes ${carrito.length}.`);
                return;
            }

            // VALIDACIÓN 1: No aplicar descuento si hay boletos de cortesía
            const hayCortesias = carrito.some(item => item.tipo_boleto === 'cortesia');
            if (hayCortesias) {
                notify.warning('No puedes aplicar descuento porque hay boletos de Cortesía (ya son gratis)');
                return;
            }

            // VALIDACIÓN 2: Si el descuento tiene tipo_boleto específico, verificar compatibilidad
            const tipoBoletoDescuento = descuento.tipo_boleto_aplicable || null;
            if (tipoBoletoDescuento) {
                // Verificar que todos los boletos sean del tipo requerido
                const todosSonDelTipo = carrito.every(item =>
                    !item.tipo_boleto || item.tipo_boleto === tipoBoletoDescuento
                );

                if (!todosSonDelTipo) {
                    const tipoNombres = {
                        'adulto': 'Adultos',
                        'nino': 'Niños',
                        'adulto_mayor': '3ra Edad',
                        'discapacitado': 'Discapacitados'
                    };
                    notify.warning(`Este descuento solo aplica a ${tipoNombres[tipoBoletoDescuento]}. Cambia el tipo de boleto primero.`);
                    return;
                }
            }

            descuentoSeleccionado = descuento;
            carrito.forEach(item => item.descuentoAplicado = true);

            // Actualizar botones
            if (contenedor) {
                contenedor.querySelectorAll('.descuento-btn').forEach(btn => {
                    btn.classList.remove('btn-success', 'btn-secondary', 'btn-outline-success', 'btn-outline-secondary');
                    if (btn.dataset.id == idPromocion) {
                        btn.classList.add('btn-success');
                    } else if (btn.dataset.id === '') {
                        btn.classList.add('btn-outline-secondary');
                    } else {
                        btn.classList.add('btn-outline-success');
                    }
                });
            }

            // Usar modo_calculo y valor (campos reales de la BD)
            const esPorcentaje = descuento.modo_calculo === 'porcentaje';
            const textoDesc = esPorcentaje
                ? `${descuento.valor}% de descuento`
                : `$${descuento.valor} de descuento en total`;

            if (infoDiv && textoSpan) {
                textoSpan.textContent = textoDesc;
                infoDiv.style.display = 'block';
            }

            if (resumenSpan) {
                resumenSpan.textContent = '-' + (esPorcentaje ? descuento.valor + '%' : '$' + descuento.valor);
                resumenSpan.style.display = 'inline';
            }

            notify.success(`Descuento "${descuento.nombre}" aplicado`);
        }
    }

    // Actualizar la lista y el total
    llenarListaBoletosTipo();

    // Actualizar botones de descuento para reflejar estado actual
    actualizarBotonesDescuentoEnModal();
}

// Mantener compatibilidad con la función anterior
function aplicarDescuentoDesdeModal() {
    // Esta función ya no se usa, pero mantenemos compatibilidad
    const select = document.getElementById('selectDescuentoModal');
    if (select) {
        toggleDescuento(select.value || null);
    }
}

// Llenar lista de boletos con selectores de tipo
function llenarListaBoletosTipo() {
    const lista = document.getElementById('listaBoletosTipo');
    if (!lista) return;

    let html = '';

    carrito.forEach((item, index) => {
        normalizarTipoBoletoItem(item);

        // Obtener precio según tipo de boleto
        const precioTipo = obtenerPrecioPorTipo(item.tipo_boleto, item.precio);
        const descuentoItem = calcularDescuentoItem(item);
        const precioFinal = Math.max(0, precioTipo - descuentoItem);

        html += `
            <div class="boleto-tipo-item p-2 border-bottom d-flex align-items-center justify-content-between" data-index="${index}">
                <div class="d-flex align-items-center gap-2">
                    <div>
                        <span class="fw-bold">${item.asiento}</span>
                        <small class="text-muted d-block">${item.categoria}</small>
                    </div>
                    <span class="precio-display text-success fw-bold" data-precio-base="${precioTipo.toFixed(2)}">$${precioFinal.toFixed(2)}</span>
                    ${descuentoItem > 0 ? `<small class="text-danger">(-$${descuentoItem.toFixed(2)})</small>` : ''}
                </div>
                <select class="form-select form-select-sm tipo-boleto-select" data-index="${index}" style="width: auto; min-width: 120px;">
                    ${generarOpcionesTipoSelect(item.tipo_boleto)}
                </select>
            </div>
        `;
    });

    lista.innerHTML = html;

    // Agregar event listeners a los selectores
    document.querySelectorAll('.tipo-boleto-select').forEach(select => {
        select.addEventListener('change', function () {
            const index = parseInt(this.dataset.index);
            if (carrito[index]) {
                carrito[index].tipo_boleto = this.value;
                actualizarPrecioEnModal(index);

                // Actualizar botones de descuento al cambiar tipo de boleto individual
                actualizarBotonesDescuentoEnModal();
            }
        });
    });

    // Actualizar total inicial
    actualizarTotalModal();
}

// Actualizar precio en el modal cuando cambia el tipo
function actualizarPrecioEnModal(index) {
    const item = carrito[index];
    if (!item) return;

    const boletoItem = document.querySelector(`.boleto-tipo-item[data-index="${index}"]`);
    if (!boletoItem) return;

    const precioDisplay = boletoItem.querySelector('.precio-display');

    // Calcular precio según tipo de boleto
    let precioFinal = obtenerPrecioPorTipo(item.tipo_boleto, item.precio);

    // Aplicar descuento si hay
    const descuentoItem = calcularDescuentoItem(item);
    precioFinal = precioFinal - descuentoItem;
    if (precioFinal < 0) precioFinal = 0;

    precioDisplay.textContent = '$' + precioFinal.toFixed(2);

    // Mostrar/ocultar alerta de cortesía
    const alertCortesia = document.getElementById('alertCortesia');
    const hayCortesia = carrito.some(i => i.tipo_boleto === 'cortesia');
    if (alertCortesia) {
        alertCortesia.style.display = hayCortesia ? 'block' : 'none';
    }

    // Actualizar total
    actualizarTotalModal();
}

// Obtener precio según tipo de boleto
function obtenerPrecioPorTipo(tipoBoleto, precioBase) {
    // Si hay precios definidos por tipo, usarlos
    const precios = typeof PRECIOS_TIPO_BOLETO !== 'undefined' ? PRECIOS_TIPO_BOLETO : null;

    if (precios) {
        switch (tipoBoleto) {
            case 'cortesia':
                return 0; // Siempre gratis
            case 'adulto':
                return precioBase; // Usar siempre el precio de la categoría para adultos
            case 'nino':
                return precios.nino || precioBase;
            case 'adulto_mayor':
                return precios.adulto_mayor || precioBase;
            case 'discapacitado':
                return precios.discapacitado || precioBase;
            default:
                return precioBase;
        }
    }

    // Si no hay precios definidos, usar precio base (cortesía = 0)
    return tipoBoleto === 'cortesia' ? 0 : precioBase;
}

// Actualizar total en el modal
function actualizarTotalModal() {
    let subtotal = 0;
    let totalDescuento = 0;

    carrito.forEach((item, index) => {
        // Obtener precio según tipo
        const precioTipo = obtenerPrecioPorTipo(item.tipo_boleto, item.precio);
        const descuentoItem = calcularDescuentoItem(item);

        // Si es cortesía, no suma al subtotal
        if (item.tipo_boleto !== 'cortesia') {
            subtotal += precioTipo;
            totalDescuento += descuentoItem;
        }
    });

    const total = Math.max(0, subtotal - totalDescuento);

    const totalElement = document.getElementById('totalModalPago');
    if (totalElement) {
        totalElement.textContent = '$' + total.toFixed(2);
    }

    // Actualizar resumen de descuento
    const resumenDescuento = document.getElementById('resumenDescuentoTotal');
    if (resumenDescuento) {
        if (totalDescuento > 0) {
            resumenDescuento.innerHTML = `<span class="text-danger">-$${totalDescuento.toFixed(2)}</span>`;
            resumenDescuento.style.display = 'inline';
        } else {
            resumenDescuento.style.display = 'none';
        }
    }
}

// Aplicar tipo de boleto a todos
function aplicarTipoATodos(tipo) {
    // VALIDACIÓN 1: Si hay un descuento que aplica a un tipo específico de boleto
    if (descuentoSeleccionado && descuentoSeleccionado.tipo_boleto_aplicable) {
        const tipoRequerido = descuentoSeleccionado.tipo_boleto_aplicable;
        if (tipo !== tipoRequerido) {
            const tipoNombres = {
                'adulto': 'Adultos',
                'nino': 'Niño',
                'adulto_mayor': '3ra Edad',
                'discapacitado': 'Discapacitados'
            };
            notify.warning(`El descuento "${descuentoSeleccionado.nombre}" solo aplica a ${tipoNombres[tipoRequerido]}. Quita el descuento primero.`);
            return;
        }
    }

    // VALIDACIÓN 2: Si cambian a cortesía, quitar cualquier descuento aplicado
    if (tipo === 'cortesia' && descuentoSeleccionado) {
        toggleDescuento(null); // Quitar descuento
        notify.info('Se quitó el descuento porque Cortesía ya es gratis');
    }

    carrito.forEach((item, index) => {
        item.tipo_boleto = tipo;
    });

    // Actualizar la lista de boletos
    llenarListaBoletosTipo();

    // Actualizar botones visualmente
    document.querySelectorAll('.tipo-cliente-btn').forEach(btn => {
        btn.classList.remove('active', 'btn-primary', 'btn-info', 'btn-warning', 'btn-success', 'btn-danger');
        btn.classList.add('btn-outline-' + getColorForType(btn.dataset.tipo));
    });

    // Marcar el botón seleccionado (corregido el selector)
    const btnActivo = document.querySelector(`.tipo-cliente-btn[data-tipo="${tipo}"]`);
    if (btnActivo) {
        btnActivo.classList.remove('btn-outline-' + getColorForType(tipo));
        btnActivo.classList.add('btn-' + getColorForType(tipo), 'active');
    }

    // Mostrar/ocultar alerta de cortesía
    const alertCortesia = document.getElementById('alertCortesia');
    if (alertCortesia) {
        alertCortesia.style.display = tipo === 'cortesia' ? 'block' : 'none';
    }

    const tipoNombre = {
        'adulto': 'Adulto',
        'nino': 'Niño',
        'adulto_mayor': '3ra Edad',
        'discapacitado': 'Discapacitado',
        'cortesia': 'Cortesía'
    };

    notify.success(`Tipo "${tipoNombre[tipo]}" aplicado a ${carrito.length} boleto(s)`);

    // ============================================
    // ACTUALIZAR BOTONES DE DESCUENTO 
    // para reflejar las nuevas condiciones
    // ============================================
    actualizarBotonesDescuentoEnModal();
}

// Obtener color para tipo de boleto
function getColorForType(tipo) {
    const colores = {
        'adulto': 'primary',
        'nino': 'info',
        'adulto_mayor': 'warning',
        'discapacitado': 'success',
        'cortesia': 'danger'
    };
    return colores[tipo] || 'secondary';
}

// ============================================
// VALIDAR REQUISITOS DE DESCUENTO APLICADO
// Esta función verifica que el descuento cumpla
// con todas las condiciones antes de confirmar
// ============================================
function validarDescuentoAplicado() {
    const errores = [];

    if (!descuentoSeleccionado) {
        return errores; // No hay descuento, nada que validar
    }

    const tipoNombres = {
        'adulto': 'Adultos',
        'nino': 'Niños',
        'adulto_mayor': '3ra Edad',
        'discapacitado': 'Discapacitados'
    };

    // 1. VALIDACIÓN: Cantidad mínima de boletos
    const cantidadMinima = parseInt(descuentoSeleccionado.min_cantidad) || 1;
    if (carrito.length < cantidadMinima) {
        errores.push(`El descuento "${descuentoSeleccionado.nombre}" requiere mínimo ${cantidadMinima} boleto(s). Tienes ${carrito.length}.`);
    }

    // 2. VALIDACIÓN: No hay cortesías si se aplica descuento
    const hayCortesias = carrito.some(item => item.tipo_boleto === 'cortesia');
    if (hayCortesias) {
        errores.push(`No puedes aplicar el descuento "${descuentoSeleccionado.nombre}" porque hay boletos de Cortesía (ya son gratis).`);
    }

    // 3. VALIDACIÓN: Tipo de boleto requerido
    const tipoBoletoRequerido = descuentoSeleccionado.tipo_boleto_aplicable || null;
    if (tipoBoletoRequerido) {
        // Contar boletos que NO son del tipo requerido
        const boletosIncorrectos = carrito.filter(item =>
            item.tipo_boleto && item.tipo_boleto !== tipoBoletoRequerido
        );

        if (boletosIncorrectos.length > 0) {
            const tipoNombre = tipoNombres[tipoBoletoRequerido] || tipoBoletoRequerido;
            errores.push(`El descuento "${descuentoSeleccionado.nombre}" solo aplica a boletos tipo "${tipoNombre}". Tienes ${boletosIncorrectos.length} boleto(s) de otro tipo.`);
        }
    }

    // 4. VALIDACIÓN: Categoría de asiento específica
    const categoriaRequerida = descuentoSeleccionado.id_categoria || null;
    if (categoriaRequerida) {
        // Contar boletos que NO son de la categoría requerida
        const boletosOtraCategoria = carrito.filter(item =>
            item.categoriaId && item.categoriaId != categoriaRequerida
        );

        if (boletosOtraCategoria.length > 0) {
            const nombreCategoria = descuentoSeleccionado.nombre_categoria || 'la categoría requerida';
            errores.push(`El descuento "${descuentoSeleccionado.nombre}" solo aplica a boletos de "${nombreCategoria}". Tienes ${boletosOtraCategoria.length} boleto(s) de otra categoría.`);
        }
    }

    // 5. VALIDACIÓN: Boletos con descuento aplicado
    const boletosConDescuento = carrito.filter(item => item.descuentoAplicado);
    if (boletosConDescuento.length === 0 && carrito.length > 0) {
        errores.push(`El descuento "${descuentoSeleccionado.nombre}" no se ha aplicado a ningún boleto.`);
    }

    return errores;
}

// Confirmar y procesar pago
async function confirmarYProcesarPago() {
    // Obtener IDs dinámicamente (importante para cuando se cambió de función via AJAX)
    ID_EVENTO = obtenerIdEvento();
    ID_FUNCION = obtenerIdFuncion();

    const idEvento = ID_EVENTO;
    const idFuncion = ID_FUNCION;

    if (!idEvento) {
        notify.error('Error: No se ha seleccionado un evento');
        return;
    }

    if (!idFuncion) {
        notify.error('Error: No se ha seleccionado una función/horario. Por favor seleccione un horario primero.');
        return;
    }

    // ============================================
    // VALIDACIÓN DE DESCUENTOS ANTES DE CONFIRMAR
    // ============================================
    if (descuentoSeleccionado) {
        const erroresDescuento = validarDescuentoAplicado();
        if (erroresDescuento.length > 0) {
            // Mostrar todos los errores de validación
            erroresDescuento.forEach(error => notify.error(error));
            return; // No continuar con la venta
        }
    }

    // Cerrar modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalTipoBoleto'));
    if (modal) {
        modal.hide();
    }

    const btnPagar = document.getElementById('btnPagar');
    btnPagar.disabled = true;
    btnPagar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';

    // BLOQUEO TOTAL: Marcar que vamos a abrir el modal de venta exitosa
    // Esto bloquea TODAS las actualizaciones automáticas hasta que el usuario presione un botón
    window.TEATRO_VENTA_MODAL_ABIERTO = true;
    console.log('[Carrito] BLOQUEO DE ACTUALIZACIONES ACTIVADO');

    // IMPORTANTE: Pausar recargas automáticas ANTES de procesar la venta
    // Esto evita que el sistema detecte el cambio y recargue la página
    if (typeof TeatroSync !== 'undefined' && TeatroSync.pauseReloads) {
        TeatroSync.pauseReloads(300000); // 5 minutos para dar tiempo a imprimir/descargar
        console.log('[Carrito] Recargas automáticas pausadas');
    }

    // Preparar datos con descuentos y tipo de boleto
    const asientosConDescuento = carrito.map(item => {
        const descuentoItem = calcularDescuentoItem(item);
        return {
            ...item,
            descuento_aplicado: descuentoItem,
            precio_final: item.precio - descuentoItem,
            id_promocion: descuentoSeleccionado ? descuentoSeleccionado.id_promocion : null,
            tipo_boleto: item.tipo_boleto || 'adulto'
        };
    });

    try {
        const response = await fetch('procesar_compra.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_evento: idEvento,
                id_funcion: idFuncion,
                asientos: asientosConDescuento,
                session_id: (window.TeatroReservas && window.TeatroReservas.sessionId)
                    ? window.TeatroReservas.sessionId
                    : null,
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('Error del servidor:', errorText);
            throw new Error(`Error del servidor(${response.status}): ${errorText.substring(0, 200)} `);
        }

        const data = await response.json();

        if (data.success) {
            console.log('Boletos recibidos:', data.boletos);
            notify.success(`¡Compra exitosa! Se generaron ${data.boletos.length} boleto(s)`);

            // Calcular total de la compra
            const totalCompra = data.boletos.reduce((sum, b) => sum + parseFloat(b.precio), 0);

            // Enviar mensaje de compra exitosa al visor cliente (animación de gracias)
            if (typeof enviarCompraExitosa === 'function') {
                enviarCompraExitosa(totalCompra, data.boletos.length);
            }

            // Agregar asientos vendidos al set
            carrito.forEach(item => asientosVendidos.add(item.asiento));

            // Limpiar carrito y descuento
            document.querySelectorAll('.seat.selected').forEach(s => s.classList.remove('selected'));
            carrito = [];
            descuentoSeleccionado = null;
            const selectDescuento = document.getElementById('selectDescuento');
            if (selectDescuento) selectDescuento.value = '';
            const descuentoInfo = document.getElementById('descuentoInfo');
            if (descuentoInfo) descuentoInfo.textContent = '';
            actualizarCarrito();
            marcarAsientosVendidos();

            // Recargar asientos vendidos desde BD para sincronización completa
            cargarAsientosVendidos();

            // Mostrar códigos QR
            mostrarBoletosGenerados(data.boletos);
        } else {
            notify.error('Error al procesar la compra: ' + data.message);
            // Liberar bloqueo si la venta falló
            window.TEATRO_VENTA_MODAL_ABIERTO = false;
        }
    } catch (error) {
        console.error('Error completo:', error);
        notify.error('Error al procesar la compra: ' + error.message);
        // Liberar bloqueo si hubo error
        window.TEATRO_VENTA_MODAL_ABIERTO = false;
    } finally {
        btnPagar.disabled = false;
        btnPagar.innerHTML = '<i class="bi bi-credit-card"></i> Procesar Pago';
    }
}

// Mostrar boletos generados - Diseño Visual Mejorado
function mostrarBoletosGenerados(boletos) {
    // Calcular total de la venta
    const totalVenta = boletos.reduce((sum, b) => sum + parseFloat(b.precio), 0);

    // Estilos inline para el modal mejorado
    const estilosModal = `
        <style>
            #modalBoletosNuevo .modal-content {
                border: none;
                border-radius: 16px;
                overflow: hidden;
            }
            #modalBoletosNuevo .modal-header {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                border: none;
                padding: 1.5rem;
            }
            #modalBoletosNuevo .boletos-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 12px;
                max-height: 320px;
                overflow-y: auto;
                padding: 16px;
                background: #f8fafc;
            }
            #modalBoletosNuevo .boletos-grid::-webkit-scrollbar {
                width: 8px;
            }
            #modalBoletosNuevo .boletos-grid::-webkit-scrollbar-track {
                background: #e2e8f0;
                border-radius: 4px;
            }
            #modalBoletosNuevo .boletos-grid::-webkit-scrollbar-thumb {
                background: #94a3b8;
                border-radius: 4px;
            }
            #modalBoletosNuevo .boleto-card {
                background: white;
                border-radius: 12px;
                padding: 12px;
                text-align: center;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                transition: transform 0.2s, box-shadow 0.2s;
                border: 1px solid #e2e8f0;
            }
            #modalBoletosNuevo .boleto-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            #modalBoletosNuevo .boleto-asiento {
                font-weight: 700;
                font-size: 1.1rem;
                color: #1e293b;
                margin-bottom: 8px;
            }
            #modalBoletosNuevo .boleto-qr {
                width: 80px;
                height: 80px;
                margin: 0 auto 8px;
                border-radius: 8px;
            }
            #modalBoletosNuevo .config-print-section {
                background: #f1f5f9;
                padding: 15px;
                border-radius: 12px;
                margin-top: 15px;
                border: 1px solid #e2e8f0;
            }
            #modalBoletosNuevo .boleto-precio {
                font-weight: 600;
                color: #10b981;
                font-size: 1rem;
            }
            #modalBoletosNuevo .boleto-codigo {
                font-size: 0.7rem;
                color: #64748b;
                font-family: monospace;
            }
            #modalBoletosNuevo .acciones-rapidas {
                background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
                padding: 20px;
            }
            #modalBoletosNuevo .accion-btn {
                flex: 1;
                padding: 16px 12px;
                border-radius: 12px;
                border: none;
                font-weight: 600;
                font-size: 0.95rem;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                transition: transform 0.2s;
                min-width: 100px;
            }
            #modalBoletosNuevo .accion-btn:hover {
                transform: scale(1.05);
            }
            #modalBoletosNuevo .accion-btn i {
                font-size: 1.5rem;
            }
            #modalBoletosNuevo .siguiente-accion {
                background: #f1f5f9;
                padding: 20px;
            }
            @media (max-width: 768px) {
                #modalBoletosNuevo .boletos-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
        </style>
    `;

    let html = estilosModal;
    html += `
    <div class="modal fade" id="modalBoletosNuevo" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <!-- Header con éxito -->
                <div class="modal-header text-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2">
                            <i class="bi bi-check-circle-fill fs-2"></i>
                        </div>
                        <div>
                            <h4 class="mb-0 fw-bold">¡Venta Exitosa!</h4>
                            <p class="mb-0 opacity-75">${boletos.length} boleto${boletos.length > 1 ? 's' : ''} generado${boletos.length > 1 ? 's' : ''} · Total: $${totalVenta.toFixed(2)}</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <!-- Grid de boletos con scroll -->
                <div class="boletos-grid">
    `;

    boletos.forEach(boleto => {
        html += `
            <div class="boleto-card">
                <div class="boleto-asiento">${boleto.asiento}</div>
                <img src="../boletos_qr/${boleto.codigo_unico}.png" alt="QR" class="boleto-qr">
                <div class="boleto-precio">$${boleto.precio.toFixed(2)}</div>
                <div class="boleto-codigo">${boleto.codigo_unico}</div>
            </div>
        `;
    });

    html += `
                </div>

                <!-- Configuración de Impresión (Cliente y Impresora) -->
                <div class="px-4">
                    <div class="config-print-section">
                        <!-- Checkbox para nombres individuales (solo si hay > 1 boleto) -->
                        ${boletos.length > 1 ? `
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="checkNombresIndividuales">
                            <label class="form-check-label fw-bold text-dark" style="color: #000;" for="checkNombresIndividuales">Asignar nombre diferente a cada boleto</label>
                        </div>
                        ` : ''}

                        <!-- Input Nombre Global -->
                        <div class="row g-3 align-items-center mb-3" id="containerNombreGlobal">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted mb-1">
                                    <i class="bi bi-person-badge"></i> Nombre del Cliente (General)
                                </label>
                                <input type="text" id="nombreClienteTicket" class="form-control" placeholder="Ej: Juan Pérez" autocomplete="off">
                            </div>
                        </div>

                        <!-- Inputs Nombres Individuales (Oculto por defecto) -->
                        <div id="containerNombresIndividuales" class="mb-3" style="display: none;">
                            <label class="form-label fw-bold small text-muted mb-2">
                                <i class="bi bi-people"></i> Nombres por asiento
                            </label>
                            <div class="row g-2">
                                ${boletos.map((b, i) => `
                                <div class="col-md-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light fw-bold" style="width: 50px; justify-content: center;">${b.asiento}</span>
                                        <input type="text" class="form-control input-nombre-individual" 
                                            data-index="${i}" 
                                            data-codigo="${b.codigo_unico}"
                                            placeholder="Nombre para ${b.asiento}">
                                    </div>
                                </div>
                                `).join('')}
                            </div>
                        </div>

                        <!-- Selector de Impresora -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted mb-1">
                                    <i class="bi bi-printer"></i> Seleccionar Impresora
                                </label>
                                <div class="input-group">
                                    <select class="form-select" id="selectImpresoraTicket">
                                        <option value="default">Predeterminada del Sistema</option>
                                    </select>
                                    <div class="input-group-text bg-white">
                                        <div class="form-check form-switch mb-0" title="Imprimir automáticamente al confirmar">
                                            <input class="form-check-input" type="checkbox" id="checkAutoPrint" checked>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acciones Rápidas -->
                <div class="acciones-rapidas">
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <button type="button" class="accion-btn btn btn-primary" onclick="descargarTodosBoletos()">
                            <i class="bi bi-download"></i>
                            <span>Descargar PDF</span>
                        </button>
                        <button type="button" class="accion-btn btn btn-warning" onclick="imprimirTodosBoletos()">
                            <i class="bi bi-printer"></i>
                            <span>Imprimir</span>
                        </button>
                        <button type="button" class="accion-btn btn btn-success" onclick="enviarTodosBoletosPorWhatsApp()">
                            <i class="bi bi-whatsapp"></i>
                            <span>WhatsApp</span>
                        </button>
                        <button type="button" class="accion-btn btn btn-danger" onclick="cancelarVentaDesdeModal()">
                            <i class="bi bi-x-circle"></i>
                            <span>Cancelar Venta</span>
                        </button>
                    </div>
                </div>

                <!-- Siguiente Acción -->
                <div class="siguiente-accion">
                    <div class="d-flex gap-3 justify-content-center">
                        <button type="button" class="btn btn-outline-success btn-lg px-4" onclick="continuarVendiendoDesdeModal()">
                            <i class="bi bi-cart-plus me-2"></i>Nueva Venta
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-lg px-4" onclick="cambiarDeEventoDesdeModal()">
                            <i class="bi bi-collection me-2"></i>Cambiar Evento
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    `;

    // Remover modal anterior si existe
    const modalAnterior = document.getElementById('modalBoletosNuevo');
    if (modalAnterior) modalAnterior.remove();

    document.body.insertAdjacentHTML('beforeend', html);
    const modal = new bootstrap.Modal(document.getElementById('modalBoletosNuevo'));
    modal.show();

    // Lógica para toggle nombres individuales
    const checkIndividuales = document.getElementById('checkNombresIndividuales');
    const containerGlobal = document.getElementById('containerNombreGlobal');
    const containerIndividuales = document.getElementById('containerNombresIndividuales');

    if (checkIndividuales) {
        checkIndividuales.addEventListener('change', function () {
            if (this.checked) {
                containerGlobal.style.display = 'none';
                containerIndividuales.style.display = 'block';
                // Enfocar el primer input individual
                const firstInput = containerIndividuales.querySelector('input');
                if (firstInput) firstInput.focus();
            } else {
                containerGlobal.style.display = 'flex'; // row usa flex
                containerIndividuales.style.display = 'none';
                document.getElementById('nombreClienteTicket').focus();
            }
        });
    }


    // Event listeners para la nueva sección
    const inputNombre = document.getElementById('nombreClienteTicket');
    const checkAuto = document.getElementById('checkAutoPrint');

    // Enfocar el input de nombre automáticamente
    setTimeout(() => {
        if (inputNombre && (!checkIndividuales || !checkIndividuales.checked)) inputNombre.focus();
    }, 500);

    // Enter en el input de nombre -> Imprimir
    if (inputNombre) {
        inputNombre.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                imprimirTodosBoletos();
            }
        });
    }

    // Also enter on individual inputs to print
    const inputsIndividuales = document.querySelectorAll('.input-nombre-individual');
    inputsIndividuales.forEach(input => {
        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                imprimirTodosBoletos();
            }
        });
    });

    // Auto-imprimir si está habilitado (lógica opcional, por ahora solo focus y enter para "confirmar")
    // El usuario pidió "mandar automaticamente", pero con el nombre pendiente, mejor esperar confirmación (Enter o Botón)

    // Cargar lista de impresoras
    const selectImpresora = document.getElementById('selectImpresoraTicket');
    if (selectImpresora) {
        // Cargar desde QZ Tray
        if (window.getPrinters) {
            window.getPrinters().then(printers => {
                if (printers && printers.length > 0) {
                    selectImpresora.innerHTML = '<option value="">-- Seleccionar Impresora --</option>';
                    printers.forEach(p => {
                        const option = document.createElement('option');
                        option.value = p;
                        option.textContent = p;
                        selectImpresora.appendChild(option);
                    });


                    // Restaurar preferencia guardada
                    const savedPrinter = localStorage.getItem('teatro_printer_pref');
                    if (savedPrinter && printers.includes(savedPrinter)) {
                        selectImpresora.value = savedPrinter;
                    } else if (printers.length > 0) {
                        // Seleccionar la primera por defecto si no hay guardada
                        selectImpresora.selectedIndex = 1;
                    }
                } else {
                    selectImpresora.innerHTML = '<option value="default">No se detectaron impresoras (QZ Tray)</option>';
                }
            }).catch(err => {
                console.error("Error cargando impresoras:", err);
                selectImpresora.innerHTML = '<option value="default" disabled>Error cargando impresoras</option>';
            });
        }

        selectImpresora.addEventListener('change', function () {
            localStorage.setItem('teatro_printer_pref', this.value);
            // notify.info('Preferencia de impresora guardada'); // Un poco molesto
        });
    }

    // Guardar boletos en variable global para acciones
    window.boletosActuales = boletos;
    console.log('[Boletos] Boletos guardados para acciones:', window.boletosActuales);

    // Limpiar al cerrar el modal
    document.getElementById('modalBoletosNuevo').addEventListener('hidden.bs.modal', function () {
        this.remove();
        delete window.boletosActuales;
        // Liberar el bloqueo de actualizaciones - la recarga se hace en los botones
        window.TEATRO_VENTA_MODAL_ABIERTO = false;
        console.log('[Carrito] BLOQUEO DE ACTUALIZACIONES LIBERADO');
    });
}

// Función para continuar vendiendo desde el modal de boletos
async function continuarVendiendoDesdeModal() {
    const modalBoletos = bootstrap.Modal.getInstance(document.getElementById('modalBoletosNuevo'));
    if (modalBoletos) {
        modalBoletos.hide();
    }
    // Enviar al cliente a la vista de horarios (mismo evento)
    if (typeof enviarMostrarHorarios === 'function') {
        enviarMostrarHorarios();
    }

    // Limpiar el horario seleccionado para que el usuario deba seleccionar uno nuevo
    const selectFuncion = document.getElementById('selectFuncion');
    if (selectFuncion) {
        selectFuncion.value = ''; // Limpiar selección
    }

    // Limpiar el input hidden de función
    const inputFuncion = document.getElementById('inputIdFuncion');
    if (inputFuncion) {
        inputFuncion.value = '';
    }

    // Resetear la variable global de función
    if (typeof ID_FUNCION !== 'undefined') {
        ID_FUNCION = null;
    }

    // Mostrar el overlay de "Seleccione un horario"
    let overlay = document.getElementById('overlaySinHorario');

    // Si el overlay no existe (porque se entró con un horario ya seleccionado), crearlo
    if (!overlay) {
        const seatMapWrapper = document.querySelector('.seat-map-wrapper');
        if (seatMapWrapper) {
            overlay = document.createElement('div');
            overlay.id = 'overlaySinHorario';
            overlay.className = 'overlay-sin-horario';
            overlay.innerHTML = `
                <div class="overlay-sin-horario-content">
                    <i class="bi bi-calendar-x"></i>
                    <h3>Seleccione un horario</h3>
                    <p>Para comenzar a vender, primero debe seleccionar un horario de función.</p>
                    <div class="arrow-indicator">
                        <i class="bi bi-arrow-up"></i>
                    </div>
                </div>
            `;
            seatMapWrapper.insertBefore(overlay, seatMapWrapper.firstChild);
        }
    }

    // Mostrar el overlay
    if (overlay) {
        overlay.classList.remove('hidden');
    }

    // Limpiar el carrito por si quedó algo
    if (window.TeatroReservas && typeof window.TeatroReservas.liberarTodos === 'function') {
        window.TeatroReservas.liberarTodos();
    }
    if (typeof carrito !== 'undefined') {
        carrito = [];
    }
    document.querySelectorAll('.seat.selected').forEach(s => s.classList.remove('selected'));
    if (typeof actualizarCarrito === 'function') {
        actualizarCarrito();
    }

    // Limpiar asientos vendidos del mapa (se cargarán nuevamente cuando se seleccione un horario)
    document.querySelectorAll('.seat.vendido').forEach(s => s.classList.remove('vendido'));
    if (typeof asientosVendidos !== 'undefined') {
        asientosVendidos = new Set();
    }

    // Liberar el bloqueo de actualizaciones
    window.TEATRO_VENTA_MODAL_ABIERTO = false;

    // <--- SINCRONIZACIÓN: NUEVA VENTA AL VISOR CLIENTE --->
    // Esto limpiará la pantalla de "Gracias por su compra"
    if (typeof enviarNuevaVenta === 'function') {
        enviarNuevaVenta();
    }

    notify.success('Seleccione un horario para continuar vendiendo');
}

// Función para cambiar de evento desde el modal de boletos
function cambiarDeEventoDesdeModal() {
    const modalBoletos = bootstrap.Modal.getInstance(document.getElementById('modalBoletosNuevo'));
    if (modalBoletos) {
        modalBoletos.hide();
    }
    // Enviar al cliente de regreso a la cartelera
    if (typeof enviarRegresarCartelera === 'function') {
        enviarRegresarCartelera();
    }
    // Redirigir a la selección de eventos
    setTimeout(() => {
        window.location.href = window.URL_REGRESAR || 'index.php';
    }, 300);
}

// Función para cancelar la venta recién realizada desde el modal de boletos
async function cancelarVentaDesdeModal() {
    if (!window.boletosActuales || window.boletosActuales.length === 0) {
        notify.warning('No hay boletos para cancelar');
        return;
    }

    // Confirmar cancelación
    const cantidadBoletos = window.boletosActuales.length;
    const confirmar = confirm(`¿Estás seguro de que deseas CANCELAR ${cantidadBoletos} boleto(s)?\n\nEsta acción liberará los asientos y anulará la venta.`);

    if (!confirmar) {
        return;
    }

    // Mostrar loading
    const btnCancelar = document.querySelector('.accion-btn.btn-danger');
    if (btnCancelar) {
        btnCancelar.disabled = true;
        btnCancelar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cancelando...';
    }

    try {
        // Cancelar cada boleto
        const codigos = window.boletosActuales.map(b => b.codigo_unico);
        let cancelados = 0;
        let errores = [];

        for (const codigo of codigos) {
            try {
                const response = await fetch('cancelar_boleto.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ codigo_unico: codigo })
                });

                const data = await response.json();

                if (data.success) {
                    cancelados++;
                } else {
                    errores.push(data.message || 'Error desconocido');
                }
            } catch (error) {
                errores.push(error.message);
            }
        }

        // Cerrar el modal de boletos SIN eliminar el elemento para evitar problemas
        const modalElement = document.getElementById('modalBoletosNuevo');
        const modalBoletos = bootstrap.Modal.getInstance(modalElement);
        if (modalBoletos) {
            modalBoletos.hide();
        }
        // Remover el modal después de que se oculte
        if (modalElement) {
            modalElement.addEventListener('hidden.bs.modal', function () {
                this.remove();
            }, { once: true });
        }

        // Liberar bloqueo
        window.TEATRO_VENTA_MODAL_ABIERTO = false;

        // Limpiar la variable de boletos actuales
        delete window.boletosActuales;

        // Actualizar el mapa de asientos SIN cambiar el horario seleccionado
        if (typeof cargarAsientosVendidos === 'function') {
            await cargarAsientosVendidos();
        }

        // Mostrar resultado
        if (cancelados === codigos.length) {
            notify.success(`¡Venta cancelada! Se liberaron ${cancelados} asiento(s). Puedes continuar vendiendo.`);
        } else if (cancelados > 0) {
            notify.warning(`Se cancelaron ${cancelados} de ${codigos.length} boletos. Algunos tuvieron errores.`);
        } else {
            notify.error('No se pudo cancelar ningún boleto. ' + errores.join(', '));
        }

        // NO llamamos a enviarRegresarCartelera() para mantener el horario seleccionado

    } catch (error) {
        console.error('Error al cancelar venta:', error);
        notify.error('Error al cancelar la venta: ' + error.message);

        // Restaurar botón
        if (btnCancelar) {
            btnCancelar.disabled = false;
            btnCancelar.innerHTML = '<i class="bi bi-x-circle"></i><span>Cancelar Venta</span>';
        }
    }
}

// Mostrar modal para elegir siguiente acción después de una venta
function mostrarModalSiguienteAccion() {
    // Remover modal anterior si existe
    const modalAnterior = document.getElementById('modalSiguienteAccion');
    if (modalAnterior) modalAnterior.remove();

    // Obtener nombre del evento actual
    const eventoActual = document.querySelector('.evento-badge')?.textContent || 'este evento';

    const modalHTML = `
        < div class="modal fade" id = "modalSiguienteAccion" tabindex = "-1" data - bs - backdrop="static" >
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            ¡Venta Completada!
                        </h5>
                    </div>
                    <div class="modal-body text-center p-4">
                        <div class="mb-4">
                            <i class="bi bi-question-circle text-primary" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="mb-3">¿Qué desea hacer ahora?</h4>
                        <p class="text-muted mb-4">Puede continuar vendiendo boletos o seleccionar otro evento.</p>

                        <div class="d-grid gap-3">
                            <button type="button" class="btn btn-success btn-lg" onclick="continuarVendiendo()">
                                <i class="bi bi-cart-plus me-2"></i>
                                Seguir vendiendo en ${eventoActual}
                            </button>
                            <button type="button" class="btn btn-primary btn-lg" onclick="cambiarDeEvento()">
                                <i class="bi bi-collection me-2"></i>
                                Seleccionar otro evento
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div >
        `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const modal = new bootstrap.Modal(document.getElementById('modalSiguienteAccion'));
    modal.show();
}

// Función para continuar vendiendo en el mismo evento
function continuarVendiendo() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalSiguienteAccion'));
    modal.hide();
    document.getElementById('modalSiguienteAccion').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
    notify.success('¡Listo para la siguiente venta!');
}

// Función para cambiar de evento
function cambiarDeEvento() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalSiguienteAccion'));
    modal.hide();
    document.getElementById('modalSiguienteAccion').addEventListener('hidden.bs.modal', function () {
        this.remove();
        window.location.href = window.URL_REGRESAR || 'index.php';
    });
}

// Descargar todos los boletos en un solo PDF usando fetch + Blob
async function descargarTodosBoletos() {
    console.log('[Boletos] Intentando descargar PDF. boletosActuales:', window.boletosActuales);

    if (!window.boletosActuales || window.boletosActuales.length === 0) {
        notify.warning('No hay boletos para descargar');
        return;
    }

    // Crear string con todos los códigos separados por comas
    const codigos = window.boletosActuales.map(b => {
        console.log('[Boletos] Procesando boleto:', b);
        return b.codigo_unico;
    }).filter(c => c); // Filtrar valores vacíos

    if (codigos.length === 0) {
        notify.error('Error: Los boletos no tienen código único');
        return;
    }

    const url = `descargar_todos_boletos.php?codigos=${codigos.join(',')}`;
    console.log('[Boletos] URL de descarga:', url);

    notify.info('Generando PDF...');

    try {
        // Usar fetch para obtener el PDF como blob
        const response = await fetch(url);

        if (!response.ok) {
            throw new Error('Error al generar el PDF');
        }

        const blob = await response.blob();

        // Crear URL de objeto temporal
        const blobUrl = URL.createObjectURL(blob);

        // Generar nombre de archivo basado en evento y fecha
        const eventoNombre = document.querySelector('.evento-badge')?.textContent?.trim() || 'Evento';
        const funcionSelect = document.getElementById('selectFuncion');
        let funcionTexto = '';
        if (funcionSelect && funcionSelect.selectedIndex > 0) {
            funcionTexto = funcionSelect.options[funcionSelect.selectedIndex].text.trim();
        }

        // Formatear fecha actual
        const ahora = new Date();
        const dia = String(ahora.getDate()).padStart(2, '0');
        const mes = String(ahora.getMonth() + 1).padStart(2, '0');
        const anio = ahora.getFullYear();
        const hora = String(ahora.getHours()).padStart(2, '0');
        const minutos = String(ahora.getMinutes()).padStart(2, '0');

        // Limpiar nombre de evento para uso en archivo
        const eventoLimpio = eventoNombre.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]/g, '').replace(/\s+/g, '_').substring(0, 30);

        // Nombre del archivo: Boletos_Evento_Fecha_Hora.pdf
        const nombreArchivo = `Boletos_${eventoLimpio}_${dia}-${mes}-${anio}_${hora}${minutos}.pdf`;

        // Crear enlace con nombre basado en evento
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = nombreArchivo;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Limpiar URL de objeto
        setTimeout(() => URL.revokeObjectURL(blobUrl), 100);

        notify.success('¡PDF descargado correctamente!');
    } catch (error) {
        console.error('[Boletos] Error al descargar PDF:', error);
        notify.error('Error al descargar el PDF: ' + error.message);
    }
}

// Imprimir todos los boletos (abre cada uno en una nueva ventana)
// Imprimir todos los boletos (Envío directo a impresora Termica via QZ Tray)
// Imprimir todos los boletos (Envío directo a impresora Termica via QZ Tray)
async function imprimirTodosBoletos() {
    if (!window.boletosActuales || window.boletosActuales.length === 0) {
        notify.warning('No hay boletos para imprimir');
        return;
    }

    const btnImprimir = document.querySelector('.accion-btn.btn-warning');
    const originalText = btnImprimir ? btnImprimir.innerHTML : '';
    if (btnImprimir) {
        btnImprimir.disabled = true;
        btnImprimir.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Conectando...';
    }

    // Obtener datos
    const selectImpresora = document.getElementById('selectImpresoraTicket');
    const nombreImpresora = selectImpresora ? selectImpresora.value : 'default';

    // Lógica de nombres: Individual o Global
    const checkIndividuales = document.getElementById('checkNombresIndividuales');
    const usarIndividuales = checkIndividuales && checkIndividuales.checked;

    let nombreGlobal = '';
    const nombresMap = {}; // codigo -> nombre

    if (usarIndividuales) {
        const inputs = document.querySelectorAll('.input-nombre-individual');
        inputs.forEach(inp => {
            const codigo = inp.getAttribute('data-codigo');
            const val = inp.value.trim();
            if (codigo && val) {
                nombresMap[codigo] = val;
            }
        });
    } else {
        const inputNombre = document.getElementById('nombreClienteTicket');
        nombreGlobal = inputNombre ? inputNombre.value.trim() : '';
    }

    const codigos = window.boletosActuales.map(b => b.codigo_unico);

    try {
        // 1. Obtener detalles completos de los boletos desde el backend
        const response = await fetch('imprimir_directo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                codigos: codigos,
                mode: 'data' // Solicitar datos JSOn
            })
        });

        const data = await response.json();

        if (data.success && data.boletos) {

            if (btnImprimir) btnImprimir.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Imprimiendo...';

            // ASIGNAR NOMBRES A LOS BOLETOS OBTENIDOS
            data.boletos.forEach(boleto => {
                if (usarIndividuales) {
                    // Si hay nombre individual para este código, usarlo
                    if (nombresMap[boleto.codigo_unico]) {
                        boleto.nombre_cliente = nombresMap[boleto.codigo_unico];
                    }
                } else {
                    // Usar nombre global
                    boleto.nombre_cliente = nombreGlobal;
                }
            });

            // 2. Enviar a QZ Tray
            // Pasamos null como 2do argumento porque los nombres ya van dentro de cada objeto boleto
            const result = await window.printTicketsQZ(data.boletos, null, nombreImpresora);

            if (result.success) {
                notify.success('Enviado a impresora con éxito');
            } else {
                console.error('Error QZ:', result.message);
                notify.error('Error QZ Tray: ' + result.message);

                // Fallback PDF
                if (confirm('No se pudo imprimir vía QZ Tray. ¿Descargar PDF?')) {
                    descargarTodosBoletos();
                }
            }
        } else {
            notify.error('Error al obtener datos del ticket: ' + data.message);
        }

    } catch (error) {
        console.error('Error:', error);
        notify.error('Error de comunicación');
    } finally {
        if (btnImprimir) {
            btnImprimir.disabled = false;
            btnImprimir.innerHTML = originalText;
        }
    }
}

// Función para abrir WhatsApp con un boleto
function enviarBoletoPorWhatsApp(codigoBoleto) {
    abrirWhatsAppWeb([codigoBoleto], false);
}

// Función para abrir WhatsApp con todos los boletos
function enviarTodosBoletosPorWhatsApp() {
    console.log('[WhatsApp] Intentando enviar boletos. boletosActuales:', window.boletosActuales);

    if (!window.boletosActuales || window.boletosActuales.length === 0) {
        notify.warning('No hay boletos para enviar');
        return;
    }

    const codigos = window.boletosActuales.map(b => b.codigo_unico).filter(c => c);
    console.log('[WhatsApp] Códigos a enviar:', codigos);

    if (codigos.length === 0) {
        notify.error('Error: Los boletos no tienen código único');
        return;
    }

    abrirWhatsAppWeb(codigos, true);
}

// Códigos de país comunes para WhatsApp
const CODIGOS_PAIS = [
    { codigo: '52', pais: '🇲🇽 México', nombre: 'México' },
    { codigo: '1', pais: '🇺🇸 Estados Unidos / 🇨🇦 Canadá', nombre: 'EE.UU./Canadá' },
    { codigo: '54', pais: '🇦🇷 Argentina', nombre: 'Argentina' },
    { codigo: '55', pais: '🇧🇷 Brasil', nombre: 'Brasil' },
    { codigo: '56', pais: '🇨🇱 Chile', nombre: 'Chile' },
    { codigo: '57', pais: '🇨🇴 Colombia', nombre: 'Colombia' },
    { codigo: '51', pais: '🇵🇪 Perú', nombre: 'Perú' },
    { codigo: '58', pais: '🇻🇪 Venezuela', nombre: 'Venezuela' },
    { codigo: '593', pais: '🇪🇨 Ecuador', nombre: 'Ecuador' },
    { codigo: '595', pais: '🇵🇾 Paraguay', nombre: 'Paraguay' },
    { codigo: '598', pais: '🇺🇾 Uruguay', nombre: 'Uruguay' },
    { codigo: '591', pais: '🇧🇴 Bolivia', nombre: 'Bolivia' },
    { codigo: '34', pais: '🇪🇸 España', nombre: 'España' },
    { codigo: '49', pais: '🇩🇪 Alemania', nombre: 'Alemania' },
    { codigo: '33', pais: '🇫🇷 Francia', nombre: 'Francia' },
    { codigo: '39', pais: '🇮🇹 Italia', nombre: 'Italia' },
    { codigo: '44', pais: '🇬🇧 Reino Unido', nombre: 'Reino Unido' },
    { codigo: '81', pais: '🇯🇵 Japón', nombre: 'Japón' },
    { codigo: '86', pais: '🇨🇳 China', nombre: 'China' },
    { codigo: '91', pais: '🇮🇳 India', nombre: 'India' }
];

// Función para abrir WhatsApp Web
async function abrirWhatsAppWeb(codigosBoletos, esMultiple) {
    // Remover modal anterior si existe
    const modalAnterior = document.getElementById('modalWhatsApp');
    if (modalAnterior) {
        modalAnterior.remove();
    }

    const textoBoleto = esMultiple ? 'boletos' : 'boleto';

    // Obtener información del evento y boletos
    let infoEvento = null;
    let asientosLista = [];

    try {
        const codigosStr = codigosBoletos.join(',');
        const response = await fetch(`obtener_info_boletos.php?codigos=${codigosStr}`);
        const data = await response.json();

        if (data.success) {
            infoEvento = data.evento;
            asientosLista = data.asientos;
        }
    } catch (error) {
        console.error('Error al obtener información de boletos:', error);
    }

    // Crear opciones de código de país
    let opcionesPais = '';
    CODIGOS_PAIS.forEach(pais => {
        const selected = pais.codigo === '52' ? 'selected' : '';
        opcionesPais += `<option value="${pais.codigo}" ${selected}>${pais.pais}</option>`;
    });

    const modalHTML = `
        <div class="modal fade" id="modalWhatsApp" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg border-0">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title d-flex align-items-center">
                            <i class="bi bi-whatsapp me-2 fs-4"></i>
                            Enviar ${textoBoleto} por WhatsApp
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info d-flex align-items-start mb-4">
                            <i class="bi bi-info-circle me-2 fs-5"></i>
                            <div>
                                <strong>Instrucciones:</strong><br>
                                    <small>Selecciona el código de país y ingresa el número de teléfono (sin espacios ni guiones).</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="codigoPais" class="form-label fw-bold">
                                <i class="bi bi-globe"></i> Código de País
                            </label>
                            <select class="form-select form-select-lg" id="codigoPais">
                                ${opcionesPais}
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="telefonoWhatsApp" class="form-label fw-bold">
                                <i class="bi bi-telephone"></i> Número de Teléfono
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light" id="codigoPaisDisplay">+52</span>
                                <input type="tel"
                                    class="form-control"
                                    id="telefonoWhatsApp"
                                    placeholder="4531197417"
                                    pattern="[0-9]+"
                                    maxlength="15"
                                    aria-describedby="codigoPaisDisplay"
                                    required>
                            </div>
                            <small class="form-text text-muted mt-1">
                                <i class="bi bi-exclamation-circle"></i> Solo números, sin espacios ni guiones
                            </small>
                        </div>

                        <div class="alert alert-warning d-flex align-items-start">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <div>
                                <strong>Nota:</strong> Se abrirá WhatsApp Web. Deberás adjuntar el${esMultiple ? 'os' : ''} PDF${esMultiple ? 's' : ''} del${esMultiple ? 'os' : ''} boleto${esMultiple ? 's' : ''} manualmente desde el chat.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-success btn-lg" onclick="confirmarAbrirWhatsApp()">
                            <i class="bi bi-whatsapp"></i> Abrir WhatsApp
                        </button>
                    </div>
                </div>
            </div>
        </div>
        `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Guardar códigos de boletos y información en el modal para usarlos después
    const modal = document.getElementById('modalWhatsApp');
    modal.dataset.codigos = JSON.stringify(codigosBoletos);
    modal.dataset.esMultiple = esMultiple;
    if (infoEvento) {
        modal.dataset.evento = JSON.stringify(infoEvento);
        modal.dataset.asientos = JSON.stringify(asientosLista);
    }

    // Actualizar el display del código de país cuando cambia el select
    const selectCodigoPais = document.getElementById('codigoPais');
    const displayCodigoPais = document.getElementById('codigoPaisDisplay');

    selectCodigoPais.addEventListener('change', function () {
        displayCodigoPais.textContent = '+' + this.value;
    });

    // Mostrar modal
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();

    // Enfocar el input después de que se muestre el modal
    modal.addEventListener('shown.bs.modal', function () {
        document.getElementById('telefonoWhatsApp').focus();
    });

    // Permitir enviar con Enter
    document.getElementById('telefonoWhatsApp').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            confirmarAbrirWhatsApp();
        }
    });

    // Limpiar al cerrar
    modal.addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}

// Función para confirmar y abrir WhatsApp
async function confirmarAbrirWhatsApp() {
    const inputTelefono = document.getElementById('telefonoWhatsApp');
    const selectCodigoPais = document.getElementById('codigoPais');
    const telefono = inputTelefono.value.trim().replace(/[^0-9]/g, '');
    const codigoPais = selectCodigoPais.value;

    if (!telefono) {
        notify.error('Por favor ingresa un número de teléfono');
        inputTelefono.focus();
        return;
    }

    if (telefono.length < 8) {
        notify.error('El número de teléfono es muy corto');
        inputTelefono.focus();
        return;
    }

    const modal = document.getElementById('modalWhatsApp');
    const codigosBoletos = JSON.parse(modal.dataset.codigos);
    const esMultiple = modal.dataset.esMultiple === 'true';

    // Obtener información del evento si está disponible
    let infoEvento = null;
    let asientosLista = [];
    if (modal.dataset.evento) {
        infoEvento = JSON.parse(modal.dataset.evento);
        asientosLista = JSON.parse(modal.dataset.asientos);
    }

    // Construir mensaje detallado
    let mensaje = 'Hola! Te envío tu';
    const textoBoleto = esMultiple ? 'boletos' : 'boleto';
    mensaje += ` ${textoBoleto} de entrada`;

    if (infoEvento) {
        mensaje += ` para: \n\n`;
        mensaje += `🎭 * ${infoEvento.titulo}* `;

        if (infoEvento.fecha) {
            mensaje += `\n📅 Fecha: ${infoEvento.fecha} `;
        }

        if (infoEvento.hora) {
            mensaje += `\n🕐 Hora: ${infoEvento.hora} `;
        }

        mensaje += `\n🎫 Asiento${esMultiple ? 's' : ''}: `;
        if (asientosLista.length > 0) {
            if (asientosLista.length === 1) {
                mensaje += asientosLista[0];
            } else {
                mensaje += asientosLista.join(', ');
            }
        }

        mensaje += `\n\n¡Nos vemos en el evento! 🎉`;
    } else {
        mensaje += '.';
    }

    // Codificar mensaje para URL
    const mensajeCodificado = encodeURIComponent(mensaje);

    // Construir número completo (código de país + número, sin el +)
    const numeroCompleto = codigoPais + telefono;

    // Construir URL de WhatsApp
    const urlWhatsApp = `https://api.whatsapp.com/send/?phone=${numeroCompleto}&text=${mensajeCodificado}&type=phone_number&app_absent=0`;

    // Descargar PDF del boleto(s) automáticamente usando fetch + Blob
    let urlDescarga;
    let nombreArchivo;

    // Generar nombre de archivo basado en evento y fecha
    const eventoNombre = infoEvento?.titulo || document.querySelector('.evento-badge')?.textContent?.trim() || 'Evento';
    const ahora = new Date();
    const dia = String(ahora.getDate()).padStart(2, '0');
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const anio = ahora.getFullYear();
    const hora = String(ahora.getHours()).padStart(2, '0');
    const minutos = String(ahora.getMinutes()).padStart(2, '0');
    const eventoLimpio = eventoNombre.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]/g, '').replace(/\s+/g, '_').substring(0, 30);

    if (esMultiple && codigosBoletos.length > 1) {
        const codigosStr = codigosBoletos.join(',');
        urlDescarga = `descargar_todos_boletos.php?codigos=${codigosStr}`;
        nombreArchivo = `Boletos_${eventoLimpio}_${dia}-${mes}-${anio}_${hora}${minutos}.pdf`;
    } else {
        urlDescarga = `descargar_boleto.php?codigo=${codigosBoletos[0]}`;
        nombreArchivo = `Boleto_${eventoLimpio}_${dia}-${mes}-${anio}_${hora}${minutos}.pdf`;
    }

    try {
        const response = await fetch(urlDescarga);
        if (response.ok) {
            const blob = await response.blob();
            const blobUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = blobUrl;
            link.download = nombreArchivo;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            setTimeout(() => URL.revokeObjectURL(blobUrl), 100);
        }
    } catch (error) {
        console.error('Error al descargar PDF:', error);
    }

    // Cerrar modal
    const bootstrapModal = bootstrap.Modal.getInstance(modal);
    bootstrapModal.hide();

    // Abrir WhatsApp en nueva ventana después de un pequeño delay para que la descarga inicie
    setTimeout(() => {
        window.open(urlWhatsApp, '_blank');

        // Mostrar notificación
        notify.success('PDF descargado y WhatsApp Web abierto. Puedes adjuntar el PDF desde el chat.');
    }, 500);
}

// Función para actualizar estadísticas
function actualizarEstadisticas() {
    const statAsientos = document.getElementById('statAsientos');
    const statTotal = document.getElementById('statTotal');

    if (statAsientos) {
        statAsientos.textContent = carrito.length;
    }

    if (statTotal) {
        const totalElement = document.getElementById('totalCompra');
        if (totalElement) {
            statTotal.textContent = totalElement.textContent;
        }
    }
}

// Actualizar el resumen de asientos (disponibles / ocupados / totales)
function actualizarResumenAsientos() {
    const lblDisp = document.getElementById('labelAsientosDisponibles');
    const lblOcup = document.getElementById('labelAsientosOcupados');
    const lblTot = document.getElementById('labelAsientosTotales');

    if (!lblDisp || !lblOcup || !lblTot) {
        return;
    }

    const seats = Array.from(document.querySelectorAll('.seat'));
    if (seats.length === 0) {
        lblDisp.textContent = '-';
        lblOcup.textContent = '-';
        lblTot.textContent = '-';
        return;
    }

    let totalVendibles = 0;
    let ocupados = 0;

    seats.forEach(seat => {
        // Excluir asientos marcados como "No Venta"
        if (seat.classList.contains('no-venta')) {
            return;
        }

        totalVendibles++;
        if (seat.classList.contains('vendido')) {
            ocupados++;
        }
    });

    const disponibles = Math.max(0, totalVendibles - ocupados);

    lblDisp.textContent = disponibles;
    lblOcup.textContent = ocupados;
    lblTot.textContent = totalVendibles;
}

// Función para limpiar toda la selección
function limpiarSeleccion() {
    if (carrito.length === 0) {
        notify.info('No hay asientos seleccionados');
        return;
    }

    const cantidad = carrito.length;

    if (window.TeatroReservas && typeof window.TeatroReservas.liberarTodos === 'function') {
        window.TeatroReservas.liberarTodos();
    }

    // Remover clase selected de todos los asientos
    document.querySelectorAll('.seat.selected').forEach(seat => {
        seat.classList.remove('selected');
    });

    // Limpiar carrito
    carrito = [];
    ultimoAsientoSeleccionado = null;

    // Limpiar descuento
    descuentoSeleccionado = null;
    const selectDescuento = document.getElementById('selectDescuento');
    if (selectDescuento) selectDescuento.value = '';
    const descuentoInfo = document.getElementById('descuentoInfo');
    if (descuentoInfo) descuentoInfo.textContent = '';

    actualizarCarrito();
    notify.success(`${cantidad} asiento(s) deseleccionado(s)`);

    // <--- SINCRONIZACIÓN: NUEVA VENTA AL VISOR CLIENTE --->
    if (typeof enviarNuevaVenta === 'function') {
        enviarNuevaVenta();
    }
}

// Función para seleccionar rango de asientos (Ctrl + Click)
function seleccionarRango(asientoActual) {
    if (!ultimoAsientoSeleccionado) return;

    const todosAsientos = Array.from(document.querySelectorAll('.seat'));
    const indexUltimo = todosAsientos.findIndex(s => s.dataset.asientoId === ultimoAsientoSeleccionado);
    const indexActual = todosAsientos.findIndex(s => s.dataset.asientoId === asientoActual);

    if (indexUltimo === -1 || indexActual === -1) return;

    const inicio = Math.min(indexUltimo, indexActual);
    const fin = Math.max(indexUltimo, indexActual);

    let seleccionados = 0;
    for (let i = inicio; i <= fin; i++) {
        const seat = todosAsientos[i];
        if (!seat.classList.contains('vendido') && !seat.classList.contains('selected')) {
            const asientoId = seat.dataset.asientoId;
            const categoriaId = seat.dataset.categoriaId;
            if (agregarAlCarrito(asientoId, categoriaId)) {
                seat.classList.add('selected');
                seleccionados++;
            }
        }
    }

    if (seleccionados > 0) {
        notify.success(`${seleccionados} asiento(s) seleccionado(s)`);
    }
}

// Función para seleccionar toda una fila
function seleccionarFila(filaLabel) {
    const asientosFila = document.querySelectorAll(`.seat[data-asiento-id^="${filaLabel}"]`);
    let seleccionados = 0;

    asientosFila.forEach(seat => {
        if (!seat.classList.contains('vendido') && !seat.classList.contains('selected')) {
            const asientoId = seat.dataset.asientoId;
            const categoriaId = seat.dataset.categoriaId;
            if (agregarAlCarrito(asientoId, categoriaId)) {
                seat.classList.add('selected');
                seleccionados++;
            }
        }
    });

    if (seleccionados > 0) {
        notify.success(`${seleccionados} asiento(s) de la fila ${filaLabel} seleccionado(s)`);
    } else {
        notify.info('No hay asientos disponibles en esta fila');
    }
}

// Función para deseleccionar toda una fila
function deseleccionarFila(filaLabel) {
    const asientosFila = document.querySelectorAll(`.seat[data-asiento-id^="${filaLabel}"].selected`);
    let deseleccionados = 0;

    asientosFila.forEach(seat => {
        const asientoId = seat.dataset.asientoId;
        removerDelCarrito(asientoId);
        seat.classList.remove('selected');
        deseleccionados++;
    });

    if (deseleccionados > 0) {
        notify.info(`${deseleccionados} asiento(s) de la fila ${filaLabel} deseleccionado(s)`);
    }
}

// Función para alternar modo de selección múltiple
function toggleModoSeleccionMultiple() {
    modoSeleccionMultiple = !modoSeleccionMultiple;
    const btn = document.getElementById('btnModoMultiple');

    if (modoSeleccionMultiple) {
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-primary');
        btn.innerHTML = '<i class="bi bi-check-square-fill"></i> Modo Múltiple: ON';
        notify.info('Modo selección múltiple activado');
    } else {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-primary');
        btn.innerHTML = '<i class="bi bi-check-square"></i> Modo Múltiple';
        notify.info('Modo selección múltiple desactivado');
    }
}

// Función removida por preferencia del usuario
// function seleccionarNAsientos() { ... }

// Función para inicializar event listeners de los asientos usando event delegation
// Event delegation es más robusto porque funciona con elementos añadidos dinámicamente
let eventListenersInicializados = false;

function inicializarEventListenersAsientos() {
    const seatMapContent = document.getElementById('seatMapContent') || document.querySelector('.seat-map-content');

    if (!seatMapContent) {
        console.warn('No se encontró el contenedor del mapa de asientos');
        return;
    }

    // Solo inicializar event delegation una vez
    if (!eventListenersInicializados) {
        // Event delegation para clicks en asientos
        seatMapContent.addEventListener('click', (e) => {
            const seat = e.target.closest('.seat');
            if (!seat) return;

            const asientoId = seat.dataset.asientoId;
            const categoriaId = seat.dataset.categoriaId;

            if (!asientoId) return;

            // Selección por rango con Ctrl
            if (e.ctrlKey && ultimoAsientoSeleccionado) {
                seleccionarRango(asientoId);
                e.stopPropagation();
                return;
            }

            // Si ya está seleccionado, remover
            if (seat.classList.contains('selected')) {
                removerDelCarrito(asientoId);
                seat.classList.remove('selected');
                ultimoAsientoSeleccionado = null;
            } else if (!seat.classList.contains('vendido')) {
                // Si no está vendido, agregar
                if (agregarAlCarrito(asientoId, categoriaId)) {
                    seat.classList.add('selected');
                    ultimoAsientoSeleccionado = asientoId;
                }
            }

            e.stopPropagation();
        });

        // Event delegation para doble click (seleccionar fila completa)
        seatMapContent.addEventListener('dblclick', (e) => {
            const seat = e.target.closest('.seat');
            if (!seat) return;

            e.preventDefault();
            e.stopPropagation();

            const asientoId = seat.dataset.asientoId;
            if (!asientoId) return;

            // Extraer la fila del código de asiento
            const filaMatch = asientoId.match(/^([A-Z]+\d*)/);
            if (filaMatch) {
                const fila = filaMatch[1];
                if (seat.classList.contains('selected')) {
                    deseleccionarFila(fila);
                } else {
                    seleccionarFila(fila);
                }
            }
        });

        // Event delegation para row labels
        seatMapContent.addEventListener('dblclick', (e) => {
            const label = e.target.closest('.row-label');
            if (!label) return;

            e.preventDefault();
            e.stopPropagation();

            const fila = label.textContent.trim();
            if (!fila) return;

            // Verificar si hay asientos seleccionados en esta fila
            const asientosFila = document.querySelectorAll(`.seat[data-asiento-id^="${fila}"].selected`);
            if (asientosFila.length > 0) {
                deseleccionarFila(fila);
            } else {
                seleccionarFila(fila);
            }
        });

        eventListenersInicializados = true;
        console.log('Event delegation de asientos inicializado');
    }

    // Configurar estilos para row labels
    document.querySelectorAll('.row-label').forEach(label => {
        label.style.cursor = 'pointer';
        const fila = label.textContent.trim();
        if (fila) {
            label.title = `Doble click para seleccionar toda la fila ${fila}`;
        }
    });

    // Inicializar estadísticas
    actualizarEstadisticas();

    console.log('Event listeners de asientos configurados');
}

// Exponer la función globalmente
window.inicializarEventListenersAsientos = inicializarEventListenersAsientos;

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', () => {
    cargarAsientosVendidos();
    cargarDescuentos();
    inicializarEventListenersAsientos();
    marcarAsientosNoVenta(); // Marcar asientos de categoría "No Venta"

    // Escuchar cambios en los descuentos desde admin
    window.addEventListener('storage', (e) => {
        if (e.key === 'descuentos_actualizados') {
            try {
                const data = JSON.parse(e.newValue);
                const idEventoActual = obtenerIdEvento();

                // Si el cambio es para nuestro evento actual, recargar descuentos
                if (data.id_evento == idEventoActual || !data.id_evento) {
                    console.log('Descuentos actualizados desde admin, recargando...');
                    cargarDescuentos();
                    notify.info('Los descuentos han sido actualizados');
                }
            } catch (err) {
                console.error('Error procesando actualización de descuentos:', err);
            }
        }
    });
});

// Función para mostrar/ocultar botones de acciones según el estado del carrito
function mostrarBotonesAcciones(mostrar) {
    // Seleccionar los botones que queremos ocultar cuando hay asientos en el carrito
    const btnEscanerQR = document.querySelector('.acciones-rapidas .btn-primary');
    const btnCancelarBoleto = document.querySelector('.acciones-rapidas .btn-danger');
    const btnCategorias = document.querySelector('.acciones-rapidas .btn-warning');

    if (mostrar) {
        // Mostrar los botones
        if (btnEscanerQR) btnEscanerQR.style.display = '';
        if (btnCancelarBoleto) btnCancelarBoleto.style.display = '';
        if (btnCategorias) btnCategorias.style.display = '';
    } else {
        // Ocultar los botones
        if (btnEscanerQR) btnEscanerQR.style.display = 'none';
        if (btnCancelarBoleto) btnCancelarBoleto.style.display = 'none';
        if (btnCategorias) btnCategorias.style.display = 'none';
    }
}

// Exponer funciones globalmente para uso en onclick
window.aplicarPreciosCatalogo = function aplicarPreciosCatalogo(catalogo) {
    if (!catalogo || !catalogo.categorias) return;

    if (catalogo.config_tipos_boleto) {
        window.CONFIG_TIPOS_BOLETO = catalogo.config_tipos_boleto;
    }

    carrito.forEach((item) => {
        normalizarTipoBoletoItem(item);
        const info = catalogo.categorias[item.categoriaId];
        if (info) {
            item.precio = parseFloat(info.precio);
            item.categoria = info.nombre;
            if (info.color) {
                item.color = info.color;
            }
        }
    });

    if (carrito.length > 0 && typeof actualizarCarrito === 'function') {
        actualizarCarrito();
    }

    // Actualizar badges de precio en la paleta del mapa (si existe)
    document.querySelectorAll('.palette-item[data-cat-id]').forEach((el) => {
        const catId = el.dataset.catId;
        const info = catalogo.categorias[catId];
        if (info) {
            const badge = el.querySelector('.badge');
            if (badge) {
                badge.textContent = '$' + Number(info.precio).toLocaleString('es-MX', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
            }
        }
    });
};

window.enviarBoletoPorWhatsApp = enviarBoletoPorWhatsApp;
window.enviarTodosBoletosPorWhatsApp = enviarTodosBoletosPorWhatsApp;
window.confirmarAbrirWhatsApp = confirmarAbrirWhatsApp;
window.descargarTodosBoletos = descargarTodosBoletos;
window.imprimirTodosBoletos = imprimirTodosBoletos;
window.mostrarModalSiguienteAccion = mostrarModalSiguienteAccion;
window.continuarVendiendo = continuarVendiendo;
window.cambiarDeEvento = cambiarDeEvento;
window.continuarVendiendoDesdeModal = continuarVendiendoDesdeModal;
window.cambiarDeEventoDesdeModal = cambiarDeEventoDesdeModal;
window.aplicarDescuentoDesdeModal = aplicarDescuentoDesdeModal;
window.toggleDescuento = toggleDescuento;
window.aplicarTipoATodos = aplicarTipoATodos;
window.getColorForType = getColorForType;
