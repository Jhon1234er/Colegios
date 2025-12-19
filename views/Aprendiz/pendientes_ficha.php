<?php require_once __DIR__ . '/../Componentes/encabezado.php'; ?>
<link rel="stylesheet" href="/css/Aprendiz/lista_estudiante.css">

<style>
  body {
    padding-bottom: 96px; /* evita que el footer fijo "cubra" la última fila y la paginación */
  }
  .pendientes-wrapper {
    max-width: 1480px;
    margin: 24px auto 120px; /* más espacio abajo para permitir un pequeño scroll extra */
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
    display:none; /* quitar línea decorativa del título solo en esta vista */
  }
  .btn-volver-ficha {
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
  .btn-volver-ficha:hover {
    background:#e5e7eb;
    text-decoration:none;
  }
  .pendientes-table-wrap {
    margin-top:10px;
    border-radius:18px;
    overflow:hidden;
    box-shadow:0 10px 30px rgba(15,23,42,.08);
    background:#fff;
    position:relative;
  }
  .pendientes-table {
    width:100%;
    border-collapse:collapse;
    font-size:.9rem;
    table-layout:auto;
  }
  .pendientes-table thead {
    background:#f3f4f6;
  }
  .pendientes-table th,
  .pendientes-table td {
    padding:10px 12px;
    border-bottom:1px solid #e5e7eb;
    vertical-align:middle;
  }
  .pendientes-table th {
    font-weight:700;
    color:#4b5563;
    font-size:.8rem;
    text-transform:uppercase;
    letter-spacing:.04em;
  }
  .pendientes-table tbody tr:nth-child(even) {
    background:#f9fafb;
  }
  .pendientes-table tbody tr:hover {
    background:#eef2ff;
  }
  .pendientes-table tbody tr:last-child td {
    padding-bottom:18px; /* espacio extra para que la última fila no se vea "cortada" al final */
  }
  .col-doc { width: 260px; }
  .col-colegio { min-width: 380px; }
  .col-jornada { width: 110px; text-align:center; }
  .col-asignar { width: 190px; text-align:center; }

  .col-doc .documento,
  .col-jornada .chip-jornada,
  .col-asignar .btn-asignar {
    white-space:nowrap;
  }

  .modal-asignar-backdrop {
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.45);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:1050;
  }
  .modal-asignar {
    background:#ffffff;
    border-radius:20px;
    padding:18px 20px 16px;
    width:100%;
    max-width:380px;
    box-shadow:0 24px 60px rgba(15,23,42,.55);
    font-size:.9rem;
  }
  .modal-asignar h5 {
    margin:0 0 6px;
    font-weight:700;
    font-size:1rem;
    color:#0f172a;
  }
  .modal-asignar p {
    margin:0 0 14px;
    color:#4b5563;
  }
  .modal-asignar .nombre-aprendiz {
    font-weight:600;
    color:#111827;
  }
  .modal-asignar-buttons {
    display:flex;
    justify-content:flex-end;
    gap:8px;
  }
  .btn-modal-cancelar,
  .btn-modal-confirmar {
    border-radius:999px;
    padding:6px 14px;
    font-size:.8rem;
    font-weight:600;
    border:1px solid transparent;
    cursor:pointer;
  }
  .btn-modal-cancelar {
    background:#ffffff;
    border-color:#d1d5db;
    color:#374151;
  }
  .btn-modal-cancelar:hover {
    background:#f3f4f6;
  }
  .btn-modal-confirmar {
    background:#22c55e;
    border-color:#16a34a;
    color:#ecfdf3;
    box-shadow:0 8px 20px rgba(34,197,94,.45);
  }
  .btn-modal-confirmar:hover {
    background:#16a34a;
  }

  .chip-grado,
  .chip-jornada {
    display:inline-block;
    padding:3px 10px;
    border-radius:999px;
    font-size:.75rem;
    font-weight:600;
    letter-spacing:.02em;
  }
  .chip-grado {
    background:#ecfdf3;
    color:#166534;
  }
  .chip-jornada {
    background:#eff6ff;
    color:#1d4ed8;
  }
  .btn-asignar {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:7px 14px;
    border-radius:999px;
    font-size:.8rem;
    font-weight:700;
    border:none;
    background:#2563eb;
    color:#fff;
    box-shadow:0 6px 14px rgba(37,99,235,.35);
    text-decoration:none;
    transition:transform .1s ease, box-shadow .1s ease, background .1s ease;
  }
  .btn-asignar:hover {
    background:#1d4ed8;
    transform:translateY(-1px);
    box-shadow:0 10px 20px rgba(37,99,235,.35);
  }
  .btn-asignar span.icon {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:18px;
    height:18px;
    border-radius:999px;
    background:rgba(255,255,255,.16);
    font-size:.9rem;
  }
  .pendientes-pager {
    display:flex;
    justify-content:flex-end;
    align-items:center;
    gap:8px;
    padding:12px 16px;
    background:#f9fafb;
    border-top:1px solid #e5e7eb;
    position:sticky;
    bottom:80px; /* se mantiene por encima del footer fijo y no corta la última fila */
    z-index:20;
  }
  .pendientes-pager a {
    min-width:34px;
    height:34px;
    border-radius:999px;
    border:1px solid #cbd5e1;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:.85rem;
    font-weight:600;
    color:#111827;
    text-decoration:none;
    padding:0 12px;
    background:#ffffff;
    box-shadow:0 2px 6px rgba(15,23,42,.10);
    transition:background-color .15s ease, color .15s ease, border-color .15s ease, box-shadow .15s ease;
  }
  .pendientes-pager a:hover {
    background:#eff6ff;
    border-color:#2563eb;
    color:#1d4ed8;
    box-shadow:0 4px 10px rgba(37,99,235,.25);
  }
  .pendientes-pager a.active {
    background:#2563eb;
    border-color:#2563eb;
    font-weight:700;
    color:#ffffff;
    box-shadow:0 6px 14px rgba(37,99,235,.40);
  }
