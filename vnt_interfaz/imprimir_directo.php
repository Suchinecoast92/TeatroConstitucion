    <?php
    // imprimir_directo.php
    // Devuelve datos JSON para impresión con QZ Tray
    // Usa el esquema correcto: boletos, asientos, evento, funciones, categorias

    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/../conexion.php'; // Usar la conexión existente en lugar de Database class si es posible, o adaptar.

    // Si conexion.php crea $conn (mysqli), usaremos eso.
    // Si no, usaremos la clase Database si existe, pero imprimir_boleto.php usa $conn.
    // Verificamos si $conn está disponible tras el include.

    header('Content-Type: application/json');

    // Leer input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $codigos = $data['codigos'] ?? [];
    $mode = $data['mode'] ?? 'data'; 

    if (empty($codigos)) {
        echo json_encode(['success' => false, 'message' => 'No se proporcionaron códigos de boletos']);
        exit;
    }

    if (!isset($conn)) {
        // Si conexion.php no nos dio $conn, intentar conectarnos manualmente o error
        // Asumimos que conexion.php funciona como en imprimir_boleto.php
        echo json_encode(['success' => false, 'message' => 'Error de conexión a BD']);
        exit;
    }

    try {
        $boletosData = [];
        
        // Preparar statement para buscar boletos repetidamente
        // Usamos el mismo JOIN que en imprimir_boleto.php para consistencia
        $sql = "
            SELECT 
                b.codigo_unico,
                b.precio_final as precio,
                b.fecha_compra,
                a.codigo_asiento as asiento,
                e.titulo as evento,
                c.nombre_categoria as categoria,
                f.fecha_hora,
                TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, ''))) AS vendedor
            FROM boletos b
            INNER JOIN asientos a ON b.id_asiento = a.id_asiento
            INNER JOIN evento e ON b.id_evento = e.id_evento
            INNER JOIN funciones f ON b.id_funcion = f.id_funcion
            INNER JOIN categorias c ON b.id_categoria = c.id_categoria
            LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario
            WHERE b.codigo_unico = ?
        ";

        $stmt = $conn->prepare($sql);

        // Ruta del logo
        $logoPath = __DIR__ . '/../resources/LogoTicket.png';
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
            $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($logoData);
        }

        foreach ($codigos as $codigo) {
            $stmt->bind_param("s", $codigo);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // Formatear fechas
                $fechaObj = new DateTime($row['fecha_hora']);
                $fecha = $fechaObj->format('d/m/Y');
                $hora = $fechaObj->format('H:i'); // 24hrs formateado

                // QR Base64 - Generar al vuelo si no existe
                $qrPath = __DIR__ . '/../boletos_qr/' . $row['codigo_unico'] . '.png';
                $qrBase64 = '';
                
                // Si el archivo QR no existe, generarlo al vuelo
                if (!file_exists($qrPath)) {
                    try {
                        // Asegurar que el directorio existe
                        $qrDir = __DIR__ . '/../boletos_qr/';
                        if (!is_dir($qrDir)) {
                            mkdir($qrDir, 0755, true);
                        }
                        
                        // Generar QR usando endroid/qr-code v4.4.9
                        // La clase QrCode es readonly, se usan named parameters
                        $qrCode = new \Endroid\QrCode\QrCode(
                            data: $row['codigo_unico'],
                            size: 300,
                            margin: 10
                        );
                        
                        $writer = new \Endroid\QrCode\Writer\PngWriter();
                        $result = $writer->write($qrCode);
                        
                        // Guardar el archivo para uso futuro
                        $result->saveToFile($qrPath);
                        
                        // Obtener Base64 directamente
                        $qrBase64 = $result->getDataUri();
                    } catch (Exception $qrException) {
                        // Si falla la generación, dejar vacío (se mostrará el código como texto)
                        error_log("Error generando QR: " . $qrException->getMessage());
                        $qrBase64 = '';
                    }
                } else {
                    // El archivo existe, cargarlo
                    $qrData = file_get_contents($qrPath);
                    $qrBase64 = 'data:image/png;base64,' . base64_encode($qrData);
                }

                $boletosData[] = [
                    'codigo_unico' => $row['codigo_unico'],
                    'precio' => (float)$row['precio'],
                    'asiento' => $row['asiento'],
                    'evento' => $row['evento'],
                    'categoria' => $row['categoria'],
                    'fecha' => $fecha,
                    'hora' => $hora . ' hrs',
                    'vendedor' => $row['vendedor'],
                    'qr_image' => $qrBase64,
                    'logo_image' => $logoBase64
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'boletos' => $boletosData
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

    if (isset($stmt)) $stmt->close();
    $conn->close();
    ?>
