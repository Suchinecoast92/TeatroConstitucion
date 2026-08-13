<?php
/**
 * Script para generar datos de prueba para estadísticas
 * - 3 a 5 eventos aleatorios
 * - Entre 100 y 300 boletos por evento
 * - Máximo 3 categorías por evento
 */

require_once 'evt_interfaz/conexion.php';

// Configuración
$num_eventos = rand(3, 5);
$start_date = new DateTime();
$categorias_posibles = ['General', 'Preferente', 'VIP'];
$tipos_boleto = ['adulto', 'nino', 'inapam'];

$titulos_obras = [
    'Romeo y Julieta - Edición Especial',
    'El Fantasma de la Ópera',
    'Los Miserables',
    'El Lago de los Cisnes',
    'Hamlet - Versión Contemporánea',
    'La Casa de Bernarda Alba',
    'Don Quijote de la Mancha',
    'El Rey León - Musical',
    'Cats - El Musical',
    'Macbeth Reimaginado'
];

echo "<pre>";
echo "====================================\n";
echo "  GENERADOR DE DATOS DE PRUEBA\n";
echo "====================================\n\n";
echo "Configuración:\n";
echo "  - Eventos a crear: $num_eventos\n";
echo "  - Boletos por evento: 100-300\n";
echo "  - Categorías: máximo 3\n\n";

