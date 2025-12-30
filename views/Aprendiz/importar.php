<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
?>
<link rel="stylesheet" href="/css/Ficha/estilos_excel.css">

  <div class="importar-container">
    <div class="card-widget">
      <h2 class="section-title">Importar Aprendices Desde Excel</h2>

  <p class="importar-descripcion">
      Descarga la plantilla, completa los datos y súbela aquí para registrar múltiples aprendices.
    </p>

  <div class="importar-descarga">
      <a class="btn btn-light" href="/?page=plantilla_import_estudiantes">Descargar plantilla</a>
    </div>

    <?php if (!empty($_GET['error'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['import_ok'])): ?>
      <?php 
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $creados    = (int)($_GET['creados'] ?? 0);
        $saltados   = (int)($_GET['saltados'] ?? 0);
        $duplicados = (int)($_GET['duplicados'] ?? 0);
        $errores    = $_SESSION['import_errores'] ?? [];
        unset($_SESSION['import_errores']);
      ?>
      <div class="alert importar-success" style="margin:12px 0;">
        <strong>Resumen de importación:</strong>
        <div>✓ Creados: <strong><?= $creados ?></strong> · ⚠️ Saltados: <strong><?= $saltados ?></strong> · ⛔ Duplicados: <strong><?= $duplicados ?></strong></div>
        <?php if (!empty($errores)): ?>
          <details style="margin-top:8px;">
            <summary style="cursor:pointer;">Ver detalles de errores (<?= count($errores) ?>)</summary>
            <ul style="margin-top:6px;">
              <?php foreach ($errores as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <form class="importar-form" method="POST" action="/?page=aprendices&action=importar_excel" enctype="multipart/form-data" style="margin-top:10px;">
      <?= csrf_input(); ?>

      <?php if (!empty($ficha_id_pre)): ?>
        <input type="hidden" name="ficha_id" value="<?= htmlspecialchars($ficha_id_pre) ?>">
      <?php endif; ?>

      <div class="row">
        <div class="col-md-12 importar-archivo">
          <label class="importar-label">Archivo Excel (.xlsx, .xls, .csv)</label>
          <input type="file" name="archivo_excel" class="form-control" accept=".xlsx,.xls,.csv" required>
          <small class="importar-small">Campos obligatorios: con "*" <br> Campos opcionales: sin "*"<br>El colegio de cada aprendiz se debe diligenciar en la columna "colegio" de la plantilla.</small>
        </div>
      </div>

  <div class="importar-botones">
        <button type="submit" class="btn btn-warning importar-submit">Importar</button>
  <a href="javascript:history.back()" class="btn btn-cancelar">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<div class="importar-loading-overlay" id="importar-loading-overlay">
  <div class="importar-loading-modal">
    <div class="importar-spinner"></div>
    <div class="importar-loading-text">Procesando importación, por favor espera...</div>
  </div>
</div>

<script>
  (function() {
    var form = document.querySelector('.importar-form');
    if (!form) return;

    var overlay = document.getElementById('importar-loading-overlay');
    var submitBtn = form.querySelector('.importar-submit');

    form.addEventListener('submit', function() {
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('is-loading');
      }
      if (overlay) {
        overlay.classList.add('is-visible');
      }
    });
  })();
</script>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
