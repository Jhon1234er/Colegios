<?php require __DIR__ . '/../Componentes/encabezado.php'; ?>

<link rel="stylesheet" href="/css/Profesor/dashboard_profesor.css">

<main>
  <?php require_once __DIR__ . '/../../helpers/auth.php'; ?>
  <input type="hidden" id="csrf_token" value="<?= csrf_token() ?>">
  <div class="parent">
    
    <!-- Bienvenida (barra azul superior) -->
    <div class="div9">
      <?php 
        $tip_contrato = strtolower($tip_contrato ?? '');
        $nombre_usuario = htmlspecialchars($_SESSION['usuario']['nombres'] . ' ' . ($_SESSION['usuario']['apellidos'] ?? ''));

        if ($tip_contrato === 'contratista') {
            $rol = 'Facilitador';
        } elseif ($tip_contrato === 'instructor') {
            $rol = 'Instructor';
        } else {
            $rol = 'Profesor';
        }
      ?>
      <?php 
        $genero = strtolower(trim((string)($_SESSION['usuario']['genero'] ?? '')));
        $bienvenida = (in_array($genero, ['f','femenino','mujer'], true)) ? 'Bienvenida' : 'Bienvenido';
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
        <div class="filtro">Ordenar por nombre</div> 
        <div class="filtro">Tarjeta</div> 
      </div>
        <h3>Mis Fichas</h3>
          <!-- Tarjetas de fichas -->
        <div id="tarjetasFichas" class="contenedor-tarjetas"></div>
        <div id="estudiantesContainer"></div>
        <!-- Registro de asistencia  -->
        <div id="calendarioAsistencia" class="calendario-wrapper"></div>

        
    </div>
  </div>
</main>

<!-- Modal Compartir Ficha -->
<div class="modal fade" id="modalCompartirFicha" tabindex="-1" aria-labelledby="modalCompartirFichaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCompartirFichaLabel">Compartir Ficha</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <h6>Ficha: <span id="fichaCompartirNombre"></span></h6>
          <p class="text-muted">Selecciona los profesores con los que deseas compartir esta ficha:</p>
        </div>
        
        <div id="profesoresContainer" class="profesores-grid">
          <!-- Los profesores se cargarán dinámicamente aquí -->
        </div>
        
        <div class="mt-3">
          <small class="text-muted">Los profesores seleccionados aparecerán con opacidad reducida y un checkmark.</small>
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
<script src="/js/Profesor/dashboard_profesor.js"></script>
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
