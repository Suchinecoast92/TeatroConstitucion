<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include "../conexion.php";
require_once __DIR__ . '/../config/ventas.php';
if(file_exists("../transacciones_helper.php")) { require_once "../transacciones_helper.php"; }

// Verificar permisos
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="display:flex;height:100vh;align-items:center;justify-content:center;font-family:sans-serif;color:#ef4444;"><h1>Acceso Denegado</h1></div>');
}

$errores_php = [];

// ==================================================================
// 2. PROCESAR FORMULARIO (POST)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $titulo = trim($_POST['titulo']);
    $desc = trim($_POST['descripcion']);
    $tipo = $_POST['tipo'];
    $ini = $_POST['inicio_venta'];
    $fin = $_POST['cierre_venta']; // Recibido del cálculo automático del front
    
    // Validaciones básicas
    if (empty($titulo)) $errores_php[] = "Falta el título.";
    if (empty($_POST['funciones'])) $errores_php[] = "Debe agregar al menos una función.";
    if (empty($ini)) $errores_php[] = "Falta inicio de venta.";

    // Imagen (Obligatoria al crear)
    $imagen_ruta = "";
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
         $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
         if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
             if (!is_dir("imagenes")) mkdir("imagenes", 0755, true);
             $ruta = "imagenes/evt_" . time() . "." . $ext;
             if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
                 $imagen_ruta = $ruta;
             } else $errores_php[] = "Error al guardar imagen.";
         } else $errores_php[] = "Formato de imagen no válido.";
    } else {
        $errores_php[] = "La imagen es obligatoria.";
    }

    if (empty($errores_php)) {
        $conn->begin_transaction();
        try {
            // 1. Insertar Evento
            $stmt = $conn->prepare("INSERT INTO evento (titulo, descripcion, imagen, tipo, inicio_venta, cierre_venta, finalizado) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("sssisss", $titulo, $desc, $imagen_ruta, $tipo, $ini, $fin);
            $stmt->execute();
            $id_nuevo = $conn->insert_id;
            $stmt->close();

            // 2. Insertar Funciones (Estado 0 = Activa)
            $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora, estado) VALUES (?, ?, 0)");
            foreach ($_POST['funciones'] as $fh) {
                $stmt_f->bind_param("is", $id_nuevo, $fh);
                $stmt_f->execute();
            }
            $stmt_f->close();

            // 3. Insertar Categorías por defecto
            $stmt_c = $conn->prepare("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)");
            // General
            $nom = 'General'; $prec = 80; $col = '#808080';
            $stmt_c->bind_param("isds", $id_nuevo, $nom, $prec, $col);
            $stmt_c->execute();
            $id_cat_gen = $conn->insert_id;
            // Discapacitado
            $nom = 'Discapacitado'; $prec = 80; $col = '#2563eb';
            $stmt_c->bind_param("isds", $id_nuevo, $nom, $prec, $col);
            $stmt_c->execute();
            $stmt_c->close();

            // 4. Generar Mapa de Asientos
            $mapa = [];
            if ($tipo == 2) { // Pasarela
                for ($f=1; $f<=10; $f++) {
                    for ($a=1; $a<=12; $a++) $mapa["PB$f-$a"] = $id_cat_gen;
                }
            }
            // Teatro (A-O)
            foreach (range('A','O') as $l) {
                for ($a=1; $a<=26; $a++) $mapa["$l$a"] = $id_cat_gen;
            }
            // Palco
            for ($a=1; $a<=30; $a++) $mapa["P$a"] = $id_cat_gen;

            $json = json_encode($mapa);
            $conn->query("UPDATE evento SET mapa_json = '$json' WHERE id_evento = $id_nuevo");

            $conn->commit();
            
            if(function_exists('registrar_transaccion')) registrar_transaccion('evento_crear', "Creó evento: $titulo");

            // ==================================================================
            // PANTALLA DE ÉXITO CON ANIMACIÓN (Igual a Editar)
            // ==================================================================
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
            <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
            <style>
                body { background-color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: system-ui, sans-serif; overflow: hidden; }
                .success-card { background: white; border-radius: 24px; padding: 40px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.08); max-width: 380px; width: 90%; opacity: 0; transform: scale(0.9); animation: popIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
                .icon-circle { width: 80px; height: 80px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 20px; box-shadow: 0 0 0 8px #f0fdf4; }
                .progress-track { height: 6px; background: #f1f5f9; border-radius: 3px; margin-top: 30px; overflow: hidden; }
                .progress-fill { height: 100%; background: #16a34a; width: 0; border-radius: 3px; transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1); }
                @keyframes popIn { to { opacity: 1; transform: scale(1); } }
                @keyframes fadeOut { to { opacity: 0; transform: translateY(10px); } }
                body.exiting { animation: fadeOut 0.4s ease-in forwards; }
            </style>
            </head>
            <body>
                <div class="success-card">
                    <div class="icon-circle"><i class="bi bi-check-lg"></i></div>
                    <h4 class="fw-bold text-dark mb-2">¡Evento Creado!</h4>
                    <p class="text-muted small mb-0">El evento ya está disponible en cartelera.</p>
                    <div class="progress-track"><div class="progress-fill" id="pBar"></div></div>
                    <p class="text-muted mt-2" style="font-size: 0.75rem; font-weight: 600;">VOLVIENDO A ACTIVOS...</p>
                </div>
                <script>
                    setTimeout(() => document.getElementById('pBar').style.width = '100%', 100);
                    localStorage.setItem("evt_upd", Date.now());
                    setTimeout(() => { 
                        document.body.classList.add('exiting'); 
                        setTimeout(() => { window.location.href = "act_evento.php"; }, 350); 
                    }, 1300); 
                </script>
            </body>
            </html>
            <?php exit;

        } catch (Exception $e) { $conn->rollback(); $errores_php[] = "Error DB: " . $e->getMessage(); }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear Evento</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    :root { --primary-color: #2563eb; --primary-dark: #1e40af; --success-color: #10b981; --danger-color: #ef4444; --bg-primary: #f8fafc; --bg-secondary: #ffffff; --text-primary: #0f172a; --border-color: #e2e8f0; --radius-lg: 16px; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, var(--bg-primary), #e2e8f0); color: var(--text-primary); padding: 30px 20px; min-height: 100vh; opacity: 0; transition: opacity 0.4s ease; }
    body.loaded { opacity: 1; }
    body.exiting { opacity: 0; }

    .main-wrapper { max-width: 850px; margin: 0 auto; }
    .card { background: white; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); padding: 40px; border: 1px solid var(--border-color); }
    
    .form-control, .form-select { border-radius: 8px; padding: 12px; border: 1px solid var(--border-color); background: #fff; }
    .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(37,99,235,0.1); }
    
    .btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
    .btn-primary { background: var(--primary-color); color: white; } .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
    .btn-secondary { background: #fff; color: var(--text-primary); border: 1px solid var(--border-color); } .btn-secondary:hover { background: #f1f5f9; }
    .btn-danger { background: #ef4444; color: white; } .btn-danger:hover { background: #dc2626; }

    .input-error { border-color: #ef4444 !important; background: #fef2f2 !important; }
    .tooltip-error { color: #ef4444; font-size: 0.85em; margin-top: 5px; display: none; font-weight: 600; }
    
    #lista-funciones { background: #f8fafc; border: 2px dashed var(--border-color); border-radius: 8px; padding: 15px; min-height: 70px; display: flex; flex-wrap: wrap; gap: 10px; }
    .funcion-item { background: white; padding: 6px 12px; border-radius: 20px; border: 1px solid var(--primary-color); color: var(--primary-color); font-weight: 600; display: flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .funcion-item button { background: none; border: none; color: #ef4444; font-size: 1.1em; padding: 0; cursor: pointer; line-height: 1; }
    .funcion-item button:hover { transform: scale(1.2); }
</style>
</head>
<body>

<div class="main-wrapper">
    <div class="mb-4">
        <button onclick="confirmarSalida()" class="btn btn-secondary shadow-sm"> 
            <i class="bi bi-arrow-left"></i> Volver a Eventos
        </button>
    </div>

    <div class="card">
        <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
            <h2 class="m-0 text-primary">
                <i class="bi bi-plus-circle-fill me-3"></i>Crear Nuevo Evento
            </h2>
        </div>

        <?php if($errores_php): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4">
                <ul class="m-0 ps-3"><?php foreach($errores_php as $e) echo "<li>$e</li>"; ?></ul>
            </div>
        <?php endif; ?>

        <form id="fCreate" method="POST" enctype="multipart/form-data">
            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label fw-bold">Título del Evento</label>
                    <input type="text" id="tit" name="titulo" class="form-control form-control-lg fw-bold" required>
                </div>

                <div class="col-12">
                    <div class="p-4 bg-light rounded-4 border">
                        <label class="fw-bold mb-3"><i class="bi bi-calendar-week me-2"></i>Gestión de Funciones</label>
                        <div class="input-group mb-3 shadow-sm">
                            <input type="text" id="fDate" class="form-control" placeholder="Selecciona Fecha" readonly>
                            <input type="text" id="fTime" class="form-control" placeholder="Hora" readonly style="max-width:130px">
                            <button type="button" id="fAdd" class="btn btn-success" disabled><i class="bi bi-plus-lg"></i> Agregar</button>
                        </div>
                        <div id="ttFunc" class="tooltip-error mb-2"></div>
                        <div id="lista-funciones">
                            <p id="noFunc" class="text-muted m-0 w-100 text-center fst-italic small">
                                <i class="bi bi-inbox me-2"></i>No hay funciones asignadas.
                            </p>
                        </div>
                        <div id="hidFunc"></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="fw-bold">Inicio Venta</label>
                    <input type="text" id="ini" name="inicio_venta" class="form-control" readonly required placeholder="Selecciona fecha">
                    <div id="ttIni" class="tooltip-error"></div>
                </div>
                
                <div class="col-md-6">
                    <label class="fw-bold text-muted">Cierre Venta (Automático)</label>
                    <input type="text" id="fin" name="cierre_venta" class="form-control" readonly required style="background-color: #e9ecef; cursor: not-allowed;">
                    <div class="form-text small"><i class="bi bi-info-circle"></i> Se calcula 24 horas después de la última función.</div>
                </div>
                
                <div class="col-12">
                    <label class="fw-bold">Descripción</label>
                    <textarea id="desc" name="descripcion" class="form-control" rows="4" required></textarea>
                    <div id="ttDesc" class="tooltip-error"></div>
                </div>
                
                <div class="col-md-7">
                    <label class="fw-bold">Imagen Promocional</label>
                    <input type="file" id="img" name="imagen" class="form-control" accept="image/*" required>
                    <div id="ttImg" class="tooltip-error"></div>
                </div>
                
                <div class="col-md-5">
                    <label class="fw-bold">Tipo de Escenario</label>
                    <select id="tipo" name="tipo" class="form-select" required>
                        <option value="">-- Selecciona --</option>
                        <option value="1">🎭 Teatro (420 Butacas)</option>
                        <option value="2">🚶 Pasarela (540 Butacas)</option>
                    </select>
                    <div id="ttTipo" class="tooltip-error"></div>
                </div>
            </div>

            <hr class="my-5">
            <button type="submit" id="bSub" class="btn btn-primary w-100 py-3 fs-5 fw-bold shadow-sm" disabled>
                <i class="bi bi-check2-circle me-2"></i> Crear Evento
            </button>
        </form>
    </div>
</div>

<div class="modal fade" id="modalCancelar" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">¿Salir sin guardar?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted">Si sales ahora, perderás la información del nuevo evento.</p>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Seguir Creando</button>
        <button type="button" class="btn btn-danger fw-bold px-4" onclick="goBack()">Salir</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script><script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('loaded');
    flatpickr.localize(flatpickr.l10ns.es);
    const now = new Date();
    let funcs = [];

    const els={add:document.getElementById('fAdd'),sub:document.getElementById('bSub'),list:document.getElementById('lista-funciones'),hid:document.getElementById('hidFunc'),no:document.getElementById('noFunc'),ttF:document.getElementById('ttFunc'),ttI:document.getElementById('ttIni'),ini:document.getElementById('ini'),fin:document.getElementById('fin'),desc:document.getElementById('desc'),img:document.getElementById('img'),tipo:document.getElementById('tipo'),ttDesc:document.getElementById('ttDesc'),ttImg:document.getElementById('ttImg'),ttTipo:document.getElementById('ttTipo')};
    
    const fpD=flatpickr("#fDate",{minDate:"today",onChange:(s,d)=>{
        if(d===new Date().toISOString().split('T')[0]) fpT.set('minTime',new Date().setMinutes(new Date().getMinutes()+5));
        else fpT.set('minTime',null);
        check();
    }});
    const fpT=flatpickr("#fTime",{enableTime:true,noCalendar:true,dateFormat:"H:i",time_24hr:true,minuteIncrement:15,onChange:check});
    const fpI=flatpickr("#ini",{enableTime:true,minDate:now,onChange:val});
    const fpE=flatpickr("#fin",{enableTime:true, clickOpens:false}); // Bloqueado

    function check(){ els.add.disabled=!(fpD.selectedDates.length && fpT.selectedDates.length); }

    els.add.onclick=()=>{
        if (!fpD.selectedDates[0] || !fpT.selectedDates[0]) return;
        
        let dt = new Date(fpD.selectedDates[0].getTime());
        dt.setHours(fpT.selectedDates[0].getHours());
        dt.setMinutes(fpT.selectedDates[0].getMinutes());
        dt.setSeconds(0);

        if(dt <= new Date(Date.now() + 60000)) return alert("La función debe ser futura.");
        if(funcs.some(d=>d.getTime()===dt.getTime())) return alert("Ya existe esta función.");
        
        funcs.push(dt); funcs.sort((a,b)=>a-b); fpD.clear(); fpT.clear(); check(); upd();
    };

    function upd(){
        els.list.innerHTML=''; els.hid.innerHTML='';
        if(!funcs.length){ 
            els.list.appendChild(els.no); 
            fpI.set('maxDate',null); 
            fpE.setDate(null); 
        } else {
            funcs.forEach((d,i)=>{
                const fechaStr = d.toLocaleDateString('es-ES', {day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit'});
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                const hours = String(d.getHours()).padStart(2, '0');
                const mins = String(d.getMinutes()).padStart(2, '0');
                const sqlDate = `${year}-${month}-${day} ${hours}:${mins}:00`;
                
                els.list.innerHTML+=`<div class="funcion-item"><i class="bi bi-calendar-event"></i> ${fechaStr}<button type="button" onclick="del(${i})" class="btn-close ms-2" style="font-size:0.6em"></button></div>`;
                els.hid.innerHTML+=`<input type="hidden" name="funciones[]" value="${sqlDate}">`;
            });
            
            fpI.set('maxDate', new Date(funcs[0].getTime() - 60000));
            
            const ultima = funcs[funcs.length-1];
            const cierre = new Date(ultima.getTime() + <?= (int) MS_CIERRE_VENTAS_POST_FUNCION ?>);
            fpE.setDate(cierre, true);
        }
        val();
    }
    window.del=i=>{funcs.splice(i,1);upd();};

    function val(){
        let ok=true; 
        [els.ttF, els.ttI, els.ttDesc, els.ttImg, els.ttTipo].forEach(e=>{ if(e) e.style.display='none'; });
        document.querySelectorAll('.input-error').forEach(e=>e.classList.remove('input-error'));
        
        if(!document.getElementById('tit').value.trim()) ok=false;
        if(!funcs.length){ err(els.ttF,null,'Añade funciones.'); ok=false; }
        
        if(!fpI.selectedDates.length){ 
             if (funcs.length) { err(els.ttI,els.ini,'Requerido.'); ok=false; }
        } else if(funcs.length && fpI.selectedDates[0] >= funcs[0]){ 
             err(els.ttI,els.ini,'Inicio venta posterior a 1ª función.'); ok=false; 
        }

        if(!els.desc.value.trim()){ err(els.ttDesc,els.desc,'Descripción obligatoria.'); ok=false; }
        if(!els.img.files.length){ err(els.ttImg,els.img,'Imagen obligatoria.'); ok=false; }
        if(!els.tipo.value){ err(els.ttTipo,els.tipo,'Selecciona escenario.'); ok=false; }
        
        els.sub.disabled = !ok;
        return ok;
    }

    function err(t,i,m){ t.textContent=m; t.style.display='flex'; if(i) i.classList.add('input-error'); }
    
    ['tit','desc','img','tipo'].forEach(id=>document.getElementById(id).addEventListener(id==='img'||id==='tipo'?'change':'input',val));
    document.getElementById('fCreate').addEventListener('submit',e=>{ if(!val()){ e.preventDefault(); alert("Faltan campos."); } });
});

const modalCancel = new bootstrap.Modal(document.getElementById('modalCancelar'));
function confirmarSalida() {
    modalCancel.show();
}

function goBack() {
    document.body.classList.remove('loaded');
    document.body.classList.add('exiting');
    setTimeout(() => window.location.href = 'act_evento.php', 350);
}
</script>
</body>
</html>