<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once __DIR__ . '/../Componentes/encabezado.php';
require_once __DIR__ . '/../../models/Materia.php';

$materiaModel = new Materia();
$materias = $materiaModel->obtenerTodas();
?>

<div class="container mt-5">
    <h2 class="mb-4">Listado de Materias</h2>

    <a href="index.php?page=crear_materia" class="btn btn-success mb-3">+ Nueva Materia</a>

    <?php if (count($materias) === 0): ?>
        <div class="alert alert-info">No hay materias registradas.</div>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Código</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($materias as $materia): ?>
                    <tr>
                        <td><?= htmlspecialchars($materia['id']) ?></td>
                        <td><?= htmlspecialchars($materia['nombre']) ?></td>
                        <td><?= htmlspecialchars($materia['codigo']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../Componentes/footer.php'; ?>
