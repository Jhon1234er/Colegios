<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
?>
<link rel="stylesheet" href="/css/Ficha/ver.css">

<div class="container">
    <h2>Ficha: <?= htmlspecialchars($ficha['nombre']) ?></h2>
    <p><strong>Número:</strong> <?= htmlspecialchars($ficha['numero'] ?? '-') ?></p>
    <p><strong>Estado:</strong> <?= htmlspecialchars($ficha['estado'] ?? 'pendiente') ?></p>
    <p><strong>Cupo total:</strong> <?= (int)($ficha['cupo_total'] ?? 0) ?></p>
    <p><strong>Cupo usado:</strong> <?= (int)($ficha['cupo_usado'] ?? 0) ?></p>

    <!-- 🔗 Link público -->
    <div class="link-publico">
        <label>Enlace de inscripción pública:</label>
        <input type="text" readonly class="form-control"
               value="<?= htmlspecialchars($_SERVER['HTTP_HOST'] . "/registro.php?token=" . $ficha['token']) ?>">
    </div>

    <!-- ➕ Botón agregar estudiante -->
    <div class="acciones">
        <a href="/?page=estudiantes&action=crear&ficha_id=<?= urlencode($ficha['id']) ?>"
           class="btn btn-primary">+ Agregar Estudiante</a>
    </div>

    <hr>

    <!-- 👨‍🎓 Listado de estudiantes -->
    <h3>Estudiantes registrados en esta ficha</h3>

    <?php if (empty($estudiantes)): ?>
        <p>No hay estudiantes registrados aún.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabla-lista">
                <thead>
                    <tr>
                        <th>Nombre completo</th>
                        <th>Documento</th>
                        <th>Colegio</th>
                        <th>Grado</th>
                        <th>Jornada</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($estudiantes as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['nombres'] . " " . $e['apellidos']) ?></td>
                        <td><?= htmlspecialchars($e['numero_documento']) ?></td>
                        <td><?= htmlspecialchars($e['colegio'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($e['grado'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($e['jornada'] ?? '-') ?></td>
                        <td>
                            <a href="/?page=estudiantes&action=editar&id=<?= urlencode($e['id']) ?>"
                               class="btn-accion btn-warning">Editar</a>
                            <a href="/?page=estudiantes&action=eliminar&id=<?= urlencode($e['id']) ?>"
                               class="btn-accion btn-danger"
                               onclick="return confirm('¿Eliminar este estudiante?')">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
