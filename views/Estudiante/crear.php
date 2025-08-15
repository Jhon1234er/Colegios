<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
require_once __DIR__ . '/../../models/Colegio.php';
$colegioModel = new Colegio();
$colegios = $colegioModel->obtenerTodos();
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
          <div class="col-md-6">
            <label>Parentesco</label>
            <input type="text" name="parentesco" required>
          </div>
          <div class="col-md-6">
            <label>Ocupación</label>
            <input type="text" name="ocupacion" required>
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
                        data-grados="<?= htmlspecialchars($colegio['grados']) ?>"
                        data-jornada="<?= htmlspecialchars($colegio['jornada']) ?>">
                  <?= $colegio['nombre'] ?>
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
          <div class="col-md-6">
            <label>Ficha</label>
            <select name="ficha_id" id="ficha_id" required>
              <option value="">Seleccione un colegio primero</option>
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
  <script src="/js/fichas_por_colegio.js"></script>
</body>
</html>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
