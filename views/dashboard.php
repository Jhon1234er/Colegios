<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: /COLEGIOS/views/login.php');
    exit;
}

$usuario = $_SESSION['usuario'];
$isAdmin = isset($usuario['rol_id']) && $usuario['rol_id'] == 1;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administrador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 30px;
            font-family: Arial, sans-serif;
        }
        .dashboard-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .logout-link {
            text-decoration: none;
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container dashboard-card">
    <h2 class="mb-4">Bienvenido al Panel</h2>

    <p><strong>Nombre:</strong> <?= htmlspecialchars($usuario['nombres']) ?> <?= htmlspecialchars($usuario['apellidos']) ?></p>
    <p><strong>Correo:</strong> <?= htmlspecialchars($usuario['correo_electronico']) ?></p>
    <p><strong>Rol:</strong> <?= $isAdmin ? 'Administrador' : 'Otro' ?></p>

    <a class="logout-link" href="/COLEGIOS/logout.php">Cerrar sesión</a>
</div>

</body>
</html>
