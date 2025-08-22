
<?php
require_once __DIR__ . '/../../models/Colegio.php';
require_once __DIR__ . '/../../models/Ficha.php';
$fichaModel = new Ficha();
$fichas = $fichaModel->obtenerTodas();
?>

<!-- FORMULARIO DE FICHA -->
<div class="container">
    <h2>Registrar Nueva Ficha</h2>
        <form method="POST" action="/?page=&action=guardar_ficha">
        <div class="row">
            <div class="">
                <label for="nombre_ficha" class="form-label">Nombre de la Ficha</label>
                <input type="text" name="nombre" id="nombre_ficha" class="form-control" required>
            </div>


            <div class="">
                <label for=" " class="">Cantidad total de registros</label>
                <input type="text" name="" id="" class=" " required>
            </div>
        </div>

</div>
    <div class="container mt-5">
        <h2 class="mb-4">Listado de Fichas</h2>
        <div class="table-responsive">
            <table class="tabla-lista">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Colegio</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fichas as $ficha): ?>
                        <tr>
                            <td><?= htmlspecialchars($ficha['nombre']) ?></td>
                            <td><?= htmlspecialchars($ficha['colegio']) ?></td>
                            <td>
                                <?php if ($ficha['activa'] ?? true): ?>
                                    <span class="badge bg-success">Activa</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactiva</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/?page=fichas&action=editar&id=<?= urlencode($ficha['id']) ?>" class="btn-accion btn-warning">Editar</a>
                                <a href="/?page=fichas&action=eliminar&id=<?= urlencode($ficha['id']) ?>" class="btn-accion btn-danger" onclick="return confirm('¿Eliminar esta ficha?')">Suspender</a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php include __DIR__ . '/../Componentes/footer.php'; ?>
