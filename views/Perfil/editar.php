<?php include '../views/Componentes/encabezado.php'; ?>

<div class="p-8">
  <h1 class="text-2xl font-bold mb-4">Editar Perfil</h1>

  <form method="POST" action="/?page=editar_perfil" class="bg-white p-6 rounded shadow-md w-full max-w-md space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700">Nombre</label>
      <input type="text" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" required
             class="mt-1 block w-full border border-gray-300 rounded-md p-2">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">Correo</label>
      <input type="email" name="correo" value="<?= htmlspecialchars($usuario['correo']) ?>" required
             class="mt-1 block w-full border border-gray-300 rounded-md p-2">
    </div>

    <button type="submit"
            class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
      Guardar cambios
    </button>
  </form>
</div>

<?php include '../views/Componentes/footer.php'; ?>
