<?php
require_once __DIR__ . '/../../helpers/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    start_secure_session();
}
require_once __DIR__ . '/../Componentes/encabezado.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Asistente</title>
    <link rel="stylesheet" href="/css/Administrador/crear.css?v=4">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
</head>
<body>
<div class="asistente-crear">
    <div class="form-title">Crear Asistente</div>
    <form method="POST" action="../../public/index.php?registro=true">
        <?= csrf_input(); ?>
        <div class="row">
            <div class="col-md-6">
                <label>Nombres</label>
                <input type="text" name="nombres" required>
            </div>
            <div class="col-md-6">
                <label>Apellidos</label>
                <input type="text" name="apellidos" required>
            </div>
            <div class="col-md-6">
                <label>Tipo de Documento</label>
                <select name="tipo_documento" class="js-choice" required>
                    <option value="">Seleccione</option>
                    <option value="CC">Cédula de Ciudadanía</option>
                    <option value="CE">Cédula de Extranjería</option>
                </select>
            </div>
            <div class="col-md-6">
                <label>Número de Documento</label>
                <input type="text" name="numero_documento" required>
            </div>
            <div class="col-md-6">
                <label for="fecha_nacimiento">
                    Fecha de Nacimiento
                    <div class="date-wrap">
                        <input type="text" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" placeholder="dd/mm/aaaa" required autocomplete="off">
                        <svg class="date-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <rect x="3" y="5" width="18" height="16" rx="4" ry="4" fill="none" stroke="currentColor" stroke-width="2"/>
                            <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
                            <line x1="8" y1="3" x2="8" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <line x1="16" y1="3" x2="16" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                </label>
            </div>
            <div class="col-md-6">
                <label>Género</label>
                <select name="genero" id="genero_asist" class="js-choice" required>
                    <option value="">Seleccione</option>
                    <option value="M">Masculino</option>
                    <option value="F">Femenino</option>
                    <option value="Otro">Otro</option>
                </select>
                <input type="text" name="genero_otro" id="genero_otro_asist" placeholder="Especifique" style="display:none;">
            </div>
            <div class="col-md-6">
                <label>Correo Electrónico</label>
                <input type="email" name="correo_electronico" required>
            </div>
            <div class="col-md-6">
                <label>Correo institucional</label>
                <input type="email" name="correo_institucional">
            </div>
            <div class="col-md-6">
                <label>Celular</label>
                <input type="text" name="celular" maxlength="10" pattern="\d{10}" placeholder="10 dígitos">
            </div>
            <div class="col-md-6">
                <label>Teléfono</label>
                <input type="text" name="telefono">
            </div>
            <div class="col-md-6">
                <label>Dirección</label>
                <input type="text" name="direccion">
            </div>
            <div class="col-md-6">
                <label>Ciudad / Municipio</label>
                <input type="text" name="municipio" placeholder="Ej: Cundinamarca">
            </div>
            <div class="col-md-6">
                <label>Barrio</label>
                <input type="text" name="barrio">
            </div>
            <div class="col-md-6">
                <label>RH</label>
                <select name="rh" class="js-choice" required>
                    <option value="">Seleccione...</option>
                    <option>A+</option><option>A-</option><option>B+</option><option>B-</option>
                    <option>O+</option><option>O-</option><option>AB+</option><option>AB-</option>
                </select>
            </div>
            <div class="col-md-6">
                <label>EPS</label>
                <select name="eps" class="js-choice" required>
                    <option value="">Seleccione...</option>
                    <option>Aliansalud EPS</option>
                    <option>Salud Total EPS S.A</option>
                    <option>EPS Sanitas</option>
                    <option>Eps Sura</option>
                    <option>Famisanar</option>
                    <option>Servicio Occidental de Salud EPS - SOS</option>
                    <option>Compensar EPS</option>
                </select>
            </div>
            <div class="col-md-6">
                <label>Estrato</label>
                <select name="estrato" class="js-choice" required>
                    <option value="">Seleccione...</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                    <option value="6">6</option>
                </select>
            </div>
            <!-- Eliminado duplicado de teléfono y correo institucional -->
            <div class="col-md-6">
                <label>Contraseña*</label>
                <input type="password" name="password" class="form-control" style="min-height:48px;padding:12px 16px;border:2px solid rgba(0,48,77,0.65);border-radius:999px;background:var(--blanco);color:var(--negro);font-size:14px;outline:none;transition:box-shadow .15s ease,border-color .15s ease;margin:0;" required>
            </div>
        </div>
        <input type="hidden" name="rol_id" value="4">
    <button type="submit" name="registro" class="btn-registrar">Registrar Asistente</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="/js/registro.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof flatpickr !== "undefined") {
        const el = document.querySelector('#fecha_nacimiento');
        if (el) {
            flatpickr(el, {
                dateFormat: 'd/m/Y',
                maxDate: 'today',
                locale: 'es',
                disableMobile: true,
                monthSelectorType: 'dropdown',
                yearSelectorType: 'dropdown',
                appendTo: document.querySelector('.asistente-crear') || undefined,
            });
            // Abrir al pulsar el icono
            const icon = document.querySelector('.date-icon');
            if (icon) icon.addEventListener('click', ()=> el._flatpickr && el._flatpickr.open());
        }
    }
});
</script>
<?php include __DIR__ . '/../Componentes/footer.php'; ?>
</body>
</html>
