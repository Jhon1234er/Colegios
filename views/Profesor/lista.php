<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
require_once __DIR__ . '/../../models/Profesor.php';

$model = new Profesor();
$profesores = $model->obtenerTodos();
?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../css/Profesor/lista_profesor.css">
<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="content-wrapper">
                <h2 class="mb-4">Facilitadores Activos</h2>
        
        <div class="btn-container">
            <a href="/?page=profesores&action=crear" class="btn-custom">
                <i class="fas fa-plus"></i>Crear Facilitador
            </a>
            <a href="/?page=profesores&action=reajuste_fichas" class="btn-custom">
                <i class="fas fa-sync"></i>Reajuste de fichas
            </a>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Documento</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th>Colegio</th>
                        <th>Título Académico</th>
                        <th>Especialidad</th>
                        <th>Fecha Ingreso</th>
                        <th>Fichas Actuales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profesores as $profesor): ?>
                        <?php $fichas = $model->obtenerFichasPorProfesor($profesor['profesor_id']); ?>
                        <tr>
                            <td><span class="profesor-name"><?= htmlspecialchars($profesor['nombres'] . ' ' . $profesor['apellidos']) ?></span></td>
                            <td><span class="documento"><?= htmlspecialchars($profesor['tipo_documento'] . ' ' . $profesor['numero_documento']) ?></span></td>
                            <td><a href="mailto:<?= htmlspecialchars($profesor['correo_electronico']) ?>" class="email"><?= htmlspecialchars($profesor['correo_electronico']) ?></a></td>
                            <td><span class="telefono"><?= htmlspecialchars($profesor['telefono']) ?></span></td>
                            <td><span class="colegio"><?= htmlspecialchars($profesor['colegio'] ?? 'Sin asignar') ?></span></td>
                            <td><span class="titulo-academico"><?= htmlspecialchars($profesor['titulo_academico']) ?></span></td>
                            <td><span class="especialidad"><?= htmlspecialchars($profesor['especialidad']) ?></span></td>
                            <td><span class="fecha-ingreso"><?= htmlspecialchars($profesor['fecha_ingreso']) ?></span></td>
                            <td class="materias-container">
                                <?php if (!empty($fichas)): ?>
                                    <?php foreach ($fichas as $ficha): ?>
                                        <span class="materia-tag"><?= htmlspecialchars($ficha['numero_ficha']) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="sin-materias">Sin fichas asignadas</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
