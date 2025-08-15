<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
require_once __DIR__ . '/../../models/Profesor.php';

$model = new Profesor();
$profesores = $model->obtenerTodos();
?>
<link rel="stylesheet" href="/css/dashboard.css">
<link rel="stylesheet" href="/css/Profesor/lista_profesor.css">

<div class="container mt-4">
    <h2 class="mb-4">Facilitadores Activos</h2>
    
    <div class="d-flex gap-2 mb-3">
        <a href="/?page=profesores&action=crear" class="btn btn-success">Crear Facilitador</a>
        <a href="/?page=profesores&action=reajuste_fichas" class="btn btn-warning">Reajuste de fichas</a>
    </div>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Nombre</th>
                <th>Documento</th>
                <th>Correo</th>
                <th>Teléfono</th>
                <th>Colegio</th>
                <th>Título Académico</th>
                <th>Especialidad</th>
                <th>Fecha Ingreso</th>
                <th>Materias</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($profesores as $profesor): ?>
                <?php $materias = $model->obtenerMateriasPorProfesor($profesor['profesor_id']); ?>
                <tr>
                    <td><?= htmlspecialchars($profesor['nombres'] . ' ' . $profesor['apellidos']) ?></td>
                    <td><?= htmlspecialchars($profesor['tipo_documento'] . ' ' . $profesor['numero_documento']) ?></td>
                    <td><?= htmlspecialchars($profesor['correo_electronico']) ?></td>
                    <td><?= htmlspecialchars($profesor['telefono']) ?></td>
                    <td><?= htmlspecialchars($profesor['colegio']) ?></td>
                    <td><?= htmlspecialchars($profesor['titulo_academico']) ?></td>
                    <td><?= htmlspecialchars($profesor['especialidad']) ?></td>
                    <td><?= htmlspecialchars($profesor['fecha_ingreso']) ?></td>
                    <td>
                        <?php if (!empty($materias)): ?>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($materias as $materia): ?>
                                    <li><?= htmlspecialchars($materia) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <em>Sin materias</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
