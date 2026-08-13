// Sistema unificado de gestión de boletos (Escanear/Verificar/Cancelar)
let html5QrCode = null;
let modalGestionBoletos = null;
let modalBoletoInfo = null;
let modoActual = 'verificar'; // 'verificar' o 'cancelar'
let metodoInput = 'camara'; // 'camara' o 'manual'

// Abrir el modal de gestión de boletos
function abrirGestionBoletos(modo = 'verificar') {
    modoActual = modo;
    metodoInput = 'camara'; // Por defecto, iniciar con cámara

    console.log('Abriendo gestión de boletos, modo:', modoActual);

    crearModalGestionBoletos();
    modalGestionBoletos = new bootstrap.Modal(document.getElementById('modalGestionBoletos'));
    modalGestionBoletos.show();

    // Configurar eventos del modal
    document.getElementById('modalGestionBoletos').addEventListener('shown.bs.modal', function () {
        if (metodoInput === 'camara') {
            iniciarEscaner();
        }
    }, { once: true });

    document.getElementById('modalGestionBoletos').addEventListener('hidden.bs.modal', function () {
        detenerEscaner();
        // Limpiar el modal
        this.remove();
    }, { once: true });
}

// Alias para compatibilidad
function abrirEscanerQR() {
    abrirGestionBoletos('verificar');
}

// Función para cancelar boleto (alias)
function abrirCancelarBoleto() {
    abrirGestionBoletos('cancelar');
}

