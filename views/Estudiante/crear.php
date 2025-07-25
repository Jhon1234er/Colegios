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
  <title>Registro de Estudiante</title>
  <link rel="stylesheet" href="/css/crear.css">
</head>
<body>
  <div class="container">
    <h2>Registro de Estudiante</h2>
    <form action="/?page=estudiantes&action=guardar" method="POST">

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
          <input type="date" name="fecha_nacimiento" required max="<?= date('Y-m-d') ?>">
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

      <h5>Información Académica</h5>
      <div class="row">
        <div class="col-md-6">
          <label>Colegio</label>
          <select name="colegio_id" required>
            <option value="">Seleccione un colegio</option>
            <?php foreach ($colegios as $colegio): ?>
              <option value="<?= $colegio['id'] ?>"><?= $colegio['nombre'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label>Grado</label>
          <input type="text" name="grado" required>
        </div>
        <div class="col-md-6">
          <label>Grupo</label>
          <input type="text" name="grupo" required>
        </div>
        <div class="col-md-6">
          <label>Jornada</label>
          <select name="jornada" required>
            <option value="">Seleccione</option>
            <option value="Mañana">Mañana</option>
            <option value="Tarde">Tarde</option>
            <option value="Noche">Noche</option>
          </select>
        </div>
      </div>

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
            <option value="TI">TI</option>
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

      <button type="submit" class="btn">Registrar Estudiante</button>
    </form>
  </div>

  <!-- Librerías opcionales -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
  <script src="public/js/crearE.js"></script>
  <?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
</body>
</html>
