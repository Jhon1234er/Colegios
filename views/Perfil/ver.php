<?php include '../views/Componentes/encabezado.php'; ?>
<link rel="stylesheet" href="/css/perfil.css">

<div class="perfil-contenedor">
  <h1 class="titulo">Mi Perfil</h1>

  <div class="perfil-grid">
    <!-- FOTO + DATOS PERSONALES -->
    <div class="card foto-datos">
      <div class="zona-foto">
        <img src="<?= $usuario['foto'] ?? '/icons/perfil.png' ?>" alt="Foto de perfil" class="foto-perfil" id="fotoPerfil">

        <!-- Botones principales visibles -->
        <div class="botones-secundarios" id="botonesPerfil">
          <button class="btn azul" onclick="activarCambioFoto()">Cambiar foto</button>
          <button class="btn verde">Editar Perfil</button>
          <button class="btn amarillo">Cambiar Contraseña</button>
        </div>

        <!-- Formulario para cambiar foto -->
        <form id="formFoto" class="form-foto" method="POST" action="/?page=subir_foto" enctype="multipart/form-data" style="display:none;">
          <input type="file" name="nueva_foto" accept="image/*">
          <div class="acciones-foto">
            <button type="submit" class="btn verde">Guardar</button>
            <button type="button" class="btn gris" onclick="cancelarCambioFoto()">Cancelar</button>
          </div>
        </form>

        <!-- Botón para eliminar foto si existe -->
        <?php if (!empty($usuario['foto'])): ?>
          <form method="POST" action="/?page=eliminar_foto">
            <button type="submit" class="btn rojo">Eliminar foto</button>
          </form>
        <?php endif; ?>
      </div>

      <!-- Datos personales -->
      <div class="datos-personales">
        <h2>Datos Personales</h2>
        <p><strong>Nombre:</strong> <?= ($usuario['nombres'] ?? '') . ' ' . ($usuario['apellidos'] ?? '') ?></p>
        <p><strong>Documento:</strong> <?= ($usuario['tipo_documento'] ?? '') . ' ' . ($usuario['numero_documento'] ?? '') ?></p>
        <p><strong>Fecha de Nacimiento:</strong> <?= $usuario['fecha_nacimiento'] ?? '' ?></p>
        <p><strong>Género:</strong>
          <?php
            $generos = ['M' => 'Masculino', 'F' => 'Femenino', 'O' => 'Otro'];
            echo isset($usuario['genero']) ? ($generos[$usuario['genero']] ?? 'No disponible') : 'No disponible';
          ?>
        </p>
        <p><strong>Correo:</strong> <?= $usuario['correo_electronico'] ?? '' ?></p>
        <p><strong>Teléfono:</strong> <?= $usuario['telefono'] ?? '' ?></p>
      </div>
    </div>

    <!-- DATOS DE CONTACTO -->
    <div class="card contacto">
      <h2>Datos de Contacto</h2>
      <p><strong>Nombre de Acudiente:</strong> <?= $usuario['acudiente'] ?? '—' ?></p>
      <p><strong>Documento del Acudiente:</strong> <?= $usuario['doc_acudiente'] ?? '—' ?></p>
      <p><strong>Correo Institucional:</strong> <?= $usuario['correo_institucional'] ?? '—' ?></p>
      <p><strong>Teléfono del Acudiente:</strong> <?= $usuario['telefono_acudiente'] ?? '—' ?></p>
      <p><strong>Dirección:</strong> <?= $usuario['direccion'] ?? '—' ?></p>
      <p><strong>Parentesco:</strong> <?= $usuario['parentesco'] ?? '—' ?></p>
      <p><strong>Ocupación:</strong> <?= $usuario['ocupacion'] ?? '—' ?></p>
    </div>
  </div>
</div>

<!-- Script para cambiar/cancelar foto -->
<script>
  function activarCambioFoto() {
    document.getElementById('botonesPerfil').style.display = 'none';
    document.getElementById('formFoto').style.display = 'block';
  }

  function cancelarCambioFoto() {
    document.getElementById('botonesPerfil').style.display = 'flex';
    document.getElementById('formFoto').style.display = 'none';
  }
</script>

<?php include '../views/Componentes/footer.php'; ?>
