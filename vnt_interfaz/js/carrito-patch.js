// Parche para sobrescribir la función aplicarDescuento
// Este archivo debe cargarse DESPUÉS de carrito.js

// Sobrescribir la función aplicarDescuento
window.aplicarDescuento = function() {
    const select = document.getElementById('selectDescuento');
    const infoElement = document.getElementById('descuentoInfo');
    
    if (!select.value) {
        descuentoSeleccionado = null;
        carrito.forEach(item => {
            item.descuentoAplicado = false;
        });
        infoElement.textContent = '';
        actualizarCarrito();
        return;
    }

    if (carrito.length === 0) {
        notify.warning('Primero selecciona asientos para aplicar el descuento');
        select.value = '';
        return;
    }

    const descuento = descuentos.find(d => d.id_promocion == select.value);
    
    if (descuento) {
        if (carrito.length < descuento.min_cantidad) {
            notify.warning(`Este descuento requiere al menos ${descuento.min_cantidad} boleto(s). Actualmente tienes ${carrito.length}.`);
            select.value = '';
            return;
        }
        
        descuentoSeleccionado = descuento;
        abrirModalSeleccionDescuento(descuento);
    }
};

// Sobrescribir removerDelCarrito para verificar descuentos
const removerDelCarritoOriginal = window.removerDelCarrito;
window.removerDelCarrito = function(asientoId) {
    // Llamar a la función original
    removerDelCarritoOriginal(asientoId);
    
    // Verificar si quedan boletos con descuento aplicado
    if (carrito.length > 0 && descuentoSeleccionado) {
        const boletosConDescuento = carrito.filter(item => item.descuentoAplicado);
        
        // Si no quedan boletos con descuento, resetear
        if (boletosConDescuento.length === 0) {
            descuentoSeleccionado = null;
            const selectDescuento = document.getElementById('selectDescuento');
            if (selectDescuento) selectDescuento.value = '';
            const descuentoInfo = document.getElementById('descuentoInfo');
            if (descuentoInfo) descuentoInfo.textContent = '';
            
            // Actualizar carrito para reflejar cambios
            actualizarCarrito();
        } else {
            // Actualizar el texto de info con la cantidad correcta
            const descuentoInfo = document.getElementById('descuentoInfo');
            if (descuentoInfo) {
                descuentoInfo.textContent = `Descuento aplicado a ${boletosConDescuento.length} boleto(s)`;
            }
        }
    }
};
