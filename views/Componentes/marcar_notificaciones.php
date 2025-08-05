<?php
session_start();
require_once '../config/db.php';

$usuario_id = $_SESSION['usuario']['id'];
$tipo_usuario = $_SESSION['usuario']['rol'];

$pdo = Database::conectar();
$stmt = $pdo->prepare("UPDATE notificaciones SET estado = 1 WHERE usuario_id = ? AND tipo_usuario = ?");
$stmt->execute([$usuario_id, $tipo_usuario]);

http_response_code(200);
