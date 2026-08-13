<?php
session_start();
// 1. CONEXIÓN
// (Ajusta la ruta si es necesario, p.ej., ../../evt_interfaz/conexion.php)
include "../../evt_interfaz/conexion.php"; 
require_once '../../transacciones_helper.php';
require_once __DIR__ . '/../../api/registrar_cambio.php';

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$id_evento_redirect = $_POST['id_evento'] ?? $_GET['id_evento'] ?? null;

// Construir la URL base para redireccionar
// (Asumiendo que tu archivo de gestión se llama index.php en esta carpeta)
$redirect_url = "index.php"; 
if ($id_evento_redirect) {
    $redirect_url .= "?id_evento=" . $id_evento_redirect;
}

// ==================================================================
// --- FUNCIÓN DE AYUDA PARA REDIRIGIR CON MENSAJES ---
// ==================================================================
function redirigir($base_url, $status, $mensaje, $id_evento = null) {
    $conector = strpos($base_url, '?') === false ? '?' : '&';
    
    // Notificar cambio en categorías para actualización en tiempo real
    if ($status === 'success' && $id_evento) {
        // Registrar cambio en BD para SSE
        registrar_cambio('categoria', intval($id_evento), null, ['mensaje' => $mensaje]);
        
        echo "<!DOCTYPE html><html><head><title>Procesando...</title></head><body>";
        echo "<script>
            // Notificar cambio en categorías (compatibilidad)
            localStorage.setItem('categorias_actualizadas', JSON.stringify({
                id_evento: " . intval($id_evento) . ",
                timestamp: Date.now()
            }));
            
            // Redirigir
            setTimeout(function() {
                window.location.href = '" . $base_url . $conector . "status=$status&msg=" . urlencode($mensaje) . "';
            }, 100);
        </script>";
        echo "<p>Procesando cambios...</p></body></html>";
        exit;
    }
    
    header("Location: " . $base_url . $conector . "status=$status&msg=" . urlencode($mensaje));
    exit;
}

// 3. VALIDACIÓN BÁSICA
if (empty($accion)) {
    redirigir($redirect_url, 'error', 'Acción no válida.');
}

// ==================================================================
// --- CASO 1: LÓGICA DE ACTUALIZACIÓN RÁPIDA DE PRECIOS ---
// ==================================================================
if ($accion === 'actualizar_todos' || $accion === 'actualizar_seleccionado') {
    
    $precio_general = (!empty($_POST['precio_general'])) ? (float)$_POST['precio_general'] : null;
    $precio_discapacitado = (!empty($_POST['precio_discapacitado'])) ? (float)$_POST['precio_discapacitado'] : null;
    $id_evento_especifico = ($accion === 'actualizar_seleccionado' && !empty($_POST['id_evento'])) ? (int)$_POST['id_evento'] : null;
    
    // Validar que los precios sean mayores a 0
    if ($precio_general !== null && $precio_general <= 0) {
        redirigir($redirect_url, 'error', 'El precio General debe ser mayor a $0.');
    }
    if ($precio_discapacitado !== null && $precio_discapacitado <= 0) {
        redirigir($redirect_url, 'error', 'El precio Discapacitado debe ser mayor a $0.');
    }
    
    // Si es actualización de un evento específico, verificar si tiene boletos vendidos
    if ($id_evento_especifico) {
        $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM boletos WHERE id_evento = ? AND estatus = 1");
        $stmt_check->bind_param("i", $id_evento_especifico);
        $stmt_check->execute();
        $boletos_vendidos = $stmt_check->get_result()->fetch_assoc()['total'];
        $stmt_check->close();
        
        if ($boletos_vendidos > 0) {
            redirigir($redirect_url, 'error', "No se puede cambiar el precio: este evento tiene $boletos_vendidos boleto(s) vendido(s). Archiva el evento primero o cancela los boletos.");
        }
    } else {
        // Actualización masiva: verificar si ALGÚN evento activo tiene boletos vendidos
        $check_any = $conn->query("SELECT e.id_evento, e.titulo, COUNT(b.id_boleto) as vendidos FROM evento e INNER JOIN boletos b ON e.id_evento = b.id_evento AND b.estatus = 1 WHERE e.finalizado = 0 GROUP BY e.id_evento HAVING vendidos > 0 LIMIT 1");
        if ($check_any && $check_any->num_rows > 0) {
            $evt_con_ventas = $check_any->fetch_assoc();
            redirigir($redirect_url, 'error', "No se puede actualizar masivamente: el evento \"{$evt_con_ventas['titulo']}\" tiene {$evt_con_ventas['vendidos']} boleto(s) vendido(s). Cambia los precios individualmente para eventos sin ventas.");
        }
    }

    try {
        if ($precio_general !== null) {
            $sql_gen = "UPDATE categorias SET precio = ? WHERE nombre_categoria = 'General'";
            if ($id_evento_especifico) {
                $sql_gen .= " AND id_evento = ?"; $stmt_gen = $conn->prepare($sql_gen); $stmt_gen->bind_param("di", $precio_general, $id_evento_especifico);
            } else { $stmt_gen = $conn->prepare($sql_gen); $stmt_gen->bind_param("d", $precio_general); }
            $stmt_gen->execute(); $stmt_gen->close();
        }
        if ($precio_discapacitado !== null) {
            $sql_dis = "UPDATE categorias SET precio = ? WHERE nombre_categoria = 'Discapacitado'";
            if ($id_evento_especifico) {
                $sql_dis .= " AND id_evento = ?"; $stmt_dis = $conn->prepare($sql_dis); $stmt_dis->bind_param("di", $precio_discapacitado, $id_evento_especifico);
            } else { $stmt_dis = $conn->prepare($sql_dis); $stmt_dis->bind_param("d", $precio_discapacitado); }
            $stmt_dis->execute(); $stmt_dis->close();
        }
    } catch (Exception $e) {
        redirigir($redirect_url, 'error', $e->getMessage());
    }
    
    registrar_transaccion('categorias_actualizacion_masiva', 'Actualización rápida de precios en categorías');
    if (function_exists('registrar_cambio')) {
        registrar_cambio('categoria', $id_evento_especifico, null, ['accion' => 'actualizacion_masiva']);
    }
    redirigir($redirect_url, 'success', 'Precios actualizados masivamente.', $id_evento_especifico);
} 

