<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
?>
<link rel="stylesheet" href="/css/Ficha/estilos_excel.css">

<div class="importar-container">
  <div class="card-widget">
    <h2 class="section-title">Importar Estudiantes Desde Excel</h2>

  <p class="importar-descripcion">
      Descarga la plantilla, completa los datos y súbela aquí para registrar múltiples aprendices.
    </p>

  <div class="importar-descarga">
      <a class="btn btn-light" href="/?page=plantilla_import_estudiantes">Descargar plantilla</a>
    </div>

    <?php if (!empty($_GET['error'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

  <form class="importar-form" method="POST" action="/?page=estudiantes&action=importar_excel" enctype="multipart/form-data" style="margin-top:10px;">
      <?= csrf_input(); ?>

      <div class="row">
        <div class="col-md-6">
          <label class="importar-label">Colegio</label>
          <select name="colegio_id" class="form-control" required>
            <option value="">Seleccione un colegio</option>
            <?php foreach (($colegios ?? []) as $col): ?>
              <option value="<?= htmlspecialchars($col['id']) ?>" <?= (!empty($colegio_pre) && $colegio_pre == $col['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($col['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="importar-label">Ficha</label>
          <select name="ficha_id" class="form-control" required>
            <option value="">Seleccione una ficha</option>
            <?php foreach (($fichas ?? []) as $f): ?>
              <option value="<?= htmlspecialchars($f['id']) ?>" <?= (!empty($ficha_id_pre) && $ficha_id_pre == $f['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($f['nombre']) ?> (<?= htmlspecialchars($f['numero'] ?? '-') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-12 importar-archivo">
          <label class="importar-label">Archivo Excel (.xlsx, .xls, .csv)</label>
          <input type="file" name="archivo_excel" class="form-control" accept=".xlsx,.xls,.csv" required>
          <small class="importar-small">Campos obligatorios: con "*" <br> Campos opcionales: sin "*"</small>
        </div>
      </div>

  <div class="importar-botones">
        <button type="submit" class="btn btn-warning">Importar</button>
  <a href="javascript:history.back()" class="btn btn-cancelar">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
