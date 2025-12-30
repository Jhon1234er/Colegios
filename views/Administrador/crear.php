<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
?>

<link rel="stylesheet" href="/css/Facilitador/crear.css">
<link rel="stylesheet" href="/css/Administrador/crear.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<div class="profesor-crear">
  <h2 class="form-title">Crear Administrador</h2>

  <form method="POST" action="/?page=registro">
    <?= csrf_input(); ?>

    <div class="form-grid">
      <div class="columna">
        <label for="nombres">Nombres*</label>
        <input type="text" name="nombres" id="nombres" required>

        <label for="tipo_documento">Tipo de documento</label>
        <select name="tipo_documento" id="tipo_documento" class="js-choice" required>
          <option value="" disabled selected>Seleccionar</option>
          <option value="CC">Cédula de Ciudadanía</option>
          <option value="CE">Cédula de Extranjería</option>
        </select>

        <label for="correo_electronico">Correo electrónico personal*</label>
        <input type="email" name="correo_electronico" id="correo_electronico" required>

        <label for="telefono">Teléfono</label>
        <input type="text" name="telefono" id="telefono">

        <label for="municipio">Ciudad / Municipio</label>
        <input type="text" name="municipio" id="municipio" placeholder="Ej: Cundinamarca">

        <label for="direccion">Dirección</label>
        <input type="text" name="direccion" id="direccion">

        <label for="barrio">Barrio</label>
        <input type="text" name="barrio" id="barrio">

        <label for="genero">Género</label>
        <select name="genero" id="genero" class="js-choice" required>
          <option value="" disabled selected>Seleccionar</option>
          <option value="M">Masculino</option>
          <option value="F">Femenino</option>
          <option value="Otro">Otro</option>
        </select>
        <input type="text" name="genero_otro" id="genero_otro" placeholder="Especifique" style="display:none;">
      </div>

      <div class="columna">
        <label for="apellidos">Apellidos*</label>
        <input type="text" name="apellidos" id="apellidos" required>

        <label for="numero_documento">Número de documento*</label>
        <input type="text" name="numero_documento" id="numero_documento" required>

        <label for="correo_institucional">Correo institucional</label>
        <input type="email" name="correo_institucional" id="correo_institucional">

        <label for="fecha_nacimiento">Fecha de nacimiento*</label>
        <div class="date-wrap">
          <input type="text" name="fecha_nacimiento" id="fecha_nacimiento" placeholder="dd/mm/aaaa" required>
          <svg class="date-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3" y="5" width="18" height="16" rx="4" ry="4" fill="none" stroke="currentColor" stroke-width="2"/>
            <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
            <line x1="8" y1="3" x2="8" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <line x1="16" y1="3" x2="16" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>

        <label for="eps">EPS</label>
        <select name="eps" id="eps" class="js-choice">
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
        <input type="text" name="eps_otro" id="eps_otro" placeholder="Especifique EPS" style="display:none;">

        <label for="rh">RH</label>
        <select name="rh" id="rh" class="js-choice">
          <option value="">Seleccione...</option>
          <option>A+</option><option>A-</option><option>B+</option><option>B-</option>
          <option>O+</option><option>O-</option><option>AB+</option><option>AB-</option>
        </select>

        <label for="estrato">Estrato</label>
        <select name="estrato" id="estrato" class="js-choice">
          <option value="">Seleccione...</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
          <option value="5">5</option>
          <option value="6">6</option>
        </select>

        <!-- Password automático = número de documento (oculto) -->
        <input type="hidden" name="password" id="password_hidden_admin">
      </div>
    </div>

    <input type="hidden" name="rol_id" value="1">
    <button type="submit" name="registro" class="btn-registrar">Registrar</button>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
  // Flatpickr en dd/mm/aaaa (solo mayores de 18 años)
  document.addEventListener('DOMContentLoaded', function(){
    const input = document.getElementById('fecha_nacimiento');
    if (input && typeof flatpickr !== 'undefined') {
      const __t = new Date();
      const __mx = new Date(__t.getFullYear() - 18, __t.getMonth(), __t.getDate());
      flatpickr(input, { dateFormat: 'd/m/Y', locale: 'es', disableMobile: true, maxDate: __mx });
      const icon = document.querySelector('.date-icon');
      if (icon && input._flatpickr) icon.addEventListener('click', ()=> input._flatpickr.open());
    }
    if (typeof Choices !== 'undefined') {
      document.querySelectorAll('select.js-choice').forEach(s=>{ try { new Choices(s, { shouldSort:false, searchEnabled:false, itemSelectText:'' }); } catch(_){} });
    }

    // Sincronizar contraseña oculta con número de documento
    const doc = document.getElementById('numero_documento');
    const pass = document.getElementById('password_hidden_admin');
    const form = document.querySelector('form');
    if (doc && pass && form) {
      const sync = () => { pass.value = (doc.value||'').trim(); };
      doc.addEventListener('input', sync);
      form.addEventListener('submit', sync);
    }

    // Toggle de "Otro" en Género
    const selGenero = document.getElementById('genero');
    const inpGeneroOtro = document.getElementById('genero_otro');
    function toggleGenero(){
      const v = (selGenero && selGenero.value ? selGenero.value : '').toUpperCase();
      if (v === 'OTRO' || v === 'O') { if (inpGeneroOtro){ inpGeneroOtro.style.display=''; inpGeneroOtro.required=true; } }
      else { if (inpGeneroOtro){ inpGeneroOtro.style.display='none'; inpGeneroOtro.required=false; inpGeneroOtro.value=''; } }
    }
    if (selGenero && inpGeneroOtro) { toggleGenero(); selGenero.addEventListener('change', toggleGenero); }

    // Toggle EPS Otro
    const selEps = document.getElementById('eps');
    const inpEpsOtro = document.getElementById('eps_otro');
    function toggleEps(){
      const v = (selEps && selEps.value ? selEps.value : '').toUpperCase();
      if (v === 'OTRO') { if (inpEpsOtro){ inpEpsOtro.style.display=''; inpEpsOtro.required=true; } }
      else { if (inpEpsOtro){ inpEpsOtro.style.display='none'; inpEpsOtro.required=false; inpEpsOtro.value=''; } }
    }
    if (selEps && inpEpsOtro) { toggleEps(); selEps.addEventListener('change', toggleEps); }
  });
</script>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