// Crear el modal dinámicamente
function crearModalGestionBoletos() {
    // Remover modal anterior si existe
    const modalAnterior = document.getElementById('modalGestionBoletos');
    if (modalAnterior) modalAnterior.remove();

    const tituloModo = modoActual === 'cancelar' ? 'Cancelar Boleto' : 'Verificar Boleto';
    const iconoModo = modoActual === 'cancelar' ? 'bi-x-circle' : 'bi-qr-code-scan';
    const colorModo = modoActual === 'cancelar' ? 'bg-danger' : 'bg-primary';

    const modalHTML = `
        <div class="modal fade" id="modalGestionBoletos" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header ${colorModo} text-white">
                        <h5 class="modal-title">
                            <i class="bi ${iconoModo}"></i> ${tituloModo}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <!-- Selector de modo -->
                        <div class="p-3 bg-light border-bottom">
                            <div class="btn-group w-100 mb-3" role="group">
                                <button type="button" class="btn ${modoActual === 'verificar' ? 'btn-primary' : 'btn-outline-primary'}" 
                                        onclick="cambiarModoGestion('verificar')">
                                    <i class="bi bi-check-circle"></i> Verificar Entrada
                                </button>
                                <button type="button" class="btn ${modoActual === 'cancelar' ? 'btn-danger' : 'btn-outline-danger'}" 
                                        onclick="cambiarModoGestion('cancelar')">
                                    <i class="bi bi-x-circle"></i> Cancelar Boleto
                                </button>
                            </div>
                            
                            <!-- Selector de método de entrada -->
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-secondary metodo-btn active" id="btnMetodoCamara"
                                        onclick="cambiarMetodoInput('camara')">
                                    <i class="bi bi-camera"></i> Cámara
                                </button>
                                <button type="button" class="btn btn-outline-secondary metodo-btn" id="btnMetodoManual"
                                        onclick="cambiarMetodoInput('manual')">
                                    <i class="bi bi-keyboard"></i> Escribir Código
                                </button>
                            </div>
                        </div>
                        
                        <!-- Área de escaneo con cámara -->
                        <div id="seccionCamara" class="p-3">
                            <div id="qr-reader" style="width: 100%; max-width: 450px; min-height: 350px; margin: 0 auto; background: #1e293b; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                <div class="text-center text-white">
                                    <div class="spinner-border text-primary mb-3" role="status"></div>
                                    <p>Esperando cámara...</p>
                                </div>
                            </div>
                            <div id="qr-reader-results" class="mt-3 text-center"></div>
                            <div class="text-center mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnEspejo" onclick="toggleEfectoEspejo()">
                                    <i class="bi bi-arrow-left-right"></i> Efecto Espejo
                                </button>
                            </div>
                        </div>
                        
                        <!-- Área de entrada manual -->
                        <div id="seccionManual" class="p-4" style="display: none;">
                            <div class="text-center mb-4">
                                <i class="bi bi-upc-scan text-muted" style="font-size: 4rem;"></i>
                                <p class="text-muted mt-2">Ingrese el código del boleto</p>
                            </div>
                            <div class="input-group input-group-lg mb-3">
                                <span class="input-group-text"><i class="bi bi-ticket-perforated"></i></span>
                                <input type="text" class="form-control text-uppercase" id="inputCodigoManual" 
                                       placeholder="Ej: ABC123XYZ" autocomplete="off"
                                       onkeypress="if(event.key === 'Enter') buscarBoletoPorCodigo()">
                            </div>
                            <button type="button" class="btn btn-lg w-100 ${modoActual === 'cancelar' ? 'btn-danger' : 'btn-primary'}" 
                                    onclick="buscarBoletoPorCodigo()">
                                <i class="bi bi-search"></i> Buscar Boleto
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Cambiar modo de gestión
function cambiarModoGestion(nuevoModo) {
    modoActual = nuevoModo;

    // Actualizar botones
    const btnVerificar = document.querySelector('[onclick="cambiarModoGestion(\'verificar\')"]');
    const btnCancelar = document.querySelector('[onclick="cambiarModoGestion(\'cancelar\')"]');
    const btnBuscar = document.querySelector('[onclick="buscarBoletoPorCodigo()"]');
    const headerModal = document.querySelector('#modalGestionBoletos .modal-header');
    const tituloModal = document.querySelector('#modalGestionBoletos .modal-title');

    if (nuevoModo === 'cancelar') {
        btnVerificar.className = 'btn btn-outline-primary';
        btnCancelar.className = 'btn btn-danger';
        btnBuscar.className = 'btn btn-lg w-100 btn-danger';
        headerModal.className = 'modal-header bg-danger text-white';
        tituloModal.innerHTML = '<i class="bi bi-x-circle"></i> Cancelar Boleto';
    } else {
        btnVerificar.className = 'btn btn-primary';
        btnCancelar.className = 'btn btn-outline-danger';
        btnBuscar.className = 'btn btn-lg w-100 btn-primary';
        headerModal.className = 'modal-header bg-primary text-white';
        tituloModal.innerHTML = '<i class="bi bi-qr-code-scan"></i> Verificar Boleto';
    }

    console.log('Modo cambiado a:', nuevoModo);
}

// Cambiar método de entrada
function cambiarMetodoInput(nuevoMetodo) {
    metodoInput = nuevoMetodo;

    const seccionCamara = document.getElementById('seccionCamara');
    const seccionManual = document.getElementById('seccionManual');
    const btnCamara = document.getElementById('btnMetodoCamara');
    const btnManual = document.getElementById('btnMetodoManual');

    if (nuevoMetodo === 'camara') {
        seccionCamara.style.display = 'block';
        seccionManual.style.display = 'none';
        btnCamara.classList.add('active');
        btnManual.classList.remove('active');
        iniciarEscaner();
    } else {
        seccionCamara.style.display = 'none';
        seccionManual.style.display = 'block';
        btnCamara.classList.remove('active');
        btnManual.classList.add('active');
        detenerEscaner();
        // Enfocar el input
        setTimeout(() => {
            document.getElementById('inputCodigoManual').focus();
        }, 100);
    }

    console.log('Método cambiado a:', nuevoMetodo);
}

// Buscar boleto por código escrito
function buscarBoletoPorCodigo() {
    const input = document.getElementById('inputCodigoManual');
    const codigo = input.value.trim().toUpperCase();

    if (!codigo) {
        notify.warning('Por favor ingrese un código de boleto');
        input.focus();
        return;
    }

    // Cerrar modal de gestión
    modalGestionBoletos.hide();

    // Procesar según el modo
    if (modoActual === 'cancelar') {
        buscarBoletoParaCancelar(codigo);
    } else {
        buscarBoleto(codigo);
    }
}

// Iniciar el escáner - VERSIÓN ULTRA ROBUSTA
// Detecta cualquier cámara: local, USB, virtual, de baja calidad
async function iniciarEscaner() {
    const qrReader = document.getElementById('qr-reader');
    const qrResults = document.getElementById('qr-reader-results');

    if (!qrReader) {
        console.error('❌ Elemento qr-reader no encontrado');
        return;
    }

    // Mostrar mensaje de carga
    qrReader.innerHTML = `
        <div class="text-center p-4" style="color: white;">
            <div class="spinner-border text-light mb-3" role="status"></div>
            <p>Buscando cámaras disponibles...</p>
        </div>
    `;

    // Detener escáner anterior si existe
    if (html5QrCode) {
        try {
            await html5QrCode.stop();
            html5QrCode.clear();
        } catch (e) {
            console.warn('No se pudo detener el escáner anterior:', e);
        }
        html5QrCode = null;
    }

    console.log('🔍 Iniciando detección de cámaras...');

    // ============================================
    // CONFIGURACIÓN DEL ESCÁNER
    // ============================================
    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0,
        disableFlip: false
    };

    // ============================================
    // CREAR INSTANCIA Y CONECTAR DIRECTAMENTE
    // (sin enumerar cámaras para evitar parpadeos)
    // ============================================
    qrReader.innerHTML = `
        <div class="text-center p-4" style="color: white;">
            <div class="spinner-border text-success mb-3" role="status"></div>
            <p>Conectando con la cámara...</p>
        </div>
    `;

    html5QrCode = new Html5Qrcode("qr-reader");

    // Intentar primero con facingMode (evita enumerar y parpadear)
    try {
        console.log('🚀 Intentando conectar con facingMode: environment');
        await html5QrCode.start(
            { facingMode: "environment" },
            config,
            onScanSuccess,
            onScanError
        );
        console.log('✅ Cámara trasera conectada');
        forzarVisibilidadVideo();
        return; // Éxito, salir
    } catch (err1) {
        console.warn('⚠️ Cámara trasera no disponible:', err1.message);
    }

    // Si falla, intentar con cámara frontal
    try {
        console.log('🚀 Intentando con facingMode: user');
        html5QrCode = new Html5Qrcode("qr-reader");
        await html5QrCode.start(
            { facingMode: "user" },
            config,
            onScanSuccess,
            onScanError
        );
        console.log('✅ Cámara frontal conectada');
        forzarVisibilidadVideo();
        return; // Éxito, salir
    } catch (err2) {
        console.warn('⚠️ Cámara frontal no disponible:', err2.message);
    }

    // ============================================
    // ÚLTIMO INTENTO: Enumerar y usar ID específico
    // ============================================
    try {
        console.log('🔄 Enumerando cámaras disponibles...');
        const cameras = await Html5Qrcode.getCameras();

        if (cameras && cameras.length > 0) {
            console.log('📷 Cámaras encontradas:', cameras);
            html5QrCode = new Html5Qrcode("qr-reader");
            await html5QrCode.start(
                cameras[0].id,
                config,
                onScanSuccess,
                onScanError
            );
            console.log('✅ Cámara conectada por ID:', cameras[0].label);
            forzarVisibilidadVideo();
            return; // Éxito, salir
        }
    } catch (err3) {
        console.error('❌ Error final:', err3.message);
    }

    // ============================================
    // SI TODO FALLA, MOSTRAR ERROR
    // ============================================
    qrReader.innerHTML = `
        <div class="alert alert-danger text-center m-3">
            <i class="bi bi-camera-video-off fs-1 d-block mb-2"></i>
            <strong>No se pudo acceder a la cámara</strong>
            <p class="mt-2 small">Verifique los permisos del navegador o si otra aplicación está usando la cámara.</p>
            <button class="btn btn-primary mt-2" onclick="iniciarEscaner()">
                <i class="bi bi-arrow-clockwise"></i> Reintentar
            </button>
            <button class="btn btn-outline-secondary mt-2" onclick="cambiarMetodoInput('manual')">
                <i class="bi bi-keyboard"></i> Modo manual
            </button>
        </div>
    `;
}

// Forzar visibilidad del video
function forzarVisibilidadVideo() {
    // Intentar varias veces
    const intentar = () => {
        const video = document.querySelector('#qr-reader video');
        const scanRegion = document.querySelector('#qr-reader__scan_region');

        if (video) {
            video.style.cssText = `
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                width: 100% !important;
                height: auto !important;
                min-height: 280px !important;
                max-height: 450px !important;
                object-fit: cover !important;
                border-radius: 12px !important;
                transform: scaleX(-1);
                background: #000;
            `;
            console.log('✅ Video configurado:', video.videoWidth, 'x', video.videoHeight);
        } else {
            console.warn('⚠️ Video no encontrado, reintentando...');
        }

        if (scanRegion) {
            scanRegion.style.cssText = `
                display: block !important;
                width: 100% !important;
                min-height: 300px !important;
                overflow: visible !important;
                background: transparent !important;
            `;
        }

        // Ocultar elementos innecesarios de la librería
        const dashboard = document.querySelector('#qr-reader__dashboard');
        if (dashboard) {
            dashboard.style.display = 'none';
        }
    };

    // Múltiples intentos para asegurar que funcione
    setTimeout(intentar, 300);
    setTimeout(intentar, 800);
    setTimeout(intentar, 1500);
    setTimeout(intentar, 3000);
}


// Aplicar efecto espejo
function aplicarEfectoEspejo() {
    const videoElement = document.querySelector('#qr-reader video');
    if (videoElement) {
        videoElement.style.transform = 'scaleX(-1)';
        videoElement.style.webkitTransform = 'scaleX(-1)';
    }
}

// Detener el escáner
function detenerEscaner() {
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
            html5QrCode = null;
        }).catch(err => {
            console.error("Error al detener escáner:", err);
        });
    }
}

// Cuando se escanea exitosamente
function onScanSuccess(decodedText, decodedResult) {
    console.log(`Código escaneado: ${decodedText}, Modo: ${modoActual}`);

    detenerEscaner();
    modalGestionBoletos.hide();

    if (modoActual === 'cancelar') {
        buscarBoletoParaCancelar(decodedText);
    } else {
        buscarBoleto(decodedText);
    }
}

// Manejo de errores del escáner
function onScanError(errorMessage) {
    // No hacer nada, es normal mientras busca el QR
}

// Buscar información del boleto para verificar
async function buscarBoleto(codigo) {
    try {
        const response = await fetch(`verificar_boleto.php?codigo=${encodeURIComponent(codigo)}`);

        if (!response.ok) {
            throw new Error(`Error del servidor: ${response.status}`);
        }

        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Respuesta del servidor:', text.substring(0, 500));
            throw new Error('El servidor no devolvió un JSON válido.');
        }

        if (data.success) {
            mostrarInfoBoleto(data.boleto, 'verificar');
        } else {
            mostrarError(data.message || 'Boleto no encontrado');
        }
    } catch (error) {
        console.error('Error al buscar boleto:', error);
        mostrarError(error.message || 'Error al verificar el boleto');
    }
}

// Buscar boleto para cancelar
async function buscarBoletoParaCancelar(codigo) {
    try {
        const response = await fetch(`verificar_boleto.php?codigo=${encodeURIComponent(codigo)}`);

        if (!response.ok) {
            throw new Error(`Error del servidor: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
            mostrarInfoBoleto(data.boleto, 'cancelar');
        } else {
            mostrarError(data.message || 'Boleto no encontrado');
        }
    } catch (error) {
        console.error('Error al buscar boleto:', error);
        mostrarError(error.message || 'Error al buscar el boleto');
    }
}

