<?php
require_once __DIR__ . '/../../helpers/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reportes</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-10">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0"><i class="fa-solid fa-chart-bar me-2 text-primary"></i>Reportes</h3>
            <a class="btn btn-outline-primary" href="/?page=calendario">
              <i class="fa-regular fa-calendar-days me-1"></i> Ir al calendario
            </a>
          </div>

          <p class="text-muted">Selecciona un rango en el calendario y usa el botón "Exportar reporte" para descargar un CSV con tus clases programadas.</p>

          <div class="alert alert-info">
            <i class="fa-solid fa-circle-info me-2"></i>
            Esta es una vista base. Si deseas, puedo agregar filtros y descargas directas aquí.
          </div>

          <ul>
            <li>Reporte por período actual del calendario.</li>
            <li>Filtro por estado y ficha (usando los filtros del calendario).</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
