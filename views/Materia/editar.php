<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../Componentes/encabezado.php';
?>
<link rel="stylesheet" href="/css/Materia/crear.css?v=2">

<div class="container">
  <div class="card-widget">
    <h2 class="section-title">Actualizar Curso</h2>

    <?php if (!isset($materia) || empty($materia)) : ?>
      <div class="alert alert-danger">No se encontró la información del curso.</div>
    <?php else: ?>
      <form method="POST" action="?page=materias&action=actualizar">
        <?= csrf_input(); ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars($materia['id']) ?>">

        <div class="row g-3" style="gap:16px;">
          <div class="col-md-2 field">
            <label class="form-label">Código</label>
            <input type="text" name="codigo" class="form-control" value="<?= htmlspecialchars($materia['codigo'] ?? '') ?>">
          </div>
          <div class="col-md-6 field">
            <label class="form-label">Denominación</label>
            <input type="text" name="denominacion" class="form-control" value="<?= htmlspecialchars($materia['denominacion'] ?? ($materia['nombre'] ?? '')) ?>">
          </div>
          <div class="col-md-2 field">
            <label class="form-label">Duración</label>
            <input type="text" name="duracion" class="form-control" value="<?= htmlspecialchars($materia['duracion'] ?? '') ?>" placeholder="Ej: 144 horas">
          </div>
          <div class="col-md-2 field">
            <label class="form-label">Versión</label>
            <input type="number" name="version" class="form-control" value="<?= htmlspecialchars($materia['version'] ?? '') ?>">
          </div>
          <div class="col-md-6 field">
            <label class="form-label">Línea Tecnoacademia</label>
            <input type="text" name="linea_tecnoacademia" class="form-control" value="<?= htmlspecialchars($materia['linea_tecnoacademia'] ?? '') ?>">
          </div>
          <div class="col-md-6 field">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($materia['descripcion'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="row" style="justify-content:flex-end;gap:12px;margin-top:20px;">
          <button type="submit" class="btn btn-warning btn-size">Guardar Cambios</button>
          <a href="/?page=materias" class="btn btn-light btn-size">Cancelar</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