// Mostrar información del boleto
function mostrarInfoBoleto(boleto, modo) {
    const modal = document.getElementById('modalBoletoInfo');
    const header = document.getElementById('boletoInfoHeader');
    const title = document.getElementById('boletoInfoTitle');
    const body = document.getElementById('boletoInfoBody');
    const footer = document.getElementById('boletoInfoFooter');

    // Configurar header según el modo y estado
    if (modo === 'cancelar') {
        header.className = 'modal-header bg-danger text-white';
        title.innerHTML = '<i class="bi bi-x-circle"></i> Cancelar Boleto';
    } else if (boleto.estatus == 1) {
        header.className = 'modal-header bg-success text-white';
        title.innerHTML = '<i class="bi bi-check-circle"></i> Boleto Válido';
    } else {
        header.className = 'modal-header bg-secondary text-white';
        title.innerHTML = '<i class="bi bi-x-circle"></i> Boleto Usado/Cancelado';
    }

    // Construir el cuerpo
    body.innerHTML = `
        <div class="text-center mb-3">
            <img src="../boletos_qr/${boleto.codigo_unico}.png" 
                 alt="QR" 
                 class="img-fluid" 
                 style="max-width: 180px; border: 2px solid #dee2e6; border-radius: 10px;"
                 onerror="this.style.display='none'">
        </div>
        <table class="table table-bordered mb-0">
            <tr>
                <th style="width:35%">Código:</th>
                <td><strong class="text-primary">${boleto.codigo_unico}</strong></td>
            </tr>
            <tr>
                <th>Evento:</th>
                <td>${boleto.evento_titulo}</td>
            </tr>
            <tr>
                <th>Función:</th>
                <td>${boleto.fecha_hora ? formatearFecha(boleto.fecha_hora) : 'No especificada'}</td>
            </tr>
            <tr>
                <th>Asiento:</th>
                <td><strong>${boleto.codigo_asiento}</strong></td>
            </tr>
            <tr>
                <th>Categoría:</th>
                <td>${boleto.nombre_categoria || 'General'}</td>
            </tr>
            <tr>
                <th>Precio:</th>
                <td>$${parseFloat(boleto.precio_final).toFixed(2)}</td>
            </tr>
            <tr>
                <th>Estado:</th>
                <td>
                    ${boleto.estatus == 1
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Usado/Cancelado</span>'}
                </td>
            </tr>
        </table>
    `;

    // Configurar footer según modo
    if (modo === 'cancelar') {
        if (boleto.estatus == 1) {
            footer.innerHTML = `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-danger" onclick="confirmarCancelacion('${boleto.codigo_unico}', '${boleto.codigo_asiento}')">
                    <i class="bi bi-x-circle"></i> Confirmar Cancelación
                </button>
            `;
        } else {
            footer.innerHTML = `
                <div class="alert alert-warning mb-0 w-100">Este boleto ya está cancelado o usado.</div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            `;
        }
    } else {
        // Modo verificar
        if (boleto.estatus == 1) {
            footer.innerHTML = `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" onclick="confirmarEntrada('${boleto.codigo_unico}')">
                    <i class="bi bi-check-circle"></i> Confirmar Entrada
                </button>
            `;
        } else {
            footer.innerHTML = `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            `;
        }
    }

    modalBoletoInfo = new bootstrap.Modal(modal);
    modalBoletoInfo.show();
}

