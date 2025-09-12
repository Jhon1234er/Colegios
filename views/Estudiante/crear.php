<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
require_once __DIR__ . '/../../models/Colegio.php';
$colegioModel = new Colegio();
$colegios = $colegioModel->obtenerTodos();

// 📌 Capturamos ficha_id: puede venir de la URL o directamente del controlador (crearConToken)
$ficha_id = $ficha_id ?? ($_GET['ficha_id'] ?? null);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Aprendiz</title>
  <link rel="stylesheet" href="/css/crear.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
</head>
<body>
  <div class="container">
    <h2>Registro de Aprendiz</h2>

    <!-- Stepper visual -->
    <ol class="stepper flex items-center w-full mb-4 sm:mb-5">
      <li class="active step">
        <div class="step-circle">1</div>
        <span class="step-label">Aprendiz</span>
      </li>
      <li class="step">
        <div class="step-circle">2</div>
        <span class="step-label">Acudiente</span>
      </li>
      <li class="step">
        <div class="step-circle">3</div>
        <span class="step-label">Colegio</span>
      </li>
    </ol>

    <form id="formEstudiante" action="/?page=estudiantes&action=guardar" method="POST">
      <?= csrf_input(); ?>

      <!-- 📌 Campo oculto con el ID de la ficha -->
      <?php if ($ficha_id): ?>
        <input type="hidden" name="ficha_id" value="<?= htmlspecialchars($ficha_id) ?>">
      <?php endif; ?>

      <!-- Paso 1: Estudiante -->
      <div class="form-step active">
        <h5>Información del Estudiante</h5>
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
            <select name="tipo_documento" required>
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
            <label>Fecha de Nacimiento</label>
            <input type="text" name="fecha_nacimiento" id="fecha_nacimiento" required>
          </div>
          <div class="col-md-6">
            <label>Correo Electrónico</label>
            <input type="email" name="correo_electronico" required>
          </div>
          <div class="col-md-6">
            <label>Teléfono</label>
            <input type="text" name="telefono">
          </div>
          <div class="col-md-6">
            <label>Dirección</label>
            <input type="text" name="direccion">
          </div>
          <div class="col-md-6">
            <label>Género</label>
            <select name="genero" required>
              <option value="">Seleccione</option>
              <option value="M">Masculino</option>
              <option value="F">Femenino</option>
            </select>
          </div>
          <div class="col-md-6">
            <label>Contraseña</label>
            <input type="password" name="password" required>
          </div>
        </div>
        <div class="form-navigation">
          <button type="button" class="next-btn">Siguiente</button>
        </div>
      </div>

      <!-- Paso 2: Acudiente -->
      <div class="form-step">
        <h5>Información del Acudiente</h5>
        <div class="row">
          <div class="col-md-6">
            <label>Nombre completo del Acudiente</label>
            <input type="text" name="nombre_completo_acudiente" required>
          </div>

          <div class="col-md-6">
            <label>Tipo de Documento</label>
            <select name="tipo_documento_acudiente" required>
              <option value="">Seleccione</option>
              <option value="CC">CC</option>
              <option value="CE">CE</option>
            </select>
          </div>

          <div class="col-md-6">
            <label>Número de Documento</label>
            <input type="text" name="numero_documento_acudiente" required>
          </div>

          <div class="col-md-6">
            <label>Teléfono</label>
            <input type="text" name="telefono_acudiente" required>
          </div>

          <!-- Parentesco -->
          <div class="col-md-6">
            <label>Parentesco</label>
            <select name="parentesco" id="parentesco" required>
              <option value="">Seleccione</option>
              <option value="Padre">Padre</option>
              <option value="Madre">Madre</option>
              <option value="Hermano/a">Hermano/a</option>
              <option value="Abuelo/a">Abuelo/a</option>
              <option value="Tío/a">Tío/a</option>
              <option value="Primo/a">Primo/a</option>
              <option value="Otro">Otro</option>
            </select>
            <input type="text" name="parentesco_otro" id="parentesco_otro"
                  placeholder="Especifique parentesco" style="display:none;">
          </div>

          <!-- Ocupación -->
          <div class="col-md-6">
            <label>Ocupación</label>
            <select name="ocupacion" id="ocupacion" required>
              <option value="">Seleccione</option>
              <option value="Empleado">Empleado(a)</option>
              <option value="Independiente">Independiente</option>
              <option value="Comerciante">Comerciante</option>
              <option value="Ama de casa">Ama de casa</option>
              <option value="Estudiante">Estudiante</option>
              <option value="Docente">Docente</option>
              <option value="Profesional">Profesional</option>
              <option value="Obrero">Obrero(a)</option>
              <option value="Conductor">Conductor(a)</option>
              <option value="Agricultor">Agricultor(a)</option>
              <option value="Pensionado">Pensionado(a)</option>
              <option value="Desempleado">Desempleado(a)</option>
              <option value="Otro">Otro</option>
            </select>
            <input type="text" name="ocupacion_otro" id="ocupacion_otro"
                  placeholder="Especifique ocupación" style="display:none;">
          </div>
        </div>

        <div class="form-navigation">
          <button type="button" class="prev-btn">Anterior</button>
          <button type="button" class="next-btn">Siguiente</button>
        </div>
      </div>


      <!-- Paso 3: Colegio -->
      <div class="form-step">
        <h5>Información del Colegio</h5>
        <div class="row">
          <div class="col-md-6">
            <label>Colegio</label>
            <select name="colegio_id" id="colegio_id" required>
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
            <select name="grado" id="grado" required>
              <option value="">Seleccione grado</option>
            </select>
          </div>
          <div class="col-md-6">
            <label>Grupo</label>
            <input type="text" name="grupo">
          </div>
          <div class="col-md-6">
            <label>Jornada</label>
            <select name="jornada" id="jornada" required>
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
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script> <!-- Español -->
  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
  <script src="/js/crearE.js"></script>
</body>
</html>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
