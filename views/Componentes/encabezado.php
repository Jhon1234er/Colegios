<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistem Scholl</title>
</head>
<body>

<nav>
    <div class="contenedor">
        <a>Sistem Scholl</a>
        <button class="" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class=""></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="">
                <li class=""><a class="" href="index.php?page=dashboard"><i class=""></i>Inicio</a></li>
                <li class=""><a class="" href="index.php?page=registro"><i class=""></i>Registrar Estudiante</a></li>
                <li class=""><a class="" href="index.php?page=listado"><i class=""></i>Listado Estudiantes</a></li>
                <li class=""><a class="" href="index.php?page=registrar_colegio"><i class=""></i>Registrar Colegio</a></li>
                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                <li class=""><a class="" href="index.php?page=usuarios"><i class=""></i>Usuarios</a></li>
                <?php endif; ?>
            </ul>
            <span class="">
                Bienvenido, <b><?= $_SESSION['usuario'] ?? 'Invitado' ?></b>
                <?php if (!empty($_SESSION['rol'])): ?>
                    <span class="">Rol: <?= htmlspecialchars($_SESSION['rol']) ?></span>
                <?php endif; ?>
            </span>
            <a href="" class="">Cerrar sesión</a>
        </div>
    </div>
</nav>

<div>