</style>

<div class="pendientes-wrapper">
  <div class="pendientes-header">
    <div>
      <h2 class="pendientes-titulo">Aprendices pendientes</h2>
      <p class="pendientes-sub">
        Selecciona un aprendiz en estado pendiente para asignarlo a la ficha
        <strong><?= htmlspecialchars($ficha['numero'] ?? $ficha['id'] ?? '') ?> - <?= htmlspecialchars($ficha['nombre'] ?? '') ?></strong>.
      </p>
    </div>
    <div>
      <a href="/?page=fichas&action=ver&id=<?= urlencode($ficha['id']) ?>" class="btn-volver-ficha">Volver a la ficha</a>
    </div>
  </div>

  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger" style="margin-bottom:12px;">
      <?= htmlspecialchars($_GET['error']) ?>
    </div>
  <?php endif; ?>

  <?php
    // Variables de paginación calculadas en el controlador
    $page       = $page ?? 1;
    $totalPages = $totalPages ?? 1;
    $pendientesPagina = $pendientesPagina ?? ($pendientes ?? []);
  ?>

  <?php if (empty($pendientesPagina)): ?>
    <div class="alert alert-info" style="margin-top:12px;">
      No hay aprendices en estado pendiente para asignar.
    </div>
  <?php else: ?>
    <div class="pendientes-table-wrap">
      <table class="pendientes-table">
        <thead>
          <tr>
            <th>Nombre completo</th>
            <th class="col-doc">Documento</th>
            <th class="col-colegio">Colegio</th>
            <th>Grado</th>
            <th class="col-jornada">Jornada</th>
            <th class="col-asignar">Asignar</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendientesPagina as $p): ?>
            <?php
              $tdoc = trim((string)($p['tipo_documento'] ?? ''));
              $ndoc = trim((string)($p['numero_documento'] ?? ''));
              $doc  = ($tdoc !== '' ? $tdoc . '. ' : '') . $ndoc;
            ?>
            <tr>
              <td><span class="estudiante-name"><?= htmlspecialchars(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? '')) ?></span></td>
              <td><span class="documento"><?= htmlspecialchars($doc) ?></span></td>
              <td><span class="colegio"><?= htmlspecialchars($p['colegio'] ?? '') ?></span></td>
              <td>
                <span class="chip-grado"><?= htmlspecialchars($p['grado'] ?? '') . ' ' . htmlspecialchars($p['grupo'] ?? '') ?></span>
              </td>
              <td class="col-jornada">
                <span class="chip-jornada"><?= htmlspecialchars($p['jornada'] ?? '') ?></span>
              </td>
              <td class="col-asignar">
                <a href="/?page=aprendices&action=asignar_pendiente&ficha_id=<?= urlencode($ficha['id']) ?>&usuario_id=<?= urlencode($p['id']) ?>"
                   class="btn-asignar"
                   data-nombre="<?= htmlspecialchars(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? '')) ?>">
                  <span class="icon">+</span>
                  <span>Agregar a ficha</span>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
        <div class="pendientes-pager">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="/?page=aprendices&action=pendientes_ficha&ficha_id=<?= urlencode($ficha['id']) ?>&p=<?= $i ?>"
               class="<?= $i === $page ? 'active' : '' ?>">
              <?= $i ?>
            </a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div id="modalConfirmAsignar" class="modal-asignar-backdrop" style="display:none;">
  <div class="modal-asignar" role="dialog" aria-modal="true">
    <h5>Agregar a la ficha</h5>
    <p>¿Deseas agregar a <span class="nombre-aprendiz" data-role="nombre-aprendiz">este aprendiz</span> a la ficha seleccionada?</p>
    <div class="modal-asignar-buttons">
      <button type="button" class="btn-modal-cancelar" data-role="cancelar-asignar">Cancelar</button>
      <button type="button" class="btn-modal-confirmar" data-role="confirmar-asignar">Agregar</button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const modal = document.getElementById('modalConfirmAsignar');
  if (!modal) return;

  const spanNombre = modal.querySelector('[data-role="nombre-aprendiz"]');
  const btnCancelar = modal.querySelector('[data-role="cancelar-asignar"]');
  const btnConfirmar = modal.querySelector('[data-role="confirmar-asignar"]');
  let urlDestino = null;

  document.querySelectorAll('.btn-asignar').forEach(function(btn){
    btn.addEventListener('click', function(ev){
      ev.preventDefault();
      urlDestino = this.getAttribute('href');
      const nombre = this.getAttribute('data-nombre') || 'este aprendiz';
      if (spanNombre) spanNombre.textContent = nombre;
      modal.style.display = 'flex';
    });
  });

  function cerrarModal(){
    modal.style.display = 'none';
    urlDestino = null;
  }

  if (btnCancelar) {
    btnCancelar.addEventListener('click', cerrarModal);
  }

  if (btnConfirmar) {
    btnConfirmar.addEventListener('click', function(){
      if (urlDestino) {
        window.location.href = urlDestino;
      }
    });
  }

  modal.addEventListener('click', function(ev){
    if (ev.target === modal) {
      cerrarModal();
    }
  });
});
</script>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
