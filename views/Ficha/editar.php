<?php
require_once __DIR__ . '/../../helpers/auth.php';
start_secure_session();

require_once __DIR__ . '/../../models/Ficha.php';
require_once __DIR__ . '/../../models/Colegio.php';
require_once __DIR__ . '/../../models/AreaDeConocimiento.php';

$fichaModel = new Ficha();
$ficha = $fichaModel->obtenerPorId($_GET['id'] ?? 0);

if (!$ficha) {
    header("Location: /?page=fichas&action=index");
    exit;
}

$materiaModel = new AreaDeConocimiento();
$cursos = array_filter($materiaModel->obtenerTodas(), function($m){
    return ($m['estado'] ?? 'activa') !== 'suspendida';
});
?>

<?php include __DIR__ . '/../Componentes/encabezado.php'; ?>
<!-- Choices.js (select con buscador) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<link rel="stylesheet" href="/css/Ficha/crear_ficha.css">

<!-- FORMULARIO DE EDICIÓN DE FICHA -->
<div class="from-wrapper">
    <div class="container">
        <h2>Editar Ficha</h2>
        <form method="POST" action="/?page=fichas&action=guardar">

            <?= csrf_input(); ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($ficha['id']) ?>">
            
            <div class="datos-ficha-container">
                <div class="ficha-select-container">
                    <label for="curso_id">Seleccione Curso</label>
                    <select name="curso_id" id="curso_id" required>
                        <option value="" selected disabled>-- Seleccione --</option>
                        <?php foreach ($cursos as $c): ?>
                          <option value="<?= htmlspecialchars($c['id']) ?>" 
                                  data-codigo="<?= htmlspecialchars($c['codigo'] ?? '') ?>" 
                                  data-denominacion="<?= htmlspecialchars($c['denominacion'] ?? ($c['nombre'] ?? '')) ?>"
                                  <?= ($ficha['curso_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($c['codigo'] ?? '') . ' - ' . ($c['denominacion'] ?? ($c['nombre'] ?? ''))) ?>
                          </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="nombre" id="nombre_ficha" value="<?= htmlspecialchars($ficha['nombre'] ?? '') ?>">
                </div>

                <div class="">
                    <label for="numero_ficha">Número de la Ficha</label>
                    <input type="text" name="numero" id="numero_ficha" required value="<?= htmlspecialchars($ficha['numero'] ?? '') ?>">
                </div>

                <div class="">
                    <label for="cupo_total">Cupo total de registros</label>
                    <input type="number" name="cupo_total" id="cupo_total" min="1" value="<?= htmlspecialchars($ficha['cupo_total'] ?? '') ?>" required>
                </div>

                <div class="">
                    <label for="jornada">Jornada</label>
                    <select name="jornada" id="jornada" required>
                        <option value="">-- Seleccione --</option>
                        <option value="Mañana" <?= ($ficha['jornada'] ?? '') === 'Mañana' ? 'selected' : '' ?>>Mañana</option>
                        <option value="Tarde" <?= ($ficha['jornada'] ?? '') === 'Tarde' ? 'selected' : '' ?>>Tarde</option>
                        <option value="Noche" <?= ($ficha['jornada'] ?? '') === 'Noche' ? 'selected' : '' ?>>Noche</option>
                        <option value="Fin de Semana" <?= ($ficha['jornada'] ?? '') === 'Fin de Semana' ? 'selected' : '' ?>>Fin de Semana</option>
                    </select>
                </div>

                <div class="">
                    <label for="fecha_inicio">Fecha de Inicio</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= htmlspecialchars($ficha['fecha_inicio'] ?? '') ?>" required>
                </div>

                <div class="">
                    <label for="fecha_fin">Fecha de Finización</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="<?= htmlspecialchars($ficha['fecha_fin'] ?? '') ?>" required>
                </div>

                <div class="">
                    <label for="estado">Estado</label>
                    <select name="estado" id="estado" required>
                        <option value="">-- Seleccione --</option>
                        <option value="activa" <?= ($ficha['estado'] ?? 'activa') === 'activa' ? 'selected' : '' ?>>Activa</option>
                        <option value="suspendida" <?= ($ficha['estado'] ?? '') === 'suspendida' ? 'selected' : '' ?>>Suspendida</option>
                        <option value="finalizada" <?= ($ficha['estado'] ?? '') === 'finalizada' ? 'selected' : '' ?>>Finalizada</option>
                    </select>
                </div>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Actualizar Ficha
                </button>
                <a href="/?page=fichas&action=index" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Choices.js
    const cursoSelect = new Choices('#curso_id', {
        searchEnabled: true,
        searchPlaceholderValue: 'Buscar curso...',
        noResultsText: 'No se encontraron cursos',
        itemSelectText: 'Presionar para seleccionar'
    });

    // Actualizar nombre de la ficha cuando se selecciona un curso
    const cursoSelectElement = document.getElementById('curso_id');
    const nombreFichaInput = document.getElementById('nombre_ficha');
    const numeroFichaInput = document.getElementById('numero_ficha');

    function actualizarNombreFicha() {
        const selectedOption = cursoSelectElement.options[cursoSelectElement.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const codigo = selectedOption.dataset.codigo || '';
            const denominacion = selectedOption.dataset.denominacion || '';
            
            if (codigo && denominacion) {
                const nombreCompleto = `${codigo} - ${denominacion}`;
                nombreFichaInput.value = nombreCompleto;
                
                // Si el número de ficha está vacío, sugerir uno basado en el código
                if (!numeroFichaInput.value && codigo) {
                    // Extraer números del código o usar el código directamente
                    const numeros = codigo.match(/\d+/g);
                    if (numeros && numeros.length > 0) {
                        numeroFichaInput.value = numeros.join('');
                    } else {
                        numeroFichaInput.value = codigo.replace(/[^a-zA-Z0-9]/g, '');
                    }
                }
            }
        }
    }

    cursoSelectElement.addEventListener('change', actualizarNombreFicha);
    
    // Ejecutar al cargar si ya hay un valor seleccionado
    if (cursoSelectElement.value) {
        actualizarNombreFicha();
    }

    // Validar fechas
    const fechaInicio = document.getElementById('fecha_inicio');
    const fechaFin = document.getElementById('fecha_fin');

    function validarFechas() {
        if (fechaInicio.value && fechaFin.value) {
            const inicio = new Date(fechaInicio.value);
            const fin = new Date(fechaFin.value);
            
            if (fin <= inicio) {
                fechaFin.setCustomValidity('La fecha de fin debe ser posterior a la fecha de inicio');
            } else {
                fechaFin.setCustomValidity('');
            }
        }
    }

    fechaInicio.addEventListener('change', validarFechas);
    fechaFin.addEventListener('change', validarFechas);
});
</script>
