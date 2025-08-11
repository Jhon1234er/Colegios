<?php require __DIR__ . '/../Componentes/encabezado.php'; ?>

<style>
    body {
  font-family: 'Segoe UI', sans-serif;
  background-color: #f0f2f5;
  color: #2c3e50;
  margin-top: 7%;
  padding: 40px 20px;
}

/* Encabezados */
h2, h3, h4 {
  color: #2c3e50;
  margin-bottom: 20px;
  text-align: center;
}
</style>

<h2>Bienvenido, Aprendiz <?= htmlspecialchars($_SESSION['usuario']['nombres']) ?></h2>

