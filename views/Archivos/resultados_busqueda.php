<?php
// Archivo para mostrar resultados de búsqueda AJAX
if (!isset($resultados) || !isset($filtro) || !isset($query)) {
    echo '<div class="alert alert-danger">Error: Parámetros de búsqueda no válidos</div>';
    exit;
}

// Helper para formatear nombres
function formatearNombreColegio($nombre) {
    $nombre = mb_convert_case($nombre, MB_CASE_TITLE, "UTF-8");
    $nombre = preg_replace_callback('/(\s|,)\s*([A-Za-z])\.([A-Za-z])\.?/u', fn($m) => $m[1] . strtoupper($m[2]) . '.' . strtoupper($m[3]), $nombre);
    $nombre = preg_replace_callback('/\(([a-zA-Z]{2,})\)/u', fn($m) => '(' . strtoupper($m[1]) . ')', $nombre);
    return $nombre;
}

$filtroTexto = [
    'colegio' => 'Colegios',
    'profesor' => 'Facilitadores', 
    'estudiante' => 'Aprendices'
];
?>

<!-- Contenedor de resultados de búsqueda -->
<div class="search-results-container">
    <div class="search-header">
        <h2>Resultados de búsqueda</h2>
    </div>
    
    <?php if (empty($resultados)): ?>
        <div class="no-results">
            <p>No se encontraron resultados para tu búsqueda.</p>
        </div>
    <?php else: ?>
        <div class="results-grid">
            <?php foreach ($resultados as $resultado): ?>
                <div class="result-card"><?php if ($filtro === 'colegio'): ?>
                        <div class="result-header">
                            <h4><?= formatearNombreColegio(htmlspecialchars($resultado['nombre'])) ?></h4>
                            <span class="result-type">Colegio</span>
                        </div>
                        <div class="result-details">
                            <p><strong>DANE:</strong> <?= htmlspecialchars($resultado['codigo_dane']) ?></p>
                            <p><strong>Tipo:</strong> <?= htmlspecialchars($resultado['tipo_institucion']) ?></p>
                            <p><strong>Ubicación:</strong> <?= formatearNombreColegio(htmlspecialchars($resultado['municipio'] . ', ' . $resultado['departamento'])) ?></p>
                            <?php if (!empty($resultado['telefono'])): ?>
                                <p><strong>Teléfono:</strong> <?= htmlspecialchars($resultado['telefono']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="result-actions">
                            <a href="/?page=colegios&action=ver&id=<?= $resultado['id'] ?>" class="btn btn-primary">Ver Detalles</a>
                            <a href="/?page=colegios&action=editar&id=<?= $resultado['id'] ?>" class="btn btn-secondary">Editar</a>
                        </div>

                    <?php elseif ($filtro === 'profesor'): ?>
                        <div class="result-header">
                            <h4 style="text-align: center;"><?= htmlspecialchars(trim(($resultado['nombres'] ?? '') . ' ' . ($resultado['apellidos'] ?? ''))) ?></h4>
                            <?php 
                                $tip_contrato = strtolower($resultado['tip_contrato'] ?? '');
                                $etiqueta_profesor = ($tip_contrato === 'instructor') ? 'Instructor' : 'Facilitador';
                            ?>
                            <span class="result-type"><?= $etiqueta_profesor ?></span>
                        </div>
                        <div class="result-details">
                            <p><strong>Documento:</strong> <?= htmlspecialchars($resultado['numero_documento'] ?? '') ?></p>
                            <?php if (!empty($resultado['correo_electronico'])): ?>
                                <p><strong>Email:</strong> <?= htmlspecialchars($resultado['correo_electronico']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($resultado['telefono'])): ?>
                                <p><strong>Teléfono:</strong> <?= htmlspecialchars($resultado['telefono']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($resultado['especialidad'])): ?>
                                <p><strong>Especialidad:</strong> <?= htmlspecialchars($resultado['especialidad']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="result-actions">
                            <button type="button" class="btn btn-primary btn-ver-detalles-profesor" data-id="<?= htmlspecialchars($resultado['id'] ?? '') ?>">
                                <i class="fas fa-eye me-1"></i> Ver Detalles
                            </button>
                            <button type="button" class="btn btn-info text-white btn-exportar-profesor" data-id="<?= htmlspecialchars($resultado['id'] ?? '') ?>" title="Exportar reporte de clases">
                                <i class="fas fa-file-export me-1"></i> Exportar
                            </button>
                        </div>

                    <?php elseif ($filtro === 'estudiante'): ?>
                        <div class="result-header">
                            <h4 style="text-align: center;"><?= htmlspecialchars(trim(($resultado['nombres'] ?? '') . ' ' . ($resultado['apellidos'] ?? ''))) ?></h4>
                            <span class="result-type">Aprendiz</span>
                        </div>
                        <div class="result-details">
                            <p><strong>Documento:</strong> <?= htmlspecialchars($resultado['numero_documento'] ?? '') ?></p>
                            <?php if (!empty($resultado['email'])): ?>
                                <p><strong>Email:</strong> <?= htmlspecialchars($resultado['email']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($resultado['telefono'])): ?>
                                <p><strong>Teléfono:</strong> <?= htmlspecialchars($resultado['telefono']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($resultado['ficha_nombre'])): ?>
                                <p><strong>Ficha:</strong> <?= htmlspecialchars($resultado['ficha_nombre']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($resultado['colegio_nombre'])): ?>
                                <p><strong>Colegio:</strong> <?= formatearNombreColegio(htmlspecialchars($resultado['colegio_nombre'])) ?></p>
                            <?php endif; ?>
                        </div>
                        
                        
                        <div class="result-actions">
                            <button type="button" class="btn btn-primary btn-ver-detalles" data-id="<?= htmlspecialchars($resultado['id'] ?? '') ?>">Ver Detalles</button>
                            <a href="/?page=estudiantes&action=editar&id=<?= htmlspecialchars($resultado['id'] ?? '') ?>" class="btn btn-secondary btn-editar" style="opacity: 0.5; pointer-events: none;">Editar</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* Contenedor de resultados de búsqueda */
.search-results-container {
    background: rgb(238, 238, 238);
    border-radius: 12px;
    padding: 20px;
    overflow-y: auto;
    margin: 0px auto;
}

.search-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #f5ffe3 0%, #e8f5e8 100%);
    border-radius: 15px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.search-header h2 {
    color: #4c7840;
    margin-bottom: 10px;
}

.search-header p {
    color: #666;
    margin: 5px 0;
}

.results-count {
    font-size: 14px;
    color: #769a69;
    font-weight: 500;
}

.no-results {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-results-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.no-results h3 {
    color: #4c7840;
    margin-bottom: 10px;
}

.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.result-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    border: 1px solid #e0e0e0;
}

.result-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.result-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.result-header h4 {
    color: #2c5530;
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    flex: 1;
}

.result-type {
    background: linear-gradient(135deg, #4c7840, #769a69);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
}

.result-details {
    margin-bottom: 20px;
}

.result-details p {
    margin: 5px 0;
    color: #666;
    font-size: 0.9rem;
}

.result-details strong {
    color: #4c7840;
    font-weight: 600;
}

.result-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 16px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: linear-gradient(135deg, #4c7840, #769a69);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(76, 120, 64, 0.3);
}

.btn-secondary {
    background: #f8f9fa;
    color: #4c7840;
    border: 1px solid #dee2e6;
}

.btn-secondary:hover {
    background: #e9ecef;
    transform: translateY(-1px);
}

/* Student Details Panel Styles */
.student-details-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    font-size: 16px;
}

.student-details-content {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    animation: fadeIn 0.3s ease-out;
}

/* Contenedor de Datos */
.data-container {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 20px;
    width: 2000px;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e40af;
    margin-bottom: 20px;
    padding-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.data-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 15px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.data-item:hover {
    background: #f1f5f9;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.data-label {
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    min-width: 120px;
    flex-shrink: 0;
}

.data-value {
    color: #1f2937;
    font-size: 0.95rem;
    line-height: 1.4;
    flex: 1;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    color: #15803d;
    border: 1px solid #86efac;
}

.status-badge::before {
    content: '●';
    margin-right: 6px;
}

.info-section {
    background: white;
    border: 2px solid #1e7432;
    border-radius: 8px;
    overflow: hidden;
}

.section-header {
    background: linear-gradient(to bottom right, #1e7432, #098b2b);
    color: white;
    padding: 12px 16px;
    font-weight: bold;
    font-size: 14px;
    text-align: center;
    letter-spacing: 0.5px;
}

.section-content {
    padding: 16px;
    min-height: 80px;
    background: white;
}

.section-content p {
    margin: 8px 0;
    font-size: 14px;
    color: #333;
}

.section-content p:first-child {
    margin-top: 0;
}

.section-content p:last-child {
    margin-bottom: 0;
}

/* Button states */
.btn-editar:disabled,
.btn-editar[style*="pointer-events: none"] {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-editar.enabled {
    opacity: 1;
    pointer-events: auto;
}

/* Animation for details panel */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .data-container {
        padding: 20px;
        max-width: 100%;
    }
    
    .data-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .section-title {
        font-size: 1rem;
        margin-bottom: 15px;
    }
}

@media (max-width: 768px) {
    .main-layout {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .result-actions {
        justify-content: center;
    }
    
    .student-details-container {
        position: relative;
        top: auto;
        max-height: 60vh;
        overflow-y: auto;
        gap: 15px;
    }
    
    .data-container {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .data-item {
        padding: 12px;
    }
    
    .section-title {
        font-size: 0.95rem;
        margin-bottom: 12px;
    }
}

@media (max-width: 480px) {
    .data-container {
        padding: 12px;
        border-radius: 8px;
    }
    
    .data-item {
        padding: 10px;
        gap: 3px;
    }
    
    .data-label {
        font-size: 0.8rem;
    }
    
    .data-value {
        font-size: 0.85rem;
    }
    
    .section-title {
        font-size: 0.9rem;
        margin-bottom: 10px;
    }
}
</style>
