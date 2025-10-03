<?php
require_once __DIR__ . '/../../helpers/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistencias</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-8+6Yv9bqA2eS9Zp2iQF0QK0yDcnmDMT8kN7e3m3qkqK0NYzv2iYwC1VZyq3G1D7W6w3QZCk3dXbju1h8Cw0K1w==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body { background:#f7f9fb; }
        .card { border: none; box-shadow: 0 4px 14px rgba(0,0,0,.06); }
    </style>
</head>
<body>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-10">
      <div class="card">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0"><i class="fa-solid fa-clipboard-check me-2 text-primary"></i>Asistencias</h3>
            <a class="btn btn-outline-primary" href="/?page=calendario">
              <i class="fa-regular fa-calendar-days me-1"></i> Ir al calendario
            </a>
          </div>
          <p class="text-muted mb-4">
            Para registrar asistencias, abre un evento desde el calendario y utiliza la pestaña "Asistencias" del detalle.
          </p>

          <div class="alert alert-info">
            <div class="d-flex align-items-start">
              <i class="fa-solid fa-circle-info me-2 mt-1"></i>
              <div>
                <strong>Tip:</strong> Si no ves la lista de estudiantes, asegúrate de que la clase esté <em>en curso</em> y que la ficha tenga estudiantes asociados.
              </div>
            </div>
          </div>

          <div class="text-center text-muted py-5">
            <i class="fa-regular fa-calendar-check fa-3x mb-3"></i>
            <p class="mb-1">Esta pantalla es una vista base de asistencias.</p>
            <p class="mb-0">Usa el calendario para gestionar y registrar la asistencia de cada clase.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
