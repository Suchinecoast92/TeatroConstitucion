<?php
// Habilitar todos los errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/ticket_precio_helper.php';
require_once __DIR__ . '/../includes/texto_encoding_helper.php';

if (!isset($_GET['codigo']) || empty($_GET['codigo'])) {
    die('Código de boleto no proporcionado');
}

// Función helper para convertir UTF-8 a ISO-8859-1 (ver includes/texto_encoding_helper.php)

$codigo_unico = $_GET['codigo'];

// Obtener información del boleto y evento
$stmt = $conn->prepare("
    SELECT 
        b.codigo_unico,
        b.precio_final,
        b.tipo_boleto,
        b.fecha_compra,
        a.codigo_asiento,
        e.titulo as evento_nombre,
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
    die('Boleto no encontrado');
}

$boleto = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Crear PDF para impresora térmica (80mm de ancho)
// 80mm = aproximadamente 226 puntos en FPDF
$ancho = 80; // mm
$pdf = new FPDF('P', 'mm', array($ancho, 135)); // Alto ajustado
$pdf->AddPage();
$pdf->SetMargins(3, 1, 3); // Márgenes aumentados para reducir ancho ~5%
$pdf->SetAutoPageBreak(true, 1);

// Incluir logo
include 'logoticket.php';

// Título principal - FUENTE MAS GRANDE
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 6, 'BOLETO DE ENTRADA', 0, 1, 'C');

// Línea separadora
$pdf->SetLineWidth(0.5);
$pdf->Line(2, $pdf->GetY(), $ancho - 2, $pdf->GetY());
$pdf->Ln(1); // Mínimo espacio tras la línea

// Nombre del evento - FUENTE MAS GRANDE
$pdf->SetFont('Arial', 'B', 14);
$nombre_evento = convertirTexto($boleto['evento_nombre']);
// Ajustar texto largo
if (strlen($nombre_evento) > 25) {
    $pdf->SetFont('Arial', 'B', 12);
}
$pdf->MultiCell(0, 5, $nombre_evento, 0, 'C');

// Fecha y hora de la función
if ($boleto['funcion_fecha']) {
    $fecha_obj = new DateTime($boleto['funcion_fecha']);
    $fecha_str = $fecha_obj->format('d/m/Y');
    $hora_str = $fecha_obj->format('H:i');
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 4, convertirTexto('FUNCIÓN'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 11); 
    $pdf->Cell(0, 5, $fecha_str . ' - ' . $hora_str . ' hrs', 0, 1, 'C');
}

// Línea separadora
$pdf->Line(2, $pdf->GetY(), $ancho - 2, $pdf->GetY());
$pdf->Ln(1);

// Información del boleto
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(25, 5, 'Asiento:', 0, 0); // Reducir alto celda
$pdf->SetFont('Arial', 'B', 14); 
$pdf->Cell(0, 5, $boleto['codigo_asiento'], 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(25, 5, convertirTexto('Categoría:'), 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, convertirTexto($boleto['nombre_categoria']), 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(25, 5, 'Precio:', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 5, convertirTexto(texto_precio_boleto_ticket((float) $boleto['precio_final'], $boleto['nombre_categoria'] ?? '', $boleto['tipo_boleto'] ?? null)), 0, 1);

// Cliente (Si existe)
if (isset($_GET['cliente']) && !empty($_GET['cliente'])) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 5, 'Cliente:', 0, 1, 'L');
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->MultiCell(0, 6, convertirTexto($_GET['cliente']), 0, 'C');
}

// Código QR
$qr_path = __DIR__ . '/../boletos_qr/' . $codigo_unico . '.png';
if (file_exists($qr_path)) {
    // QR centrado
    $qr_size = 45;
    $qr_x = ($ancho - $qr_size) / 2;
    $pdf->Image($qr_path, $qr_x, $pdf->GetY() + 1, $qr_size, $qr_size); // +1 para pequeño margen sup
    $pdf->Ln($qr_size + 2); // Espacio justo para el QR
} else {
    $pdf->Ln(2);
}

// Código único
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 4, $codigo_unico, 0, 1, 'C');

// Línea separadora
$pdf->Line(2, $pdf->GetY(), $ancho - 2, $pdf->GetY());
$pdf->Ln(1);

// Fecha de compra
$fecha_compra = new DateTime($boleto['fecha_compra']);
$pdf->SetFont('Arial', 'I', 7);
$pdf->Cell(0, 3, 'Compra: ' . $fecha_compra->format('d/m/Y H:i'), 0, 1, 'C');

// Nota al pie
$pdf->SetFont('Arial', 'I', 6);
$pdf->MultiCell(0, 3, convertirTexto('Válido solo para la función indicada. Consérvelo.'), 0, 'C');
// $pdf->Line(2, $pdf->GetY(), $ancho - 2, $pdf->GetY());

// Salida del PDF
$pdf->Output('I', 'Ticket_' . $boleto['codigo_asiento'] . '.pdf');
?>

