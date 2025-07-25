<?php require_once __DIR__ . '/../Componentes/encabezado.php'; ?>

<div class="container mt-5">
    <h2 class="mb-4">Lista de Estudiantes</h2>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle text-center">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Tipo Doc</th>
                    <th>Documento</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th>Colegio</th>
                    <th>Grado</th>
                    <th>Grupo</th>
                    <th>Jornada</th>
                    <th>Fecha Ingreso</th>
                    <th>Nombre Acudiente</th>
                    <th>Tipo Doc Acudiente</th>
                    <th>Doc Acudiente</th>
                    <th>Tel. Acudiente</th>
                    <th>Parentesco</th>
                    <th>Ocupación</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($estudiantes)) : ?>
                    <?php foreach ($estudiantes as $index => $e) : ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($e['nombres']) ?></td>
                            <td><?= htmlspecialchars($e['apellidos']) ?></td>
                            <td><?= htmlspecialchars($e['tipo_documento']) ?></td>
                            <td><?= htmlspecialchars($e['numero_documento']) ?></td>
                            <td><?= htmlspecialchars($e['correo_electronico']) ?></td>
                            <td><?= htmlspecialchars($e['telefono']) ?></td>
                            <td><?= htmlspecialchars($e['colegio']) ?></td>
                            <td><?= htmlspecialchars($e['grado']) ?></td>
                            <td><?= htmlspecialchars($e['grupo']) ?></td>
                            <td><?= htmlspecialchars($e['jornada']) ?></td>
                            <td><?= htmlspecialchars($e['fecha_ingreso']) ?></td>
                            <td><?= htmlspecialchars($e['nombre_completo_acudiente']) ?></td>
                            <td><?= htmlspecialchars($e['tipo_documento_acudiente']) ?></td>
                            <td><?= htmlspecialchars($e['numero_documento_acudiente']) ?></td>
                            <td><?= htmlspecialchars($e['telefono_acudiente']) ?></td>
                            <td><?= htmlspecialchars($e['parentesco']) ?></td>
                            <td><?= htmlspecialchars($e['ocupacion']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="18">No hay estudiantes registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