// ==================================================================
// --- CASO 2: LÓGICA CRUD (CREAR, ACTUALIZAR, BORRAR) ---
// ==================================================================
else {
    
    if ($id_evento_redirect === null) {
        redirigir('index.php', 'error', 'ID de evento faltante.');
    }
    
    try {
        switch ($accion) {
            
            case 'crear':
                $nombre_categoria = $_POST['nombre_categoria'] ?? '';
                if (empty($nombre_categoria)) throw new Exception("El nombre es obligatorio.");
                $precio = isset($_POST['precio']) ? (float)$_POST['precio'] : 0.00; 
                $color = $_POST['color'] ?? '#E0E0E0';
                
                if ($precio < 0) throw new Exception("El precio no puede ser negativo.");
                
                $check = $conn->prepare("SELECT id_categoria FROM categorias WHERE id_evento = ? AND nombre_categoria = ?");
                $check->bind_param("is", $id_evento_redirect, $nombre_categoria);
                $check->execute(); $check->store_result();
                if ($check->num_rows > 0) throw new Exception("El nombre '$nombre_categoria' ya existe.");
                $check->close();
                
                $stmt = $conn->prepare("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isds", $id_evento_redirect, $nombre_categoria, $precio, $color);
                $stmt->execute(); $stmt->close();
                registrar_transaccion('categoria_crear', 'Creó categoría: ' . $nombre_categoria);
                if (function_exists('registrar_cambio')) {
                    registrar_cambio('categoria', (int) $id_evento_redirect, null, ['accion' => 'crear', 'nombre' => $nombre_categoria, 'precio' => $precio]);
                }
                redirigir($redirect_url, 'success', 'Categoría creada con éxito.', $id_evento_redirect);
                break;

            case 'actualizar':
                $id_categoria = $_POST['id_categoria'] ?? null;
                $nombre_categoria = $_POST['nombre_categoria'] ?? '';
                if (empty($id_categoria)) throw new Exception("Datos incompletos.");
                $precio = isset($_POST['precio']) ? (float)$_POST['precio'] : 0.00; 
                $color = $_POST['color'] ?? '#E0E0E0';
                
                if ($precio < 0) throw new Exception("El precio no puede ser negativo.");
                
                // Verificar si el evento tiene boletos vendidos con esta categoría
                $stmt_boletos = $conn->prepare("
                    SELECT COUNT(*) as total FROM boletos 
                    WHERE id_evento = ? AND id_categoria = ? AND estatus = 1
                ");
                $stmt_boletos->bind_param("ii", $id_evento_redirect, $id_categoria);
                $stmt_boletos->execute();
                $boletos_con_cat = $stmt_boletos->get_result()->fetch_assoc()['total'];
                $stmt_boletos->close();
                
                // Obtener el precio actual para ver si realmente cambió
                $stmt_precio_actual = $conn->prepare("SELECT precio FROM categorias WHERE id_categoria = ? AND id_evento = ?");
                $stmt_precio_actual->bind_param("ii", $id_categoria, $id_evento_redirect);
                $stmt_precio_actual->execute();
                $row_precio = $stmt_precio_actual->get_result()->fetch_assoc();
                $precio_actual = $row_precio ? (float) $row_precio['precio'] : 0.0;
                $stmt_precio_actual->close();
                
                if ($boletos_con_cat > 0 && $precio_actual != (float) $precio) {
                    throw new Exception("No se puede cambiar el precio: hay $boletos_con_cat boleto(s) vendido(s) con esta categoría. Los boletos ya cobrados quedarían con precio inconsistente.");
                }
                
                $check = $conn->prepare("SELECT id_categoria FROM categorias WHERE id_evento = ? AND nombre_categoria = ? AND id_categoria != ?");
                $check->bind_param("isi", $id_evento_redirect, $nombre_categoria, $id_categoria);
                $check->execute(); $check->store_result();
                if ($check->num_rows > 0) throw new Exception("El nombre '$nombre_categoria' ya existe.");
                $check->close();
                
                $stmt = $conn->prepare("UPDATE categorias SET nombre_categoria = ?, precio = ?, color = ? WHERE id_categoria = ? AND id_evento = ?");
                $stmt->bind_param("sdsii", $nombre_categoria, $precio, $color, $id_categoria, $id_evento_redirect);
                $stmt->execute(); $stmt->close();

                // Sincronizar precios_tipo_boleto si es categoría estándar
                $mapa_cat_tipo = [
                    'General' => 'general',
                    'Niño' => 'nino',
                    'Nino' => 'nino',
                    '3ra Edad' => 'adulto_mayor',
                    'Adulto Mayor' => 'adulto_mayor',
                    'Discapacitado' => 'discapacitado',
                ];
                if (isset($mapa_cat_tipo[$nombre_categoria])) {
                    $tipo = $mapa_cat_tipo[$nombre_categoria];
                    $chk = $conn->query("SHOW TABLES LIKE 'precios_tipo_boleto'");
                    if ($chk && $chk->num_rows > 0) {
                        $stmt_pt = $conn->prepare("
                            INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados)
                            VALUES (?, ?, ?, 0)
                            ON DUPLICATE KEY UPDATE precio = VALUES(precio)
                        ");
                        $stmt_pt->bind_param("isd", $id_evento_redirect, $tipo, $precio);
                        $stmt_pt->execute();
                        $stmt_pt->close();
                        if ($tipo === 'general') {
                            $stmt_ad = $conn->prepare("
                                INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados)
                                VALUES (?, 'adulto', ?, 0)
                                ON DUPLICATE KEY UPDATE precio = VALUES(precio)
                            ");
                            $stmt_ad->bind_param("id", $id_evento_redirect, $precio);
                            $stmt_ad->execute();
                            $stmt_ad->close();
                        }
                    }
                }

                registrar_transaccion('categoria_actualizar', 'Actualizó categoría: ' . $nombre_categoria);
                redirigir($redirect_url, 'success', 'Categoría actualizada.', $id_evento_redirect);
                break;
                
            case 'borrar':
                $id_categoria = $_GET['id_categoria'] ?? null;
                if (empty($id_categoria)) throw new Exception("ID faltante.");
                
                $stmt = $conn->prepare("DELETE FROM categorias WHERE id_categoria = ? AND id_evento = ?");
                $stmt->bind_param("ii", $id_categoria, $id_evento_redirect);
                $stmt->execute(); $stmt->close();
                registrar_transaccion('categoria_borrar', 'Eliminó categoría ID ' . $id_categoria);
                redirigir($redirect_url, 'success', 'Categoría eliminada.', $id_evento_redirect);
                break;

            default:
                throw new Exception("Acción desconocida.");
        }
    } catch (Exception $e) {
        redirigir($redirect_url, 'error', $e->getMessage());
    }
}

$conn->close();
?>