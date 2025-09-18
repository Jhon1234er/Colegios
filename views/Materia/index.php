<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../Componentes/encabezado.php';
?>
<link rel="stylesheet" href="/css/Materia/crear.css?v=2">

<div class="container">
  <div class="card-widget">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
      <h2 class="section-title" style="margin:0;">Listado de Cursos</h2>
      <a href="/?page=materias&action=crear" class="btn btn-primary">+ Registrar Cursos</a>
    </div>

    <?php
      // Variables de paginación provenientes del controlador
      $perPage = $perPage ?? 15;
      $totalMaterias = $totalMaterias ?? (is_array($materias) ? count($materias) : 0);
      $pageNum = $pageNum ?? 1;
      $totalPages = $totalPages ?? 1;
      $offset = $offset ?? 0;
      $from = $totalMaterias ? ($offset + 1) : 0;
      $to = min($offset + $perPage, $totalMaterias);
    ?>

    <!-- Toolbar de filtros -->
    <form method="GET" action="" style="display:flex;gap:10px;align-items:flex-end;margin-top:14px;flex-wrap:wrap;">
      <input type="hidden" name="page" value="materias">
      <div>
        <label style="display:block;color:#475569;font-weight:600;margin-bottom:4px;">Buscar</label>
        <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Código o denominación" style="min-width:240px;">
      </div>
      <div>
        <label style="display:block;color:#475569;font-weight:600;margin-bottom:4px;">Estado</label>
        <select class="form-control" name="estado">
          <option value="">Todos</option>
          <option value="activa" <?= (($estado ?? '')==='activa')?'selected':'' ?>>Activa</option>
          <option value="suspendida" <?= (($estado ?? '')==='suspendida')?'selected':'' ?>>Suspendida</option>
        </select>
      </div>
      <div>
        <label style="display:block;color:#475569;font-weight:600;margin-bottom:4px;">Línea</label>
        <select class="form-control" name="linea" style="min-width:220px;">
          <option value="">Todas</option>
          <?php if (!empty($lineas)) foreach ($lineas as $ln): ?>
            <option value="<?= htmlspecialchars($ln) ?>" <?= (($linea ?? '')===$ln)?'selected':'' ?>><?= htmlspecialchars($ln) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="btn btn-warning btn-size">Filtrar</button>
        <?php $isFiltering = !empty($q) || !empty($estado) || !empty($linea); ?>
        <a href="/?page=materias" class="<?= $isFiltering ? 'btn btn-warning btn-size' : 'btn btn-light btn-size' ?>">Limpiar</a>
      </div>
    </form>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
      <div style="font-weight:600;color:#475569;">
        Total cursos: <?= (int)$totalMaterias ?>
      </div>
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="color:#64748b;">Mostrando <?= (int)$from ?>–<?= (int)$to ?> de <?= (int)$totalMaterias ?></span>
        <div>
          <?php $prev = max(1, $pageNum - 1); $next = min($totalPages, $pageNum + 1); $qs = http_build_query(array_filter(['page'=>'materias','q'=>$q??'','estado'=>$estado??'','linea'=>$linea??''])); ?>
          <a class="btn btn-light btn-size" href="/?<?= $qs ?>&p=<?= $prev ?>" <?= $pageNum <= 1 ? 'style="pointer-events:none;opacity:.6;"' : '' ?>>
            ◀
          </a>
          <span style="margin:0 6px; color:#334155; font-weight:600;"><?= (int)$pageNum ?> / <?= (int)$totalPages ?></span>
          <a class="btn btn-light btn-size" href="/?<?= $qs ?>&p=<?= $next ?>" <?= $pageNum >= $totalPages ? 'style="pointer-events:none;opacity:.6;"' : '' ?>>
            ▶
          </a>
        </div>
      </div>
    </div>

    <?php if (empty($materias)): ?>
      <div class="alert alert-info" style="margin-top:16px;">No hay cursos registrados.</div>
    <?php else: ?>
      <div class="table-responsive" style="margin-top:12px;">
        <table class="tabla-lista">
          <thead>
            <tr>
              <th>Código</th>
              <th>Denominación</th>
              <th>Duración</th>
              <th>Versión</th>
              <th>Línea Tecnoacademia</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($materias as $m): ?>
              <tr>
                <td><?= htmlspecialchars($m['codigo'] ?? '') ?></td>
                <td><?= htmlspecialchars($m['denominacion'] ?? ($m['nombre'] ?? '')) ?></td>
                <td><?= htmlspecialchars($m['duracion'] ?? '') ?></td>
                <td><?= htmlspecialchars($m['version'] ?? '') ?></td>
                <td><?= htmlspecialchars($m['linea_tecnoacademia'] ?? '') ?></td>
                <td>
                  <?php $estado = $m['estado'] ?? 'activa'; ?>
                  <?php if ($estado === 'suspendida'): ?>
                    <span class="badge badge-danger">Suspendida</span>
                  <?php else: ?>
                    <span class="badge badge-success">Activa</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="/?page=materias&action=editar&id=<?= urlencode($m['id']) ?>" class="btn-accion btn-warning btn-size">Actualizar</a>
                  <?php if (($m['estado'] ?? 'activa') === 'suspendida'): ?>
                    <a href="/?page=materias&action=activar&id=<?= urlencode($m['id']) ?>" class="btn-accion btn-activar btn-size btn-confirm" data-action="activar">Activar</a>
                  <?php else: ?>
                    <a href="/?page=materias&action=suspender&id=<?= urlencode($m['id']) ?>" class="btn-accion btn-danger btn-size btn-confirm" data-action="suspender">Suspender</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal de confirmación -->
<div id="confirmModal" class="modal-overlay" style="display:none;">
  <div class="modal-card">
    <h3 class="modal-title">Confirmar acción</h3>
    <p id="confirmText">¿Deseas continuar?</p>
    <div class="modal-actions">
      <button id="modalCancel" class="btn btn-light btn-size" type="button">Cancelar</button>
      <a id="modalConfirm" href="#" class="btn btn-danger btn-size">Confirmar</a>
    </div>
  </div>
  <div class="modal-backdrop"></div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const modal = document.getElementById('confirmModal');
  const confirmText = document.getElementById('confirmText');
  const modalConfirm = document.getElementById('modalConfirm');
  const modalCancel = document.getElementById('modalCancel');

  function openModal(message, href, confirmClass) {
    confirmText.textContent = message;
    modalConfirm.href = href;
    modalConfirm.className = 'btn ' + confirmClass + ' btn-size';
    modal.style.display = 'flex';
    modal.style.pointerEvents = 'auto';
  }
  function closeModal(){
    modal.style.display = 'none';
    modal.style.pointerEvents = 'none';
  }

  document.querySelectorAll('.btn-confirm').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault();
      const href = this.getAttribute('href');
      const action = this.dataset.action; // 'suspender' | 'activar'
      const msgSuspend = 'Al suspender, se desactivarán todos los elementos relacionados (colegios, instructores/facilitadores, fichas, etc.). ¿Deseas continuar?';
      const msgActivar = 'Se activará nuevamente este curso y podrá usarse en todas las funciones. ¿Deseas continuar?';
      const message = action === 'suspender' ? msgSuspend : msgActivar;
      const cls = action === 'suspender' ? 'btn-danger' : 'btn-activar';
      openModal(message, href, cls);
    });
  });

  modalCancel.addEventListener('click', closeModal);
  modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
});
</script>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
