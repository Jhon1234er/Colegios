<?php
$es_publico = $es_publico ?? false;
if (!$es_publico) {
    require_once __DIR__ . '/../Componentes/encabezado.php';
}
require_once __DIR__ . '/../../models/Colegio.php';
$colegioModel = new Colegio();
$colegios = $colegioModel->obtenerTodos();

// 📌 Capturamos ficha_id: puede venir de la URL o directamente del controlador (crearConToken)
$ficha_id = $ficha_id ?? ($_GET['ficha_id'] ?? null);
$modo_pendiente = isset($modo_pendiente)
    ? (bool)$modo_pendiente
    : (isset($_GET['pendiente']) && (string)$_GET['pendiente'] === '1');

// Siempre permitir modo pendiente para administradores
$modo_pendiente = true;

$form_action = $form_action ?? '/?page=aprendices&action=guardar';
?>

<!DOCTYPE html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registro de Aprendiz - Sistem School</title>

  <!-- Fuente Inter para unificar estilo con el resto de la plataforma -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/css/Aprendiz/crear.css?v=6">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
  <div class="container estudiante-crear">
    <?php if (isset($_GET['success']) && $_GET['success'] == '1' && $es_publico): ?>
      <div class="registro-completado">
          <div class="success-icon"></div>
          <h2>¡Registro Completado!</h2>
          <p class="mensaje-exito">Tu inscripción ha sido procesada exitosamente.</p>
          <div class="detalles-registro">
              <h3>¿Qué sigue ahora?</h3>
              <ul>
                  <li>"¡Gracias por tu tiempo! Tu registro se ha completado con éxito."</li>
                  <li>Ahora puedes cerrar esta ventana.</li>
              </ul>
          </div>
          <div class="acciones-finales">
              <button onclick="window.close();" class="btn btn-primary">Cerrar</button>
          </div>
      </div>
    <?php elseif (isset($ficha_llena) && $ficha_llena && $es_publico): ?>
      <h2>Registro de Aprendiz</h2>
      <div class="alert alert-warning" style="margin-top:12px;">⚠️ El cupo de esta ficha está completo. El registro público está cerrado.</div>
    <?php else: ?>
    <h2>Registro de Aprendiz</h2>

    <!-- Stepper visual -->
    <ol class="stepper flex items-center w-full mb-4 sm:mb-5">
      <li class="active step"><div class="step-circle">1</div><span class="step-label">Aprendiz</span></li>
      <li class="step"><div class="step-circle">2</div><span class="step-label">Acudientes</span></li>
      <li class="step"><div class="step-circle">3</div><span class="step-label">Información médica</span></li>
      <li class="step"><div class="step-circle">4</div><span class="step-label">Datos escolares</span></li>
    </ol>

    <form id="formEstudiante" action="<?= htmlspecialchars($form_action) ?>" method="POST">
      <?= csrf_input(); ?>

      <?php if ($ficha_id): ?>
        <input type="hidden" name="ficha_id" value="<?= htmlspecialchars($ficha_id) ?>">
      <?php endif; ?>

      <!-- Paso 1: Estudiante -->
      <div class="form-step active">
        <div class="row row1">
          <div class="col-md-6">
            <label>Nombres *</label>
            <input type="text" name="nombres" required>
          </div>

          <div class="col-md-6">
            <label>Apellidos *</label>
            <input type="text" name="apellidos" required>
          </div>
          <div class="col-md-6">
            <label>Tipo de documento *</label>
            <select name="tipo_documento" class="js-choice" required>
              <option value="">Seleccione</option>
              <option value="CC">Cédula de ciudadanía</option>
              <option value="TI">Tarjeta de identidad</option>
              <option value="RC">Registro civil</option>
              <option value="PPT">Permiso de Permanencia</option>
            </select>
          </div>
          <div class="col-md-6">
            <label>Número de documento *</label>
            <input type="text" name="numero_documento" required>
          </div>
          <div class="col-md-6">
            <label for="fecha_nacimiento">
              Fecha de nacimiento *
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
            <label>Correo personal *</label>
            <input type="email" name="correo_electronico" required>
          </div>
          <div class="col-md-6">
            <label>Correo Institucional</label>
            <input type="email" name="correo_institucional" placeholder="usuario@institucion.edu.co">
          </div>
          <div class="col-md-6">
            <label>Celular *</label>
            <input type="text" name="celular" maxlength="10" pattern="[0-9]{10}" placeholder="10 dígitos" required>
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
            <label>RH *</label>
            <select name="rh" class="js-choice" required>
              <option value="">Seleccione...</option>
              <option>A+</option>
              <option>A-</option>
              <option>B+</option>
              <option>B-</option>
              <option>O+</option>
              <option>O-</option>
              <option>AB+</option>
              <option>AB-</option>
            </select>
          </div>
          <div class="col-md-6">
            <label>Género *</label>
            <select name="genero" id="genero_est" class="js-choice" required>
              <option value="">Seleccione</option>
              <option value="M">Masculino</option>
              <option value="F">Femenino</option>
              <option value="Prefiero no decirlo">Prefiero no decirlo</option>
              <option value="Otro">Otro (especifique)</option>
            </select>
            <input type="text" name="genero_otro" id="genero_otro_est" placeholder="Especifique (opcional)" style="display:none;">
          </div>
          <div class="col-md-6">
            <label>EPS *</label>
            <select name="eps" class="js-choice" required>
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
            <input type="text" name="eps_otro" id="eps_otro_est" placeholder="Especifique EPS (opcional)" style="display:none;">
          </div>
          <div class="col-md-6">
            <label>Estrato *</label>
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

      <!-- Paso 2: Familiares -->
      <div class="form-step">
        <div id="acudientes-container">
                    <div class="acudiente-item" data-index="0">
            <button type="button" class="btn-eliminar-acudiente" style="display:none;">&times;</button>
            <div class="row row2">
              <div class="col-md-6"><label>Nombres *</label><input type="text" name="acudientes[0][nombres]" required></div>
              <div class="col-md-6"><label>Apellidos *</label><input type="text" name="acudientes[0][apellidos]" required></div>
              <div class="col-md-6"><label>Tipo de documento *</label>
                <select name="acudientes[0][tipo_documento]" class="js-choice" required>
                  <option value="">Seleccione</option>
                  <option value="CC">Cédula de ciudadanía</option>
                  <option value="TI">Tarjeta de identidad</option>
                  <option value="PP">Pasaporte</option>
                  <option value="PPT">Permiso de Permanencia</option>
                </select>
              </div>
              <div class="col-md-6"><label>Número de documento *</label>
              <input type="text" name="acudientes[0][numero_documento]" required></div>
              <div class="col-md-6"><label>Género *</label>
                <select name="acudientes[0][genero]" class="gen-acu js-choice" required>
                  <option value="">Seleccione</option>
                  <option value="M">Masculino</option>
                  <option value="F">Femenino</option>
                  <option value="Otro">Otro</option>
                </select>
                <input type="text" name="acudientes[0][genero_otro]" class="gen-acu-otro" placeholder="Especifique (opcional)" style="display:none;">
              </div>
              <div class="col-md-6"><label>Celular *</label>
              <input type="text" name="acudientes[0][celular]" maxlength="10" pattern="[0-9]{10}" placeholder="10 dígitos" required></div>
              <div class="col-md-6"><label>Correo *</label>
              <input type="email" name="acudientes[0][correo]" required></div>
              <div class="col-md-6"><label>Parentesco *</label>
                <select name="acudientes[0][parentesco]" class="par-acu js-choice" required>
                  <option value="">Seleccione</option>
                  <option>Padre</option>
                  <option>Madre</option>
                  <option>Hermano/a</option>
                  <option>Abuelo/a</option>
                  <option>Tío/a</option>
                  <option>Primo/a</option>
                  <option>Otro</option>
                </select>
                <input type="text" name="acudientes[0][parentesco_otro]" class="par-acu-otro" placeholder="Especifique parentesco (opcional)" style="display:none;">
              </div>
              <div class="col-md-6"><label>Ocupación *</label>
                <select name="acudientes[0][ocupacion]" class="ocu-acu js-choice" required>
                  <option value="">Seleccione</option>
                  <option>Empleado</option><option>Independiente</option><option>Comerciante</option><option>Ama de casa</option><option>Estudiante</option><option>Docente</option><option>Profesional</option><option>Obrero</option><option>Conductor</option><option>Agricultor</option><option>Pensionado</option><option>Desempleado</option><option>Otro</option>
                </select>
                <input type="text" name="acudientes[0][ocupacion_otro]" class="ocu-acu-otro" placeholder="Especifique ocupación (opcional)" style="display:none;">
              </div>
              <div class="col-md-6">
                <label>¿Es acudiente? *</label>
                <select name="acudientes[0][es_acudiente]" class="js-choice" required>
                  <option value="">Seleccione</option>
                  <option value="1" selected>Sí</option>
                  <option value="0">No</option>
                </select>
              </div>
            </div>
            <hr>
          </div>
        </div>
        <button type="button" id="btnAddAcudiente" class="btn btn-secondary">Agregar otro acudiente</button>
        <small class="d-block mt-2 text-muted">Puedes añadir hasta dos acudientes. Debes marcar al menos uno como acudiente.</small>
        <div class="form-navigation" style="margin-top:12px;">
          <button type="button" class="prev-btn">Anterior</button>
          <button type="button" class="next-btn">Siguiente</button>
        </div>
      </div>

      <!-- Paso 3: Información médica -->
      <div class="form-step">
        <div class="row row2">
          <div class="col-md-6"><label>Padece alguna enfermedad? *</label>
            <select name="ficha_medica[padece_enfermedad]" id="fm_enf" class="js-choice" required>
              <option value="">Seleccione...</option>
              <option value="1">Sí</option>
              <option value="0">No</option>
            </select>
            <input type="text" name="ficha_medica[enfermedad_detalle]" id="fm_enf_det" placeholder="¿Cuál(es)?" style="display:none;">
          </div>
          <div class="col-md-6"><label>Alergias? *</label>
            <select name="ficha_medica[tiene_alergias]" id="fm_al" class="js-choice" required>
              <option value="">Seleccione...</option>
              <option value="1">Sí</option>
              <option value="0">No</option>
            </select>
            <input type="text" name="ficha_medica[alergias_detalle]" id="fm_al_det" placeholder="¿Cuál(es)?" style="display:none;">
          </div>
          <div class="col-md-6"><label>Medicamentos permanentes? *</label>
            <select name="ficha_medica[medicamento_permanente]" id="fm_med" class="js-choice" required>
              <option value="">Seleccione...</option>
              <option value="1">Sí</option>
              <option value="0">No</option>
            </select>
            <input type="text" name="ficha_medica[medicamento_detalle]" id="fm_med_det" placeholder="¿Cuál(es)?" style="display:none;">
          </div>
          <div class="col-md-6"><label>Discapacidad? *</label>
            <select name="ficha_medica[discapacidad]" id="fm_disc" class="js-choice" required>
              <option value="">Seleccione...</option>
              <option value="1">Sí</option>
              <option value="0">No</option>
            </select>
            <input type="text" name="ficha_medica[discapacidad_detalle]" id="fm_disc_det" placeholder="¿Cuál(es)?" style="display:none;">
          </div>
        </div>
        <div class="form-navigation">
          <button type="button" class="prev-btn">Anterior</button>
          <button type="button" class="next-btn">Siguiente</button>
        </div>
      </div>


      <!-- Paso 4: Datos escolares -->
      <div class="form-step">
        <h5>Datos escolares</h5>
        <div class="row row2">
          <?php if (empty($ficha_id) && !$modo_pendiente): ?>
          <div class="col-md-6">
            <label>Ficha</label>
            <select name="ficha_id" id="ficha_id" class="js-choice">
              <option value="">Seleccione una ficha (opcional)</option>
              <?php if (!empty($fichas)): foreach ($fichas as $f): ?>
                <option value="<?= htmlspecialchars($f['id']) ?>"><?= htmlspecialchars(($f['numero'] ?? '') . ' - ' . ($f['nombre'] ?? '')) ?></option>
              <?php endforeach; endif; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label>&nbsp;</label>
            <div class="form-check" style="margin-top: 8px;">
              <input type="checkbox" class="form-check-input" id="sin_ficha" name="sin_ficha" value="1">
              <label class="form-check-label" for="sin_ficha">
                Registrar sin ficha (quedará pendiente)
              </label>
            </div>
            <small class="text-muted">Si aún no tienes la ficha creada, marca esta opción y el aprendiz quedará en lista de pendientes.</small>
          </div>
          <?php elseif ($modo_pendiente && empty($ficha_id)): ?>
            <input type="hidden" name="sin_ficha" value="1">
          <?php endif; ?>
          <div class="col-md-6">
            <label>Colegio *</label>
            <select name="colegio_id" id="colegio_id" class="js-choice" required>
              <option value="">Seleccione un colegio</option>
              <?php foreach ($colegios as $colegio): ?>
                <option value="<?= $colegio['id'] ?>"
                        data-grados='<?= htmlspecialchars(json_encode($colegio['grados']), ENT_QUOTES, 'UTF-8') ?>'
                        data-jornada='<?= htmlspecialchars(json_encode($colegio['jornada']), ENT_QUOTES, 'UTF-8') ?>'>
                  <?= htmlspecialchars($colegio['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label>Grado *</label>
            <select name="grado" id="grado" class="js-choice" required>
              <option value="">Seleccione grado</option>
            </select>
          </div>
          <div class="col-md-6">
            <label>Grupo</label>
            <input type="text" name="grupo">
          </div>
          <div class="col-md-6">
            <label>Jornada *</label>
            <select name="jornada" id="jornada" class="js-choice" required>
              <option value="">Seleccione jornada</option>
            </select>
          </div>
          <div class="col-md-6">
            <label>¿Has hecho cursos en Tecnoacademia? *</label>
            <select name="ficha_medica[cursos_tecnoacademia]" id="fm_cursos" class="js-choice" required>
              <option value="">Seleccione...</option>
              <option value="1">Sí</option>
              <option value="0">No</option>
            </select>
            <input type="text" name="ficha_medica[cursos_tecnoacademia_detalle]" id="fm_cursos_det" placeholder="¿Cuáles?" style="display:none;">
          </div>
        </div>
        <div class="form-navigation">
          <button type="button" class="prev-btn">Anterior</button>
          <button type="submit" class="submit-btn">Registrar Aprendiz</button>
        </div>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="/js/Aprendiz/crearE.js?v=1"></script>
  <script>
    // Defensa: ocultar todos los pasos excepto el primero al cargar
    (function(){
      const steps = Array.from(document.querySelectorAll('.form-step'));
      steps.forEach((s,i)=>{ s.classList.toggle('active', i===0); });
    })();
    // Mostrar input "Otro" para género del estudiante
    const genEst = document.getElementById('genero_est');
    const genEstOtro = document.getElementById('genero_otro_est');
    if (genEst) genEst.addEventListener('change', () => {
      const show = genEst.value === 'Otro';
      genEstOtro.style.display = show ? 'block' : 'none';
      genEstOtro.required = false;
      if (!show) genEstOtro.value = '';
    });

    // Mostrar input "Otro" para EPS del estudiante
    const epsEst = document.querySelector('select[name="eps"]');
    const epsEstOtro = document.getElementById('eps_otro_est');
    if (epsEst && epsEstOtro) epsEst.addEventListener('change', () => {
      const show = epsEst.value === 'Otro';
      epsEstOtro.style.display = show ? 'block' : 'none';
      epsEstOtro.required = false;
      if (!show) epsEstOtro.value = '';
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
    bindToggle('fm_cursos','fm_cursos_det');

    // Permitir registrar sin ficha (quedará Pendiente)
    (function(){
      var chk = document.getElementById('sin_ficha');
      var selFicha = document.querySelector('select[name="ficha_id"]');
      if (!chk || !selFicha) return;
      var updateRequired = function(){
        var sinFicha = chk.checked;
        selFicha.required = !sinFicha;
        if (sinFicha) {
          selFicha.value = '';
        }
      };
      chk.addEventListener('change', updateRequired);
      updateRequired();
    })();

    // Guardar una copia limpia del template ANTES de que Choices.js lo modifique
    const acudienteTemplate = document.querySelector('.acudiente-item').cloneNode(true);

    function bindAcudienteOtroHandlers(scope){
      const selGen = scope.querySelector('.gen-acu');
      const inpGen = scope.querySelector('.gen-acu-otro');
      if (selGen && inpGen) {
        const h = ()=>{ const show = selGen.value==='Otro'; inpGen.style.display=show?'block':'none'; inpGen.required=false; if(!show) inpGen.value=''; };
        selGen.addEventListener('change', h); h();
      }
      const selPar = scope.querySelector('.par-acu');
      const inpPar = scope.querySelector('.par-acu-otro');
      if (selPar && inpPar) {
        const h2 = ()=>{ const show = selPar.value==='Otro'; inpPar.style.display=show?'block':'none'; inpPar.required=false; if(!show) inpPar.value=''; };
        selPar.addEventListener('change', h2); h2();
      }
      const selOcu = scope.querySelector('.ocu-acu');
      const inpOcu = scope.querySelector('.ocu-acu-otro');
      if (selOcu && inpOcu) {
        const h3 = ()=>{ const show = selOcu.value==='Otro'; inpOcu.style.display=show?'block':'none'; inpOcu.required=false; if(!show) inpOcu.value=''; };
        selOcu.addEventListener('change', h3); h3();
      }
    }

    // Añadir otro acudiente (máximo 2 acudientes)
    document.getElementById('btnAddAcudiente')?.addEventListener('click', function() {
        const container = document.getElementById('acudientes-container');
        const index = container.children.length;
        if (index >= 2) {
          if (window.showFormError) {
            window.showFormError('Solo puedes añadir hasta dos acudientes.');
          } else {
            alert('Solo puedes añadir hasta dos acudientes.');
          }
          return;
        }
        const newItem = acudienteTemplate.cloneNode(true);

        // Actualizar nombres de los campos en el nuevo item
        newItem.querySelectorAll('input, select').forEach(el => {
            const name = el.getAttribute('name');
            if (name) {
                el.name = name.replace(/\\[0\\]/, `[${index}]`);
            }
        });

        // Mostrar el botón de eliminar
        newItem.querySelector('.btn-eliminar-acudiente').style.display = 'block';

        container.appendChild(newItem);

        // Inicializar Choices.js en los nuevos selects del item recién agregado (si existe Choices)
        if (typeof Choices !== 'undefined') {
          newItem.querySelectorAll('select.js-choice').forEach(select => {
            try { new Choices(select, { searchEnabled: false, shouldSort: false, itemSelectText: '' }); } catch(_) {}
          });
        }

        // Enlazar toggles de "Otro" en el nuevo item
        bindAcudienteOtroHandlers(newItem);
    });

    // Delegación de eventos para eliminar acudientes
    document.getElementById('acudientes-container').addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-eliminar-acudiente')) {
            e.target.closest('.acudiente-item').remove();
        }
    });
    // Primer item: enlazar toggles de "Otro"
    (function(){
      const first = document.querySelector('#acudientes-container .acudiente-item');
      if(!first) return; bindAcudienteOtroHandlers(first);
    })();
  </script>
</body>
</html>

<?php if (!$es_publico) require_once __DIR__ . '/../Componentes/footer.php'; ?>
