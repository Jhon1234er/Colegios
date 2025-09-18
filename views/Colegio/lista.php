<?php include __DIR__ . '/../Componentes/encabezado.php'; ?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../css/Colegio/lista_colegio.css">

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="content-wrapper">
                <h2 class="mb-4">Colegios Registrados</h2>
        
        <div class="btn-container">
            <a href="/?page=crear_colegio" class="btn-primary btn-custom">
                <i class="fas fa-plus"></i>Registrar Colegio
            </a>
        </div>

    <?php if (!empty($colegios)): ?>
        <div class="table-container">
            <table class="table">
                <thead>
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
                        <th>Cursos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($colegios as $colegio): ?>
                        <tr>
                            <td><span class="colegio-id"><?= htmlspecialchars($colegio['id']) ?></span></td>
                            <td><span class="colegio-name"><?= htmlspecialchars($colegio['nombre']) ?></span></td>
                            <td><span class="codigo-dane"><?= htmlspecialchars($colegio['codigo_dane']) ?></span></td>
                            <td><span class="nit"><?= htmlspecialchars($colegio['nit']) ?></span></td>
                            <td><span class="tipo-institucion"><?= htmlspecialchars($colegio['tipo_institucion']) ?></span></td>
                            <td><span class="direccion"><?= htmlspecialchars($colegio['direccion']) ?></span></td>
                            <td><span class="telefono"><?= htmlspecialchars($colegio['telefono']) ?></span></td>
                            <td><a href="mailto:<?= htmlspecialchars($colegio['correo']) ?>" class="email"><?= htmlspecialchars($colegio['correo']) ?></a></td>
                            <td><span class="municipio"><?= htmlspecialchars($colegio['municipio']) ?></span></td>
                            <td><span class="departamento"><?= htmlspecialchars($colegio['departamento']) ?></span></td>
                            <td>
                                <?php if (!empty($colegio['materias'])): ?>
                                    <ul class="materias-list">
                                        <?php foreach ($colegio['materias'] as $materia): ?>
                                            <li><?= htmlspecialchars($materia['nombre']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="sin-materias">Sin Cursos</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/?page=eliminar_colegio&id=<?= $colegio['id'] ?>" 
                                   class="btn-danger" 
                                   onclick="return confirm('¿Eliminar este colegio?')">
                                    <i class="fas fa-ban"></i> Suspender
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
    </div>
</div>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
