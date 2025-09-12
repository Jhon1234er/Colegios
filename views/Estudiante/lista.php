<?php require_once __DIR__ . '/../Componentes/encabezado.php'; ?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../css/Estudiante/lista_estudiante.css">

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="content-wrapper">
                <h2 class="mb-4">Aprendices Matriculados</h2>

        <div class="table-container">
            <table class="table">
                <thead>
                <tr>
                    <th>Nombre Completo</th>
                    <th>Documento</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th>Colegio</th>
                    <th>Grado</th>
                    <th>Jornada</th>
                    <th>Fecha Ingreso</th>
                    <th>Nombre Acudiente</th>
                    <th>Documento del Acudiente</th>
                    <th>Tel. Acudiente</th>
                    <th>Parentesco</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($estudiantes)) : ?>
                    <?php foreach ($estudiantes as $index => $e) : ?>
                        <tr>
                            <td><span class="estudiante-name"><?= htmlspecialchars($e['nombres'] . ' ' . $e['apellidos']) ?></span></td>
                            <td><span class="documento"><?= htmlspecialchars($e['tipo_documento'] . ' ' . $e['numero_documento']) ?></span></td>
                            <td><a href="mailto:<?= htmlspecialchars($e['correo_electronico']) ?>" class="email"><?= htmlspecialchars($e['correo_electronico']) ?></a></td>
                            <td><span class="telefono"><?= htmlspecialchars($e['telefono']) ?></span></td>
                            <td><span class="colegio"><?= htmlspecialchars($e['colegio']) ?></span></td>
                            <td><span class="grado"><?= htmlspecialchars($e['grado']) . ' ' . (htmlspecialchars($e['grupo'])) ?></span></td>
                            <td><span class="jornada"><?= htmlspecialchars($e['jornada']) ?></span></td>
                            <td><span class="fecha-ingreso"><?= htmlspecialchars($e['fecha_ingreso']) ?></span></td>
                            <td><span class="acudiente-name"><?= htmlspecialchars($e['nombre_completo_acudiente']) ?></span></td>
                            <td><span class="documento"><?= htmlspecialchars($e['tipo_documento_acudiente']) . ' ' . (htmlspecialchars($e['numero_documento_acudiente'])) ?></span></td> 
                            <td><span class="telefono"><?= htmlspecialchars($e['telefono_acudiente']) ?></span></td>
                            <td><span class="parentesco"><?= htmlspecialchars($e['parentesco']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="12">No hay estudiantes registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
