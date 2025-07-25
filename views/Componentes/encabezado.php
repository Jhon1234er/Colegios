<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistem Scholl</title>
</head>
<body>

<nav>
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem;">
        <div>
            <strong>Sistem Scholl</strong>
        </div>
        <div>
            <a href="/?page=profesores&action=crear" class="btn btn-success mb-3">Crear Profesor</a>
            <a href="/?page=profesores" class="btn btn-primary mb-3">Ver Profesores</a>
            <a href="?page=estudiantes&action=crear">Crear Estudiante</a> |
            <a href="/?page=colegios&action=crear">Ingresar Colegio</a> |
            <a href="?page=crear_materia">Registrar Materia</a> |
            <a href="?page=materias">Ver Materias</a> | 
            <a href="/logout.php">Cerrar sesión</a>
        </div>
    </div>
</nav>

<div class="contenido">
