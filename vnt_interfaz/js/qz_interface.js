/// <reference path="qz-tray.js" />
// qz_interface.js
// Manejo de impresión cliente con QZ Tray

const QZ_CONFIG = {
    host: 'localhost', // QZ Tray suele correr localmente
    connected: false
};

function textoPrecioTicket(precio, categoria, tipoBoleto) {
    const tipo = (tipoBoleto || '').toLowerCase();
    if (tipo === 'cortesia') return 'CORTESÍA';

    const p = Number(precio);
    if (p > 0) return '$' + p.toFixed(2);

    const cat = (categoria || '').toLowerCase();
    if (cat.includes('cortes')) return 'CORTESÍA';
    if (cat.includes('no venta') || cat.includes('noventa')) return '$0.00';

    const cfg = (typeof window.CONFIG_TIPOS_BOLETO !== 'undefined' && window.CONFIG_TIPOS_BOLETO) || {};
    if (cfg.evento_gratuito) return 'EVENTO GRATUITO';
    return '$0.00';
}

// Inicializar QZ
// Inicializar QZ
// Configuración de Seguridad (Firma de mensajes) global
// Esto permite "Remember this decision" y suprime advertencias
qz.security.setCertificatePromise(function (resolve, reject) {
    console.log("QZ: Solicitando cerfiticado...");
    fetch('utils/qz_cert.pem')
        .then(data => {
            console.log("QZ: Certificado encontrado");
            return data.text();
        })
        .then(resolve)
        .catch(err => {
            console.error("QZ: Error cargando certificado", err);
            reject(err);
        });
});

qz.security.setSignaturePromise(function (toSign) {
    return function (resolve, reject) {
        console.log("QZ: Solicitando firma para:", toSign);
        fetch('sign_message.php?request=' + toSign)
            .then(data => data.text())
            .then(signed => {
                console.log("QZ: Firma recibida", signed.substring(0, 20) + "...");
                resolve(signed);
            })
            .catch(err => {
                console.error("QZ: Error firmando mensaje", err);
                reject(err);
            });
    };
});

async function initQZ() {
    if (QZ_CONFIG.connected) return true;

    try {
        if (!qz.websocket.isActive()) {
            await qz.websocket.connect();
        }
        QZ_CONFIG.connected = true;
        console.log("QZ Tray conectado");
        return true;
    } catch (err) {
        console.error("Error conectando a QZ Tray:", err);
        return false;
    }
}

// Obtener lista de impresoras
async function getPrinters() {
    if (!await initQZ()) return [];

    try {
        return await qz.printers.find();
    } catch (err) {
        console.error("Error obteniendo impresoras:", err);
        return [];
    }
}

