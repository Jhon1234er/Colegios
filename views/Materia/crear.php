<?php include __DIR__ . '/../Componentes/encabezado.php'; ?>
<link rel="stylesheet" href="/css/crear.css">
<link rel="stylesheet" href="/css/lista.css">
<link rel="stylesheet" href="/css/Materia/crear.css">

<div class="container">
    <h2><?= isset($materia) ? 'Actualizar Materia' : 'Registrar Materia' ?></h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= isset($materia) ? '/?page=materias&action=actualizar' : '/materias/guardar' ?>">
        <?php if (isset($materia)): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($materia['id']) ?>">
        <?php endif; ?>
        <div class="row">
            <div class="col-md-6">
                <label for="nombre" class="form-label">Nombre de la Materia</label>
                <input type="text" id="nombre" name="nombre" class="form-control" required
                       value="<?= isset($materia) ? htmlspecialchars($materia['nombre']) : '' ?>">
            </div>

            <div class="col-md-6">
                <label for="codigo" class="form-label">Código</label>
                <input type="text" id="codigo" name="codigo" class="form-control" required
                       value="<?= isset($materia) ? htmlspecialchars($materia['codigo']) : '' ?>"
                       <?= isset($materia) ? 'readonly style="background:#eee;cursor:not-allowed;"' : '' ?>>
            </div>
        </div>

        <div class="row" style="justify-content: flex-end; margin-top: 20px;">
            <button type="submit" class="btn btn-primary"><?= isset($materia) ? 'Actualizar' : 'Registrar' ?></button>
            <?php if (isset($materia)): ?>
                <a href="/?page=materias" class="btn-cancelar">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../models/Materia.php';

$materiaModel = new Materia();
$materias = $materiaModel->obtenerTodas();
?>

<div class="container mt-5">
    <h2 class="mb-4">Listado de Materias</h2>

    <?php if (count($materias) === 0): ?>
        <div class="alert alert-info">No hay materias registradas.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabla-lista">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Código</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materias as $m): ?>
                        <tr class="<?= (isset($m['activo']) && $m['activo'] == 0) ? 'fila-inactiva' : '' ?>">
                            <td><?= htmlspecialchars($m['nombre']) ?></td>
                            <td><?= htmlspecialchars($m['codigo']) ?></td>
                            <td>
                                <?php if (isset($m['activo']) && $m['activo'] == 0): ?>
                                    <span class="badge bg-danger">Inactiva</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Activa</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($m['activo']) && $m['activo'] == 0): ?>
                                    <a href="/?page=materias&action=activar&id=<?= urlencode($m['id']) ?>" class="btn-accion btn-activar" onclick="return confirm('¿Seguro que deseas activar esta materia?')">Activar</a>
                                <?php else: ?>
                                    <a href="/?page=materias&action=editar&id=<?= urlencode($m['id']) ?>" class="btn-accion btn-warning">Actualizar</a>
                                    <a href="/?page=materias&action=desactivar&id=<?= urlencode($m['id']) ?>" class="btn-accion btn-danger" onclick="return confirm('¿Seguro que deseas desactivar esta materia?')">Desactivar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php
require_once __DIR__ . '/../../models/Colegio.php';
$colegioModel = new Colegio();
$colegios = $colegioModel->obtenerTodos();
?>

<!-- FORMULARIO DE MATERIA (ya existente, lo dejas tal cual aquí arriba) -->

<hr style="margin: 50px 0; border: 1px solid #ccc;">

<!-- FORMULARIO DE FICHA -->
<div class="container">
    <h2>Registrar Nueva Ficha</h2>
        <form method="POST" action="/?page=materias&action=guardar_ficha">
        <div class="row">
            <div class="col-md-6">
                <label for="nombre_ficha" class="form-label">Nombre de la Ficha</label>
                <input type="text" name="nombre" id="nombre_ficha" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label for="colegio_id_ficha" class="form-label">Colegio</label>
                <select name="colegio_id" id="colegio_id_ficha" class="form-select" required>
                    <option value="">Seleccione un colegio</option>
                    <?php foreach ($colegios as $colegio): ?>
                        <option value="<?= $colegio['id'] ?>"><?= $colegio['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-navigation" style="justify-content: flex-end; margin-top: 20px;">
            <button type="submit" class="submit-btn">Registrar Ficha</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
