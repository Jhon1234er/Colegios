<?php
// Vista: Iniciar seguimiento por ausencia
// Variables esperadas en $data: ['est' => ..., 'ficha' => ..., 'fecha' => 'YYYY-mm-dd']
$est = $data['est'] ?? [];
$ficha = $data['ficha'] ?? [];
$fecha = $data['fecha'] ?? date('Y-m-d');
// Fallbacks desde la URL por si los SELECT no devolvieron filas
$reqFichaId = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : 0;
$reqEstId   = isset($_GET['estudiante_id']) ? (int)$_GET['estudiante_id'] : 0;

$aprendiz = trim(($est['nombres'] ?? '') . ' ' . ($est['apellidos'] ?? ''));
$telAcud = $est['telefono_acudiente'] ?? '';
$nomAcud = $est['nombre_completo_acudiente'] ?? '';
$fichaIdHidden = (int)($ficha['id'] ?? $reqFichaId);
$estIdHidden   = (int)($est['id'] ?? $reqEstId);
$fichaNumero = $ficha['numero'] ?? ($ficha['id'] ?? ($reqFichaId ?: ''));
$fichaNombre = $ficha['nombre'] ?? '';
?>
<?php require __DIR__ . '/../Componentes/encabezado.php'; ?>
<!-- Select2 y jQuery UI Datepicker -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<link rel="stylesheet" href="/css/Seguimiento/seguimiento.css?v=20251023-1">
  <div class="sg-wrap">
    <div class="sg-card">
      <div class="sg-header">
        <h1 class="sg-title">Seguimiento por ausencia</h1>
        <a href="/?page=asistente" class="sg-back">Volver</a>
      </div>
      <div class="sg-body">
        <div class="sg-row">
          <div class="sg-col">
            <div class="sg-label">Aprendiz</div>
            <div class="sg-value"><?= htmlspecialchars($aprendiz ?: '—') ?></div>
          </div>
          <div class="sg-col">
            <div class="sg-label">Ficha</div>
            <div class="sg-value"><?= htmlspecialchars($fichaNumero) ?> <span class="muted">(<?= htmlspecialchars($fichaNombre) ?>)</span></div>
          </div>
          <div class="sg-col">
            <div class="sg-label">Fecha de inasistencia</div>
            <div class="sg-value"><?= htmlspecialchars($fecha) ?></div>
          </div>
        </div>

        <form method="post" action="/?page=seguimiento_ausencia_guardar">
          <input type="hidden" name="ficha_id" value="<?= $fichaIdHidden ?>">
          <input type="hidden" name="estudiante_id" value="<?= $estIdHidden ?>">
          <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha) ?>">

          <div class="sg-section">Datos de contacto</div>
          <div class="sg-row">
            <div class="sg-col">
              <div class="sg-field">
                <div class="sg-label">Contacto</div>
                <select name="contacto" required class="sg-select">
                  <option value="Acudiente" selected>Acudiente</option>
                  <option value="Padre">Padre</option>
                  <option value="Madre">Madre</option>
                  <option value="Tutor">Tutor</option>
                </select>
              </div>
            </div>
            <div class="sg-col">
              <div class="sg-field">
                <div class="sg-label">Vía de contacto</div>
                <select name="via" required class="sg-select">
                  <option value="Llamada" selected>Llamada</option>
                  <option value="WhatsApp">WhatsApp</option>
                  <option value="Correo">Correo</option>
                </select>
              </div>
            </div>
          </div>

          <div class="sg-row">
            <div class="sg-col">
              <div class="sg-field">
                <div class="sg-label">Nombre del acudiente</div>
                <input class="sg-input" type="text" name="nombre_acudiente" value="<?= htmlspecialchars($nomAcud) ?>" placeholder="Nombre del acudiente" readonly title="Campo no editable">
              </div>
            </div>
            <div class="sg-col">
              <div class="sg-field">
                <div class="sg-label">Teléfono del acudiente</div>
                <input class="sg-input" type="text" name="telefono" value="<?= htmlspecialchars($telAcud) ?>" placeholder="Teléfono" readonly title="Campo no editable">
              </div>
            </div>
          </div>

          <div class="sg-section">Motivo y observaciones</div>
          <div class="sg-row">
            <div class="sg-col">
              <div class="sg-field">
                <div class="sg-label">Motivo informado</div>
                <select name="motivo" required class="sg-select">
                  <option value="No respondió" selected>No respondió</option>
                  <option value="Enfermedad">Enfermedad</option>
                  <option value="Cita médica">Cita médica</option>
                  <option value="Dificultad de transporte">Dificultad de transporte</option>
                  <option value="Motivos personales">Motivos personales</option>
                  <option value="Otro">Otro</option>
                </select>
              </div>
            </div>
            <div class="sg-col">
              <div class="sg-field">
                <div class="sg-label">Fecha de contacto</div>
                <input class="sg-input" type="text" id="fecha_contacto" name="fecha_contacto" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
              </div>
            </div>
          </div>
          <div class="sg-field">
            <div class="sg-label">Observaciones</div>
            <textarea class="sg-textarea" name="observaciones" placeholder="Notas de la llamada o conversación..." ></textarea>
            <div class="muted">Ejemplo: Se llamó dos veces sin respuesta. Se enviará recordatorio por WhatsApp.</div>
          </div>

          <div class="sg-actions">
            <a href="/?page=asistente" class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar seguimiento</button>
          </div>
        </form>

        <?php if (!empty($data['hist'])): ?>
          <div class="sg-section" style="margin-top:20px;">Historial de seguimientos</div>
          <div class="muted" style="margin-bottom:8px;">Últimos <?= count($data['hist']) ?> registros</div>
          <div class="sg-table-wrap">
            <table class="sg-table">
              <thead>
                <tr>
                  <th>Fecha contacto</th>
                  <th>Vía</th>
                  <th>Contacto</th>
                  <th>Teléfono</th>
                  <th>Motivo</th>
                  <th>Observaciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($data['hist'] as $h): ?>
                  <tr>
                    <td><?= htmlspecialchars($h['fecha'] ?? '') ?></td>
                    <td><?= htmlspecialchars($h['via'] ?? '') ?></td>
                    <td><?= htmlspecialchars($h['contacto'] ?? '') ?></td>
                    <td><?= htmlspecialchars($h['telefono'] ?? '') ?></td>
                    <td><?= htmlspecialchars($h['motivo'] ?? '') ?></td>
                    <td style="white-space:pre-wrap;"><?= nl2br(htmlspecialchars($h['observaciones'] ?? '')) ?></td>
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

<?php require __DIR__ . '/../Componentes/footer.php'; ?>
