<?php
/**
 * Descarga un solo boleto en PDF
 * VERSIÓN CORREGIDA
 */

// Limpiar cualquier buffer existente
while (ob_get_level()) {
    ob_end_clean();
}

// Configuración de errores
error_reporting(0);
ini_set('display_errors', 0);

// Cargar dependencias
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/ticket_precio_helper.php';
require_once __DIR__ . '/../includes/texto_encoding_helper.php';

// Verificar parámetros
if (!isset($_GET['codigo']) || empty($_GET['codigo'])) {
    header('Content-Type: text/plain');
    die('Código de boleto no proporcionado');
}

// Función helper: includes/texto_encoding_helper.php

$codigo_unico = $_GET['codigo'];

// Obtener información del boleto (solo boletos activos con estatus = 1)
$stmt = $conn->prepare("
    SELECT 
        b.codigo_unico,
        b.precio_final,
        b.tipo_boleto,
        a.codigo_asiento,
        e.titulo as evento_nombre,
        e.imagen,
        c.nombre_categoria,
        f.fecha_hora as funcion_fecha,
        TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, ''))) AS vendedor_nombre
    FROM boletos b
    INNER JOIN asientos a ON b.id_asiento = a.id_asiento
    INNER JOIN evento e ON b.id_evento = e.id_evento
    INNER JOIN funciones f ON b.id_funcion = f.id_funcion
    INNER JOIN categorias c ON b.id_categoria = c.id_categoria
    LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario
    WHERE b.codigo_unico = ? AND b.estatus = 1
");

$stmt->bind_param("s", $codigo_unico);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: text/plain');
    die('Boleto no encontrado');
}

$boleto = $result->fetch_assoc();
$stmt->close();
$conn->close();

// ===== CREAR PDF =====
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);

// Título con mejor diseño
$pdf->SetFont('Arial', 'B', 20);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 12, 'BOLETO DE ENTRADA', 0, 1, 'C', true);
$pdf->Ln(8);

// Imagen del evento (si existe)
$imagen_path = __DIR__ . '/../evt_interfaz/' . $boleto['imagen'];
if (!empty($boleto['imagen']) && file_exists($imagen_path)) {
    $imagen_info = getimagesize($imagen_path);
    if ($imagen_info) {
        $ancho_imagen = $imagen_info[0];
        $alto_imagen = $imagen_info[1];
        
        $ancho_maximo = 140;
        $alto_maximo = 70;
        
        $ratio = $ancho_imagen / $alto_imagen;
        
        if ($ancho_imagen > $alto_imagen) {
            $ancho_final = min($ancho_maximo, $ancho_imagen);
            $alto_final = $ancho_final / $ratio;
            if ($alto_final > $alto_maximo) {
                $alto_final = $alto_maximo;
                $ancho_final = $alto_final * $ratio;
            }
        } else {
            $alto_final = min($alto_maximo, $alto_imagen);
            $ancho_final = $alto_final * $ratio;
            if ($ancho_final > $ancho_maximo) {
                $ancho_final = $ancho_maximo;
                $alto_final = $ancho_final / $ratio;
            }
        }
        
        $x_centrado = (210 - $ancho_final) / 2;
        $pdf->Image($imagen_path, $x_centrado, $pdf->GetY(), $ancho_final, $alto_final);
        $pdf->Ln($alto_final + 8);
    } else {
        $pdf->Image($imagen_path, 35, $pdf->GetY(), 140, 70);
        $pdf->Ln(78);
    }
} else {
    $pdf->Ln(3);
}

// Información del evento
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 8, convertirTexto($boleto['evento_nombre']), 0, 1, 'C');
$pdf->Ln(8);

// Línea separadora
$pdf->SetLineWidth(0.5);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(8);

// Detalles del boleto
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(45, 7, 'Asiento:', 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, $boleto['codigo_asiento'], 0, 1);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(45, 7, convertirTexto('Categoría:'), 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, convertirTexto($boleto['nombre_categoria']), 0, 1);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(45, 7, 'Precio:', 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 128, 0);
$pdf->Cell(0, 7, convertirTexto(texto_precio_boleto_ticket((float) $boleto['precio_final'], $boleto['nombre_categoria'] ?? '', $boleto['tipo_boleto'] ?? null)), 0, 1);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(45, 7, convertirTexto('Función:'), 0, 0);
$pdf->SetFont('Arial', '', 11);
$fecha_funcion = new DateTime($boleto['funcion_fecha']);
$pdf->Cell(0, 7, $fecha_funcion->format('d/m/Y H:i'), 0, 1);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(45, 7, convertirTexto('Vendido por:'), 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, convertirTexto(!empty(trim($boleto['vendedor_nombre'])) ? trim($boleto['vendedor_nombre']) : 'Sin asignar'), 0, 1);

$pdf->Ln(5);

// Código QR
$qr_path = __DIR__ . '/../boletos_qr/' . $codigo_unico . '.png';
if (file_exists($qr_path)) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, convertirTexto('Código de verificación:'), 0, 1, 'C');
    $pdf->Ln(3);
    
    $qr_size = 50;
    $qr_x = (210 - $qr_size) / 2;
    $pdf->Image($qr_path, $qr_x, $pdf->GetY(), $qr_size, $qr_size);
    $pdf->Ln($qr_size + 5);
}

// Código único
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, 'Codigo: ' . $codigo_unico, 0, 1, 'C');

$pdf->Ln(8);

// Línea separadora
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

// Nota al pie
$pdf->SetFont('Arial', 'I', 8);
$pdf->MultiCell(0, 4, convertirTexto('Este boleto es válido únicamente para la función indicada. Conserve este boleto para su ingreso al evento.'), 0, 'C');

// ===== GENERAR NOMBRE DEL ARCHIVO =====
$nombre_archivo = 'Boleto_' . $boleto['codigo_asiento'] . '_' . date('d-m-Y') . '.pdf';

// ===== SALIDA DEL PDF USANDO EL MÉTODO CORRECTO DE FPDF =====
$pdf->Output('D', $nombre_archivo);
exit;
