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

                <div class="">
                    <label>Días de clases</label>
                    <div class="dias-semana-container">
                        <div class="checkbox-group">
                            <label class="checkbox-item">
                                <input type="checkbox" name="dias_semana[]" value="lunes" checked>
                                <span class="checkmark"></span>
                                Lunes
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="dias_semana[]" value="martes" checked>
                                <span class="checkmark"></span>
                                Martes
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="dias_semana[]" value="miercoles" checked>
                                <span class="checkmark"></span>
                                Miércoles
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="dias_semana[]" value="jueves" checked>
                                <span class="checkmark"></span>
                                Jueves
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="dias_semana[]" value="viernes" checked>
                                <span class="checkmark"></span>
                                Viernes
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="dias_semana[]" value="sabado">
                                <span class="checkmark"></span>
                                Sábado
                            </label>
                        </div>
                    </div>
                    <small class="form-text text-muted">Selecciona los días en que se impartirán las clases</small>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Crear Ficha</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
