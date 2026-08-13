// Mejoras para el menú del punto de venta

// Función para actualizar estadísticas en tiempo real
function actualizarEstadisticasMenu() {
    const statAsientos = document.getElementById('statAsientos');
    const statTotal = document.getElementById('statTotal');
    
    if (statAsientos && typeof carrito !== 'undefined') {
        statAsientos.textContent = carrito.length;
    }
    
    if (statTotal) {
        const totalElement = document.getElementById('totalCompra');
        if (totalElement) {
            statTotal.textContent = totalElement.textContent;
        }
    }
}

// Función para colapsar/expandir secciones
function toggleSeccion(seccionId) {
    const content = document.getElementById(`seccion-${seccionId}`);
    const header = content ? content.previousElementSibling : null;
    
    if (content && header) {
        if (content.classList.contains('collapsed')) {
            content.classList.remove('collapsed');
            header.classList.remove('collapsed');
        } else {
            content.classList.add('collapsed');
            header.classList.add('collapsed');
        }
    }
}

// Función para limpiar la selección
function limpiarSeleccion() {
    if (typeof carrito !== 'undefined' && carrito.length > 0) {
        if (confirm('¿Deseas limpiar todos los asientos seleccionados?')) {
            if (window.TeatroReservas && typeof window.TeatroReservas.liberarTodos === 'function') {
                window.TeatroReservas.liberarTodos();
            }
            // Remover clase selected de todos los asientos
            document.querySelectorAll('.seat.selected').forEach(seat => {
                seat.classList.remove('selected');
            });
            
            // Limpiar carrito
            carrito = [];
            
            // Limpiar descuento
            const selectDescuento = document.getElementById('selectDescuento');
            if (selectDescuento) selectDescuento.value = '';
            
            const descuentoInfo = document.getElementById('descuentoInfo');
            if (descuentoInfo) descuentoInfo.textContent = '';
            
            if (typeof descuentoSeleccionado !== 'undefined') {
                descuentoSeleccionado = null;
            }
            
            // Actualizar vista
            if (typeof actualizarCarrito === 'function') {
                actualizarCarrito();
            }
            
            if (typeof notify !== 'undefined') {
                notify.info('Selección limpiada');
            }
        }
    } else {
        if (typeof notify !== 'undefined') {
            notify.info('No hay asientos seleccionados');
        }
    }
}

// Función para ver categorías
function verCategorias() {
    const modal = new bootstrap.Modal(document.getElementById('modalCategorias'));
    modal.show();
}

// Función para ver asientos vendidos
async function verAsientosVendidos() {
    const modal = new bootstrap.Modal(document.getElementById('modalAsientosVendidos'));
    modal.show();
    
    const urlParams = new URLSearchParams(window.location.search);
    const idEvento = urlParams.get('id_evento');
    
    if (!idEvento) {
        document.getElementById('listaAsientosVendidos').innerHTML = `
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> 
                No hay evento seleccionado
            </div>
        `;
        return;
    }
    
    try {
        const response = await fetch(`obtener_asientos_vendidos.php?id_evento=${idEvento}`);
        const data = await response.json();
        
        if (data.success && data.asientos.length > 0) {
            let html = '<div class="row g-2">';
            
            // Agrupar por categoría si es posible
            const asientosOrdenados = data.asientos.sort();
            
            asientosOrdenados.forEach(asiento => {
                html += `
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="badge bg-danger w-100 p-2 fs-6">
                            <i class="bi bi-x-circle"></i> ${asiento}
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            html += `<div class="alert alert-info mt-3 mb-0">
                <strong>${data.asientos.length}</strong> asiento(s) vendido(s)
            </div>`;
            
            document.getElementById('listaAsientosVendidos').innerHTML = html;
        } else {
            document.getElementById('listaAsientosVendidos').innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> 
                    No hay asientos vendidos aún. ¡Todos disponibles!
                </div>
            `;
        }
    } catch (error) {
        console.error('Error al cargar asientos vendidos:', error);
        document.getElementById('listaAsientosVendidos').innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i> 
                Error al cargar los asientos vendidos
            </div>
        `;
    }
}

// Observar cambios en el carrito para actualizar estadísticas automáticamente
function inicializarObservadorEstadisticas() {
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(() => {
            actualizarEstadisticasMenu();
        });
        
        const totalElement = document.getElementById('totalCompra');
        if (totalElement) {
            observer.observe(totalElement, { 
                childList: true, 
                characterData: true, 
                subtree: true 
            });
        }
        
        const carritoItems = document.getElementById('carritoItems');
        if (carritoItems) {
            observer.observe(carritoItems, { 
                childList: true, 
                subtree: true 
            });
        }
    }
}

// Inicializar mejoras del menú
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar estadísticas
    setTimeout(() => {
        actualizarEstadisticasMenu();
        inicializarObservadorEstadisticas();
    }, 500);
    
    // Mantener la sección del carrito abierta por defecto
    const seccionCarrito = document.getElementById('seccion-carrito');
    if (seccionCarrito) {
        seccionCarrito.classList.remove('collapsed');
    }
});

// Exportar funciones para uso global
window.actualizarEstadisticasMenu = actualizarEstadisticasMenu;
window.toggleSeccion = toggleSeccion;
window.limpiarSeleccion = limpiarSeleccion;
window.verCategorias = verCategorias;
window.verAsientosVendidos = verAsientosVendidos;


// Mejorar feedback visual del selector de descuentos
document.addEventListener('DOMContentLoaded', () => {
    const selectDescuento = document.getElementById('selectDescuento');
    const contenedor = document.querySelector('.descuento-selector-separado');
    
    if (selectDescuento && contenedor) {
        selectDescuento.addEventListener('change', () => {
            if (selectDescuento.value) {
                contenedor.classList.add('activo');
            } else {
                contenedor.classList.remove('activo');
            }
        });
    }
});
