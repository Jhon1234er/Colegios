<?php require_once __DIR__ . '/../Componentes/encabezado.php'; ?>
<link rel="stylesheet" href="/css/Aprendiz/lista_estudiante.css">

<style>
  body {
    padding-bottom: 96px;
  }
  .pendientes-wrapper {
    max-width: 1480px;
    margin: 24px auto 120px;
    padding: 0 32px;
  }
  .pendientes-header {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:10px;
  }
  .pendientes-titulo {
    margin:0;
    font-size:1.55rem;
    font-weight:700;
    color:#0f172a;
  }
  .pendientes-sub {
    margin:4px 0 0;
    font-size:.9rem;
    color:#64748b;
  }
  .pendientes-wrapper h2::after {
    display:none;
  }
  .btn-volver {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border-radius:999px;
    padding:8px 20px;
    font-weight:600;
    border:1px solid #e5e7eb;
    background:#f9fafb;
    color:#111827;
    font-size:.85rem;
    text-decoration:none;
    box-shadow:0 4px 10px rgba(15,23,42,.10);
    cursor:pointer;
  }
  .btn-volver:hover {
    background:#e5e7eb;
  }
  .success-message {
    background: #10b981;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: 500;
  }
</style>

<div class="pendientes-wrapper">
  <div class="pendientes-header">
    <div>
      <h2 class="pendientes-titulo">Aprendices Pendientes</h2>
      <p class="pendientes-sub">Lista de aprendices registrados sin ficha asignada</p>
    </div>
    <div>
      <a href="/?page=dashboard_profesor" class="btn-volver">
        ← Volver a Mis Fichas
      </a>
    </div>
  </div>

  <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
    <div class="success-message">
      ✅ ¡Aprendiz registrado exitosamente en lista de pendientes!
    </div>
  <?php endif; ?>

  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Nombre Completo</th>
          <th>Documento</th>
          <th>Colegio</th>
          <th>Grado</th>
          <th>Jornada</th>
          <th>Fecha de Registro</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pendientes)): ?>
          <tr>
            <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
              No hay aprendices pendientes en este momento.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($pendientes as $pendiente): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($pendiente['nombres'] . ' ' . $pendiente['apellidos']) ?></strong>
              </td>
              <td><?= htmlspecialchars($pendiente['tipo_documento']) ?> - <?= htmlspecialchars($pendiente['numero_documento']) ?></td>
              <td><?= htmlspecialchars($pendiente['colegio']) ?></td>
              <td><?= htmlspecialchars($pendiente['grado']) ?></td>
              <td><?= htmlspecialchars($pendiente['jornada']) ?></td>
              <td><?= !empty($pendiente['fecha_registro']) ? date('d/m/Y', strtotime($pendiente['fecha_registro'])) : 'N/A' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
