x<?php
/**
 * Control de Entrada - Escáner de Boletos
 * Página compacta para validar y marcar boletos como usados
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Control de Entrada - Teatro</title>
    
    <meta name="description" content="Sistema de control de entrada para validación de boletos mediante escáner QR">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --danger-gradient: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            --dark-bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.95);
        }
        
        * { font-family: 'Inter', sans-serif; }
        
        body {
            background: var(--dark-bg);
            min-height: 100vh;
            color: #e2e8f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: 
                radial-gradient(ellipse at 20% 80%, rgba(102, 126, 234, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(118, 75, 162, 0.15) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        .main-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
        }
        
        /* Header compacto */
        .page-header {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .page-header h1 {
            font-weight: 800;
            font-size: 1.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0;
        }
        
        /* Card principal */
        .scanner-card {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            overflow: hidden;
        }
        
        .card-body-custom {
            padding: 15px;
        }
        
        /* Selector de cámara */
        .camera-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .camera-selector select {
            flex: 1;
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.2);
            color: #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.9rem;
        }
        
        .camera-selector select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-icon {
            background: rgba(51, 65, 85, 0.8);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 10px 14px;
            color: #e2e8f0;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-icon:hover {
            background: #667eea;
            border-color: #667eea;
        }
        
        /* Contenedor de cámara */
        .camera-container {
            position: relative;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            min-height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .camera-container video {
            width: 100%;
            height: auto;
            display: block;
            transform: scaleX(-1);
        }
        
        /* Marco de escaneo */
        .scan-overlay {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 250px; height: 250px;
            pointer-events: none;
        }
        
        .scan-corner {
            position: absolute;
            width: 25px; height: 25px;
            border-color: #667eea;
            border-style: solid;
            border-width: 0;
        }
        
        .scan-corner.top-left { top: 0; left: 0; border-top-width: 3px; border-left-width: 3px; border-radius: 6px 0 0 0; }
        .scan-corner.top-right { top: 0; right: 0; border-top-width: 3px; border-right-width: 3px; border-radius: 0 6px 0 0; }
        .scan-corner.bottom-left { bottom: 0; left: 0; border-bottom-width: 3px; border-left-width: 3px; border-radius: 0 0 0 6px; }
        .scan-corner.bottom-right { bottom: 0; right: 0; border-bottom-width: 3px; border-right-width: 3px; border-radius: 0 0 6px 0; }
        
        .scan-line {
            position: absolute;
            width: 100%; height: 2px;
            background: linear-gradient(90deg, transparent, #667eea, transparent);
            animation: scanLine 2s linear infinite;
        }
        
        @keyframes scanLine {
            0% { top: 0; opacity: 1; }
            100% { top: calc(100% - 2px); opacity: 1; }
        }
        
        .camera-loading {
            text-align: center;
            color: #94a3b8;
        }
        
        .camera-loading .spinner-border {
            width: 40px; height: 40px;
            color: #667eea;
        }
        
        /* Separador */
        .divider {
            display: flex;
            align-items: center;
            margin: 15px 0;
            color: #64748b;
            font-size: 0.85rem;
        }
        
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.1);
        }
        
        .divider span {
            padding: 0 15px;
        }
        
        /* Input manual */
        .input-group-custom {
            display: flex;
            gap: 10px;
        }
        
        .input-group-custom input {
            flex: 1;
            background: #1e293b;
            border: 2px solid rgba(255,255,255,0.15);
            color: #e2e8f0;
            border-radius: 12px;
            padding: 14px 15px;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .input-group-custom input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .input-group-custom input::placeholder {
            text-transform: none;
            letter-spacing: normal;
            font-weight: 400;
            color: #64748b;
        }
        
        .btn-scan {
            background: var(--primary-gradient);
            border: none;
            border-radius: 12px;
            padding: 14px 20px;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .btn-scan:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        /* Modal personalizado */
        .modal-content {
            background: var(--card-bg);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            overflow: hidden;
        }
        
        .modal-backdrop.show {
            opacity: 0.8;
        }
        
        .result-header {
            padding: 30px 20px;
            text-align: center;
        }
        
        .result-header.valid { background: var(--success-gradient); }
        .result-header.used { background: rgba(71, 85, 105, 0.9); }
        .result-header.invalid { background: var(--danger-gradient); }
        
        .result-header i { font-size: 3.5rem; }
        .result-header h4 { margin: 12px 0 0; font-weight: 700; font-size: 1.5rem; }
        
        .result-body {
            background: var(--card-bg);
            padding: 20px;
        }
        
        .ticket-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .ticket-item {
            background: rgba(51, 65, 85, 0.5);
            padding: 12px 15px;
            border-radius: 10px;
        }
        
        .ticket-item.full { grid-column: span 2; }
        
        .ticket-item label {
            font-size: 0.75rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 5px;
        }
        
        .ticket-item span {
            font-size: 1rem;
            font-weight: 600;
            color: #e2e8f0;
        }
        
        .result-actions {
            padding: 20px;
            background: rgba(51, 65, 85, 0.3);
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        
        .btn-confirm {
            background: var(--success-gradient);
            border: none;
            border-radius: 12px;
            padding: 14px 35px;
            font-weight: 700;
            font-size: 1.05rem;
            color: white;
            flex: 1;
            max-width: 280px;
        }
        
        .btn-confirm:hover {
            transform: scale(1.03);
            box-shadow: 0 5px 20px rgba(17, 153, 142, 0.4);
            color: white;
        }
        
        .btn-confirm:disabled {
            opacity: 0.6;
            transform: none;
        }
        
        .btn-secondary-custom {
            background: rgba(51, 65, 85, 0.8);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 14px 25px;
            font-size: 1.05rem;
            color: #e2e8f0;
        }
        
        .btn-secondary-custom:hover {
            background: rgba(71, 85, 105, 0.8);
            color: white;
        }
        
        /* Toast */
        .toast-container {
            position: fixed;
            top: 15px; right: 15px;
            z-index: 9999;
        }
        
        .custom-toast {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.1);
            animation: toastIn 0.3s ease;
            margin-bottom: 8px;
        }
        
        @keyframes toastIn {
            from { opacity: 0; transform: translateX(80px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .custom-toast.success { border-left: 3px solid #38ef7d; }
        .custom-toast.error { border-left: 3px solid #eb3349; }
        .custom-toast.warning { border-left: 3px solid #f5576c; }
        
        .custom-toast i { font-size: 1.2rem; }
        .custom-toast.success i { color: #38ef7d; }
        .custom-toast.error i { color: #eb3349; }
        .custom-toast.warning i { color: #f5576c; }
        
        /* Link de regreso */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 15px;
            transition: color 0.2s;
        }
        
        .back-link:hover { color: #667eea; }
        
        .hidden { display: none !important; }
        
        @media (max-width: 480px) {
            .input-group-custom {
                flex-direction: column;
            }
            
            .btn-scan {
                justify-content: center;
            }
            
            .ticket-grid {
                grid-template-columns: 1fr;
            }
            
            .ticket-item.full { grid-column: span 1; }
        }
    </style>
</head>
<body>
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="main-container">
        <div class="page-header">
            <h1><i class="bi bi-qr-code-scan"></i> Control de Entrada</h1>
        </div>
        
        <div class="scanner-card">
            <div class="card-body-custom">
                <!-- Selector de cámara -->
                <div class="camera-selector">
                    <select id="cameraSelect" onchange="switchCamera(this.value)">
                        <option value="">Detectando cámaras...</option>
                    </select>
                    <button class="btn-icon" onclick="toggleMirror()" title="Espejo">
                        <i class="bi bi-symmetry-horizontal"></i>
                    </button>
                </div>
                
                <!-- Cámara -->
                <div class="camera-container" id="cameraContainer">
                    <div class="camera-loading" id="cameraLoading">
                        <div class="spinner-border" role="status"></div>
                        <p class="mt-2 mb-0">Iniciando cámara...</p>
                    </div>
                    <video id="videoPreview" autoplay playsinline muted style="display: none;"></video>
                </div>
                
                <div class="divider"><span>o ingresa el código</span></div>
                
                <!-- Input manual -->
                <div class="input-group-custom">
                    <input 
                        type="text" 
                        id="manualCode" 
                        placeholder="Código del boleto"
                        autocomplete="off"
                        spellcheck="false"
                    >
                    <button class="btn-scan" onclick="processManualCode()">
                        <i class="bi bi-search"></i> Verificar
                    </button>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- Modal de Resultado -->
    <div class="modal fade" id="resultModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="result-header" id="resultHeader">
                    <i class="bi bi-check-circle-fill"></i>
                    <h4>Boleto Válido</h4>
                </div>
                <div class="result-body">
                    <div class="ticket-grid" id="ticketInfo"></div>
                </div>
                <div class="result-actions" id="resultActions"></div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    
    <script>
        let html5QrCode = null;
        let currentCameraId = null;
        let mirrorEnabled = true;
        let isProcessing = false;
        let resultModal = null;
        let lastScannedCode = null;
        let lastScanTime = 0;
        
        document.addEventListener('DOMContentLoaded', async () => {
            resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
            await initializeCameras();
            setupManualInput();
            setupKeyboardShortcuts();
        });
        
        // Atajos de teclado globales
        function setupKeyboardShortcuts() {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    const btnConfirm = document.querySelector('.btn-confirm:not(:disabled)');
                    
                    // Si hay un resultado visible y el focus no está en el input
                    if (document.getElementById('resultModal').classList.contains('show') && document.activeElement.id !== 'manualCode') {
                        if (btnConfirm) {
                            btnConfirm.click();
                        }
                    }
                }
                
                // Escape para cerrar resultado
                if (e.key === 'Escape') {
                    if (document.getElementById('resultModal').classList.contains('show')) {
                        hideResult();
                    }
                }
            });
        }
        
        async function initializeCameras() {
            const cameraSelect = document.getElementById('cameraSelect');
            
            try {
                await navigator.mediaDevices.getUserMedia({ video: true });
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoDevices = devices.filter(device => device.kind === 'videoinput');
                
                if (videoDevices.length === 0) {
                    cameraSelect.innerHTML = '<option value="">Sin cámaras</option>';
                    showCameraError('No se detectaron cámaras');
                    return;
                }
                
                cameraSelect.innerHTML = videoDevices.map((device, index) => {
                    const label = device.label || `Cámara ${index + 1}`;
                    return `<option value="${device.deviceId}">${label}</option>`;
                }).join('');
                
                await startCamera(videoDevices[0].deviceId);
                
            } catch (error) {
                if (error.name === 'NotAllowedError') {
                    showCameraError('Permiso de cámara denegado');
                } else {
                    showCameraError('Error: ' + error.message);
                }
                cameraSelect.innerHTML = '<option value="">Error</option>';
            }
        }
        
        async function startCamera(deviceId) {
            const cameraLoading = document.getElementById('cameraLoading');
            
            cameraLoading.style.display = 'block';
            
            if (html5QrCode) {
                try {
                    await html5QrCode.stop();
                    html5QrCode.clear();
                } catch (e) {}
            }
            
            try {
                html5QrCode = new Html5Qrcode("cameraContainer");
                
                await html5QrCode.start(
                    deviceId,
                    { fps: 10, aspectRatio: 1.0 },
                    onQRCodeScanned,
                    () => {}
                );
                
                currentCameraId = deviceId;
                
                setTimeout(() => {
                    cameraLoading.style.display = 'none';
                    
                    const video = document.querySelector('#cameraContainer video');
                    if (video) {
                        video.style.cssText = `
                            display: block !important;
                            width: 100% !important;
                            height: auto !important;
                            min-height: 280px;
                            max-height: 350px;
                            object-fit: cover;
                            border-radius: 12px;
                            transform: ${mirrorEnabled ? 'scaleX(-1)' : 'scaleX(1)'};
                        `;
                    }
                    
                    const dashboard = document.querySelector('#cameraContainer__dashboard');
                    if (dashboard) dashboard.style.display = 'none';
                }, 400);
                
            } catch (error) {
                showCameraError('Error al iniciar cámara');
            }
        }
        
        async function switchCamera(deviceId) {
            if (!deviceId || deviceId === currentCameraId) return;
            await startCamera(deviceId);
        }
        
        function showCameraError(message) {
            document.getElementById('cameraLoading').innerHTML = `
                <i class="bi bi-camera-video-off" style="font-size: 2.5rem; color: #f5576c;"></i>
                <p class="mt-2 mb-0">${message}</p>
                <button class="btn-icon mt-2" onclick="initializeCameras()">
                    <i class="bi bi-arrow-clockwise"></i> Reintentar
                </button>
            `;
        }
        
        function toggleMirror() {
            mirrorEnabled = !mirrorEnabled;
            const video = document.querySelector('#cameraContainer video');
            if (video) {
                video.style.transform = mirrorEnabled ? 'scaleX(-1)' : 'scaleX(1)';
            }
        }
        
        async function onQRCodeScanned(decodedText) {
            if (isProcessing) return;
            
            const now = Date.now();
            const code = decodedText.trim().toUpperCase();
            
            // Evitar escaneos duplicados del mismo código en menos de 3 segundos
            if (code === lastScannedCode && (now - lastScanTime) < 3000) {
                return;
            }
            
            lastScannedCode = code;
            lastScanTime = now;
            
            if ('vibrate' in navigator) navigator.vibrate(100);
            
            // Pausar el escáner mientras se procesa
            if (html5QrCode && html5QrCode.isScanning) {
                await html5QrCode.pause(true);
            }
            
            await verifyTicket(code);
        }
        
        function setupManualInput() {
            const input = document.getElementById('manualCode');
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') processManualCode();
            });
            input.addEventListener('input', () => {
                input.value = input.value.toUpperCase();
            });
        }
        
        async function processManualCode() {
            const input = document.getElementById('manualCode');
            const code = input.value.trim().toUpperCase();
            
            if (!code) {
                showToast('Ingrese un código', 'warning');
                input.focus();
                return;
            }
            
            await verifyTicket(code);
            input.value = '';
        }
        
        async function verifyTicket(code) {
            if (isProcessing) return;
            isProcessing = true;
            
            try {
                const response = await fetch(`../vnt_interfaz/verificar_boleto.php?codigo=${encodeURIComponent(code)}`);
                const data = await response.json();
                
                if (data.success) {
                    showTicketResult(data.boleto);
                } else {
                    showInvalidTicket(code, data.message || 'No encontrado');
                }
            } catch (error) {
                showToast('Error de conexión', 'error');
            }
            
            isProcessing = false;
        }
        
        function showTicketResult(ticket) {
            const resultHeader = document.getElementById('resultHeader');
            const ticketInfo = document.getElementById('ticketInfo');
            const resultActions = document.getElementById('resultActions');
            
            const isValid = ticket.estatus == 1;
            
            resultHeader.className = 'result-header ' + (isValid ? 'valid' : 'used');
            resultHeader.innerHTML = isValid
                ? '<i class="bi bi-check-circle-fill"></i><h4>Boleto Válido</h4>'
                : '<i class="bi bi-exclamation-triangle-fill"></i><h4>Ya Usado</h4>';
            
            ticketInfo.innerHTML = `
                <div class="ticket-item full">
                    <label>Código</label>
                    <span style="color: #667eea; letter-spacing: 1px;">${ticket.codigo_unico}</span>
                </div>
                <div class="ticket-item full">
                    <label>Evento</label>
                    <span>${ticket.evento_titulo}</span>
                </div>
                <div class="ticket-item">
                    <label>Asiento</label>
                    <span>${ticket.codigo_asiento}</span>
                </div>
                <div class="ticket-item">
                    <label>Categoría</label>
                    <span>${ticket.nombre_categoria || 'General'}</span>
                </div>
            `;
            
            if (isValid) {
                resultActions.innerHTML = `
                    <button class="btn-secondary-custom" onclick="hideResult()">
                        <i class="bi bi-x-lg"></i> Cancelar
                    </button>
                    <button class="btn-confirm" onclick="confirmEntry('${ticket.codigo_unico}')">
                        <i class="bi bi-check2-circle"></i> Confirmar Entrada
                    </button>
                `;
            } else {
                resultActions.innerHTML = `
                    <button class="btn-confirm" style="background: var(--primary-gradient); max-width: 100%;" onclick="hideResult()">
                        <i class="bi bi-qr-code-scan"></i> Escanear Siguiente
                    </button>
                `;
            }
            
            resultModal.show();
        }
        
        function showInvalidTicket(code, message) {
            const resultHeader = document.getElementById('resultHeader');
            const ticketInfo = document.getElementById('ticketInfo');
            const resultActions = document.getElementById('resultActions');
            
            resultHeader.className = 'result-header invalid';
            resultHeader.innerHTML = '<i class="bi bi-x-circle-fill"></i><h4>No Válido</h4>';
            
            ticketInfo.innerHTML = `
                <div class="ticket-item full" style="text-align: center;">
                    <label>Código</label>
                    <span style="color: #f5576c;">${code}</span>
                </div>
                <div class="ticket-item full" style="text-align: center;">
                    <label>Motivo</label>
                    <span>${message}</span>
                </div>
            `;
            
            resultActions.innerHTML = `
                <button class="btn-confirm" style="background: var(--danger-gradient); max-width: 100%;" onclick="hideResult()">
                    <i class="bi bi-qr-code-scan"></i> Escanear Siguiente
                </button>
            `;
            
            resultModal.show();
            showToast('Boleto no encontrado', 'error');
        }
        
        function hideResult() {
            resultModal.hide();
            
            // Reanudar el escáner cuando se cierra el modal
            setTimeout(async () => {
                if (html5QrCode && html5QrCode.getState() === Html5QrcodeScannerState.PAUSED) {
                    await html5QrCode.resume();
                }
                document.getElementById('manualCode').focus();
            }, 300);
        }
        
        async function confirmEntry(codigoUnico) {
            const btn = document.querySelector('.btn-confirm');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Procesando...';
            
            try {
                const response = await fetch('../vnt_interfaz/confirmar_entrada.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ codigo_unico: codigoUnico })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('¡Entrada confirmada!', 'success');
                    
                    if ('vibrate' in navigator) navigator.vibrate([100, 50, 100]);
                    
                    const resultHeader = document.getElementById('resultHeader');
                    resultHeader.innerHTML = '<i class="bi bi-check-circle-fill"></i><h4>¡Entrada OK!</h4>';
                    
                    document.getElementById('resultActions').innerHTML = `
                        <button class="btn-confirm" style="max-width: 100%;" onclick="hideResult()">
                            <i class="bi bi-qr-code-scan"></i> Escanear Siguiente
                        </button>
                    `;
                    
                    // Resetear el último código escaneado para permitir re-escaneo
                    lastScannedCode = null;
                } else {
                    showToast(data.message || 'Error', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check2-circle"></i> Confirmar Entrada';
                }
            } catch (error) {
                showToast('Error de conexión', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2-circle"></i> Confirmar Entrada';
            }
        }
        
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill' };
            
            const toast = document.createElement('div');
            toast.className = `custom-toast ${type}`;
            toast.innerHTML = `<i class="bi ${icons[type]}"></i><span>${message}</span>`;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'toastIn 0.2s ease reverse';
                setTimeout(() => toast.remove(), 200);
            }, 2500);
        }
    </script>
</body>
</html>
