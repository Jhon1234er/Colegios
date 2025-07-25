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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f9f9f9;
    }
    .form-container {
      max-width: 900px;
      margin: auto;
      background-color: white;
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      margin-top: 40px;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2 class="mb-4 text-center">Registro de Estudiante</h2>
    <form action="/?page=estudiantes&action=guardar" method="POST">

      <h5>Información del Estudiante</h5>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label>Nombres</label>
          <input type="text" name="nombres" class="form-control" required>
        </div>
        <div class="col-md-6 mb-3">
          <label>Apellidos</label>
          <input type="text" name="apellidos" class="form-control" required>
        </div>
        <div class="col-md-4 mb-3">
          <label>Tipo de Documento</label>
          <select name="tipo_documento" class="form-control" required>
            <option value="">Seleccione</option>
            <option value="TI">TI</option>
            <option value="CC">CC</option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label>Número de Documento</label>
          <input type="text" name="numero_documento" class="form-control" required>
        </div>
        <div class="col-md-4 mb-3">
          <label>Fecha de Nacimiento</label>
          <input type="date" name="fecha_nacimiento" class="form-control" required max="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label>Correo Electrónico</label>
          <input type="email" name="correo_electronico" class="form-control" required>
        </div>
        <div class="col-md-4 mb-3">
          <label>Teléfono</label>
          <input type="text" name="telefono" class="form-control">
        </div>
        <div class="col-md-4 mb-3">
          <label>Dirección</label>
          <input type="text" name="direccion" class="form-control">
        </div>
        <div class="col-md-4 mb-3">
          <label>Género</label>
          <select name="genero" class="form-control" required>
            <option value="">Seleccione</option>
            <option value="M">Masculino</option>
            <option value="F">Femenino</option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label>Contraseña</label>
          <input type="password" name="password" class="form-control" required>
        </div>
      </div>

      <h5 class="mt-4">Información Académica</h5>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label>Colegio</label>
          <select name="colegio_id" class="form-control" required>
            <option value="">Seleccione un colegio</option>
            <?php foreach ($colegios as $colegio): ?>
              <option value="<?= $colegio['id'] ?>"><?= $colegio['nombre'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 mb-3">
          <label>Grado</label>
          <input type="text" name="grado" class="form-control" required>
        </div>
        <div class="col-md-2 mb-3">
          <label>Grupo</label>
          <input type="text" name="grupo" class="form-control" required>
        </div>
        <div class="col-md-2 mb-3">
          <label>Jornada</label>
          <select name="jornada" class="form-control" required>
            <option value="">Seleccione</option>
            <option value="Mañana">Mañana</option>
            <option value="Tarde">Tarde</option>
            <option value="Noche">Noche</option>
          </select>
        </div>
      </div>

      <h5 class="mt-4">Información del Acudiente</h5>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label>Nombre completo del Acudiente</label>
          <input type="text" name="nombre_completo_acudiente" class="form-control" required>
        </div>
        <div class="col-md-3 mb-3">
          <label>Tipo de Documento</label>
          <select name="tipo_documento_acudiente" class="form-control" required>
            <option value="">Seleccione</option>
            <option value="CC">CC</option>
            <option value="TI">TI</option>
          </select>
        </div>
        <div class="col-md-3 mb-3">
          <label>Número de Documento</label>
          <input type="text" name="numero_documento_acudiente" class="form-control" required>
        </div>
        <div class="col-md-4 mb-3">
          <label>Teléfono</label>
          <input type="text" name="telefono_acudiente" class="form-control" required>
        </div>
        <div class="col-md-4 mb-3">
          <label>Parentesco</label>
          <input type="text" name="parentesco" class="form-control" required>
        </div>
        <div class="col-md-4 mb-3">
          <label>Ocupación</label>
          <input type="text" name="ocupacion" class="form-control" required>
        </div>
      </div>

      <button type="submit" class="btn btn-primary mt-3">Registrar Estudiante</button>
    </form>
  </div>

  <!-- En tu crear.php -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="public/js/crearE.js"></script>
<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>

</body>
</html>
