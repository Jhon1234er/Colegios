<?php
require_once __DIR__ . '/../../helpers/auth.php';
start_secure_session(); // ⚡ inicia la sesión segura antes de todo

require_once __DIR__ . '/../../models/Colegio.php';
require_once __DIR__ . '/../../models/Ficha.php';
require_once __DIR__ . '/../../models/Materia.php';

$fichaModel = new Ficha();
$fichas = $fichaModel->obtenerTodas();
$materiaModel = new Materia();
$cursos = array_filter($materiaModel->obtenerTodas(), function($m){
    return ($m['estado'] ?? 'activa') !== 'suspendida';
});
?>

<?php include __DIR__ . '/../Componentes/encabezado.php'; ?>
<!-- Choices.js (select con buscador) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<link rel="stylesheet" href="/css/Ficha/crear_ficha.css">

<!-- FORMULARIO DE FICHA -->
<div class="from-wrapper">
    <div class="container">
        <h2>Registrar Nueva Ficha</h2>
        <form method="POST" action="/?page=fichas&action=guardar">
            <?= csrf_input(); ?> <!-- ✅ Token CSRF correcto -->

            <div class="">
                <div class="">
                    <label for="curso_id">Seleccione Curso</label>
                    <select name="curso_id" id="curso_id" required>
                        <option value="" selected disabled>-- Seleccione --</option>
                        <?php foreach ($cursos as $c): ?>
                          <option value="<?= htmlspecialchars($c['id']) ?>" data-codigo="<?= htmlspecialchars($c['codigo'] ?? '') ?>" data-denominacion="<?= htmlspecialchars($c['denominacion'] ?? ($c['nombre'] ?? '')) ?>">
                            <?= htmlspecialchars(($c['codigo'] ?? '') . ' - ' . ($c['denominacion'] ?? ($c['nombre'] ?? ''))) ?>
                          </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="nombre" id="nombre_ficha" value="">
                </div>

                <div class="">
                    <label for="numero_ficha">Número de la Ficha</label>
                    <input type="text" name="numero" id="numero_ficha" required readonly>
                </div>

                <div class="">
                    <label for="cupo_total">Cupo total de registros</label>
                    <input type="number" name="cupo_total" id="cupo_total" required>
                </div>

                <div class="">
                    <label>Días de clases</label>
                    <div class="dias-semana-container">
                        <div class="checkbox-group">
                            <label class="checkbox-item">
                                <input type="checkbox" name="dias_semana[]" value="lunes" checked data-dia="lunes">
                                <span class="checkmark"></span>
                                Lunes
                                <select name="jornadas[lunes]" class="select-jornada">
                                  <option value="mañana">Mañana</option>
                                  <option value="tarde">Tarde</option>
                                </select>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="dias_semana[]" value="martes" checked data-dia="martes">
                                <span class="checkmark"></span>
                                Martes
                                <select name="jornadas[martes]" class="select-jornada"><option value="mañana">Mañana</option><option value="tarde">Tarde</option></select>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="dias_semana[]" value="miercoles" checked data-dia="miercoles">
                                <span class="checkmark"></span>
                                Miércoles
                                <select name="jornadas[miercoles]" class="select-jornada"><option value="mañana">Mañana</option><option value="tarde">Tarde</option></select>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="dias_semana[]" value="jueves" checked data-dia="jueves">
                                <span class="checkmark"></span>
                                Jueves
                                <select name="jornadas[jueves]" class="select-jornada"><option value="mañana">Mañana</option><option value="tarde">Tarde</option></select>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="dias_semana[]" value="viernes" checked data-dia="viernes">
                                <span class="checkmark"></span>
                                Viernes
                                <select name="jornadas[viernes]" class="select-jornada"><option value="mañana">Mañana</option><option value="tarde">Tarde</option></select>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="dias_semana[]" value="sabado" data-dia="sabado">
                                <span class="checkmark"></span>
                                Sábado
                                <select name="jornadas[sabado]" class="select-jornada" disabled><option value="mañana">Mañana</option><option value="tarde">Tarde</option></select>
                            </label>
                        </div>
                    </div>
                    <small class="form-text text-muted">Selecciona los días en que se impartirán las clases y la jornada por día</small>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Crear Ficha</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const selCurso = document.getElementById('curso_id');
  const inputNumero = document.getElementById('numero_ficha');
  const inputNombre = document.getElementById('nombre_ficha');

  // Inicializar Choices en el select de cursos (buscador incorporado)
  const choices = new Choices(selCurso, {
    searchEnabled: true,
    itemSelectText: '',
    shouldSort: false,
    placeholder: true,
    searchPlaceholderValue: 'Buscar curso...'
  });

  selCurso.addEventListener('change', function(){
    const opt = selCurso.options[selCurso.selectedIndex];
    const codigo = opt.getAttribute('data-codigo') || '';
    const denom = opt.getAttribute('data-denominacion') || '';
    inputNumero.value = codigo;
    inputNombre.value = denom;
  });

  // Habilitar/deshabilitar select de jornada por día segun checkbox
  document.querySelectorAll('input[type=checkbox][name="dias_semana[]"]').forEach(cb => {
    const dia = cb.getAttribute('data-dia');
    const sel = cb.closest('.checkbox-item').querySelector('.select-jornada');
    const toggle = () => { sel.disabled = !cb.checked; };
    cb.addEventListener('change', toggle);
    toggle();
  });
});
</script>
