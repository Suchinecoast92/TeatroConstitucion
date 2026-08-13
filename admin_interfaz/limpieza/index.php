<?php
session_start();
include "../../conexion.php";

// Verificar acceso admin
if (!isset($_SESSION['usuario_id'])) {
    die('Acceso denegado');
}
if ($_SESSION['usuario_rol'] !== 'admin') {
    die('Solo administradores pueden acceder a esta función');
}

// Tablas que NO se deben limpiar (mantener siempre)
$tablas_protegidas = [
    'usuarios',      // Usuarios del sistema
    'asientos',      // Estructura de asientos (no cambia)
];

// Manejar solicitud de limpieza
$mensaje = null;
$tipo_mensaje = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $password = $_POST['password'] ?? '';

    // Verificar contraseña del admin
    $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = ? AND rol = 'admin'");
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $mensaje = "Error de autenticación";
        $tipo_mensaje = "error";
    } else {
        $admin = $result->fetch_assoc();

        // Verificar contraseña (usando password_verify ya que están hasheadas)
        if (!password_verify($password, $admin['password'])) {
            $mensaje = "Contraseña incorrecta";
            $tipo_mensaje = "error";
        } else {
            // Contraseña correcta, proceder con la limpieza
            try {
                $conn->query("SET FOREIGN_KEY_CHECKS = 0");

                // Obtener todas las tablas de la BD
                $result = $conn->query("SHOW TABLES");
                $tablas_limpiadas = [];

                while ($row = $result->fetch_row()) {
                    $tabla = $row[0];

                    // Verificar si está protegida
                    if (in_array($tabla, $tablas_protegidas)) {
                        continue;
                    }

                    // Truncar la tabla
                    if ($conn->query("TRUNCATE TABLE `$tabla`")) {
                        $tablas_limpiadas[] = $tabla;
                    }
                }

                $conn->query("SET FOREIGN_KEY_CHECKS = 1");

                // Registrar la acción
                if (function_exists('registrar_transaccion')) {
                    require_once '../../transacciones_helper.php';
                    registrar_transaccion('limpieza_bd', 'Limpieza completa de BD: ' . implode(', ', $tablas_limpiadas));
                }

                $mensaje = "Limpieza completada. Se limpiaron " . count($tablas_limpiadas) . " tablas";
                $tipo_mensaje = "success";

            } catch (Exception $e) {
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                $mensaje = "Error durante la limpieza: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
    $stmt->close();
}

// Obtener información de las tablas
$tablas_info = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tabla = $row[0];
    $count_result = $conn->query("SELECT COUNT(*) as total FROM `$tabla`");
    $count = $count_result ? $count_result->fetch_assoc()['total'] : 0;
    $protegida = in_array($tabla, $tablas_protegidas);

    $tablas_info[] = [
        'nombre' => $tabla,
        'registros' => $count,
        'protegida' => $protegida
    ];
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpieza de Base de Datos</title>
    <link rel="icon" href="../../crt_interfaz/imagenes_teatro/nat.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border: #475569;
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --warning: #f59e0b;
            --success: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 30px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header .icon-warning {
            font-size: 5rem;
            color: var(--danger);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.7;
                transform: scale(1.05);
            }
        }

        .header h1 {
            font-size: 2rem;
            margin: 20px 0 10px;
            color: var(--danger);
        }

        .header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .warning-box {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.1) 100%);
            border: 2px solid var(--danger);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .warning-box h3 {
            color: var(--danger);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .warning-box ul {
            margin-left: 30px;
            color: var(--text-secondary);
        }

        .warning-box li {
            margin-bottom: 8px;
        }

        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 30px;
        }

        .table-item {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-item.protected {
            border-color: var(--success);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, transparent 100%);
        }

        .table-item.danger {
            border-color: var(--danger);
        }

        .table-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .table-count {
            background: var(--bg-input);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .protected-badge {
            background: var(--success);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .action-section {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 30px;
            border: 2px solid var(--border);
        }

        .action-section h3 {
            color: var(--danger);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 14px;
            background: var(--bg-input);
            border: 2px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--danger);
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--danger);
            margin-top: 3px;
        }

        .checkbox-group label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .btn-danger {
            width: 100%;
            padding: 16px;
            font-size: 1.1rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--danger) 0%, var(--danger-dark) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.4);
        }

        .btn-danger:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        .section-title {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .legend {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .legend-dot.protected {
            background: var(--success);
        }

        .legend-dot.danger {
            background: var(--danger);
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <i class="bi bi-exclamation-triangle-fill icon-warning"></i>
            <h1>⚠️ Limpieza de Base de Datos</h1>
            <p>Esta acción eliminará TODOS los datos de las tablas seleccionadas</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>">
                <i class="bi bi-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'x-circle' ?>"></i>
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <div class="warning-box">
            <h3><i class="bi bi-shield-exclamation"></i> Advertencia Importante</h3>
            <ul>
                <li><strong>Esta acción es IRREVERSIBLE</strong> - No hay forma de recuperar los datos una vez
                    eliminados</li>
                <li>Se eliminarán: eventos, funciones, boletos, ventas, descuentos, categorías, etc.</li>
                <li>Se conservarán: usuarios del sistema y estructura de asientos</li>
                <li>Ideal para: reiniciar el sistema antes de una nueva temporada</li>
            </ul>
        </div>

        <h3 class="section-title">
            <i class="bi bi-database"></i> Estado Actual de las Tablas
        </h3>

        <div class="legend">
            <div class="legend-item">
                <div class="legend-dot protected"></div>
                <span>Protegida (no se borrará)</span>
            </div>
            <div class="legend-item">
                <div class="legend-dot danger"></div>
                <span>Se borrará</span>
            </div>
        </div>

        <div class="tables-grid">
            <?php foreach ($tablas_info as $tabla): ?>
                <div class="table-item <?= $tabla['protegida'] ? 'protected' : 'danger' ?>">
                    <div>
                        <div class="table-name"><?= htmlspecialchars($tabla['nombre']) ?></div>
                        <span class="table-count"><?= number_format($tabla['registros']) ?> registros</span>
                    </div>
                    <?php if ($tabla['protegida']): ?>
                        <span class="protected-badge"><i class="bi bi-shield-check"></i> PROTEGIDA</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="action-section">
            <h3><i class="bi bi-trash3"></i> Ejecutar Limpieza Completa</h3>

            <form method="POST" id="formLimpieza" onsubmit="return validarFormulario()">
                <input type="hidden" name="accion" value="limpiar">

                <div class="checkbox-group">
                    <input type="checkbox" id="confirmacion1" name="confirmacion1" required>
                    <label for="confirmacion1">
                        Entiendo que esta acción <strong>ELIMINARÁ PERMANENTEMENTE</strong> todos los eventos,
                        boletos, ventas, descuentos y demás datos del sistema.
                    </label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="confirmacion2" name="confirmacion2" required>
                    <label for="confirmacion2">
                        Confirmo que he realizado un <strong>respaldo de la base de datos</strong> antes de proceder
                        y acepto la responsabilidad de esta acción.
                    </label>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="bi bi-key"></i> Ingrese su contraseña de administrador para confirmar
                    </label>
                    <input type="password" id="password" name="password" required
                        placeholder="Contraseña del administrador">
                </div>

                <button type="submit" class="btn-danger" id="btnLimpiar" disabled>
                    <i class="bi bi-trash3-fill"></i>
                    ELIMINAR TODOS LOS DATOS
                </button>
            </form>
        </div>
    </div>

    <script>
        const checkbox1 = document.getElementById('confirmacion1');
        const checkbox2 = document.getElementById('confirmacion2');
        const btnLimpiar = document.getElementById('btnLimpiar');
        const passwordInput = document.getElementById('password');

        function actualizarBoton() {
            const todosChecked = checkbox1.checked && checkbox2.checked;
            const passwordFilled = passwordInput.value.length > 0;
            btnLimpiar.disabled = !(todosChecked && passwordFilled);
        }

        checkbox1.addEventListener('change', actualizarBoton);
        checkbox2.addEventListener('change', actualizarBoton);
        passwordInput.addEventListener('input', actualizarBoton);

        function validarFormulario() {
            if (!checkbox1.checked || !checkbox2.checked) {
                alert('Debe marcar ambas casillas de confirmación');
                return false;
            }
            if (!passwordInput.value) {
                alert('Debe ingresar su contraseña');
                return false;
            }

            // Mostrar modal de confirmación personalizado
            mostrarModalConfirmacion();
            return false; // Prevenir envío hasta confirmar
        }

        function mostrarModalConfirmacion() {
            // Crear overlay
            const overlay = document.createElement('div');
            overlay.id = 'modalConfirmOverlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                animation: fadeIn 0.3s ease;
            `;

            overlay.innerHTML = `
                <div style="
                    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
                    border: 3px solid #ef4444;
                    border-radius: 20px;
                    padding: 40px;
                    max-width: 500px;
                    text-align: center;
                    animation: scaleIn 0.3s ease;
                ">
                    <i class="bi bi-exclamation-octagon-fill" style="font-size: 5rem; color: #ef4444; display: block; margin-bottom: 20px;"></i>
                    <h2 style="color: #ef4444; margin-bottom: 15px; font-size: 1.8rem;">¿ESTÁ COMPLETAMENTE SEGURO?</h2>
                    <p style="color: #94a3b8; font-size: 1.1rem; margin-bottom: 30px; line-height: 1.6;">
                        Esta acción <strong style="color: #f1f5f9;">ELIMINARÁ PERMANENTEMENTE</strong> todos los datos 
                        excepto usuarios y asientos.<br><br>
                        <span style="color: #f59e0b; font-weight: bold;">¡ESTA ACCIÓN NO SE PUEDE DESHACER!</span>
                    </p>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <button onclick="cerrarModalConfirmacion()" style="
                            padding: 14px 30px;
                            background: #475569;
                            color: white;
                            border: none;
                            border-radius: 10px;
                            font-size: 1rem;
                            font-weight: 600;
                            cursor: pointer;
                        ">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button onclick="ejecutarLimpieza()" style="
                            padding: 14px 30px;
                            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                            color: white;
                            border: none;
                            border-radius: 10px;
                            font-size: 1rem;
                            font-weight: 600;
                            cursor: pointer;
                        ">
                            <i class="bi bi-trash3-fill"></i> SÍ, ELIMINAR TODO
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);
        }

        function cerrarModalConfirmacion() {
            const overlay = document.getElementById('modalConfirmOverlay');
            if (overlay) {
                overlay.remove();
            }
        }

        function ejecutarLimpieza() {
            cerrarModalConfirmacion();

            // Mostrar indicador de carga
            btnLimpiar.innerHTML = '<i class="bi bi-hourglass-split" style="animation: spin 1s infinite linear;"></i> Procesando...';
            btnLimpiar.disabled = true;

            // Enviar formulario
            document.getElementById('formLimpieza').onsubmit = null;
            document.getElementById('formLimpieza').submit();
        }
    </script>

    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes scaleIn {
            from {
                transform: scale(0.8);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }
    </style>

    <!-- Botón de regreso -->

</body>

</html>