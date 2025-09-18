<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
?>
<link rel="stylesheet" href="/css/crear.css">

<div class="container">
  <div class="card-widget">
    <h2 class="section-title">Importar Estudiantes desde Excel</h2>

    <p style="color:#475569; margin-top:4px;">
      Descarga la plantilla, completa los datos y súbela aquí para registrar múltiples aprendices.
    </p>

    <div style="margin:12px 0; display:flex; gap:8px; flex-wrap:wrap;">
      <a class="btn btn-light" href="/?page=plantilla_import_estudiantes">Descargar plantilla ejemplo</a>
    </div>

    <?php if (!empty($_GET['error'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <form method="POST" action="/?page=estudiantes&action=importar_excel" enctype="multipart/form-data" style="margin-top:10px;">
      <?= csrf_input(); ?>

      <div class="row">
        <div class="col-md-6">
          <label style="font-weight:600;">Colegio</label>
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
          <label style="font-weight:600;">Ficha</label>
          <select name="ficha_id" class="form-control" required>
            <option value="">Seleccione una ficha</option>
            <?php foreach (($fichas ?? []) as $f): ?>
              <option value="<?= htmlspecialchars($f['id']) ?>" <?= (!empty($ficha_id_pre) && $ficha_id_pre == $f['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($f['nombre']) ?> (<?= htmlspecialchars($f['numero'] ?? '-') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-12" style="margin-top:12px;">
          <label style="font-weight:600;">Archivo Excel (.xlsx, .xls, .csv)</label>
          <input type="file" name="archivo_excel" class="form-control" accept=".xlsx,.xls,.csv" required>
          <small style="color:#64748b;">Las columnas mínimas requeridas son: nombres, apellidos, tipo_documento, numero_documento, genero, grado, jornada. Otras son opcionales.</small>
        </div>
      </div>

      <div style="display:flex; gap:10px; margin-top:16px;">
        <button type="submit" class="btn btn-warning">Importar</button>
        <a href="javascript:history.back()" class="btn btn-light">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
