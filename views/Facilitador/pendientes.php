<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
?>
<style>
  .aprob-wrapper { max-width: 1440px; margin: 24px auto; padding: 0 24px; }
  .aprob-title { font-weight: 900; letter-spacing: .4px; margin: 6px 0 2px; color: #00304D; text-align: center; }
  .aprob-sub { text-align:center; color:#64748b; margin-bottom: 16px; display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:wrap; }
  .aprob-badge { display:inline-block; background:#00304D; color:#fff; font-weight:800; padding:6px 10px; border-radius:999px; font-size:12px; }
  .aprob-card { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; overflow: visible; box-shadow: 0 8px 24px rgba(2,6,23,.06); padding: 6px; }
  .aprob-scroll { overflow-x: auto; border-radius: 10px; }
  .aprob-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 1300px; }
  .aprob-table thead th:last-child, .aprob-table tbody td:last-child { width: 240px; }
  .aprob-table thead th { background: #f1f5f9; color: #00304D; padding: 14px 12px; font-weight: 800; letter-spacing: .3px; text-align: center; border-bottom: 1px solid #e2e8f0; }
  .aprob-table tbody td { padding: 12px 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a; vertical-align: middle; }
  .aprob-table tbody tr:nth-child(odd) { background: #f8fafc; }
  .aprob-table tbody tr:hover { background: #eef2ff; }
  .aprob-actions { text-align: right; white-space: nowrap; }
  .btn-activar, .btn-bloquear { display: inline-block; padding: 10px 14px; border-radius: 12px; font-weight: 800; letter-spacing: .3px; border: 2px solid transparent; transition: transform .15s ease, background .15s ease, border-color .15s ease; }
  .btn-activar { background: #16a34a; border-color: #16a34a; color: #fff; margin-right: 8px; }
  .btn-activar:hover { background: #15803d; border-color: #15803d; transform: translateY(-1px); }
  .btn-bloquear { background: #dc2626; border-color: #dc2626; color: #fff; }
  .btn-bloquear:hover { background: #b91c1c; border-color: #b91c1c; transform: translateY(-1px); }
  .aprob-mail a { color: #2563eb; font-weight: 600; text-decoration: none; }
  .aprob-mail a:hover { text-decoration: underline; }
</style>
<div class="container aprob-wrapper">
  <?php $totalPend = isset($rows) && is_array($rows) ? count($rows) : 0; ?>
  <h2 class="aprob-title">Cuentas en proceso de activación o bloqueo</h2>
  <div class="aprob-sub">
    <span>Revisa las cuentas registradas recientemente y decide si activarlas o bloquearlas.</span>
    <span class="aprob-badge"><?= (int)$totalPend ?> pendiente<?= ($totalPend===1?'':'s') ?></span>
  </div>
  <?php if (empty($rows)): ?>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:22px 18px;color:#334155;text-align:center;box-shadow:0 8px 24px rgba(2,6,23,.06);">
      <div style="font-weight:800;color:#00304D;margin-bottom:6px;">No hay solicitudes pendientes</div>
      <div style="font-size:14px;">Cuando se registren nuevos facilitadores, aparecerán aquí para su aprobación.</div>
    </div>
  <?php else: ?>
  <div class="table-responsive aprob-card">
    <div class="aprob-scroll">
    <table class="table aprob-table">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Correo</th>
          <th>Especialidad</th>
          <th>Tipo</th>
          <th>Registrado</th>
          <th style="text-align:center;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="white-space:nowrap;">
              <?= htmlspecialchars(trim(($r['nombres'] ?? '').' '.($r['apellidos'] ?? ''))) ?>
            </td>
            <td class="aprob-mail" style="text-align:center;">
              <a href="mailto:<?= htmlspecialchars($r['correo_electronico'] ?? '') ?>">
                <?= htmlspecialchars($r['correo_electronico'] ?? '') ?>
              </a>
            </td>
            <td style="text-align:center;">
              <?= htmlspecialchars($r['especialidad'] ?? '') ?>
            </td>
            <td style="text-align:center;">
              <?= htmlspecialchars($r['tipo_contrato'] ?? ($r['tip_contrato'] ?? '')) ?>
            </td>
            <td style="white-space:nowrap;text-align:center;">
              <?php 
                $f = $r['creado_en'] ?? '';
                echo $f && strtotime($f) ? date('d/m/Y H:i', strtotime($f)) : '—';
              ?>
            </td>
            <td class="aprob-actions" style="text-align:center;">
              <button type="button" class="btn-activar" data-uid="<?= (int)$r['usuario_id'] ?>">Activar</button>
              <button type="button" class="btn-bloquear" data-uid="<?= (int)$r['usuario_id'] ?>">Bloquear</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<script>
// Acciones inline: activar / bloquear
(function(){
  function post(url, data){
    const fd = new FormData();
    Object.keys(data||{}).forEach(k=> fd.append(k, data[k]));
    return fetch(url, { method:'POST', body: fd, credentials: 'include' });
  }
  function handle(action, uid){
    const url = action === 'activar' ? 'index.php?page=facilitador_aprobar' : 'index.php?page=facilitador_rechazar';
    post(url, { usuario_id: uid }).then(r=>r.json()).then(j=>{
      if (j && j.success){
        if (typeof showGlobalNotice === 'function') {
          showGlobalNotice(action === 'activar' ? 'Cuenta activada' : 'Registro bloqueado', action === 'activar' ? 'La cuenta fue activada.' : 'La solicitud fue bloqueada.');
        }
        setTimeout(()=> location.reload(), 600);
      } else {
        alert(j && j.message ? j.message : 'No se pudo completar la acción');
      }
    }).catch(()=> alert('Error de red'));
  }
  document.addEventListener('click', function(ev){
    const a = ev.target.closest('.btn-activar');
    const b = ev.target.closest('.btn-bloquear');
    if (a){ ev.preventDefault(); handle('activar', a.getAttribute('data-uid')); }
    if (b){ ev.preventDefault(); handle('bloquear', b.getAttribute('data-uid')); }
  });
})();
</script>
<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
