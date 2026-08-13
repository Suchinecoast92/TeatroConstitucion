<?php
session_start();

// --- 🎲 DICCIONARIO DE TEMAS Y PALABRAS ---
$palabras = [
     // 🎭 PERSONAJES Y MEMES VIRALES
"Alfredo Adame", "Medio Metro", "Lupita TikTok", "Cachetes Hijo de Perra", "Karely Ruiz", "Cepillín",

// 🏫 PROFES / PERSONAJES DE ESCUELA
"Profe Héctor", "Profe Hugo", "Fidel Barda", "Profe Memo",
"Luis Miranda", "Eliseo", "Profe Homero",

// 🎌 ANIME Y MANGA
"Goku", "Vegeta", "Naruto", "Pikachu",

// 🇲🇽 COSAS MEXICANAS
"Tacos al Pastor", "El Chavo del 8", "Don Ramón", "Chabelo",
"Oxxo", "Elote con Chile", "Guacamole", "Día de Muertos",
"Mariachi", "Mazapán", "La Llorona", "El Fua",
"Niño del Oxxo", "La Rosa de Guadalupe"
];

// --- 1. CONFIGURACIÓN INICIAL (Crear Partida) ---
if (isset($_POST["iniciar_juego"])) {
    $numJugadores = intval($_POST["num_jugadores"]);
    if ($numJugadores < 3) $numJugadores = 3; 

    // ELEGIR TEMA Y IMPOSTOR RANDOM
    $palabraAleatoria = $palabras[array_rand($palabras)];
    $impostorAleatorio = rand(1, $numJugadores); 

    $_SESSION["ronda"] = [
        "total" => $numJugadores,
        "palabra" => $palabraAleatoria,
        "impostor" => $impostorAleatorio,
        "turno_actual" => 1,
        "mostrar_resultado" => false 
    ];
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- 2. RESETEAR JUEGO (REINICIAR A 0) ---
if (isset($_POST["reset"])) {
    unset($_SESSION["ronda"]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- 3. REVELAR LA VERDAD ---
if (isset($_POST["revelar_verdad"])) {
    $_SESSION["ronda"]["mostrar_resultado"] = true;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- 4. LÓGICA DEL TURNO ---
$mostrarOverlay = false;
$mensajeOverlay = "";
$esImpostor = false;
$segundosMostrar = 3; 
$datos = isset($_SESSION["ronda"]) ? $_SESSION["ronda"] : null;

if (isset($_POST["ver_palabra"]) && $datos) {
    $turnoJugador = $datos["turno_actual"];
    
    if ($turnoJugador == $datos["impostor"]) {
        $mensajeOverlay = "IMPOSTOR";
        $esImpostor = true;
    } else {
        $mensajeOverlay = $datos["palabra"];
    }
    
    $mostrarOverlay = true;
    $_SESSION["ronda"]["turno_actual"]++;
}

// ¿Ya pasaron todos?
$todosPasaron = ($datos && $_SESSION["ronda"]["turno_actual"] > $datos["total"]);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Juego del Impostor</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; text-align: center; background-color: #2c3e50; margin: 0; padding: 20px; color: white; }
        .container { max-width: 600px; margin: 40px auto; background: white; color: #333; padding: 40px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        
        h1 { color: #2c3e50; margin-bottom: 5px; }
        .instruccion { font-size: 24px; color: #7f8c8d; margin-bottom: 30px; display: block; }

        input[type="number"] { font-size: 22px; padding: 10px; width: 100px; text-align: center; border-radius: 8px; border: 2px solid #bdc3c7; margin: 20px 0; }
        
        .btn { color: white; width: 100%; font-size: 22px; padding: 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: bold; transition: transform 0.1s; display: block; text-decoration: none; }
        .btn:active { transform: scale(0.98); }
        
        .btn-start { background-color: #27ae60; }
        .btn-ver { background-color: #2980b9; box-shadow: 0 4px 0 #1c5980; }
        .btn-revelar { background-color: #8e44ad; box-shadow: 0 4px 0 #6c3483; }
        
        /* Botón de reinicio final */
        .btn-reset-final { background-color: #e74c3c; margin-top: 20px; font-size: 18px; padding: 15px; }
        
        /* Botón de abortar partida (pequeño) */
        .btn-abort { 
            background-color: transparent; 
            border: 2px solid #e74c3c; 
            color: #e74c3c; 
            font-size: 16px; 
            padding: 10px 20px; 
            border-radius: 8px; 
            cursor: pointer; 
            margin-top: 40px;
            font-weight: bold;
        }
        .btn-abort:hover { background-color: #e74c3c; color: white; }

        .resultado-box { background: #f1c40f; padding: 30px; border-radius: 15px; margin-top: 20px; color: #2c3e50; animation: fadeIn 1s; }
        .espera-box { background: #ecf0f1; padding: 30px; border-radius: 15px; border: 2px dashed #95a5a6; animation: fadeIn 1s; }

        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.98); display: flex; flex-direction: column; justify-content: center; align-items: center; z-index: 1000; }
        .card-word { background: white; padding: 50px; border-radius: 20px; text-align: center; min-width: 320px; animation: zoomIn 0.3s; }
        .palabra-gigante { font-size: 40px; font-weight: 900; color: #2c3e50; display: block; margin: 30px 0; line-height: 1.1; word-wrap: break-word;}
        .impostor-style .palabra-gigante { color: #c0392b; letter-spacing: 2px; } 
        .contador { width: 80px; height: 80px; background: #3498db; color: white; font-size: 40px; font-weight: bold; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto; }

        @keyframes zoomIn { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>

<div class="container">

    <?php if (!$datos): ?>
        <h1>⚙️ Nuevo Juego</h1>
        <p>¿Cuántas personas van a jugar?</p>
        <form method="POST">
            <input type="number" name="num_jugadores" value="6" min="3" max="50" required>
            <br>
            <button type="submit" name="iniciar_juego" class="btn btn-start">✅ Comenzar</button>
        </form>

    <?php elseif (!$todosPasaron): ?>
        <h1 style="font-size: 40px;">Jugador <?= $datos["turno_actual"] ?></h1>
        <span class="instruccion">Toma la computadora</span>
        
        <form method="POST">
            <button type="submit" name="ver_palabra" class="btn btn-ver">👁️ Ver mi palabra secreta</button>
        </form>
        
        <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
            <p style="font-size: 14px; color: #aaa;">Turno <?= $datos["turno_actual"] ?> de <?= $datos["total"] ?></p>
        </div>

    <?php elseif (!$datos["mostrar_resultado"]): ?>
        <h1>🗳️ ¡A Votar!</h1>
        <div class="espera-box">
            <p>Debatan: ¿Quién miente?</p>
            <p style="font-size: 60px; margin: 20px 0;">🕵️‍♂️ ❓</p>
            <p style="font-size:14px; color:#999;">Cuando estén listos:</p>
        </div>
        <br>
        <form method="POST">
            <button type="submit" name="revelar_verdad" class="btn btn-revelar">🔓 REVELAR LA VERDAD</button>
        </form>

    <?php else: ?>
        <h1>🏁 La Verdad</h1>
        <div class="resultado-box">
            <p>El tema secreto era:</p>
            <h2 style="font-size:30px; text-transform:uppercase;"><?= $datos["palabra"] ?></h2>
            <hr style="opacity:0.3;">
            <p>El Impostor era el:</p>
            <h2 style="color:#c0392b; font-size: 40px;">JUGADOR <?= $datos["impostor"] ?> 😈</h2>
        </div>
        <form method="POST">
            <button type="submit" name="reset" class="btn btn-reset-final">🔄 Iniciar Nueva Partida</button>
        </form>
    <?php endif; ?>

    <?php if ($datos): ?>
        <hr style="margin-top: 40px; opacity: 0.2;">
        <form method="POST">
            <button type="submit" name="reset" class="btn-abort" onclick="return confirm('¿Seguro que quieres cancelar la partida actual y volver al inicio?');">⚠️ Cancelar y Reiniciar a 0</button>
        </form>
    <?php endif; ?>

</div>

<?php if ($mostrarOverlay): ?>
    <div class="overlay">
        <div class="card-word <?= $esImpostor ? 'impostor-style' : '' ?>">
            <h2 style="margin:0; color:#7f8c8d;">TU PALABRA ES:</h2>
            <span class="palabra-gigante"><?=$mensajeOverlay?></span>
            <div class="contador" id="contador"><?=$segundosMostrar?></div>
        </div>
    </div>
    <script>
        let tiempo = <?=$segundosMostrar?>;
        const display = document.getElementById('contador');
        const cuenta = setInterval(() => {
            tiempo--;
            display.textContent = tiempo;
            if (tiempo <= 0) {
                clearInterval(cuenta);
                window.location.href = window.location.pathname; 
            }
        }, 1000);
    </script>
<?php endif; ?>

</body>
</html>