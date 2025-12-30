<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
require_once __DIR__ . '/../../models/Facilitador.php';

$model = new Facilitador();
$profesores = $model->obtenerTodos();
?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../css/Facilitador/lista_profesor.css">
<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="content-wrapper">
                <h2 class="mb-4">Facilitadores Activos</h2>
        
        <div class="btn-container">
            <a href="/?page=facilitadores&action=crear" class="btn-custom">
                <i class="fas fa-plus"></i>Crear Facilitador
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
                        <th>Título Académico</th>
                        <th>Especialidad</th>
                        <th>Fecha Ingreso</th>
                        <th>Fichas Actuales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profesores as $profesor): ?>
                        <?php $fichas = $model->obtenerFichasPorFacilitador($profesor['profesor_id']); ?>
                        <tr>
                            <td><span class="profesor-name"><?= htmlspecialchars($profesor['nombres'] . ' ' . $profesor['apellidos']) ?></span></td>
                            <td><span class="documento"><?= htmlspecialchars($profesor['numero_documento']) ?></span></td>
                            <td><a href="mailto:<?= htmlspecialchars($profesor['correo_electronico']) ?>" class="email"><?= htmlspecialchars($profesor['correo_electronico']) ?></a></td>
                            <td><span class="telefono"><?= htmlspecialchars($profesor['telefono']) ?></span></td>
                            <td><span class="titulo-academico"><?= htmlspecialchars($profesor['titulo_academico']) ?></span></td>
                            <td><span class="especialidad"><?= htmlspecialchars($profesor['especialidad']) ?></span></td>
                            <td><span class="fecha-ingreso"><?= htmlspecialchars($profesor['fecha_ingreso']) ?></span></td>
                            <td class="materias-container">
                                <?php if (!empty($fichas)): ?>
                                    <?php foreach ($fichas as $ficha): ?>
                                        <?php 
                                          $nf = isset($ficha['numero_ficha']) ? (string)$ficha['numero_ficha'] : null; 
                                          if ($nf === null || $nf === '') { $nf = $ficha['numero'] ?? ($ficha['nombre'] ?? ''); }
                                          $nf = (string)$nf;
                                        ?>
                                        <span class="materia-tag"><?= htmlspecialchars($nf !== '' ? $nf : 'Ficha') ?></span>
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
