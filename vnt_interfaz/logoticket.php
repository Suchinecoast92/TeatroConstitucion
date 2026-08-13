<?php
// logoticket.php
// Inserta el logo en el ticket
// Se asume que $pdf es una instancia válida de FPDF

$logo_path = __DIR__ . '/../resources/LogoTicket.png';

if (file_exists($logo_path)) {
    // Ajustar posición y tamaño del logo
    // 80mm ancho ticket - márgenes 3mm = 74mm área útil
    // Centramos el logo
    $logo_width = 25; // 25mm de ancho
    $x_pos = ($ancho - $logo_width) / 2;
    // $pdf->GetY() debería ser el inicio de la página o donde queramos el logo
    
    $pdf->Image($logo_path, $x_pos, $pdf->GetY(), $logo_width);
    $pdf->Ln($logo_width / 1.5); // Espacio suficiente para evitar solapamiento
}
?>
