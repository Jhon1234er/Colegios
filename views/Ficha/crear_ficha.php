<?php
require_once __DIR__ . '/../../helpers/auth.php';
start_secure_session(); // ⚡ inicia la sesión segura antes de todo

require_once __DIR__ . '/../../models/Colegio.php';
require_once __DIR__ . '/../../models/Ficha.php';

$fichaModel = new Ficha();
$fichas = $fichaModel->obtenerTodas();
?>

<?php include __DIR__ . '/../Componentes/encabezado.php'; ?>

<link rel="stylesheet" href="/css/Ficha/crear_ficha.css">

<!-- FORMULARIO DE FICHA -->
<div class="from-wrapper">
    <div class="container">
        <h2>Registrar Nueva Ficha</h2>
        <form method="POST" action="/?page=fichas&action=guardar">
            <?= csrf_input(); ?> <!-- ✅ Token CSRF correcto -->

            <div class="">
                <div class="">
                    <label for="nombre_ficha">Nombre de la Ficha</label>
                    <input type="text" name="nombre" id="nombre_ficha" required>
                </div>

                <div class="">
                    <label for="numero_ficha">Número de la Ficha</label>
                    <input type="text" name="numero" id="numero_ficha" required>
                </div>

                <div class="">
                    <label for="cupo_total">Cupo total de registros</label>
                    <input type="number" name="cupo_total" id="cupo_total" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Crear Ficha</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
