<?php
include "../conexion.php";

$mensaje = '';
$tipo_mensaje = '';
$boleto_info = null;

// Procesar confirmación de entrada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_entrada'])) {
    $codigo_unico = $_POST['codigo_unico'];
    
    $stmt = $conn->prepare("UPDATE boletos SET estatus = 0 WHERE codigo_unico = ? AND estatus = 1");
    $stmt->bind_param("s", $codigo_unico);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $mensaje = "Entrada confirmada exitosamente. El boleto ha sido marcado como usado.";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "Error: El boleto ya fue usado o no existe.";
        $tipo_mensaje = "danger";
    }
    $stmt->close();
}

// Buscar boleto por código
if (isset($_GET['codigo']) && !empty($_GET['codigo'])) {
    $codigo_buscar = strtoupper(trim($_GET['codigo']));
    
    $stmt = $conn->prepare("
        SELECT 
            b.id_boleto,
            b.codigo_unico,
            b.precio_final,
            b.estatus,
            a.codigo_asiento,
            c.nombre_categoria,
            e.titulo as evento_titulo,
            e.tipo as evento_tipo
        FROM boletos b
        INNER JOIN asientos a ON b.id_asiento = a.id_asiento
        INNER JOIN evento e ON b.id_evento = e.id_evento
        LEFT JOIN categorias c ON b.id_categoria = c.id_categoria
        WHERE b.codigo_unico = ?
    ");
    
    $stmt->bind_param("s", $codigo_buscar);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $boleto_info = $result->fetch_assoc();
        
        if ($boleto_info['estatus'] == 0) {
            $mensaje = "ADVERTENCIA: Este boleto ya fue usado.";
            $tipo_mensaje = "warning";
        } else {
            $mensaje = "Boleto válido. Puede confirmar la entrada.";
            $tipo_mensaje = "success";
        }
    } else {
        $mensaje = "Boleto no encontrado. Verifique el código.";
        $tipo_mensaje = "danger";
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escanear QR - Control de Entrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .scanner-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .qr-preview {
            max-width: 300px;
            margin: 20px auto;
            display: block;
            border: 3px solid #dee2e6;
            border-radius: 10px;
        }
        .status-badge {
            font-size: 1.2em;
            padding: 10px 20px;
        }
        .btn-scan {
            font-size: 1.2em;
            padding: 15px;
        }
    </style>
</head>
<body>
    <div class="scanner-container">
        <div class="card">
            <div class="card-header bg-primary text-white text-center">
                <h3><i class="bi bi-qr-code-scan"></i> Control de Entrada</h3>
            </div>
            <div class="card-body">
                
                <?php if ($mensaje): ?>
                <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Formulario de búsqueda -->
                <form method="GET" class="mb-4">
                    <div class="input-group input-group-lg">
                        <input 
                            type="text" 
                            name="codigo" 
                            class="form-control" 
                            placeholder="Ingrese código del boleto"
                            value="<?= isset($_GET['codigo']) ? htmlspecialchars($_GET['codigo']) : '' ?>"
                            autofocus
                            required
                        >
                        <button class="btn btn-primary btn-scan" type="submit">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    </div>
                    <small class="text-muted">Escanee el código QR o ingrese el código manualmente</small>
                </form>
                
                <?php if ($boleto_info): ?>
                <hr>
                
                <!-- Información del boleto -->
                <div class="text-center mb-3">
                    <?php if ($boleto_info['estatus'] == 1): ?>
                        <span class="badge bg-success status-badge">
                            <i class="bi bi-check-circle"></i> BOLETO VÁLIDO
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger status-badge">
                            <i class="bi bi-x-circle"></i> BOLETO USADO
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- QR Code -->
                <img 
                    src="../boletos_qr/<?= htmlspecialchars($boleto_info['codigo_unico']) ?>.png" 
                    alt="QR Code" 
                    class="qr-preview"
                >
                
                <!-- Detalles -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tr>
                            <th>Código:</th>
                            <td><strong><?= htmlspecialchars($boleto_info['codigo_unico']) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Evento:</th>
                            <td><?= htmlspecialchars($boleto_info['evento_titulo']) ?></td>
                        </tr>
                        <tr>
                            <th>Asiento:</th>
                            <td><strong><?= htmlspecialchars($boleto_info['codigo_asiento']) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Categoría:</th>
                            <td><?= htmlspecialchars($boleto_info['nombre_categoria'] ?? 'General') ?></td>
                        </tr>
                        <tr>
                            <th>Precio:</th>
                            <td>$<?= number_format($boleto_info['precio_final'], 2) ?></td>
                        </tr>
                        <tr>
                            <th>Estado:</th>
                            <td>
                                <?php if ($boleto_info['estatus'] == 1): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Usado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Botón de confirmación -->
                <?php if ($boleto_info['estatus'] == 1): ?>
                <form method="POST" onsubmit="return confirm('¿Confirmar entrada? Esta acción no se puede deshacer.');">
                    <input type="hidden" name="codigo_unico" value="<?= htmlspecialchars($boleto_info['codigo_unico']) ?>">
                    <button type="submit" name="confirmar_entrada" class="btn btn-success btn-lg w-100">
                        <i class="bi bi-check-circle"></i> Confirmar Entrada
                    </button>
                </form>
                <?php else: ?>
                <div class="alert alert-warning text-center">
                    <i class="bi bi-exclamation-triangle"></i> Este boleto ya fue utilizado
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
                
                <hr>
                <div class="text-center">
                    <a href="index.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left"></i> Volver al Punto de Venta
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
