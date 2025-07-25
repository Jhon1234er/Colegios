    <?php include __DIR__ . '/../Componentes/encabezado.php'; ?>

    <div class="container mt-4">
        <h2>Registrar Materia</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/materias/guardar">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre de la Materia</label>
                <input type="text" class="form-control" id="nombre" name="nombre" required>
            </div>

            <div class="mb-3">
                <label for="codigo" class="form-label">Código</label>
                <input type="text" class="form-control" id="codigo" name="codigo" required>
            </div>

            <button type="submit" class="btn btn-primary">Registrar</button>
            <a href="/materias" class="btn btn-secondary">Ver Todas</a>
        </form>
    </div>

    <?php include __DIR__ . '/../Componentes/footer.php'; ?>
