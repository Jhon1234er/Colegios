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
// Datos de cabecera
$cantidad = is_array($resultados) ? count($resultados) : 0;
$etiqueta = $filtroTexto[$filtro] ?? 'Resultados';
?>

<link rel="stylesheet" href="/css/Componentes/resultados.css">
<style>
  /* Forzar fondo de página blanco en esta vista (sobrescribe encabezado gris) */
  html, body { background: #ffffff !important; }
</style>

<!-- Backbar inline (será movida fuera del contenedor por JS tras la carga) -->
<div id="backbar-inline" class="backbar" style="display:none;">
  <button class="backbar__btn" type="button" id="back-to-dashboard">
    <span class="backbar__icon" aria-hidden="true">←</span>
    <span class="backbar__label">Volver al inicio</span>
  </button>
  <!-- Puedes añadir chips/resumen si quieres -->
  <div class="backbar__extra" aria-hidden="true"></div>
  </div>

<!-- Contenedor de resultados de búsqueda -->
<div class="search-results-container">
    <div class="search-header">
        <h2>Resultados de búsqueda</h2>
        <span class="results-count" aria-live="polite"><?= $cantidad ?> resultado<?= $cantidad === 1 ? '' : 's' ?></span>
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
                            <?php
                              $nombresP = trim(($resultado['nombres'] ?? ''));
                              $apellidosP = trim(($resultado['apellidos'] ?? ''));
                              $nombreCompletoP = trim($nombresP . ' ' . $apellidosP);
                              $tip_raw = $resultado['tip_contrato'] ?? '';
                              $tip_contrato = mb_strtolower(trim((string)$tip_raw), 'UTF-8');
                              // Regla: planta => Instructor; contratista => Facilitador (por defecto)
                              $etiqueta_profesor = (in_array($tip_contrato, ['planta','instructor'], true)) ? 'Instructor' : 'Facilitador';
                            ?>
                            <h4><?= htmlspecialchars($nombreCompletoP) ?></h4>
                            <span class="result-type result-type--facilitador"><?= $etiqueta_profesor ?></span>
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
                            <button type="button" class="btn btn-primary btn-ver-detalles-profesor" data-id="<?= htmlspecialchars($resultado['id'] ?? '') ?>">Ver Detalles</button>
                            <button type="button" class="btn btn-secondary btn-exportar-profesor" data-id="<?= htmlspecialchars($resultado['id'] ?? '') ?>" title="Exportar reporte de clases">Exportar</button>
                        </div>

                    <?php elseif ($filtro === 'estudiante'): ?>
                        <div class="result-header">
                            <h4><?= htmlspecialchars(trim(($resultado['nombres'] ?? '') . ' ' . ($resultado['apellidos'] ?? ''))) ?></h4>
                            <span class="badge badge-secondary result-type">Aprendiz</span>
                        </div>
                        <div class="result-details">
                            <p><strong>Documento:</strong> <?= htmlspecialchars($resultado['numero_documento'] ?? '') ?></p>
                            <?php if (!empty($resultado['email'])): ?>
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

