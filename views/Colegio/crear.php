<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
require_once __DIR__ . '/../../models/Materia.php';

$materiaModel = new Materia();
$materias = $materiaModel->obtenerTodas(); // Asegúrate que existe este método en Materia.php
?>
<link rel="stylesheet" href="/css/crear.css">

<div class="container mt-4">
    <h2>Registrar Colegio</h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/?page=colegios&action=guardar">
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre del Colegio</label>
            <input type="text" class="form-control" id="nombre" name="nombre" required>
        </div>

        <div class="mb-3">
            <label for="codigo_dane" class="form-label">Código DANE</label>
            <input type="text" class="form-control" id="codigo_dane" name="codigo_dane" required>
        </div>

        <div class="mb-3">
            <label for="nit" class="form-label">NIT</label>
            <input type="text" class="form-control" id="nit" name="nit" required>
        </div>

        <div class="mb-3">
            <label for="tipo_institucion" class="form-label">Tipo de Institución</label>
            <input type="text" class="form-control" id="tipo_institucion" name="tipo_institucion" required>
        </div>

        <div class="mb-3">
            <label for="direccion" class="form-label">Dirección</label>
            <input type="text" class="form-control" id="direccion" name="direccion" required>
        </div>

        <div class="mb-3">
            <label for="telefono" class="form-label">Teléfono</label>
            <input type="text" class="form-control" id="telefono" name="telefono" required>
        </div>

        <div class="mb-3">
            <label for="correo" class="form-label">Correo</label>
            <input type="email" class="form-control" id="correo" name="correo" required>
        </div>

        <div class="mb-3">
            <label for="municipio" class="form-label">Municipio</label>
            <input type="text" class="form-control" id="municipio" name="municipio" required>
        </div>

        <div class="mb-3">
            <label for="departamento" class="form-label">Departamento</label>
            <input type="text" class="form-control" id="departamento" name="departamento" required>
        </div>

        <div class="mb-3">
            <label for="materias" class="form-label">Materias Asociadas</label>
            <select id="materias" name="materias[]" class="form-select" multiple required>
                <?php foreach ($materias as $materia): ?>
                    <option value="<?= $materia['id'] ?>"><?= htmlspecialchars($materia['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Registrar Colegio</button>
    </form>
</div>

<!-- Choices.js -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
    new Choices('#materias', {
        removeItemButton: true,
        placeholderValue: 'Selecciona materias',
        noResultsText: 'No se encontraron materias',
        searchEnabled: true
    });
</script>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
