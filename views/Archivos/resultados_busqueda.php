<?php
// Evitar acceso directo
if (!isset($resultados) || !isset($filtro) || !isset($query)) {
    // Redirigir o mostrar un error si se accede directamente
    header('Location: /?page=dashboard');
    exit;
}

$filtro_texto = ucfirst($filtro);
if ($filtro === 'profesor') {
    $filtro_texto = 'Facilitador/Instructor';
}
?>

<div class="p-4 md:p-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl md:text-2xl font-bold text-gray-800">
            Resultados para <span class="text-green-600"><?= htmlspecialchars($query) ?></span> en <span class="text-green-600"><?= htmlspecialchars($filtro_texto) ?></span>
        </h2>
        <button id="volver-dashboard" class="bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300 ease-in-out">
            &larr; Volver al Panel
        </button>
    </div>

    <?php if (empty($resultados)): ?>
        <div class="text-center py-10 px-4 bg-white rounded-lg shadow-md">
            <p class="text-gray-500 text-lg">No se encontraron resultados.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
            <?php switch ($filtro):
                case 'colegio': ?>
                    <?php foreach ($resultados as $item): ?>
                        <div class="bg-white rounded-lg shadow-md p-4 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="font-bold text-lg text-gray-900"><?= htmlspecialchars($item['nombre']) ?></h3>
                            <p class="text-sm text-gray-600">DANE: <?= htmlspecialchars($item['codigo_dane']) ?></p>
                            <p class="text-sm text-gray-600">Dirección: <?= htmlspecialchars($item['direccion']) ?></p>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($item['municipio']) ?>, <?= htmlspecialchars($item['departamento']) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <?php break; ?>

                <?php case 'profesor': ?>
                    <?php foreach ($resultados as $item): ?>
                        <div class="bg-white rounded-lg shadow-md p-4 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="font-bold text-lg text-gray-900"><?= htmlspecialchars($item['nombres'] . ' ' . $item['apellidos']) ?></h3>
                            <p class="text-sm text-gray-600">Especialidad: <?= htmlspecialchars($item['especialidad']) ?></p>
                            <p class="text-sm text-gray-600">Email: <?= htmlspecialchars($item['correo_electronico']) ?></p>
                            <p class="text-sm text-gray-600">Contrato: <?= htmlspecialchars($item['tip_contrato']) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <?php break; ?>

                <?php case 'estudiante': ?>
                    <?php foreach ($resultados as $item): ?>
                        <div class="bg-white rounded-lg shadow-md p-4 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="font-bold text-lg text-gray-900"><?= htmlspecialchars($item['nombre_completo']) ?></h3>
                            <p class="text-sm text-gray-600">Ficha: <?= htmlspecialchars($item['ficha']) ?></p>
                            <p class="text-sm text-gray-600">Grado: <?= htmlspecialchars($item['grado']) ?></p>
                            <p class="text-sm text-gray-600">Jornada: <?= htmlspecialchars($item['jornada']) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <?php break; ?>

            <?php endswitch; ?>
        </div>
    <?php endif; ?>
</div>