// Mostrar error
function mostrarError(mensaje) {
    const modal = document.getElementById('modalBoletoInfo');
    const header = document.getElementById('boletoInfoHeader');
    const title = document.getElementById('boletoInfoTitle');
    const body = document.getElementById('boletoInfoBody');
    const footer = document.getElementById('boletoInfoFooter');

    header.className = 'modal-header bg-danger text-white';
    title.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Error';

    body.innerHTML = `
        <div class="alert alert-danger text-center mb-0">
            <i class="bi bi-x-circle" style="font-size: 3rem;"></i>
            <p class="mt-3 mb-0 fs-5">${mensaje}</p>
        </div>
    `;

    footer.innerHTML = `
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary" onclick="modalBoletoInfo.hide(); abrirGestionBoletos('${modoActual}')">
            <i class="bi bi-arrow-repeat"></i> Intentar de nuevo
        </button>
    `;

    modalBoletoInfo = new bootstrap.Modal(modal);
    modalBoletoInfo.show();
}

// Confirmar entrada
async function confirmarEntrada(codigoUnico) {
    const confirmar = await mostrarConfirmacion(
        '¿Confirmar entrada?',
        'Esta acción marcará el boleto como usado.'
    );

    if (!confirmar) return;

    try {
        const response = await fetch('confirmar_entrada.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ codigo_unico: codigoUnico })
        });

        const data = await response.json();

        if (data.success) {
            modalBoletoInfo.hide();
            notify.success('Entrada confirmada exitosamente');
        } else {
            notify.error(data.message || 'No se pudo confirmar la entrada');
        }
    } catch (error) {
        console.error('Error:', error);
        notify.error('Error al confirmar la entrada');
    }
}

