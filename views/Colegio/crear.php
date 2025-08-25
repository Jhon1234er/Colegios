<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
?>
<link rel="stylesheet" href="/css/Colegio/crear.css">
<div class="formulario-registro-colegio">

    <div class="container mt-4">
        <h2>Registrar Colegio</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/?page=colegios&action=guardar">
            <?= csrf_input(); ?>
            <div class="row">
                <div class="col-md-6">
                    <label for="departamento" class="form-label">Departamento</label>
                    <select id="departamento" name="departamento" class="form-select" required></select>

                    <label for="municipio" class="form-label">Municipio</label>
                    <select id="municipio" name="municipio" class="form-select" required></select>

                    <label for="nombre" class="form-label">Nombre del Colegio</label>
                    <select id="nombre" name="nombre" class="form-select" required></select>

                    <label for="codigo_dane" class="form-label">Código DANE</label>
                    <input type="text" class="form-control" id="codigo_dane" name="codigo_dane" required>

                    <label for="nit" class="form-label">NIT</label>
                    <input type="text" class="form-control" id="nit" name="nit" required>

                    <label for="tipo_institucion" class="form-label">Tipo de Institución</label>
                    <select id="tipo_institucion" name="tipo_institucion" class="form-select" required>
                    <option value="">Seleccione</option>
                    <option value="Pública">Pública</option>
                    <option value="Privada">Privada</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="text" class="form-control" id="telefono" name="telefono" required>

                    <label for="correo" class="form-label">Correo</label>
                    <input type="email" class="form-control" id="correo" name="correo" required>

                    <label for="direccion" class="form-label">Dirección</label>
                    <input type="text" class="form-control" id="direccion" name="direccion" required>

                    <label for="jornada" class="form-label">Jornada</label>
                    <input type="text" class="form-control" id="jornada" name="jornada" required>

                    <label for="grados" class="form-label">Grados</label>
                    <input type="text" class="form-control" id="grados" name="grados" required>

                    <label for="calendario" class="form-label">Calendario</label>
                    <input type="text" class="form-control" id="calendario" name="calendario" required>

                </div>
            </div>

            <button type="submit" class="btn btn-primary">Registrar Colegio</button>
        </form>
    </div>
</div>
<!-- Choices.js -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="js/filtros.js"></script>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
