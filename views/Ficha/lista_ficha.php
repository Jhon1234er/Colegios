<table class="tabla-lista">
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Número</th>
            <th>Cupo</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($fichas as $ficha): ?>
            <tr>
                <td><?= htmlspecialchars($ficha['nombre']) ?></td>
                <td><?= htmlspecialchars($ficha['numero']) ?></td>
                <td><?= $ficha['cupo_usado'] ?>/<?= $ficha['cupo_total'] ?></td>
                <td><?= ucfirst($ficha['estado']) ?></td>
                <td>
                    <a href="/?page=fichas&action=ver&id=<?= urlencode($ficha['id']) ?>" class="btn btn-info">Ver</a>
                    <a href="/?page=fichas&action=editar&id=<?= urlencode($ficha['id']) ?>" class="btn btn-warning">Editar</a>
                    <a href="/?page=fichas&action=eliminar&id=<?= urlencode($ficha['id']) ?>" class="btn btn-danger" onclick="return confirm('¿Eliminar esta ficha?')">Suspender</a>
                </td>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>
