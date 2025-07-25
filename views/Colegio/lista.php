<?php include __DIR__ . '/../Componentes/encabezado.php'; ?>

<div class="container mt-4">
    <h2>Colegios Registrados</h2>
    <a href="/?page=crear_colegio" class="btn btn-primary mb-3">Registrar Nuevo Colegio</a>

    <?php if (!empty($colegios)): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Código DANE</th>
                        <th>NIT</th>
                        <th>Tipo de Institución</th>
                        <th>Dirección</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Municipio</th>
                        <th>Departamento</th>
                        <th>Materias</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($colegios as $colegio): ?>
                        <tr>
                            <td><?= htmlspecialchars($colegio['id']) ?></td>
                            <td><?= htmlspecialchars($colegio['nombre']) ?></td>
                            <td><?= htmlspecialchars($colegio['codigo_dane']) ?></td>
                            <td><?= htmlspecialchars($colegio['nit']) ?></td>
                            <td><?= htmlspecialchars($colegio['tipo_institucion']) ?></td>
                            <td><?= htmlspecialchars($colegio['direccion']) ?></td>
                            <td><?= htmlspecialchars($colegio['telefono']) ?></td>
                            <td><?= htmlspecialchars($colegio['correo']) ?></td>
                            <td><?= htmlspecialchars($colegio['municipio']) ?></td>
                            <td><?= htmlspecialchars($colegio['departamento']) ?></td>
                            <td>
                                <?php if (!empty($colegio['materias'])): ?>
                                    <ul class="mb-0">
                                        <?php foreach ($colegio['materias'] as $materia): ?>
                                            <li><?= htmlspecialchars($materia['nombre']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <em>Sin materias</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/?page=eliminar_colegio&id=<?= $colegio['id'] ?>" 
                                   class="btn btn-danger btn-sm" 
                                   onclick="return confirm('¿Eliminar este colegio?')">
                                    Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>No hay colegios registrados.</p>
    <?php endif ?>
</div>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
