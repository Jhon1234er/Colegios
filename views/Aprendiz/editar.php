<?php require_once __DIR__ . '/../Componentes/encabezado.php'; ?>
<link rel="stylesheet" href="/css/Aprendiz/crear.css">

<style>
  .container.estudiante-crear.editar-aprendiz {
    max-width: 900px;
    margin: 110px auto 48px;
    padding: 32px 40px 30px;
    border-radius: 28px;
    border: 1px solid rgba(15,23,42,.06);
    box-shadow: 0 24px 60px rgba(15,23,42,.18);
    background: #ffffff;
  }
  .editar-aprendiz .crear-container {
    margin: 0;
    padding: 0;
    border: 0;
    box-shadow: none;
    background: transparent;
    width: 100%;
  }
  .editar-aprendiz-title {
    margin: 0 0 20px;
    font-size: 1.6rem;
    font-weight: 800;
    text-align: center;
    color: #0f172a;
  }
  .editar-aprendiz .row1,
  .editar-aprendiz .row2 {
    margin-top: 14px;
  }
  .editar-aprendiz .btns-footer {
    margin-top: 26px;
    display: flex;
    justify-content: center;
    gap: 10px;
  }
</style>

<div class="container estudiante-crear editar-aprendiz">
  <div class="crear-container">
    <h2 class="editar-aprendiz-title">Editar aprendiz</h2>

    <form method="post" action="/?page=aprendices&action=actualizar">
      <?= csrf_input(); ?>
      <input type="hidden" name="usuario_id" value="<?= htmlspecialchars($estudiante['usuario_id']) ?>">
      <?php if (!empty($ficha_actual_id)): ?>
        <input type="hidden" name="ficha_id" value="<?= htmlspecialchars($ficha_actual_id) ?>">
      <?php endif; ?>

      <div class="row row1">
        <div class="col-md-6">
          <label for="nombres">Nombres</label>
          <input type="text" name="nombres" id="nombres" class="form-control" required
                 value="<?= htmlspecialchars($estudiante['nombres'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label for="apellidos">Apellidos</label>
          <input type="text" name="apellidos" id="apellidos" class="form-control" required
                 value="<?= htmlspecialchars($estudiante['apellidos'] ?? '') ?>">
        </div>
      </div>

      <div class="row row2">
        <div class="col-md-6">
          <label for="tipo_documento">Tipo de documento</label>
          <?php $tipoDocActual = $estudiante['tipo_documento'] ?? ''; ?>
          <select name="tipo_documento" id="tipo_documento" class="form-control" required>
            <option value="">Seleccione</option>
            <option value="CC" <?= ($tipoDocActual === 'CC' ? 'selected' : '') ?>>Cédula de ciudadanía</option>
            <option value="TI" <?= ($tipoDocActual === 'TI' ? 'selected' : '') ?>>Tarjeta de identidad</option>
            <option value="RC" <?= ($tipoDocActual === 'RC' ? 'selected' : '') ?>>Registro civil</option>
            <option value="PPT" <?= ($tipoDocActual === 'PPT' ? 'selected' : '') ?>>Permiso de Permanencia</option>
          </select>
        </div>
        <div class="col-md-6">
          <label for="numero_documento">Número de documento</label>
          <input type="text" name="numero_documento" id="numero_documento" class="form-control" required
                 value="<?= htmlspecialchars($estudiante['numero_documento'] ?? '') ?>">
        </div>
      </div>

      <div class="row row2" style="margin-top: 12px;">
        <div class="col-md-6">
          <label for="correo_electronico">Correo electrónico</label>
          <input type="email" name="correo_electronico" id="correo_electronico" class="form-control"
                 value="<?= htmlspecialchars($estudiante['correo_electronico'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label for="telefono">Teléfono / Celular</label>
          <input type="text" name="telefono" id="telefono" class="form-control"
                 value="<?= htmlspecialchars($estudiante['telefono'] ?? '') ?>">
        </div>
      </div>

      <div class="row row2" style="margin-top: 12px;">
        <div class="col-md-4">
          <label for="grado">Grado</label>
          <input type="text" name="grado" id="grado" class="form-control"
                 value="<?= htmlspecialchars($estudiante['grado'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label for="grupo">Grupo</label>
          <input type="text" name="grupo" id="grupo" class="form-control"
                 value="<?= htmlspecialchars($estudiante['grupo'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label for="jornada">Jornada</label>
          <input type="text" name="jornada" id="jornada" class="form-control"
                 value="<?= htmlspecialchars($estudiante['jornada'] ?? '') ?>">
        </div>
      </div>

      <div class="btns-footer">
        <button type="submit" class="btn btn-primario">Guardar cambios</button>
        <?php if (!empty($ficha_actual_id)): ?>
          <a href="/?page=fichas&action=ver&id=<?= urlencode($ficha_actual_id) ?>" class="btn btn-secundario">Cancelar</a>
        <?php else: ?>
          <a href="/?page=aprendices" class="btn btn-secundario">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