try {
    $conn->begin_transaction();

    // 1. Obtener usuario para logs
    $id_usuario = 1;
    $resUser = $conn->query("SELECT id_usuario FROM usuarios LIMIT 1");
    if($resUser && $rowUsr = $resUser->fetch_assoc()) {
        $id_usuario = $rowUsr['id_usuario'];
    }

    // 2. Crear/obtener categorías (máximo 3)
    $cat_ids = [];
    $num_categorias = rand(2, 3);
    $categorias_usar = array_slice($categorias_posibles, 0, $num_categorias);
    
    foreach($categorias_usar as $cat_nombre) {
        $check = $conn->query("SELECT id_categoria FROM categorias WHERE nombre_categoria = '$cat_nombre'");
        if($check->num_rows > 0) {
            $cat_ids[$cat_nombre] = $check->fetch_assoc()['id_categoria'];
        } else {
            $conn->query("INSERT INTO categorias (nombre_categoria) VALUES ('$cat_nombre')");
            $cat_ids[$cat_nombre] = $conn->insert_id;
        }
    }
    echo "Categorías configuradas: " . implode(', ', array_keys($cat_ids)) . "\n\n";

    $stats_total = [
        'eventos' => 0,
        'funciones' => 0,
        'boletos' => 0,
        'ingresos' => 0
    ];

    shuffle($titulos_obras);
    
    for ($i = 0; $i < $num_eventos; $i++) {
        // --- Crear Evento ---
        $titulo = $titulos_obras[$i] ?? "Obra de Teatro #" . rand(1000, 9999);
        $desc = "Una producción espectacular que cautivará al público con su increíble puesta en escena.";
        $img = "uploads/default_event.jpg";
        $inicio_venta = (clone $start_date)->modify("-30 days")->format('Y-m-d H:i:s');
        $cierre_venta = (clone $start_date)->modify("+60 days")->format('Y-m-d H:i:s');
        
        $stmtEv = $conn->prepare("INSERT INTO evento (titulo, descripcion, imagen, tipo, inicio_venta, cierre_venta, finalizado) VALUES (?, ?, ?, 1, ?, ?, 0)");
        $stmtEv->bind_param("sssss", $titulo, $desc, $img, $inicio_venta, $cierre_venta);
        $stmtEv->execute();
        $id_evento = $conn->insert_id;
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🎭 EVENTO: $titulo\n";
        echo "   ID: $id_evento\n";
        $stats_total['eventos']++;

        // --- Crear Funciones (2-4 por evento) ---
        $num_funciones = rand(2, 4);
        $funciones_ids = [];
        
        echo "   📅 Funciones:\n";
        for ($f = 0; $f < $num_funciones; $f++) {
            $fecha_funcion = clone $start_date;
            $fecha_funcion->modify("+" . rand(1, 60) . " days");
            $hora = rand(16, 21);
            $fecha_funcion->setTime($hora, 0, 0);
            
            $fecha_hora_str = $fecha_funcion->format('Y-m-d H:i:s');
            
            // Estructura correcta: id_evento, fecha_hora, estado (0 = activo, 1 = finalizado)
            $stmtFun = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora, estado) VALUES (?, ?, 0)");
            $stmtFun->bind_param("is", $id_evento, $fecha_hora_str);
            $stmtFun->execute();
            $id_funcion = $conn->insert_id;
            $funciones_ids[] = $id_funcion;
            
            echo "      - " . $fecha_funcion->format('Y-m-d H:i') . " (ID: $id_funcion)\n";
            $stats_total['funciones']++;
        }

        // --- Simular Ventas (100-300 boletos) ---
        $total_boletos = rand(100, 300);
        $ingresos_evento = 0;
        
        echo "   🎫 Generando $total_boletos boletos...\n";
        
        // Distribuir boletos entre funciones con asientos únicos
        $boletos_por_funcion = [];
        foreach ($funciones_ids as $fid) {
            $boletos_por_funcion[$fid] = 0;
        }

        for ($b = 0; $b < $total_boletos; $b++) {
            // Elegir función con menos boletos para distribuir
            asort($boletos_por_funcion);
            $id_funcion = array_key_first($boletos_por_funcion);
            $asiento_num = $boletos_por_funcion[$id_funcion] + 1;
            $boletos_por_funcion[$id_funcion]++;
            
            $cat_keys = array_keys($cat_ids);
            $cat_nombre = $cat_keys[array_rand($cat_keys)];
            $id_categoria = $cat_ids[$cat_nombre];
            
            // Precio según categoría
            $precio_base = match($cat_nombre) {
                'VIP' => rand(350, 500),
                'Preferente' => rand(200, 350),
                default => rand(100, 200)
            };
            $precio_final = $precio_base + rand(-15, 15);
            $descuento = rand(0, 1) ? rand(10, 50) : 0;
            
            // Fecha de venta (últimos 30 días)
            $fecha_venta = clone $start_date;
            $fecha_venta->modify("-" . rand(0, 30) . " days");
            $fecha_venta->setTime(rand(9, 22), rand(0, 59), rand(0, 59));
            $fecha_venta_str = $fecha_venta->format('Y-m-d H:i:s');
            
            // Tipo de boleto y código único - usar asiento secuencial para evitar duplicados
            $tipo_boleto = $tipos_boleto[array_rand($tipos_boleto)];
            $codigo_unico = 'TKT-' . strtoupper(uniqid()) . '-' . $b;
            $id_asiento = $asiento_num; // Asiento secuencial único por función
            
            // Estructura correcta de boletos
            $stmtBol = $conn->prepare("INSERT INTO boletos (id_evento, id_funcion, id_asiento, id_categoria, codigo_unico, precio_base, descuento_aplicado, precio_final, tipo_boleto, id_usuario, fecha_compra, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmtBol->bind_param("iiiisddisis", $id_evento, $id_funcion, $id_asiento, $id_categoria, $codigo_unico, $precio_base, $descuento, $precio_final, $tipo_boleto, $id_usuario, $fecha_venta_str);
            $stmtBol->execute();
            
            $ingresos_evento += $precio_final;
            $stats_total['boletos']++;
            $stats_total['ingresos'] += $precio_final;
            
            // Log de transacción (cada 10 boletos)
            if ($b % 10 === 0) {
                $stmtLog = $conn->prepare("INSERT INTO transacciones (id_usuario, accion, descripcion, datos_json, fecha_hora) VALUES (?, 'venta', ?, ?, ?)");
                $desc_log = "Venta de boletos para $titulo";
                $json = json_encode([
                    'evento' => ['titulo' => $titulo, 'id' => $id_evento],
                    'total' => $precio_final * rand(1, 3),
                    'cantidad' => rand(1, 3),
                    'cliente' => 'Cliente ' . rand(1, 999),
                    'funcion' => $id_funcion
                ]);
                $stmtLog->bind_param("isss", $id_usuario, $desc_log, $json, $fecha_venta_str);
                $stmtLog->execute();
            }
        }
        
        echo "   💰 Ingresos del evento: $" . number_format($ingresos_evento, 2) . "\n\n";
    }

    $conn->commit();
    
    echo "====================================\n";
    echo "  ✅ RESUMEN FINAL\n";
    echo "====================================\n";
    echo "  🎭 Eventos creados: " . $stats_total['eventos'] . "\n";
    echo "  📅 Funciones creadas: " . $stats_total['funciones'] . "\n";
    echo "  🎫 Boletos vendidos: " . $stats_total['boletos'] . "\n";
    echo "  💰 Ingresos totales: $" . number_format($stats_total['ingresos'], 2) . "\n";
    echo "====================================\n\n";
    echo "¡Datos generados exitosamente!\n";
    echo "Ahora puedes revisar la sección de Estadísticas.\n";
    echo "</pre>";

} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "</pre>";
}

// Limpiar archivos temporales
@unlink('check_structure.php');
@unlink('check_tables.php');
?>
