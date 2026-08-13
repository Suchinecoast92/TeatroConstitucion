// Sistema de selección individual de descuentos por boleto

// Abrir modal para seleccionar boletos con descuento
function abrirModalSeleccionDescuento(descuento) {
    descuentoSeleccionado = descuento;

    // Crear el modal dinámicamente
    const modalHTML = `
        <div class="modal fade" id="modalSeleccionDescuento" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title">
                            <i class="bi bi-tag-fill"></i> Aplicar Descuento
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <strong>${descuento.nombre}</strong><br>
                            <small>
                                ${descuento.modo_calculo === 'porcentaje'
            ? `Descuento del ${descuento.valor}%`
            : `Descuento de $${parseFloat(descuento.valor).toFixed(2)}`}
                                ${descuento.nombre_categoria ? ` (solo ${descuento.nombre_categoria})` : ''}
                            </small>
                        </div>
                        
                        <p class="mb-3">
                            <i class="bi bi-info-circle text-primary"></i>
                            Selecciona los boletos a los que deseas aplicar este descuento:
                        </p>
                        
                        <div class="mb-3">
                            <button class="btn btn-sm btn-outline-primary me-2" onclick="seleccionarTodosDescuento()">
                                <i class="bi bi-check-all"></i> Seleccionar Todos
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="deseleccionarTodosDescuento()">
                                <i class="bi bi-x"></i> Deseleccionar Todos
                            </button>
                        </div>
                        
                        <div id="listaBoletosDescuento" class="lista-boletos-descuento">
                            <!-- Se llenará dinámicamente -->
                        </div>
                        
                        <div class="mt-3 p-3 bg-light rounded">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><strong>Boletos con descuento:</strong></span>
                                <span class="badge bg-primary" id="contadorDescuentos">0</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span><strong>Descuento total:</strong></span>
                                <span class="text-success fw-bold" id="totalDescuentoModal">$0.00</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-warning" onclick="confirmarDescuentos()">
                            <i class="bi bi-check-circle"></i> Aplicar Descuento
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remover modal anterior si existe
    const modalAnterior = document.getElementById('modalSeleccionDescuento');
    if (modalAnterior) {
        modalAnterior.remove();
    }

    // Agregar modal al DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Llenar lista de boletos
    llenarListaBoletosDescuento();

    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('modalSeleccionDescuento'));
    modal.show();

    // Limpiar al cerrar
    document.getElementById('modalSeleccionDescuento').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}

// Llenar lista de boletos en el modal
function llenarListaBoletosDescuento() {
    const lista = document.getElementById('listaBoletosDescuento');
    if (!lista) return;

    let html = '';

    carrito.forEach((item, index) => {
        // Verificar si el descuento aplica a esta categoría
        const aplicaCategoria = !descuentoSeleccionado.id_categoria ||
            descuentoSeleccionado.id_categoria == item.categoriaId;

        const deshabilitado = !aplicaCategoria;
        const checked = item.descuentoAplicado && aplicaCategoria ? 'checked' : '';

        // Calcular descuento para este item
        let montoDescuento = 0;
        if (aplicaCategoria) {
            if (descuentoSeleccionado.modo_calculo === 'porcentaje') {
                montoDescuento = item.precio * (parseFloat(descuentoSeleccionado.valor) / 100);
            } else {
                montoDescuento = parseFloat(descuentoSeleccionado.valor);
            }
            montoDescuento = Math.min(montoDescuento, item.precio);
        }

        html += `
            <div class="boleto-descuento-item ${deshabilitado ? 'disabled' : ''}" data-index="${index}">
                <div class="form-check">
                    <input class="form-check-input boleto-checkbox" 
                           type="checkbox" 
                           id="boleto_${index}" 
                           data-index="${index}"
                           ${checked}
                           ${deshabilitado ? 'disabled' : ''}
                           onchange="actualizarContadorDescuentos()">
                    <label class="form-check-label" for="boleto_${index}">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <div>
                                <strong>${item.asiento}</strong>
                                <small class="d-block text-muted">${item.categoria}</small>
                                <span class="text-success">$${item.precio.toFixed(2)}</span>
                            </div>
                            <div class="text-end">
                                ${aplicaCategoria ? `
                                    <span class="badge bg-success">-$${montoDescuento.toFixed(2)}</span>
                                    <small class="d-block text-primary">$${(item.precio - montoDescuento).toFixed(2)}</small>
                                ` : `
                                    <small class="text-muted">No aplica</small>
                                `}
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        `;
    });

    lista.innerHTML = html;
    actualizarContadorDescuentos();
}

// Seleccionar todos los boletos
function seleccionarTodosDescuento() {
    document.querySelectorAll('.boleto-checkbox:not(:disabled)').forEach(checkbox => {
        checkbox.checked = true;
    });
    actualizarContadorDescuentos();
}

// Deseleccionar todos los boletos
function deseleccionarTodosDescuento() {
    document.querySelectorAll('.boleto-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    actualizarContadorDescuentos();
}

// Actualizar contador de descuentos
function actualizarContadorDescuentos() {
    const checkboxes = document.querySelectorAll('.boleto-checkbox:checked:not(:disabled)');
    const contador = document.getElementById('contadorDescuentos');
    const totalDescuento = document.getElementById('totalDescuentoModal');

    if (contador) {
        contador.textContent = checkboxes.length;
    }

    // Calcular total de descuento
    let total = 0;
    checkboxes.forEach(checkbox => {
        const index = parseInt(checkbox.dataset.index);
        const item = carrito[index];

        if (item) {
            let montoDescuento = 0;
            if (descuentoSeleccionado.modo_calculo === 'porcentaje') {
                montoDescuento = item.precio * (parseFloat(descuentoSeleccionado.valor) / 100);
            } else {
                montoDescuento = parseFloat(descuentoSeleccionado.valor);
            }
            montoDescuento = Math.min(montoDescuento, item.precio);
            total += montoDescuento;
        }
    });

    if (totalDescuento) {
        totalDescuento.textContent = `$${total.toFixed(2)}`;
    }
}

// Confirmar aplicación de descuentos
function confirmarDescuentos() {
    const checkboxes = document.querySelectorAll('.boleto-checkbox');
    let aplicados = 0;

    // Marcar los boletos seleccionados
    checkboxes.forEach(checkbox => {
        const index = parseInt(checkbox.dataset.index);
        if (carrito[index]) {
            carrito[index].descuentoAplicado = checkbox.checked && !checkbox.disabled;
            if (checkbox.checked && !checkbox.disabled) {
                aplicados++;
            }
        }
    });

    // Actualizar info de descuento
    const infoElement = document.getElementById('descuentoInfo');
    if (infoElement) {
        if (aplicados > 0) {
            let infoTexto = `Descuento aplicado a ${aplicados} boleto(s)`;
            infoElement.textContent = infoTexto;
        } else {
            infoElement.textContent = '';
            descuentoSeleccionado = null;
            const selectDescuento = document.getElementById('selectDescuento');
            if (selectDescuento) selectDescuento.value = '';
        }
    }

    // Cerrar modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalSeleccionDescuento'));
    if (modal) {
        modal.hide();
    }

    // Actualizar carrito
    actualizarCarrito();

    if (aplicados > 0) {
        notify.success(`Descuento aplicado a ${aplicados} boleto(s)`);
    }
}


