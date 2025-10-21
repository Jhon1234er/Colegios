<?php 
require_once __DIR__ . '/../Componentes/encabezado.php';
$titulo = 'Importar Estudiantes';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="fas fa-file-import me-2"></i> Importar Estudiantes desde Excel
                    </h4>
                    <p class="text-muted mb-0">
                        Sube un archivo Excel con la información de los estudiantes para importarlos al sistema.
                    </p>
                </div>
                <div class="card-body">
                    <form action="?page=estudiante_importar_excel" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <?php echo csrf_input(); ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="colegio_id" class="form-label">Colegio <span class="text-danger">*</span></label>
                                    <select class="form-select" id="colegio_id" name="colegio_id" required>
                                        <option value="">Seleccione un colegio...</option>
                                        <?php foreach ($colegios as $colegio): ?>
                                            <option value="<?= htmlspecialchars($colegio['id']) ?>" <?= isset($colegio_pre) && $colegio_pre == $colegio['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($colegio['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Por favor seleccione un colegio.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ficha_id" class="form-label">Ficha (Opcional)</label>
                                    <select class="form-select" id="ficha_id" name="ficha_id">
                                        <option value="">Sin ficha específica</option>
                                        <?php foreach ($fichas as $ficha): ?>
                                            <option value="<?= htmlspecialchars($ficha['id']) ?>" <?= isset($ficha_id_pre) && $ficha_id_pre == $ficha['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($ficha['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="archivo_excel" class="form-label">
                                Archivo Excel <span class="text-danger">*</span>
                                <small class="text-muted d-block">Formatos soportados: .xls, .xlsx</small>
                            </label>
                            <input type="file" class="form-control" id="archivo_excel" name="archivo_excel" accept=".xls,.xlsx" required>
                            <div class="invalid-feedback">
                                Por favor seleccione un archivo Excel válido.
                            </div>
                            <div class="form-text">
                                <a href="<?= APP_URL ?>/assets/plantillas/plantilla_estudiantes.xlsx" class="text-primary">
                                    <i class="fas fa-download me-1"></i> Descargar plantilla de ejemplo
                                </a>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Instrucciones:</h6>
                            <ol class="mb-0">
                                <li>Descargue la plantilla de ejemplo para asegurar el formato correcto.</li>
                                <li>Complete los datos de los estudiantes en la hoja de cálculo.</li>
                                <li>Guarde el archivo en formato Excel (.xls o .xlsx).</li>
                                <li>Seleccione el colegio y la ficha (opcional) correspondiente.</li>
                                <li>Suba el archivo completado.</li>
                            </ol>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="?page=estudiante" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Volver al listado
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i> Importar Estudiantes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validación del formulario
(function () {
    'use strict'
    
    // Obtener el formulario al que queremos agregar la validación
    var forms = document.querySelectorAll('.needs-validation')
    
    // Bucle sobre los formularios y evitar el envío
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                form.classList.add('was-validated')
            }, false)
        })
})()
</script>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
