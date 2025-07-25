<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: /');
    exit;
}

$usuario = $_SESSION['usuario'];
$isAdmin = isset($usuario['rol_id']) && $usuario['rol_id'] == 1;

//PARA LLAMAR EL TOTAL DE USUARIOS
require_once __DIR__ . '/../models/Usuario.php';
$usuarioModel = new Usuario();
$totalUsuarios = $usuarioModel->contarUsuarios();

//PARA LLAMAR EL TOTAL DE USUARIOS
require_once __DIR__ . '/../models/Estudiante.php';
$estudianteModel = new Estudiante();
$totalEstudiante = $estudianteModel ->contarEstudiantes();

//PARA LLAMAR EL TOTAL DE PROFESORES
require_once __DIR__ . '/../models/Profesor.php';
$profesorModel = new Profesor();
$totalProfesores = $profesorModel -> contarProfesores();

//PARA LLAMAR EL TOTAL DE MATERIAS
require_once __DIR__ . '/../models/Materia.php';
$materiaModel = new Materia();
$totalMaterias = $materiaModel -> contarMaterias();

//PARA LLAMAR LA LISTA DE COLEGIOS
require_once __DIR__ . '/../models/Colegio.php';
$colegioModel = new Colegio();
$colegios = $colegioModel->obtenerTodos(); 
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administrador</title>
    <link rel="stylesheet" href="/css/dashboard.css">
</head>
<body>
    <?php include 'Componentes/encabezado.php'; ?>

    <div class="parent">
        <div class="div1" style="overflow-y: auto; padding: 1rem;"> <p style="color: #666;">Selecciona un colegio en la tabla para ver sus profesores y materias.</p> </div>

        <div class="div2" style="overflow-y: auto; padding: 1rem;"> <p style="color: #666;">Selecciona un colegio en la tabla para ver sus estudiantes</p> </div>
        
        <div class="div3" style="overflow-y: auto; padding: 1rem;">
            <h3>Estadísticas</h3>
            <p class="esta"style="color: #666;">Selecciona un colegio en la tabla para ver las asistencias de sus estudiantes</p> 
            <canvas id="graficoColegios" width="400" height="100"></canvas>
        </div>

        
        <div class="div4">
            <h3 class="Tabla">Colegios Existentes</h3>
            <table class="tabla-colegios">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Municipio</th>
                        <th>Departamento</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($colegios as $colegio): ?>
                        <tr>
                            <td><?= htmlspecialchars($colegio['nombre']) ?></td>
                            <td><?= htmlspecialchars($colegio['tipo_institucion']) ?></td>
                            <td><?= htmlspecialchars($colegio['municipio']) ?></td>
                            <td><?= htmlspecialchars($colegio['departamento']) ?></td>
                            <td>
                                <button class="btn-ver-colegio" data-id="<?= $colegio['id'] ?>">Ver Informacion</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="div5">
            <h3>Total de Materias</h3>
            <p style="font-size: 2rem; font-weight: bold;">
            <?= $totalMaterias ?>
            </p>
        </div>
        
        <div class="div6">
            <h3>Total de Profesores</h3>
            <p style="font-size: 2rem; font-weight: bold;">
            <?= $totalProfesores ?>
            </p>
        </div>
        
        <div class="div7">
            <h3>Total de Usuarios</h3>
            <p style="font-size: 2rem; font-weight: bold;">
            <?= $totalUsuarios ?>
            </p>
        </div>

        <div class="div8">
            <h3>Total de Estudiantes</h3>
            <p style="font-size: 2rem; font-weight: bold;">
            <?= $totalEstudiante ?>
            </p>
        </div>

        <div class="div9">
            <p> <strong>Bienvenido</strong> <?= htmlspecialchars($usuario['nombres']) ?> <?= htmlspecialchars($usuario['apellidos']) ?> a Sistem scholl</p>
            <p class="texto-rol"><strong>Rol:</strong> <?= $isAdmin ? 'Administrador' : 'Otro' ?></p>

        </div>
    </div>

    <?php include 'Componentes/footer.php'; ?>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>


<!-- Tu JS personalizado -->
<script src="/../js/dashboard.js"></script>
</body>
</html>
