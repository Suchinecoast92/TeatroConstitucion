<?php
session_start();

// Evitar que el navegador muestre login/panel en caché al usar "atrás" tras cerrar sesión
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (isset($_SESSION['usuario_id'])) {
    if (($_SESSION['usuario_rol'] ?? '') === 'admin') {
        header('Location: index.php');
    } else {
        header('Location: index_empleado.php');
    }
    exit();
}

require_once 'conexion.php';
require_once 'transacciones_helper.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($nombre) || empty($password)) {
        $error = 'Por favor, ingrese nombre y contraseña';
    } elseif (!preg_match('/^[A-Za-z0-9]+$/', $password)) {
        $error = 'La contraseña solo puede contener letras y números, sin espacios ni símbolos';
    } else {
        $stmt = $conn->prepare("SELECT id_usuario, nombre, apellido, password, rol, activo FROM usuarios WHERE nombre = ? AND activo = 1");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $usuario = $result->fetch_assoc();
            
            if (password_verify($password, $usuario['password'])) {
                $_SESSION['usuario_id'] = $usuario['id_usuario'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_apellido'] = $usuario['apellido'];
                $_SESSION['usuario_rol'] = $usuario['rol'];
                $_SESSION['login_time'] = time();
                registrar_transaccion('login', 'Inicio de sesión');

                if ($usuario['rol'] === 'admin') {
                    header("Location: index.php");
                } else {
                    header("Location: index_empleado.php");
                }
                exit();
            } else {
                $error = 'Contraseña incorrecta';
            }
        } else {
            $error = 'Usuario no encontrado o inactivo';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Teatro</title>
    <link rel="icon" href="crt_interfaz/imagenes_teatro/nat.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/teatro-style.css">
    <style>
        body {
            background: var(--bg-primary);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-container {
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
            border: 1px solid var(--border-color);
            animation: slideIn 0.4s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header {
            background: var(--gradient-primary);
            padding: 40px 32px;
            text-align: center;
        }

        .login-logo {
            width: 72px;
            height: 72px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 2rem;
            color: white;
        }

        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 4px;
        }

        .login-header p {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
        }

        .login-body {
            padding: 32px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--accent-blue);
            font-size: 1.1rem;
        }

        .form-group input {
            width: 100%;
            padding: 14px 14px 14px 48px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-family: var(--font-family);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            transition: var(--transition-normal);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(21, 97, 240, 0.2);
        }

        .form-group input::placeholder {
            color: var(--text-muted);
        }

        .error-message {
            background: var(--danger-bg);
            color: var(--danger);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(255, 69, 58, 0.3);
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--accent-blue);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 600;
            font-family: var(--font-family);
            cursor: pointer;
            transition: var(--transition-normal);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            background: var(--accent-blue-hover);
            box-shadow: var(--shadow-glow);
            transform: translateY(-2px);
        }

        .login-footer {
            text-align: center;
            padding: 20px 32px 28px;
            color: var(--text-muted);
            font-size: 0.85rem;
            border-top: 1px solid var(--border-color);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <i class="bi bi-ticket-perforated-fill"></i>
            </div>
            <h1>Sistema de Teatro</h1>
            <p>Ingrese sus credenciales</p>
        </div>
        
        <form method="POST" action="" class="login-body" autocomplete="off">
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="nombre">Usuario</label>
                <div class="input-wrapper">
                    <i class="bi bi-person-fill"></i>
                    <input type="text" id="nombre" name="nombre" required autofocus
                           value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>"
                           placeholder="Nombre de usuario">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock-fill"></i>
                    <input type="password" id="password" name="password" required
                           placeholder="••••••••" pattern="[A-Za-z0-9]+" title="Solo letras y números, sin espacios ni símbolos" autocomplete="off">
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right"></i>
                Iniciar Sesión
            </button>
        </form>
        
        <div class="login-footer">
            <p>Sistema de Teatro © 2025</p>
        </div>
    </div>

    <script>
        (function() {
            const form = document.querySelector('.login-body');
            const passwordInput = document.getElementById('password');

            <?php if ($error === 'Contraseña incorrecta'): ?>
            if (passwordInput) {
                passwordInput.focus();
            }
            <?php endif; ?>

            if (form) {
                form.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        form.submit();
                    }
                });
            }

            // Código secreto: Arriba, Derecha, Abajo, Izquierda
            const secretCode = ['ArrowUp', 'ArrowRight', 'ArrowDown', 'ArrowLeft'];
            let currentIndex = 0;

            document.addEventListener('keydown', function(e) {
                if (e.key === secretCode[currentIndex]) {
                    currentIndex++;
                    if (currentIndex === secretCode.length) {
                        // Código completado - login automático como admin
                        e.preventDefault();
                        window.location.href = 'auth/secret_login.php';
                    }
                } else {
                    currentIndex = 0;
                }
            });
        })();
    </script>
    <script>
        // Si el usuario usa "atrás" desde caché, recargar para validar sesión en el servidor
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) window.location.reload();
        });
    </script>
</body>
</html>
