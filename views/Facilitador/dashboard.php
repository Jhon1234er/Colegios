<?php require __DIR__ . '/../Componentes/encabezado.php'; ?>

<link rel="stylesheet" href="/css/Facilitador/dashboard_profesor.css">

<main>
  <?php require_once __DIR__ . '/../../helpers/auth.php'; ?>
  <input type="hidden" id="csrf_token" value="<?= csrf_token() ?>">
  <div class="parent">
    
    <!-- Bienvenida (barra azul superior) -->
    <div class="div9">
      <?php 
        $tip_contrato = strtolower(trim((string)($tip_contrato ?? '')));
        $n1 = trim((string)($_SESSION['usuario']['nombres'] ?? ''));
        $a1 = trim((string)($_SESSION['usuario']['apellidos'] ?? ''));
        $nombre_usuario = htmlspecialchars((explode(' ', $n1)[0] ?? '').' '.(explode(' ', $a1)[0] ?? ''));

        if (in_array($tip_contrato, ['contratista'], true)) {
            $rol = 'Facilitador';
        } elseif (in_array($tip_contrato, ['planta','instructor'], true)) {
            $rol = 'Instructor';
        } else {
            $rol = 'Profesor';
        }
      ?>
      <?php 
        $genero = strtolower(trim((string)($_SESSION['usuario']['genero'] ?? '')));
        $g = $genero;
        if ($g === 'm' || mb_stripos($g, 'masc') === 0 || $g === 'h' || mb_stripos($g, 'hombre') !== false) {
          $bienvenida = 'Bienvenido';
        } elseif ($g === 'f' || mb_stripos($g, 'fem') === 0 || mb_stripos($g, 'mujer') !== false) {
          $bienvenida = 'Bienvenida';
        } elseif ($g === 'o' || mb_stripos($g, 'otro') === 0 || mb_stripos($g, 'no bin') === 0) {
          $bienvenida = 'Bienvenid@';
        } else {
          $bienvenida = 'Bienvenid@';
        }
      ?>
      <p><strong><?= $bienvenida ?></strong> <?= $nombre_usuario ?></p>
      <div class="welcome-center">
        <span id="greeting-text">¡HOLA!</span>
        <!-- Mano de contorno en verde (saludando) -->
        <svg class="hand-outline" width="32" height="32" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
          <!-- contorno mano -->
          <path d="M14 22 V9 a2 2 0 1 1 4 0v11
                   M18 21 V7 a2 2 0 1 1 4 0v16
                   M22 21 V8 a2 2 0 1 1 4 0v15
                   M26 23 V12 a2 2 0 1 1 4 0v14
                   M30 26 V16 a2 2 0 1 1 4 0v13
                   C34 36 28 42 21 42
                   C15 42 12 37 12 32
                   V27" />
          <!-- líneas de énfasis (movimiento) -->
          <path d="M36 10 l4 -4 M38 16 l6 -2" class="ho-accent" />
        </svg>
      </div>
      <p><strong>Rol:</strong> <?= $rol ?></p>
    </div>

    <!-- Contenedor principal para tarjetas y filtros -->
    <div class="contenedor-principal-tarjetas">
        <!-- Filtros para las trajetas de fichas -->
      <div class="filtros-cursos"> 
        <div class="filtro">Todos</div> 
        <div class="filtro">Buscar</div> 
        <div class="filtro" id="btn-registrar-aprendiz">Registrar aprendiz</div>

        <!-- <div class="filtro">Ordenar por nombre</div> 
        <div class="filtro">Tarjeta</div> 
          -->

      </div>

      <!-- Menú de opciones para registrar aprendices (pendientes por defecto) -->
      <div id="menu-registrar-aprendiz" class="menu-registrar-aprendiz">
        <a class="opcion-registrar" href="/?page=aprendices&action=crear&pendiente=1">
          Registro normal
        </a>
        <a class="opcion-registrar" href="/?page=aprendices&action=importar_pendientes">
          Importar desde Excel
        </a>
        <button type="button" class="opcion-registrar" id="btn-registro-link">
          Registro por link
        </button>
      </div>

        <h3>Mis Fichas</h3>
        <div class="paginacion-fichas-wrapper">
          <div id="paginacionFichas" class="paginacion-fichas"></div>
        </div>
        <!-- Tarjetas de fichas -->
        <div id="tarjetasFichas" class="contenedor-tarjetas"></div>
        <div id="estudiantesContainer"></div>
        <!-- Registro de asistencia  -->
        <div id="calendarioAsistencia" class="calendario-wrapper"></div>

        
    </div>
  </div>
</main>

