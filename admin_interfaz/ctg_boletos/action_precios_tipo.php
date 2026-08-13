<?php
// Acción para manejar precios por tipo de boleto
include "../../evt_interfaz/conexion.php";
require_once __DIR__ . '/../../api/registrar_cambio.php';

// Obtener datos del formulario
$id_evento = isset($_POST['id_evento']) && $_POST['id_evento'] !== '' ? (int)$_POST['id_evento'] : null;
$accion = $_POST['accion'] ?? '';
$usa_diferenciados = isset($_POST['usa_diferenciados']) ? 1 : 0;

$precios = [
    'general' => isset($_POST['precio_general']) ? (float)$_POST['precio_general'] : 0,
    'nino' => isset($_POST['precio_nino']) ? (float)$_POST['precio_nino'] : 0,
    'adulto_mayor' => isset($_POST['precio_adulto_mayor']) ? (float)$_POST['precio_adulto_mayor'] : 0,
    'discapacitado' => isset($_POST['precio_discapacitado']) ? (float)$_POST['precio_discapacitado'] : 0,
    'cortesia' => 0 // Siempre gratis
];

// Verificar que la tabla existe y tiene la columna usa_diferenciados
$conn->query("
    CREATE TABLE IF NOT EXISTS precios_tipo_boleto (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_evento INT NULL COMMENT 'NULL = precio global para todos los eventos',
        tipo_boleto VARCHAR(50) NOT NULL,
        precio DECIMAL(10,2) NOT NULL DEFAULT 0,
        activo TINYINT(1) DEFAULT 1,
        usa_diferenciados TINYINT(1) DEFAULT 0,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_evento_tipo (id_evento, tipo_boleto)
    )
");

// Verificar si la columna usa_diferenciados existe
$check_col = $conn->query("SHOW COLUMNS FROM precios_tipo_boleto LIKE 'usa_diferenciados'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE precios_tipo_boleto ADD COLUMN usa_diferenciados TINYINT(1) DEFAULT 0");
}