// Confirmar cancelación de boleto
async function confirmarCancelacion(codigoUnico, codigoAsiento) {
    const confirmar = await mostrarConfirmacion(
        '¿Cancelar este boleto?',
        `Se cancelará el boleto del asiento ${codigoAsiento}. Esta acción liberará el asiento para venta.`,
        'danger'
    );

    if (!confirmar) return;

    try {
        const response = await fetch('cancelar_boleto.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ codigo_unico: codigoUnico })
        });

        const data = await response.json();

        if (data.success) {
            modalBoletoInfo.hide();
            notify.success('Boleto cancelado exitosamente. El asiento ya está disponible.');

            // Recargar asientos vendidos para actualizar el mapa
            if (typeof cargarAsientosVendidos === 'function') {
                cargarAsientosVendidos();
            }
        } else {
            notify.error(data.message || 'No se pudo cancelar el boleto');
        }
    } catch (error) {
        console.error('Error:', error);
        notify.error('Error al cancelar el boleto');
    }
}

// Mostrar modal de confirmación personalizado
function mostrarConfirmacion(titulo, mensaje, tipo = 'warning') {
    return new Promise((resolve) => {
        const colorClass = tipo === 'danger' ? 'bg-danger text-white' : 'bg-warning text-dark';
        const btnClass = tipo === 'danger' ? 'btn-danger' : 'btn-warning';

        const modalHtml = `
            <div class="modal fade" id="modalConfirmacion" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header ${colorClass}">
                            <h5 class="modal-title">
                                <i class="bi bi-exclamation-triangle"></i> ${titulo}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">${mensaje}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btnCancelarConfirm">
                                No, cancelar
                            </button>
                            <button type="button" class="btn ${btnClass}" id="btnConfirmar">
                                Sí, confirmar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('modalConfirmacion'));

        document.getElementById('btnConfirmar').addEventListener('click', () => {
            modal.hide();
            resolve(true);
        });

        document.getElementById('btnCancelarConfirm').addEventListener('click', () => {
            modal.hide();
            resolve(false);
        });

        document.getElementById('modalConfirmacion').addEventListener('hidden.bs.modal', function () {
            this.remove();
        });

        modal.show();
    });
}

// Formatear fecha
function formatearFecha(fechaStr) {
    const fecha = new Date(fechaStr);
    const opciones = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    };
    return fecha.toLocaleString('es-MX', opciones);
}

// Variable para controlar el efecto espejo
let espejoActivo = true;

// Alternar efecto espejo
function toggleEfectoEspejo() {
    const videoElement = document.querySelector('#qr-reader video');
    const btnEspejo = document.getElementById('btnEspejo');

    if (videoElement) {
        espejoActivo = !espejoActivo;

        if (espejoActivo) {
            videoElement.style.transform = 'scaleX(-1)';
            videoElement.style.webkitTransform = 'scaleX(-1)';
            if (btnEspejo) btnEspejo.innerHTML = '<i class="bi bi-arrow-left-right"></i> Espejo: ON';
        } else {
            videoElement.style.transform = 'scaleX(1)';
            videoElement.style.webkitTransform = 'scaleX(1)';
            if (btnEspejo) btnEspejo.innerHTML = '<i class="bi bi-arrow-left-right"></i> Espejo: OFF';
        }
    }
}

// Exportar funciones globalmente
window.abrirGestionBoletos = abrirGestionBoletos;
window.abrirEscanerQR = abrirEscanerQR;
window.abrirCancelarBoleto = abrirCancelarBoleto;
window.toggleEfectoEspejo = toggleEfectoEspejo;
window.cambiarModoGestion = cambiarModoGestion;
window.cambiarMetodoInput = cambiarMetodoInput;
window.buscarBoletoPorCodigo = buscarBoletoPorCodigo;
