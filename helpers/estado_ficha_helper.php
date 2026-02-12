<?php
/**
 * Helper functions para el sistema escolar SENA
 */

/**
 * Mapeo de IDs de estado de fichas a descripciones
 * @param int $estado_id
 * @return string
 */
function getEstadoFichaDescripcion($estado_id) {
    $estados_map = [
        1 => 'Activa',
        2 => 'Suspendida', 
        3 => 'Finalizada',
        4 => 'Archivada'
    ];
    return $estados_map[$estado_id] ?? 'Activa';
}

/**
 * Obtener color para badge de estado de ficha
 * @param int $estado_id
 * @return string
 */
function getEstadoFichaColor($estado_id) {
    $colores = [
        1 => 'background:#dcfce7;color:#14532d;', // Activa - verde
        2 => 'background:#fef2f2;color:#991b1b;', // Suspendida - rojo
        3 => 'background:#f0f9ff;color:#1e40af;', // Finalizada - azul
        4 => 'background:#f8fafc;color:#475569;'  // Archivada - gris
    ];
    return $colores[$estado_id] ?? $colores[1];
}

/**
 * Obtener color para dot de estado de ficha
 * @param int $estado_id
 * @return string
 */
function getEstadoFichaDotColor($estado_id) {
    $dot_colors = [
        1 => 'background:#22c55e;', // Activa - verde
        2 => 'background:#dc2626;', // Suspendida - rojo
        3 => 'background:#2563eb;', // Finalizada - azul
        4 => 'background:#64748b;'  // Archivada - gris
    ];
    return $dot_colors[$estado_id] ?? $dot_colors[1];
}

/**
 * Mapeo de texto a ID de estado de ficha
 * @param string $estado_texto
 * @return int
 */
function getEstadoFichaId($estado_texto) {
    $mapeo = [
        'activa' => 1, 'activo' => 1, 'active' => 1,
        'suspendida' => 2, 'suspendido' => 2, 'suspended' => 2, 'cerrada' => 2, 'cerrado' => 2,
        'finalizada' => 3, 'finalizado' => 3, 'finished' => 3,
        'archivada' => 4, 'archivado' => 4, 'archived' => 4
    ];
    return $mapeo[strtolower($estado_texto)] ?? 1;
}

/**
 * Verificar si una ficha permite acciones según su estado
 * @param array $ficha Datos de la ficha
 * @param string $accion Tipo de acción a verificar
 * @return array ['permitido' => bool, 'mensaje' => string]
 */
function validarAccionesFicha($ficha, $accion = '') {
    $estado_id = (int)($ficha['estado_id'] ?? 1);
    $cupo_total = (int)($ficha['cupo_total'] ?? 0);
    $cupo_usado = (int)($ficha['cupo_usado'] ?? 0);
    $cupo_disponible = $cupo_total - $cupo_usado;
    
    switch ($estado_id) {
        case 1: // Activa
            if ($cupo_disponible <= 0 && in_array($accion, ['registrar_aprendiz', 'importar', 'asignar_pendiente'])) {
                return ['permitido' => false, 'mensaje' => 'La ficha está activa pero ha alcanzado su cupo máximo.'];
            }
            return ['permitido' => true, 'mensaje' => ''];
            
        case 2: // Suspendida
            return ['permitido' => false, 'mensaje' => 'La ficha está suspendida. No se permiten acciones.'];
            
        case 3: // Finalizada
            return ['permitido' => false, 'mensaje' => 'La ficha está finalizada. No se permiten acciones.'];
            
        case 4: // Archivada
            return ['permitido' => false, 'mensaje' => 'La ficha está archivada. No se permiten acciones.'];
            
        default:
            return ['permitido' => true, 'mensaje' => ''];
    }
}
?>