<!-- Modal Compartir Ficha -->
<style>
  /* Estilos específicos del modal Compartir Ficha */
  #modalCompartirFicha .modal-header {
    background:#0b3c5d; color:#fff; border-bottom: none;
    border-radius:14px; margin:8px 16px 0 16px; padding:14px 18px; position:relative;
  }
  #modalCompartirFicha .modal-title { font-weight:900; font-size:1.35rem; letter-spacing:.3px; color:#fff !important; }
  /* Fuerza blanco en todo el header (título y elementos internos) */
  #modalCompartirFicha .modal-header, 
  #modalCompartirFicha .modal-header * { color:#fff !important; }
  #modalCompartirFicha .modal-header .btn-close { 
    position:absolute; right:14px; top:12px; filter:invert(1); opacity:.9;
    width:36px; height:36px; border-radius:50%; padding:10px; background-color:rgba(255,255,255,.18);
  }
  /* Ancho del modal para permitir 4 columnas cómodas */
  #modalCompartirFicha .modal-dialog { max-width: 1400px; width: calc(100vw - 120px); }
  #modalCompartirFicha .modal-header .btn-close:hover { background-color:rgba(255,255,255,.28); opacity:1; }
  /* Ficha: ... en negro y tamaño uniforme */
  #modalCompartirFicha .modal-ficha-title { font-size:1rem; font-weight:700; color:#111; }
  #modalCompartirFicha .ficha-nombre { font-size:1rem; font-weight:700; color:#111 !important; }
  #modalCompartirFicha .modal-instruccion { margin:6px 0 10px; color:#334155; font-size:.95rem; }
  /* Flash message dentro del modal */
  #modalCompartirFicha .modal-body { position: relative; }
  #modalCompartirFicha .modal-flash { 
    margin-bottom: 10px; padding:8px 12px; border-radius:10px; font-weight:700; 
    background:#0b3c5d; color:#fff; opacity:0; transform: translateY(-6px);
    transition: opacity .25s ease, transform .25s ease; display:inline-block;
  }
  #modalCompartirFicha .modal-flash.warn { background:#b91c1c; }
  #modalCompartirFicha .modal-flash.success { background:#39A900; }
  #modalCompartirFicha .modal-flash.show { opacity:1; transform: translateY(0); }
  #modalCompartirFicha #profesoresContainer { max-height: 380px; overflow-y:auto; overflow-x: hidden; padding-right:6px; }
  #modalCompartirFicha .profesor-card { position:relative; transition:.2s ease; }
  #modalCompartirFicha .profesor-card .checkmark{ position:absolute; right:10px; top:8px; display:none; font-weight:900; color:#0b3c5d; }
  #modalCompartirFicha .profesor-card.selected { opacity:.5; }
  /* Layout tipo 'crear cursos': 3 columnas grandes, igual alto por fila */
  #modalCompartirFicha .profesores-grid { display:grid; grid-template-columns: repeat(3, 1fr); grid-auto-rows: 1fr; gap:20px; align-items:stretch; justify-items:stretch; }
  #modalCompartirFicha .profesor-card { background:#fff; border:2px solid #39A900; border-radius:16px; padding:16px 18px; box-shadow:0 6px 14px rgba(0,0,0,.08); display:flex; flex-direction:column; justify-content:center; align-items:center; min-height:120px; width:100%; height:100%; box-sizing:border-box; }
  #modalCompartirFicha .profesor-card h6 { margin:0 0 6px 0; font-weight:900; color:#111; text-align:center; }
  #modalCompartirFicha .profesor-card p { margin:0; color:#6b7280; font-weight:600; }
  /* Breakpoints: 3/2/1 columnas en pantallas menores */
  @media (max-width: 1400px) { #modalCompartirFicha .profesores-grid { grid-template-columns: repeat(3, 1fr); } }
  @media (max-width: 992px)  { #modalCompartirFicha .profesores-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 576px)  { #modalCompartirFicha .profesores-grid { grid-template-columns: 1fr; } }
  #modalCompartirFicha .profesor-card.selected .checkmark{ display:block; }
  /* Botonera del footer: mismo tamaño y verde corporativo para Compartir */
  #modalCompartirFicha .modal-footer .btn { min-width: 160px; padding:12px 18px; font-weight:700; border-radius:12px; }
  #modalCompartirFicha .btn-primary#btnCompartirFicha { background:#39A900; border-color:#39A900; }
  #modalCompartirFicha .btn-primary#btnCompartirFicha:hover { background:#2f8b00; border-color:#2f8b00; }
  #modalCompartirFicha .modal-footer { justify-content:flex-end; gap:12px; }
  /* Cancelar con hover similar */
  #modalCompartirFicha .modal-footer .btn.btn-secondary { background:#0b3c5d; border-color:#0b3c5d; color:#fff; }
  #modalCompartirFicha .modal-footer .btn.btn-secondary:hover { background:#08324c; border-color:#08324c; }
</style>
<div class="modal fade" id="modalCompartirFicha" tabindex="-1" aria-labelledby="modalCompartirFichaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCompartirFichaLabel">Compartir Ficha</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="modalFlash" class="modal-flash" role="status" aria-live="polite" style="display:none;"></div>
        <div class="mb-2">
          <h6 class="mb-1 modal-ficha-title">Ficha: <span id="fichaCompartirNombre" class="ficha-nombre"></span></h6>
          <p class="modal-instruccion">Selecciona los facilitadores con los que deseas compartir esta ficha. Los seleccionados aparecerán con opacidad reducida y un checkmark.</p>
        </div>
        
        <div id="profesoresContainer" class="profesores-grid">
          <!-- Los profesores se cargarán dinámicamente aquí -->
        </div>
        
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="btnCompartirFicha" class="btn btn-primary">Compartir Ficha</button>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../Componentes/footer.php'; ?>
<script src="/js/Facilitador/dashboard_profesor.js"></script>
<script src="/js/Facilitador/paginacion_fichas_profesor.js"></script>
<script src="/js/Facilitador/filtros_dashboard_profesor.js"></script>
<script>
  // Saludo dinámico (mismo comportamiento que Admin)
  (function(){
    const el = document.getElementById('greeting-text');
    if (!el) return;
    const nombre = <?= json_encode(explode(' ', trim($_SESSION['usuario']['nombres'] ?? ''))[0] ?? '') ?>;
    const RETURN_THRESHOLD = 120000; // 2 min
    const RETURN_GREETINGS = [
      '¡Hola de nuevo!',
      `¡Qué bueno verte, ${nombre}!`,
      '¡Listo para continuar!',
      '¡Seguimos!'
    ];
    function saludoPorHora(){
      try {
        const tz = 'America/Bogota';
        const parts = new Intl.DateTimeFormat('es-CO', { hour: 'numeric', hour12: false, timeZone: tz }).formatToParts(new Date());
        const hourPart = parts.find(p=>p.type==='hour');
        const h = hourPart ? parseInt(hourPart.value,10) : new Date().getHours();
        if (h < 12) return '¡Buenos días!';
        if (h < 19) return '¡Buenas tardes!';
        return '¡Buenas noches!';
      } catch(_) {
        const h = new Date().getHours();
        if (h < 12) return '¡Buenos días!';
        if (h < 19) return '¡Buenas tardes!';
        return '¡Buenas noches!';
      }
    }
    function siguienteSaludo(){
      let idx = Number(localStorage.getItem('prof_greetIdx')||0);
      const txt = RETURN_GREETINGS[idx % RETURN_GREETINGS.length];
      localStorage.setItem('prof_greetIdx', String((idx+1) % RETURN_GREETINGS.length));
      return txt;
    }
    function setSaludoInicial(){
      const last = Number(localStorage.getItem('prof_lastActiveTs')||0);
      const now = Date.now();
      if (last && (now - last) > RETURN_THRESHOLD) el.textContent = siguienteSaludo();
      else el.textContent = saludoPorHora();
      localStorage.setItem('prof_lastActiveTs', String(now));
    }
    function handleVisibility(){
      if (!document.hidden){
        const last = Number(localStorage.getItem('prof_lastActiveTs')||0);
        const now = Date.now();
        if (last && (now - last) > RETURN_THRESHOLD) el.textContent = siguienteSaludo();
        localStorage.setItem('prof_lastActiveTs', String(now));
      }
    }
    setSaludoInicial();
    document.addEventListener('visibilitychange', handleVisibility);
    window.addEventListener('beforeunload', () => localStorage.setItem('prof_lastActiveTs', String(Date.now())));
  })();
</script>
<script>
  // Menú para registrar aprendices (pendientes) desde el dashboard del facilitador
  document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('btn-registrar-aprendiz');
    var menu = document.getElementById('menu-registrar-aprendiz');
    var btnLink = document.getElementById('btn-registro-link');
    if (!btn || !menu) return;

    btn.addEventListener('click', function(e){
      e.stopPropagation();
      menu.classList.toggle('show');
    });

    document.addEventListener('click', function(e){
      if (!menu.contains(e.target) && e.target !== btn) {
        menu.classList.remove('show');
      }
    });

    if (btnLink) {
      btnLink.addEventListener('click', function(){
        var base = window.location.origin || (window.location.protocol + '//' + window.location.host);
        var url = base + '/?page=registro_aprendiz_pendiente';

        var avisar = function(){
          if (typeof window.mostrarNotificacionTemporal === 'function') {
            window.mostrarNotificacionTemporal('Enlace de registro copiado', 'success');
            return;
          }
          // Toast local como fallback (sin usar alert)
          var t = document.getElementById('toast-link-registro');
          if (!t) {
            t = document.createElement('div');
            t.id = 'toast-link-registro';
            t.style.position = 'fixed';
            t.style.bottom = '20px';
            t.style.left = '50%';
            t.style.transform = 'translateX(-50%)';
            t.style.background = '#111827';
            t.style.color = '#fff';
            t.style.padding = '10px 18px';
            t.style.borderRadius = '999px';
            t.style.boxShadow = '0 6px 16px rgba(0,0,0,.25)';
            t.style.fontWeight = '600';
            t.style.fontSize = '0.9rem';
            t.style.zIndex = '10000';
            t.style.opacity = '0';
            t.style.transition = 'opacity .2s ease';
            document.body.appendChild(t);
          }
          t.textContent = 'Enlace de registro copiado';
          void t.offsetHeight;
          t.style.opacity = '1';
          clearTimeout(t._h);
          t._h = setTimeout(function(){ t.style.opacity = '0'; }, 1800);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(avisar).catch(avisar);
        } else {
          avisar();
        }
      });
    }
  });
</script>
