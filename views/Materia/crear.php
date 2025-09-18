<?php include __DIR__ . '/../Componentes/encabezado.php'; ?>
<link rel="stylesheet" href="/css/crear.css">
<link rel="stylesheet" href="/css/Materia/crear.css">

<div class="container">
    <div class="card-widget">
        <h2 class="section-title"><?= isset($materia) ? 'Actualizar Curso' : 'Registrar Cursos' ?></h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= isset($materia) ? '?page=materias&action=actualizar' : '?page=materias&action=guardar' ?>">
      <?= csrf_input(); ?>

      <?php if (isset($materia)): ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars($materia['id']) ?>">
        <div class="row g-3">
          <div class="col-md-2">
            <label class="form-label">Código</label>
            <input type="text" name="codigo" class="form-control" value="<?= htmlspecialchars($materia['codigo'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Denominación</label>
            <input type="text" name="denominacion" class="form-control" value="<?= htmlspecialchars($materia['denominacion'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Duración</label>
            <input type="text" name="duracion" class="form-control" value="<?= htmlspecialchars($materia['duracion'] ?? '') ?>" placeholder="Ej: 144 horas">
          </div>
          <div class="col-md-2">
            <label class="form-label">Versión</label>
            <input type="number" name="version" class="form-control" value="<?= htmlspecialchars($materia['version'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Línea Tecnoacademia</label>
            <input type="text" name="linea_tecnoacademia" class="form-control" value="<?= htmlspecialchars($materia['linea_tecnoacademia'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($materia['descripcion'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="row" style="justify-content: flex-end; margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="/?page=materias" class="btn-cancelar">Cancelar</a>
        </div>
      <?php else: ?>
        <div class="courses-header">
          <button type="button" class="btn btn-primary" id="btnAgregarCurso">+ Agregar curso</button>
        </div>
        <div id="course-grid" class="course-grid">
          <div class="course-card">
            <div class="card-row">
              <div class="field">
                <label>Código</label>
                <input type="text" name="codigo[]" class="form-control" placeholder="Ej: 83930184">
              </div>
              <div class="field field-wide">
                <label>Denominación</label>
                <input type="text" name="denominacion[]" class="form-control" placeholder="Denominación">
              </div>
            </div>
            <div class="card-row">
              <div class="field">
                <label>Duración</label>
                <input type="text" name="duracion[]" class="form-control" placeholder="Ej: 144 horas">
              </div>
              <div class="field">
                <label>Versión</label>
                <input type="number" name="version[]" class="form-control" placeholder="Ej: 1">
              </div>
              <div class="field field-wide">
                <label>Línea Tecnoacademia</label>
                <input type="text" name="linea_tecnoacademia[]" class="form-control" placeholder="Ej: ELECTRÓNICA Y ROBÓTICA">
              </div>
            </div>
            <div class="card-actions">
              <button type="button" class="btn btn-sm btn-danger btnEliminarCurso">Eliminar</button>
            </div>
          </div>
        </div>
        <div class="row" style="justify-content: flex-end; margin-top: 20px;">
          <button type="submit" class="btn btn-primary">Guardar Cursos</button>
        </div>
      <?php endif; ?>
    </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const grid = document.getElementById('course-grid');
  const btnAgregarCurso = document.getElementById('btnAgregarCurso');
  if (!grid || !btnAgregarCurso) return;

  function crearCard() {
    const card = document.createElement('div');
    card.className = 'course-card';
    card.innerHTML = `
      <div class="card-row">
        <div class="field">
          <label>Código</label>
          <input type="text" name="codigo[]" class="form-control" placeholder="Ej: 83930184">
        </div>
        <div class="field field-wide">
          <label>Denominación</label>
          <input type="text" name="denominacion[]" class="form-control" placeholder="Denominación">
        </div>
      </div>
      <div class="card-row">
        <div class="field">
          <label>Duración</label>
          <input type="text" name="duracion[]" class="form-control" placeholder="Ej: 144 horas">
        </div>
        <div class="field">
          <label>Versión</label>
          <input type="number" name="version[]" class="form-control" placeholder="Ej: 1">
        </div>
        <div class="field field-wide">
          <label>Línea Tecnoacademia</label>
          <input type="text" name="linea_tecnoacademia[]" class="form-control" placeholder="Ej: ELECTRÓNICA Y ROBÓTICA">
        </div>
      </div>
      <div class="card-actions">
        <button type="button" class="btn btn-sm btn-danger btnEliminarCurso">Eliminar</button>
      </div>
    `;
    return card;
  }

  btnAgregarCurso.addEventListener('click', function(){
    grid.appendChild(crearCard());
  });

  grid.addEventListener('click', function(e){
    if (e.target && e.target.classList.contains('btnEliminarCurso')) {
      const card = e.target.closest('.course-card');
      const cards = grid.querySelectorAll('.course-card');
      if (cards.length > 1) card.remove();
    }
  });
});
</script>