// Generar HTML del boleto
function formatTicketHTML(boleto, cliente) {
    // Estilos para impresora térmica (80mm)
    // Usamos medidas en px o % para ancho. 80mm es aprox 300-500px dependiendo dpi
    // QZ rasteriza, así que HTML simple es suficiente

    // Logo y QR vienen ya en boleto.logo_image y boleto.qr_image como Base64

    // Fallback si no hay imágenes
    const logoImg = boleto.logo_image ? `<img src="${boleto.logo_image}" style="width: 150px; margin-bottom: 5px;">` : '<h3>TEATRO</h3>';
    const qrImg = boleto.qr_image ? `<img src="${boleto.qr_image}" style="width: 130px; margin-top: 10px;">` : `<p>${boleto.codigo_unico}</p>`;

    // Determinar nombre a mostrar: Específico del boleto > Global > Vacío
    const nombreMostrar = boleto.nombre_cliente || cliente;

    // Formatear Fecha y Hora (Natural)
    // boleto.fecha viene dd/mm/yyyy. boleto.hora viene HH:mm hrs
    let fechaTexto = boleto.fecha;
    let horaTexto = boleto.hora;

    try {
        if (boleto.fecha && boleto.fecha.includes('/')) {
            const [d, m, y] = boleto.fecha.split('/');
            const dateObj = new Date(y, m - 1, d);
            // Opción: jueves 27 de agosto de 2026
            const opcionesFecha = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            fechaTexto = dateObj.toLocaleDateString('es-ES', opcionesFecha);
        }

        if (boleto.hora) {
            // Extraer HH:mm
            const match = boleto.hora.match(/(\d+):(\d+)/);
            if (match) {
                let h = parseInt(match[1]);
                const min = match[2];
                const ampm = h >= 12 ? 'pm' : 'am';
                h = h % 12;
                h = h ? h : 12; // el 0 es 12
                horaTexto = `${h}:${min} ${ampm}`;
            }
        }
    } catch (e) {
        console.error("Error formateando fecha", e);
    }

    return `
        <div style="font-family: Arial, Helvetica, sans-serif; text-align: center; width: 100%; max-width: 332px; margin: 0 auto; color: #000; font-weight: bold; transform: scale(0.95); transform-origin: top center;">
            <!-- Cabecera -->
            <div style="margin-bottom: 5px;">
                ${logoImg}
                <div style="font-size: 14px; font-weight: 900; text-transform: uppercase;">TEATRO CONSTITUCION</div>
            </div>
            
            <div style="border-bottom: 3px solid #000; margin: 5px 0;"></div>
            
            <!-- Evento -->
            <div style="text-align: center; margin: 5px 0;">
                <div style="font-size: 12px; text-transform: uppercase; margin-bottom: 2px;">Evento:</div>
                <div style="font-size: 18px; font-weight: 900; line-height: 1.1; margin-bottom: 5px;">${boleto.evento}</div>
                
                <div style="font-size: 14px; margin-top: 2px; font-weight: 900; text-transform: capitalize;">
                    ${fechaTexto}
                </div>
                <div style="font-size: 14px; font-weight: 900;">
                    a las ${horaTexto}
                </div>
            </div>
            
            <div style="border-bottom: 2px dashed #000; margin: 5px 0;"></div>
            
            <!-- Detalles -->
            <div style="margin: 5px 0;">
                <div style="font-size: 18px; font-weight: 900; border: 2px solid #000; padding: 5px 8px; display: inline-block; border-radius: 5px;">ASIENTO: ${boleto.asiento}</div>
                <div style="font-size: 14px; margin-top: 5px;">${boleto.categoria}</div>
                <div style="font-size: 18px; font-weight: 900; margin-top: 5px;">
                    ${textoPrecioTicket(boleto.precio, boleto.categoria, boleto.tipo_boleto)}
                </div>
            </div>

            <!-- Cliente -->
            ${nombreMostrar ? `
            <div style="border-bottom: 2px dashed #000; margin: 5px 0;"></div>
            <div style="margin: 5px 0; text-align: left;">
                <div style="font-size: 12px;">Cliente:</div>
                <div style="font-size: 14px; font-weight: 900;">${nombreMostrar}</div>
            </div>
            ` : ''}
            
            <div style="border-bottom: 3px solid #000; margin: 5px 0;"></div>
            
            <!-- QR -->
            <div style="margin: 5px 0; text-align: center;">
                <img src="${boleto.qr_image}" style="width: 160px; margin-top: 5px;">
                <div style="font-size: 12px; margin-top: 2px; font-weight: 900;">${boleto.codigo_unico}</div>
            </div>
            
            <!-- Footer -->
            <div style="margin-top: 10px; font-size: 11px; font-weight: bold; padding: 0 5px;">
                ${boleto.vendedor ? `<div style="font-size: 10px; margin-bottom: 5px;">Le atendio: ${boleto.vendedor}</div>` : ''}
                NOTA: Solo se harán válidos cambios o aclaraciones si se conserva este boleto.
                <br><br>
                <span style="font-size: 13px;">¡Gracias por su compra!</span>
            </div>
            
            <!-- Espacio final para corte -->
            <div style="height: 30px;"></div>
        </div>
    `;
}

// Imprimir lista de boletos
async function printTicketsQZ(boletos, cliente, impresoraName) {
    if (!await initQZ()) return { success: false, message: "No se pudo conectar con QZ Tray" };

    try {
        // Configuración de impresora
        let config;

        // Determinar impresora
        if (!impresoraName || impresoraName === 'default') {
            // Fallback
            const printers = await qz.printers.find();
            // Preferir POS
            const posPrinter = printers.find(p => p.includes('POS') || p.includes('Epson') || p.includes('Thermal'));
            impresoraName = posPrinter || printers[0];
        }

        // Crear configuración para HTML (rasterización)
        config = qz.configs.create(impresoraName, {
            rasterize: true, // Importante para convertir HTML/Img a píxeles de impresora térmica
            altPrinting: true // A veces ayuda en Windows
            // encoding: 'ISO-8859-1' // Si fuera RAW, pero es Raster
        });

        const printData = [];

        // Generar una página HTML por boleto
        for (let boleto of boletos) {
            const htmlContent = formatTicketHTML(boleto, cliente);
            printData.push({
                type: 'pixel',
                format: 'html',
                flavor: 'plain', // o 'file' si fuera url
                data: htmlContent
            });
        }

        await qz.print(config, printData);
        return { success: true, message: `Enviado a ${impresoraName}` };
    } catch (err) {
        console.error("Error QZ Print:", err);
        return { success: false, message: err.message || err };
    }
}

// Exponer globalmente
window.initQZ = initQZ;
window.getPrinters = getPrinters;
window.printTicketsQZ = printTicketsQZ;
