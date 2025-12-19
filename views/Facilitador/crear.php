<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
<link rel="stylesheet" href="/css/Facilitador/crear.css">

<div class="profesor-crear">
  <h2 class="form-title">Registro de Facilitador</h2>
  <?php
    $isPublic = (($_GET['page'] ?? '') === 'registro_profesor');
    $formAction = $isPublic ? '/?page=registro_profesor_guardar' : '/?page=facilitadores&action=guardar';
  ?>
  <form action="<?= htmlspecialchars($formAction) ?>" method="POST" id="registroProfesorForm">
      <?= csrf_input(); ?>
    <div class="form-grid">
      <!-- Columna izquierda (orden solicitado) -->
      <div class="columna">
        <label>Nombres</label>
        <input type="text" name="nombres" class="form-nombre" required>

        <label>Tipo de Documento</label>
        <select name="tipo_documento" class="form-tipo" required>
          <option value="">Seleccione...</option>
          <option value="CC">Cédula de Ciudadanía</option>
          <option value="CE">Cédula de Extranjería</option>
        </select>

        <div>
        <label for="fecha_nacimiento">Fecha de nacimiento</label>
        <div class="date-wrap">
          <input id="fecha_nacimiento" name="fecha_nacimiento" type="text" placeholder="dd/mm/aaaa" />
          <svg class="date-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3" y="5" width="18" height="16" rx="4" ry="4" fill="none" stroke="currentColor" stroke-width="2"/>
            <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
            <line x1="8" y1="3" x2="8" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <line x1="16" y1="3" x2="16" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

        <label>Correo Electrónico</label>
        <input type="email" name="correo_electronico" class="form-personal" required>

        <label>Celular</label>
        <input type="text" name="celular" class="form-numero" required>

        <label>Ciudad / Municipio</label>
        <input type="text" name="municipio" class="form-personal" placeholder="Ej: Cundinamarca">

        <label>RH</label>
        <select name="rh" class="form-rh" required>
          <option value="">Seleccione...</option>
          <option>A+</option><option>A-</option><option>B+</option><option>B-</option>
          <option>O+</option><option>O-</option><option>AB+</option><option>AB-</option>
        </select>

        <label>Estrato</label>
        <select name="estrato" class="form-select-contrato">
          <option value="">Seleccione...</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
          <option value="5">5</option>
          <option value="6">6</option>
        </select>

        <label>Título Académico</label>
        <input type="text" name="titulo_academico" class="form-control-titulo" placeholder="Opcional">
      </div>

      <!-- Columna derecha (orden solicitado) -->
      <div class="columna">
        <label>Apellidos</label>
        <input type="text" name="apellidos" class="form-apellido" required>

        <label>Número de Documento</label>
        <input type="text" name="numero_documento" class="form-documento" required>

        <label>Género</label>
        <select name="genero" id="genero_prof" class="form-tipo" required>
          <option value="">Seleccione...</option>
          <option value="M">Masculino</option>
          <option value="F">Femenino</option>
          <option value="Otro">Otro</option>
        </select>
        <input type="text" name="genero_otro" id="genero_otro_prof" placeholder="Especifique" style="display:none;">
        <div id="genero_otro_spacer" style="display:none; height:56px;"></div>

        <label>Correo Electrónico Institucional</label>
        <input type="email" name="correo_institucional" class="form-institucional" required>

        <label>Dirección</label>
        <input type="text" name="direccion" class="form-personal">

        <label>Barrio</label>
        <input type="text" name="barrio" class="form-personal">

        <label>EPS</label>
        <select name="eps" id="eps_prof" class="form-select-contrato">
          <option value="">Seleccione...</option>
          <option>Aliansalud EPS</option>
          <option>Salud Total EPS S.A</option>
          <option>EPS Sanitas</option>
          <option>Eps Sura</option>
          <option>Famisanar</option>
          <option>Servicio Occidental de Salud EPS - SOS</option>
          <option>Compensar EPS</option>
          <option value="Otro">Otro</option>
        </select>
        <input type="text" name="eps_otro" id="eps_otro_prof" placeholder="Especifique" 
        style="display:none;">

        <label>Especialidad</label>
        <input type="text" name="especialidad" class="form-control-especialidad" placeholder="Opcional">

        <label>Tipo de Contrato</label>
        <select name="tip_contrato" class="form-select-contrato" required>
          <option value="">Seleccione...</option>
          <option value="planta">Planta</option>
          <option value="contratista">Contratista</option>
        </select>

      </div>
      
    </div>
    <!-- Contraseña automática desde el número de documento -->
    <input type="hidden" name="password" id="password_hidden_prof">
<button type="submit" class="btn-registrar">Registrar Profesor</button>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="/js/Facilitador/crearP.js?v=2"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  // Password auto = numero_documento
  const form = document.getElementById('registroProfesorForm');
  const doc = document.querySelector('input[name="numero_documento"]');
  const pass = document.getElementById('password_hidden_prof');
  if (form && doc && pass) {
    const sync = () => { pass.value = (doc.value||'').trim(); };
    doc.addEventListener('input', sync);
    form.addEventListener('submit', sync);
  }
  // Genero otro toggle
  const sel = document.getElementById('genero_prof');
  const otro = document.getElementById('genero_otro_prof');
  const spacer = document.getElementById('genero_otro_spacer');
  function toggleOtro(){
    const v = (sel && sel.value ? sel.value : '').toUpperCase();
    if (v === 'OTRO' || v === 'O') {
      if (otro) { otro.style.display=''; otro.required=true; }
      if (spacer) { spacer.style.display=''; }
    } else {
      if (otro) { otro.style.display='none'; otro.required=false; otro.value=''; }
      if (spacer) { spacer.style.display='none'; }
    }
  }
  if (sel && otro) { toggleOtro(); sel.addEventListener('change', toggleOtro); }

  // EPS otro toggle
  const epsSel = document.getElementById('eps_prof');
  const epsOtro = document.getElementById('eps_otro_prof');
  function toggleEpsOtro(){
    const v = (epsSel && epsSel.value ? epsSel.value : '').toUpperCase();
    if (v === 'OTRO') {
      if (epsOtro) { epsOtro.style.display=''; epsOtro.required=true; }
    } else {
      if (epsOtro) { epsOtro.style.display='none'; epsOtro.required=false; epsOtro.value=''; }
    }
  }
  if (epsSel && epsOtro) { toggleEpsOtro(); epsSel.addEventListener('change', toggleEpsOtro); }
});
</script>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