try {
    // Validar que los precios sean mayores a 0 (excepto cortesía) si se están guardando manual
    if ($accion === 'guardar' || $accion === 'aplicar_todos') {
        foreach ($precios as $tipo => $precio) {
            if ($tipo !== 'cortesia' && $precio < 0) { // Cambiado a < 0 para permitir eventos gratis
                $redirect = ($id_evento ? "index.php?id_evento=$id_evento" : "index.php") . "&status=error&msg=" . urlencode("El precio de '$tipo' no puede ser negativo.");
                header("Location: $redirect");
                exit;
            }
        }
    }

    // Verificar si hay boletos vendidos antes de cambiar precios
    // Verificar si hay boletos vendidos antes de cambiar precios (CANDADO ELIMINADO PARA PERMITIR CAMBIOS DINÁMICOS)
    // El sistema POS y de contabilidad ya protege el precio histórico de los boletos individuales.

    switch ($accion) {
        case 'guardar':
            // Guardar precios (globales o para un evento específico)
            foreach ($precios as $tipo => $precio) {
                if ($id_evento === null) {
                    // Precio global
                    $stmt = $conn->prepare("
                        INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) 
                        VALUES (NULL, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
                    ");
                    $stmt->bind_param("sdi", $tipo, $precio, $usa_diferenciados);
                } else {
                    // Precio específico del evento
                    $stmt = $conn->prepare("
                        INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) 
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
                    ");
                    $stmt->bind_param("isdi", $id_evento, $tipo, $precio, $usa_diferenciados);
                }
                $stmt->execute();
                $stmt->close();
            }
            
            // También mantener compatibilidad con 'adulto' = 'general'
            if ($id_evento === null) {
                $stmt = $conn->prepare("
                    INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) 
                    VALUES (NULL, 'adulto', ?, ?)
                    ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
                ");
                $stmt->bind_param("di", $precios['general'], $usa_diferenciados);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) 
                    VALUES (?, 'adulto', ?, ?)
                    ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
                ");
                $stmt->bind_param("idi", $id_evento, $precios['general'], $usa_diferenciados);
                $stmt->execute();
                $stmt->close();
            }

            // --- SYNC CATEGORIAS TABLE (CRITICAL FOR MAPA) ---
            $mapa_nombres = [
                'general' => 'General',
                'nino' => 'Niño',
                'adulto_mayor' => '3ra Edad',
                'discapacitado' => 'Discapacitado',
            ];
            
            foreach ($mapa_nombres as $key => $nombre_cat) {
                if (!isset($precios[$key])) continue;
                $p = $precios[$key];

                if ($id_evento) {
                    // Update specific event
                    $stmt = $conn->prepare("UPDATE categorias SET precio = ? WHERE id_evento = ? AND nombre_categoria = ?");
                    $stmt->bind_param("dis", $p, $id_evento, $nombre_cat);
                    $stmt->execute();
                    $stmt->close();

                    // Also update 'Adulto Mayor' compatibility
                    if ($key == 'adulto_mayor') {
                         $other = 'Adulto Mayor';
                         $stmt = $conn->prepare("UPDATE categorias SET precio = ? WHERE id_evento = ? AND nombre_categoria = ?");
                         $stmt->bind_param("dis", $p, $id_evento, $other);
                         $stmt->execute();
                         $stmt->close();
                    }
                } else {
                    // Update ALL active events? The user expects global change to reflect.
                    // We'll update all categories matching this name.
                    $stmt = $conn->prepare("UPDATE categorias SET precio = ? WHERE nombre_categoria = ?");
                    $stmt->bind_param("ds", $p, $nombre_cat);
                    $stmt->execute();
                    $stmt->close();
                    
                     if ($key == 'adulto_mayor') {
                         $other = 'Adulto Mayor';
                         $stmt = $conn->prepare("UPDATE categorias SET precio = ? WHERE nombre_categoria = ?");
                         $stmt->bind_param("ds", $p, $other);
                         $stmt->execute();
                         $stmt->close();
                    }
                }
            }
            // ------------------------------------------------
            
            $msg = $id_evento ? 'Precios guardados para este evento' : 'Precios globales actualizados';
            $redirect = $id_evento ? "index.php?id_evento=$id_evento&status=success&msg=" . urlencode($msg) 
                                   : "index.php?status=success&msg=" . urlencode($msg);
            break;
            
        case 'hacer_gratis':
            $precios_gratis = ['general' => 0, 'nino' => 0, 'adulto_mayor' => 0, 'discapacitado' => 0, 'cortesia' => 0];
            foreach ($precios_gratis as $tipo => $precio) {
                if ($id_evento === null) {
                    $stmt = $conn->prepare("INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) VALUES (NULL, ?, ?, 0) ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)");
                    $stmt->bind_param("sd", $tipo, $precio);
                } else {
                    $stmt = $conn->prepare("INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)");
                    $stmt->bind_param("isd", $id_evento, $tipo, $precio);
                }
                $stmt->execute();
                $stmt->close();
            }
            
            // Adulto compatibility
            if ($id_evento === null) {
                $stmt = $conn->prepare("INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) VALUES (NULL, 'adulto', 0, 0) ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)");
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) VALUES (?, 'adulto', 0, 0) ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)");
                $stmt->bind_param("i", $id_evento);
                $stmt->execute();
                $stmt->close();
            }

            // Sync categorías a $0
            $mapa_nombres = ['general' => 'General', 'nino' => 'Niño', 'adulto_mayor' => '3ra Edad', 'discapacitado' => 'Discapacitado'];
            foreach ($mapa_nombres as $key => $nombre_cat) {
                 if ($id_evento) {
                     $stmt = $conn->prepare("UPDATE categorias SET precio = 0 WHERE id_evento = ? AND nombre_categoria = ?");
                     $stmt->bind_param("is", $id_evento, $nombre_cat);
                     $stmt->execute();
                     $stmt->close();
                     if ($key == 'adulto_mayor') {
                         $stmt = $conn->prepare("UPDATE categorias SET precio = 0 WHERE id_evento = ? AND nombre_categoria = 'Adulto Mayor'");
                         $stmt->bind_param("i", $id_evento);
                         $stmt->execute();
                         $stmt->close();
                     }
                 } else {
                     $stmt = $conn->prepare("UPDATE categorias SET precio = 0 WHERE nombre_categoria = ?");
                     $stmt->bind_param("s", $nombre_cat);
                     $stmt->execute();
                     $stmt->close();
                     if ($key == 'adulto_mayor') {
                         $stmt = $conn->prepare("UPDATE categorias SET precio = 0 WHERE nombre_categoria = 'Adulto Mayor'");
                         $stmt->execute();
                         $stmt->close();
                     }
                 }
            }
            
            $msg = $id_evento ? 'El evento ahora es COMPLETAMENTE GRATIS' : 'Precios globales establecidos en GRAUÍTO';
            $redirect = $id_evento ? "index.php?id_evento=$id_evento&status=success&msg=" . urlencode($msg) : "index.php?status=success&msg=" . urlencode($msg);
            break;

        case 'hacer_pago':
            $precios_pago = ['general' => 80, 'nino' => 50, 'adulto_mayor' => 60, 'discapacitado' => 40, 'cortesia' => 0];
            foreach ($precios_pago as $tipo => $precio) {
                if ($id_evento === null) {
                    $stmt = $conn->prepare("INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) VALUES (NULL, ?, ?, 0) ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)");
                    $stmt->bind_param("sd", $tipo, $precio);
                } else {
                    $stmt = $conn->prepare("INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)");
                    $stmt->bind_param("isd", $id_evento, $tipo, $precio);
                }
                $stmt->execute();
                $stmt->close();
            }
            
            if ($id_evento === null) {
                $stmt = $conn->prepare("INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) VALUES (NULL, 'adulto', 80, 0) ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)");
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) VALUES (?, 'adulto', 80, 0) ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)");
                $stmt->bind_param("i", $id_evento);
                $stmt->execute();
                $stmt->close();
            }

            // Sync categorías
            $mapa_nombres = ['general' => 'General', 'nino' => 'Niño', 'adulto_mayor' => '3ra Edad', 'discapacitado' => 'Discapacitado'];
            foreach ($mapa_nombres as $key => $nombre_cat) {
                 $p = $precios_pago[$key];
                 if ($id_evento) {
                     $stmt = $conn->prepare("UPDATE categorias SET precio = ? WHERE id_evento = ? AND nombre_categoria = ?");
                     $stmt->bind_param("dis", $p, $id_evento, $nombre_cat);
                     $stmt->execute();
                     $stmt->close();
                     if ($key == 'adulto_mayor') {
                         $stmt = $conn->prepare("UPDATE categorias SET precio = ? WHERE id_evento = ? AND nombre_categoria = 'Adulto Mayor'");
                         $stmt->bind_param("di", $p, $id_evento);
                         $stmt->execute();
                         $stmt->close();
                     }
                 } else {
                     $stmt = $conn->prepare("UPDATE categorias SET precio = ? WHERE nombre_categoria = ?");
                     $stmt->bind_param("ds", $p, $nombre_cat);
                     $stmt->execute();
                     $stmt->close();
                     if ($key == 'adulto_mayor') {
                         $stmt = $conn->prepare("UPDATE categorias SET precio = ? WHERE nombre_categoria = 'Adulto Mayor'");
                         $stmt->bind_param("d", $p);
                         $stmt->execute();
                         $stmt->close();
                     }
                 }
            }
            
            $msg = $id_evento ? 'El evento ahora es DE PAGO ($80 base)' : 'Precios globales restablecidos ($80 base)';
            $redirect = $id_evento ? "index.php?id_evento=$id_evento&status=success&msg=" . urlencode($msg) : "index.php?status=success&msg=" . urlencode($msg);
            break;
            
        case 'usar_global':
            // Eliminar precios específicos del evento
            if ($id_evento) {
                $stmt = $conn->prepare("DELETE FROM precios_tipo_boleto WHERE id_evento = ?");
                $stmt->bind_param("i", $id_evento);
                $stmt->execute();
                $stmt->close();

                // Sync: Set this event's categories to global prices
                // 1. Get global prices
                $globales = [];
                $res = $conn->query("SELECT tipo_boleto, precio FROM precios_tipo_boleto WHERE id_evento IS NULL");
                while($row = $res->fetch_assoc()) {
                    $globales[$row['tipo_boleto']] = $row['precio'];
                }

                $mapa_nombres = [
                    'general' => 'General',
                    'nino' => 'Niño',
                    'adulto_mayor' => '3ra Edad',
                    'discapacitado' => 'Discapacitado',
                ];

                foreach ($mapa_nombres as $key => $nombre_cat) {
                    if (isset($globales[$key])) {
                        $p = $globales[$key];
                        $stmt = $conn->prepare("UPDATE categorias SET precio = ? WHERE id_evento = ? AND nombre_categoria = ?");
                        $stmt->bind_param("dis", $p, $id_evento, $nombre_cat);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            
            $redirect = "index.php?id_evento=$id_evento&status=success&msg=" . urlencode('Ahora este evento usa precios globales');
            break;
            
        case 'aplicar_todos':
            // Aplicar precios globales a todos los eventos
            $conn->query("DELETE FROM precios_tipo_boleto WHERE id_evento IS NOT NULL");
            
            // Actualizar globales
            foreach ($precios as $tipo => $precio) {
                $stmt = $conn->prepare("
                    INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) 
                    VALUES (NULL, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
                ");
                $stmt->bind_param("sdi", $tipo, $precio, $usa_diferenciados);
                $stmt->execute();
                $stmt->close();
            }
            
            // Compatibilidad con adulto
            $stmt = $conn->prepare("
                INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) 
                VALUES (NULL, 'adulto', ?, ?)
                ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
            ");
            $stmt->bind_param("di", $precios['general'], $usa_diferenciados);
            $stmt->execute();
            $stmt->close();
            
            // --- SYNC ALL CATEGORIAS ---
            $mapa_nombres = [
                'general' => 'General',
                'nino' => 'Niño',
                'adulto_mayor' => '3ra Edad',
                'discapacitado' => 'Discapacitado',
            ];
             foreach ($mapa_nombres as $key => $nombre_cat) {
                if (!isset($precios[$key])) continue;
                $p = $precios[$key];
                $stmt = $conn->prepare("UPDATE categorias SET precio = ? WHERE nombre_categoria = ?");
                $stmt->bind_param("ds", $p, $nombre_cat);
                $stmt->execute();
                $stmt->close();
            }
            // ---------------------------

            $redirect = "index.php?status=success&msg=" . urlencode('Precios aplicados a todos los eventos');
            break;
            
        default:
            $redirect = "index.php?status=error&msg=" . urlencode('Acción no válida');
    }
    
    // Si la acción fue exitosa, notificamos el cambio para que los puntos de venta se actualicen en tiempo real
    if (isset($redirect) && strpos($redirect, 'status=success') !== false) {
        if (function_exists('registrar_cambio')) {
            $evtIdStr = $id_evento ? $id_evento : 'global';
            // Registramos un cambio tipo 'categoria' para que el index.php recargue sus variables/precios.
            registrar_cambio('categoria', $id_evento, null, ['mensaje' => "Precios actualizados ($evtIdStr)"]);
        }
    }
    
} catch (Exception $e) {
    $redirect = "index.php?status=error&msg=" . urlencode($e->getMessage());
}

$conn->close();
header("Location: $redirect");
exit;
?>
