<?php
// Vista: Iniciar seguimiento por ausencia
// Variables esperadas en $data: ['est' => ..., 'ficha' => ..., 'fecha' => 'YYYY-mm-dd']
$est = $data['est'] ?? [];
$ficha = $data['ficha'] ?? [];
$fecha = $data['fecha'] ?? date('Y-m-d');
$aprendiz = trim(($est['nombres'] ?? '') . ' ' . ($est['apellidos'] ?? ''));
$telAcud = $est['telefono_acudiente'] ?? '';
$nomAcud = $est['nombre_completo_acudiente'] ?? '';
$fichaNumero = $ficha['numero'] ?? ($ficha['id'] ?? '');
$fichaNombre = $ficha['nombre'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Seguimiento por ausencia</title>
  <!-- Select2 y jQuery UI Datepicker -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <style>
    body { background:#f5f7fb; margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, 'Open Sans', 'Helvetica Neue', Arial, sans-serif; }
    .wrap { max-width: 920px; margin: 24px auto; padding: 0 16px; }
    .card { background:#fff; border:1px solid #e9edf2; border-radius:10px; box-shadow: 0 4px 18px rgba(0,0,0,0.05); }
    .card-header { padding:16px 20px; border-bottom:1px solid #eef2f7; display:flex; align-items:center; justify-content: space-between; }
    .card-title { margin:0; font-weight:700; color:#162447; font-size:20px; }
    .btn { display:inline-block; padding:8px 14px; border-radius:8px; text-decoration:none; font-weight:600; cursor:pointer; border:1px solid transparent; }
    .btn-primary { background:#0d6efd; color:#fff; border-color:#0d6efd; }
    .btn-outline { background:#fff; color:#334155; border-color:#cbd5e1; }
    .btn:disabled { opacity:.6; cursor:not-allowed; }
    .card-body { padding: 20px; }
    .row { display:flex; flex-wrap:wrap; gap:16px; }
    .col { flex:1 1 260px; }
    .label { color:#64748b; font-size:13px; margin-bottom:6px; }
    .value { font-weight:700; color:#0f172a; }
    .section-title { color:#0d6efd; font-weight:700; margin: 12px 0 8px; }
    .field { margin-bottom:12px; }
    .field input[type="text"], .field input[type="date"], .field select, .field textarea { width:94%; padding:6px 8px; border:1px solid #cbd5e1; border-radius:8px; outline:none; font-size:14px; }
    .field input[type="text"], .field input[type="date"], .field select { height: 23px; }
    .field textarea { min-height: 100px; resize: vertical; }
    .field textarea { padding:12px; line-height:1.4; }
    .muted { color:#64748b; font-size:13px; }
    .actions { padding:16px 20px; border-top:1px solid #eef2f7; display:flex; gap:10px; justify-content:flex-end; }
    /* Estilos Select2 consistentes */
    .select2-container { width:94% !important; }
    .select2-container--default .select2-selection--single { height:23px; border:1px solid #cbd5e1; border-radius:8px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:23px; padding-left:10px; font-size:14px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height:23px; right:8px; }
    .ui-datepicker { font-family: inherit; border-radius:10px; border:1px solid #e5e7eb; box-shadow:0 8px 18px rgba(0,0,0,0.08); padding:.5rem; }
    /* Campos no editables */
    .field input[readonly] { background:#f8fafc; color:#475569; cursor:not-allowed; }
    .field input[readonly]:focus { box-shadow:none; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="card-header">
        <h1 class="card-title">Seguimiento por ausencia</h1>
        <a href="/?page=asistente" class="btn btn-outline">Volver</a>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col">
            <div class="label">Aprendiz</div>
            <div class="value"><?= htmlspecialchars($aprendiz ?: '—') ?></div>
          </div>
          <div class="col">
            <div class="label">Ficha</div>
            <div class="value"><?= htmlspecialchars($fichaNumero) ?> <span class="muted">(<?= htmlspecialchars($fichaNombre) ?>)</span></div>
          </div>
          <div class="col">
            <div class="label">Fecha de inasistencia</div>
            <div class="value"><?= htmlspecialchars($fecha) ?></div>
          </div>
        </div>

        <form method="post" action="/?page=seguimiento_ausencia_guardar" style="margin-top:16px;">
          <input type="hidden" name="ficha_id" value="<?= (int)($ficha['id'] ?? 0) ?>">
          <input type="hidden" name="estudiante_id" value="<?= (int)($est['id'] ?? 0) ?>">
          <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha) ?>">

          <div class="section-title">Datos de contacto</div>
          <div class="row">
            <div class="col">
              <div class="field">
                <div class="label">Contacto</div>
                <select name="contacto" required>
                  <option value="Acudiente" selected>Acudiente</option>
                  <option value="Padre">Padre</option>
                  <option value="Madre">Madre</option>
                  <option value="Tutor">Tutor</option>
                </select>
              </div>
            </div>
            <div class="col">
              <div class="field">
                <div class="label">Vía de contacto</div>
                <select name="via" required>
                  <option value="Llamada" selected>Llamada</option>
                  <option value="WhatsApp">WhatsApp</option>
                  <option value="Correo">Correo</option>
                </select>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col">
              <div class="field">
                <div class="label">Nombre del acudiente</div>
                <input type="text" name="nombre_acudiente" value="<?= htmlspecialchars($nomAcud) ?>" placeholder="Nombre del acudiente" readonly title="Campo no editable">
              </div>
            </div>
            <div class="col">
              <div class="field">
                <div class="label">Teléfono del acudiente</div>
                <input type="text" name="telefono" value="<?= htmlspecialchars($telAcud) ?>" placeholder="Teléfono" readonly title="Campo no editable">
              </div>
            </div>
          </div>

          <div class="section-title">Motivo y observaciones</div>
          <div class="row">
            <div class="col">
              <div class="field">
                <div class="label">Motivo informado</div>
                <select name="motivo" required>
                  <option value="No respondió" selected>No respondió</option>
                  <option value="Enfermedad">Enfermedad</option>
                  <option value="Cita médica">Cita médica</option>
                  <option value="Dificultad de transporte">Dificultad de transporte</option>
                  <option value="Motivos personales">Motivos personales</option>
                  <option value="Otro">Otro</option>
                </select>
              </div>
            </div>
            <div class="col">
              <div class="field">
                <div class="label">Fecha de contacto</div>
                <input type="text" id="fecha_contacto" name="fecha_contacto" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
              </div>
            </div>
          </div>
          <div class="field">
            <div class="label">Observaciones</div>
            <textarea name="observaciones" placeholder="Notas de la llamada o conversación..." ></textarea>
            <div class="muted">Ejemplo: Se llamó dos veces sin respuesta. Se enviará recordatorio por WhatsApp.</div>
          </div>

          <div class="actions">
            <a href="/?page=asistente" class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar seguimiento</button>
          </div>
        </form>

        <?php if (!empty($data['hist'])): ?>
          <div class="section-title" style="margin-top:20px;">Historial de seguimientos</div>
          <div class="muted" style="margin-bottom:8px;">Últimos <?= count($data['hist']) ?> registros</div>
          <div style="overflow:auto;">
            <table style="width:100%; border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="text-align:left; border-bottom:1px solid #e2e8f0; padding:8px;">Fecha contacto</th>
                  <th style="text-align:left; border-bottom:1px solid #e2e8f0; padding:8px;">Vía</th>
                  <th style="text-align:left; border-bottom:1px solid #e2e8f0; padding:8px;">Contacto</th>
                  <th style="text-align:left; border-bottom:1px solid #e2e8f0; padding:8px;">Teléfono</th>
                  <th style="text-align:left; border-bottom:1px solid #e2e8f0; padding:8px;">Motivo</th>
                  <th style="text-align:left; border-bottom:1px solid #e2e8f0; padding:8px;">Observaciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($data['hist'] as $h): ?>
                  <tr>
                    <td style="border-bottom:1px solid #f1f5f9; padding:8px;"><?= htmlspecialchars($h['fecha'] ?? '') ?></td>
                    <td style="border-bottom:1px solid #f1f5f9; padding:8px;"><?= htmlspecialchars($h['via'] ?? '') ?></td>
                    <td style="border-bottom:1px solid #f1f5f9; padding:8px;"><?= htmlspecialchars($h['contacto'] ?? '') ?></td>
                    <td style="border-bottom:1px solid #f1f5f9; padding:8px;"><?= htmlspecialchars($h['telefono'] ?? '') ?></td>
                    <td style="border-bottom:1px solid #f1f5f9; padding:8px;"><?= htmlspecialchars($h['motivo'] ?? '') ?></td>
                    <td style="border-bottom:1px solid #f1f5f9; padding:8px; white-space:pre-wrap;"><?= nl2br(htmlspecialchars($h['observaciones'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <!-- JS: jQuery, Select2 y jQuery UI -->
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      // Select2 en selects
      if (window.jQuery && $.fn.select2) {
        $('select[name="contacto"]').select2({ width:'100%', placeholder:'Seleccione contacto' });
        $('select[name="via"]').select2({ width:'100%', placeholder:'Seleccione vía' });
        $('select[name="motivo"]').select2({ width:'100%', placeholder:'Seleccione motivo' });
      }
      // Datepicker en fecha_contacto con formato YYYY-MM-DD
      if (window.jQuery && $.fn.datepicker) {
        $('#fecha_contacto').datepicker({ dateFormat:'yy-mm-dd' }).datepicker('setDate', new Date());
      }
    });
  </script>
</body>
</html>
