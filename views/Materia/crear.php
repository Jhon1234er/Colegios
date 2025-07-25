<?php include __DIR__ . '/../Componentes/encabezado.php'; ?>
<link rel="stylesheet" href="/css/crear.css">

<div class="container">
    <h2>Registrar Materia</h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/materias/guardar">
        <div class="row">
            <div class="col-md-6">
                <label for="nombre" class="form-label">Nombre de la Materia</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>

            <div class="col-md-6">
                <label for="codigo" class="form-label">Código</label>
                <input type="text" id="codigo" name="codigo" required>
            </div>
        </div>

        <div class="row" style="justify-content: flex-end; margin-top: 20px;">
            <button type="submit" class="btn">Registrar</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
