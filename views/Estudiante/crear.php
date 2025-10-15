<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
require_once __DIR__ . '/../../models/Colegio.php';
$colegioModel = new Colegio();
$colegios = $colegioModel->obtenerTodos();

// 📌 Capturamos ficha_id: puede venir de la URL o directamente del controlador (crearConToken)
$ficha_id = $ficha_id ?? ($_GET['ficha_id'] ?? null);
?>

<!DOCTYPE html>
<head>
  <meta charset="UTF-8">
  <title>Registro de Aprendiz</title>
  <link rel="stylesheet" href="/css/Estudiante/crear.css?v=2">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
  <div class="container estudiante-crear">
    <h2>Registro de Aprendiz</h2>

    <!-- Stepper visual -->
    <ol class="stepper flex items-center w-full mb-4 sm:mb-5">
      <li class="active step"><div class="step-circle">1</div><span class="step-label">Aprendiz</span></li>
      <li class="step"><div class="step-circle">2</div><span class="step-label">Familiares</span></li>
      <li class="step"><div class="step-circle">3</div><span class="step-label">Emergencias</span></li>
      <li class="step"><div class="step-circle">4</div><span class="step-label">Colegio</span></li>
    </ol>

    <form id="formEstudiante" action="/?page=estudiantes&action=guardar" method="POST">
      <?= csrf_input(); ?>

      <!-- 📌 Campo oculto con el ID de la ficha -->
      <?php if ($ficha_id): ?>
        <input type="hidden" name="ficha_id" value="<?= htmlspecialchars($ficha_id) ?>">
      <?php endif; ?>

      <!-- Paso 1: Estudiante -->
      <div class="form-step active">
        <div class="row">
          <div class="col-md-6">
            <label>Nombres</label>
            <input type="text" name="nombres" required>
          </div>
    
      <div class="col-md-6">
            <label>Apellidos</label>
            <input type="text" name="apellidos" required>
          </div>
          <div class="col-md-6">
            <label>Tipo de Documento</label>
            <select name="tipo_documento" class="js-choice" required>
              <option value="">Seleccione</option>
              <option value="TI">TI</option>
              <option value="CC">CC</option>
            </select>
          </div>
          <div class="col-md-6">
            <label>Número de Documento</label>
            <input type="text" name="numero_documento" required>
          </div>
          <div class="col-md-6">
            <label for="fecha_nacimiento">
              Fecha de Nacimiento
              <div class="date-wrap">
                <input type="text" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" placeholder="dd/mm/aaaa" required>
                <svg class="date-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <rect x="3" y="5" width="18" height="16" rx="4" ry="4" fill="none" stroke="currentColor" stroke-width="2"/>
                  <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
                  <line x1="8" y1="3" x2="8" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  <line x1="16" y1="3" x2="16" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
              </div>
            </label>
          </div>
          <div class="col-md-6">
            <label>Correo Electrónico</label>
            <input type="email" name="correo_electronico" required>
          </div>
          <div class="col-md-6">
            <label>Celular</label>
            <input type="text" name="celular" maxlength="10" pattern="\\d{10}" placeholder="10 dígitos" required>
          </div>
          <div class="col-md-6">
            <label>Ciudad / Municipio</label>
            <input type="text" name="municipio" placeholder="Ej: Cundinamarca">
          </div>
          <div class="col-md-6">
            <label>Dirección</label>
            <input type="text" name="direccion">
          </div>
          <div class="col-md-6">
            <label>Barrio</label>
            <input type="text" name="barrio">
          </div>
          <div class="col-md-6">
            <label>RH</label>
            <select name="rh" class="js-choice" required>
              <option value="">Seleccione...</option>
              <option>A+</option><option>A-</option><option>B+</option><option>B-</option>
              <option>O+</option><option>O-</option><option>AB+</option><option>AB-</option>
            </select>
          </div>
          <div class="col-md-6">
            <label>Género</label>
            <select name="genero" id="genero_est" class="js-choice" required>
              <option value="">Seleccione</option>
              <option value="M">Masculino</option>
              <option value="F">Femenino</option>
              <option value="Otro">Otro</option>
            </select>
            <input type="text" name="genero_otro" id="genero_otro_est" placeholder="Especifique" style="display:none;">
          </div>
          <div class="col-md-6">
            <label>EPS</label>
            <select name="eps" class="js-choice" required>
              <option value="">Seleccione...</option>
              <option>Aliansalud EPS</option>
              <option>Salud Total EPS S.A</option>
              <option>EPS Sanitas</option>
              <option>Eps Sura</option>
              <option>Famisanar</option>
              <option>Servicio Occidental de Salud EPS - SOS</option>
              <option>Compensar EPS</option>
            </select>
          </div>
          <div class="col-md-6">
            <label>Estrato</label>
            <select name="estrato" class="js-choice" required>
              <option value="">Seleccione...</option>
              <option value="1">1</option>
              <option value="2">2</option>
              <option value="3">3</option>
              <option value="4">4</option>
              <option value="5">5</option>
              <option value="6">6</option>
            </select>
          </div>
          <!-- La contraseña se genera automáticamente con el número de documento -->
        </div>
        <div class="form-navigation">
          <button type="button" class="next-btn">Siguiente</button>
        </div>
      </div>

      <!-- Paso 2: Familiares (acudientes múltiples) -->
      <div class="form-step">
        <h5>Familiares / Acudientes</h5>
        <p>Este acudiente será contactado en caso de emergencia. Puedes añadir más.</p>
        <div id="acudientes-container">
          <div class="acudiente-item" data-index="0">
            <div class="row">
              <div class="col-md-6"><label>Nombres</label><input type="text" name="acudientes[0][nombres]" required></div>
              <div class="col-md-6"><label>Apellidos</label><input type="text" name="acudientes[0][apellidos]" required></div>
              <div class="col-md-6"><label>Tipo de Documento</label>
                <select name="acudientes[0][tipo_documento]" class="js-choice" required>
                  <option value="">Seleccione</option>
                  <option value="CC">C.C</option><option value="CE">C.E</option><option value="PP">P.P</option><option value="PPT">PPT</option>
                </select>
              </div>
              <div class="col-md-6"><label>Número de Documento</label><input type="text" name="acudientes[0][numero_documento]" required></div>
              <div class="col-md-6"><label>Género</label>
                <select name="acudientes[0][genero]" class="gen-acu js-choice" required>
                  <option value="">Seleccione</option><option value="M">Masculino</option><option value="F">Femenino</option><option value="Otro">Otro</option>
                </select>
                <input type="text" name="acudientes[0][genero_otro]" class="gen-acu-otro" placeholder="Especifique" style="display:none;">
              </div>
              <div class="col-md-6"><label>Celular</label><input type="text" name="acudientes[0][celular]" maxlength="10" pattern="\\d{10}" placeholder="10 dígitos" required></div>
              <div class="col-md-6"><label>Correo</label><input type="email" name="acudientes[0][correo]"></div>
              <div class="col-md-6"><label>Parentesco</label>
                <select name="acudientes[0][parentesco]" class="js-choice" required>
                  <option value="">Seleccione</option><option>Padre</option><option>Madre</option><option>Hermano/a</option><option>Abuelo/a</option><option>Tío/a</option><option>Primo/a</option><option>Otro</option>
                </select>
              </div>
              <div class="col-md-6"><label>Ocupación</label>
                <select name="acudientes[0][ocupacion]" class="js-choice" required>
                  <option value="">Seleccione</option>
                  <option>Empleado</option><option>Independiente</option><option>Comerciante</option><option>Ama de casa</option><option>Estudiante</option><option>Docente</option><option>Profesional</option><option>Obrero</option><option>Conductor</option><option>Agricultor</option><option>Pensionado</option><option>Desempleado</option><option>Otro</option>
                </select>
              </div>
              <div class="col-md-6"><label>¿Contacto de emergencia?</label>
                <select name="acudientes[0][es_contacto_emergencia]" class="js-choice"><option value="1">Sí</option><option value="0">No</option></select>
              </div>
            </div>
            <hr>
          </div>
        </div>
        <button type="button" id="btnAddAcudiente" class="btn btn-secondary">Agregar otro acudiente</button>
        <div class="form-navigation" style="margin-top:12px;">
          <button type="button" class="prev-btn">Anterior</button>
          <button type="button" class="next-btn">Siguiente</button>
        </div>
      </div>

      <!-- Paso 3: Emergencias / Salud -->
      <div class="form-step">
        <h5>En caso de emergencia</h5>
        <div class="row">
          <div class="col-md-6"><label>Padece alguna enfermedad</label>
            <select name="ficha_medica[padece_enfermedad]" id="fm_enf" class="js-choice"><option value="0">No</option><option value="1">Sí</option></select>
            <input type="text" name="ficha_medica[enfermedad_detalle]" id="fm_enf_det" placeholder="¿Cuál?" style="display:none;">
          </div>
          <div class="col-md-6"><label>Alergias</label>
            <select name="ficha_medica[tiene_alergias]" id="fm_al" class="js-choice"><option value="0">No</option><option value="1">Sí</option></select>
            <input type="text" name="ficha_medica[alergias_detalle]" id="fm_al_det" placeholder="¿A qué?" style="display:none;">
          </div>
          <div class="col-md-6"><label>Medicamento permanente</label>
            <select name="ficha_medica[medicamento_permanente]" id="fm_med" class="js-choice"><option value="0">No</option><option value="1">Sí</option></select>
            <input type="text" name="ficha_medica[medicamento_detalle]" id="fm_med_det" placeholder="¿Cuál?" style="display:none;">
          </div>
          <div class="col-md-6"><label>Discapacidad</label>
            <select name="ficha_medica[discapacidad]" id="fm_disc" class="js-choice"><option value="0">No</option><option value="1">Sí</option></select>
            <input type="text" name="ficha_medica[discapacidad_detalle]" id="fm_disc_det" placeholder="¿Cuál?" style="display:none;">
          </div>
          <div class="col-md-6"><label>¿Cursos en Tecnoacademia?</label>
            <select name="ficha_medica[cursos_tecnoacademia]" class="js-choice"><option value="0">No</option><option value="1">Sí</option></select>
          </div>
        </div>
        <div class="form-navigation">
          <button type="button" class="prev-btn">Anterior</button>
          <button type="button" class="next-btn">Siguiente</button>
        </div>
      </div>


      <!-- Paso 4: Colegio -->
      <div class="form-step">
        <h5>Información del Colegio</h5>
        <div class="row">
          <div class="col-md-6">
            <label>Colegio</label>
            <select name="colegio_id" id="colegio_id" class="js-choice" required>
              <option value="">Seleccione un colegio</option>
              <?php foreach ($colegios as $colegio): ?>
                <option value="<?= $colegio['id'] ?>"
                        data-grados='<?= json_encode($colegio['grados']) ?>'
                        data-jornada='<?= json_encode($colegio['jornada']) ?>'>
                  <?= htmlspecialchars($colegio['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label>Grado</label>
            <select name="grado" id="grado" class="js-choice" required>
              <option value="">Seleccione grado</option>
            </select>
          </div>
          <div class="col-md-6">
            <label>Grupo</label>
            <input type="text" name="grupo">
          </div>
          <div class="col-md-6">
            <label>Jornada</label>
            <select name="jornada" id="jornada" class="js-choice" required>
              <option value="">Seleccione jornada</option>
            </select>
          </div>
        </div>
        <div class="form-navigation">
          <button type="button" class="prev-btn">Anterior</button>
          <button type="submit" class="submit-btn">Registrar Estudiante</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="/js/crearE.js?v=1"></script>
  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
  <script src="/js/crearE.js"></script>
  <script>
    // Defensa: ocultar todos los pasos excepto el primero al cargar
    (function(){
      const steps = Array.from(document.querySelectorAll('.form-step'));
      steps.forEach((s,i)=>{ s.classList.toggle('active', i===0); });
    })();
    // Inicializar Choices.js en TODOS los selects (estilo unificado)
    (function(){
      if (typeof Choices === 'undefined') return;
      const options = { searchEnabled: false, shouldSort: false, itemSelectText: '' };
      const instances = new Map();
      document.querySelectorAll('select').forEach(el => {
        instances.set(el, new Choices(el, options));
      });

      // Forzar despliegue hacia ARRIBA en EPS y Estrato
      ['eps','estrato'].forEach(name => {
        const el = document.querySelector(`select[name="${name}"]`);
        if (!el) return;
        const prev = instances.get(el);
        try { prev && prev.destroy(); } catch (_) {}
        const topOpts = Object.assign({}, options, { position: 'top' });
        instances.set(el, new Choices(el, topOpts));
      });

      // Refrescar cuando grado/jornada cambian sus opciones dinámicamente
      ['grado','jornada'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        new MutationObserver(()=>{
          const ins = instances.get(el);
          if (ins) {
            ins.setChoices(Array.from(el.options).map(o=>({value:o.value,label:o.text,selected:o.selected})), 'value', 'label', true);
          }
        }).observe(el, { childList: true });
      });

      // Al clonar acudiente, inicializar los nuevos selects
      const btn = document.getElementById('btnAddAcudiente');
      if (btn) btn.addEventListener('click', ()=>{
        setTimeout(()=>{
          document.querySelectorAll('#acudientes-container select.js-choice').forEach(el=>{
            if (!instances.get(el)) instances.set(el, new Choices(el, options));
          });
        }, 0);
      });
    })();
    // Mostrar input "Otro" para género del estudiante
    const genEst = document.getElementById('genero_est');
    const genEstOtro = document.getElementById('genero_otro_est');
    if (genEst) genEst.addEventListener('change', () => {
      const show = genEst.value === 'Otro';
      genEstOtro.style.display = show ? 'block' : 'none';
      genEstOtro.required = show;
      if (!show) genEstOtro.value = '';
    });

    // Toggle campos detalle en ficha médica
    function bindToggle(selId, inputId){
      const s = document.getElementById(selId), i = document.getElementById(inputId);
      if(!s || !i) return; const h=()=>{const sh=s.value==='1'; i.style.display=sh?'block':'none'; i.required=sh; if(!sh) i.value='';};
      s.addEventListener('change', h); h();
    }
    bindToggle('fm_enf','fm_enf_det');
    bindToggle('fm_al','fm_al_det');
    bindToggle('fm_med','fm_med_det');
    bindToggle('fm_disc','fm_disc_det');

    // Añadir otro acudiente
    document.getElementById('btnAddAcudiente')?.addEventListener('click', function(){
      const cont = document.getElementById('acudientes-container');
      const items = cont.querySelectorAll('.acudiente-item');
      const idx = items.length;
      const tpl = items[0].cloneNode(true);
      tpl.setAttribute('data-index', idx);
      tpl.querySelectorAll('input,select').forEach(el=>{
        el.value='';
        const name = el.getAttribute('name');
        if(name){ el.setAttribute('name', name.replace(/\[0\]/, '['+idx+']')); }
        if(el.classList.contains('gen-acu-otro')){ el.style.display='none'; el.required=false; }
      });
      // toggle genero Otro dentro de cada item
      const selGen = tpl.querySelector('.gen-acu');
      const inpGenOtro = tpl.querySelector('.gen-acu-otro');
      selGen.addEventListener('change', ()=>{ const show = selGen.value==='Otro'; inpGenOtro.style.display=show?'block':'none'; inpGenOtro.required=show; if(!show) inpGenOtro.value=''; });
      cont.appendChild(tpl);
    });
    // Primer item: enlazar cambio de género Otro
    (function(){
      const first = document.querySelector('#acudientes-container .acudiente-item');
      if(!first) return; const sel=first.querySelector('.gen-acu'); const inp=first.querySelector('.gen-acu-otro');
      sel.addEventListener('change', ()=>{ const show = sel.value==='Otro'; inp.style.display=show?'block':'none'; inp.required=show; if(!show) inp.value=''; });
    })();
  </script>
</body>
</html>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
