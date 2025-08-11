<?php require __DIR__ . '/../Componentes/encabezado.php'; ?>

<link rel="stylesheet" href="/css/Profesor/dashboard_profesor.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (!empty($_GET['success']) && $_GET['success'] === 'asistencia'): ?>
    <div style="background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 1em; margin-bottom: 1em;">
        Asistencia registrada exitosamente.
    </div>
<?php elseif (!empty($_GET['error']) && $_GET['error'] === 'asistencia_ya_registrada'): ?>
    <div id="alerta-asistencia" style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 1em; margin-bottom: 1em;">
        Ya registraste asistencia hoy para esta ficha.
    </div>
<?php endif; ?>
<style>
  html {
    scroll-behavior: smooth;
  }
  body {
    -webkit-font-smoothing: antialiased;
  }
</style>

<h2>Bienvenido, Facilitador <?= htmlspecialchars($_SESSION['usuario']['nombres']) ?></h2>

<h3>Mis Fichas</h3>
<a href="/?page=crear_tarea" class="boton-tarea">Nueva Actividad</a>

<ul id="lista-fichas"></ul>

<div id="estudiantes-ficha" style="margin-top:2em; text-align:center;">Seleccione una ficha para saber de sus Aprendices</div>

<?php
require_once '../config/db.php';
$pdo = Database::conectar();

// Obtener ficha_id desde GET o POST
$ficha_id = $_GET['ficha_id'] ?? $_POST['ficha_id'] ?? null;
$ficha_nombre = 'Ficha desconocida';

if ($ficha_id) {
    $stmt = $pdo->prepare("SELECT nombre FROM fichas WHERE id = ?");
    $stmt->execute([$ficha_id]);
    $ficha = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ficha) {
        $ficha_nombre = $ficha['nombre'];
    }
}
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ul = document.getElementById('lista-fichas');
    const estudiantesDiv = document.getElementById('estudiantes-ficha');

let fichasGlobal = []; // <- Guardamos todas las fichas

// Cargar fichas del profesor
fetch('index.php?page=profesorficha')
    .then(res => res.json())
    .then(fichas => {
        fichasGlobal = fichas; // <- Guardamos aquí

        if (!Array.isArray(fichas) || !fichas.length) {
            ul.innerHTML = '<li>No tienes fichas asignadas.</li>';
            return;
        }

        fichas.forEach(f => {
            const li = document.createElement('li');
            li.innerHTML = `
                ${f.nombre}
                <button data-id="${f.id}" class="btn-ver-estudiantes">Ver Aprendices</button>
                <a href="/?page=ver_notas&ficha_id=${f.id}">Ver notas</a>

            `;
            ul.appendChild(li);
        });
    })
    .catch(err => {
        ul.innerHTML = '<li>Error cargando fichas.</li>';
    });

ul.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-ver-estudiantes')) {
        const fichaId = e.target.dataset.id;
        const fichaNombre = fichasGlobal.find(f => f.id == fichaId)?.nombre || 'Ficha desconocida';

        estudiantesDiv.innerHTML = '<p>Cargando aprendices...</p>';

            fetch(`index.php?page=estudiantesporficha&ficha_id=${fichaId}`)
            .then(res => res.json())
            .then(estudiantes => {
                if (!Array.isArray(estudiantes) || !estudiantes.length) {
                    estudiantesDiv.innerHTML = '<p>No hay aprendices en esta ficha.</p>';
                    return;
                }

                const estudiantesValidos = estudiantes.filter(e => e.id);
                if (!estudiantesValidos.length) {
                    estudiantesDiv.innerHTML = '<p>❌ Error: los estudiantes no tienen ID definido. Verifica tu backend.</p>';
                    return;
                }

                let html = `
                    <h2>Registrando asistencia para la ficha: ${fichaNombre}</h2>
                        <form method="POST" action="/?page=guardar_asistencia">
                        <input type="hidden" name="ficha_id" value="${fichaId}">
                        <table border="1" cellpadding="5">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                estudiantesValidos.forEach(e => {
                    html += `
                        <tr>
                            <td>${e.nombres} ${e.apellidos}</td>
                            <td>
                                <select name="asistencias[${e.id}][estado]">
                                    <option value="presente">Presente</option>
                                    <option value="ausente">Ausente</option>
                                    <option value="tarde">Tarde</option>
                                    <option value="justificado">Justificado</option>
                                </select>
                                <input type="hidden" name="asistencias[${e.id}][estudiante_id]" value="${e.id}">
                            </td>
                            <td>
                                <input type="text" name="asistencias[${e.id}][observacion]" placeholder="Opcional">
                            </td>
                        </tr>
                    `;
                });

                html += `
                        </tbody>
                        </table>
                        <br>
                        <button type="button" onclick="confirmarEnvio(this)">Guardar asistencia de hoy</button>
                    </form>
                `;

                estudiantesDiv.innerHTML = html;
            })
            .catch(err => {
                estudiantesDiv.innerHTML = '<p>Error al cargar estudiantes.</p>';
            });
    }
});

});
// Desaparecer la alerta automáticamente luego de 4 segundos
setTimeout(() => {
    const alerta = document.getElementById('alerta-asistencia');
    if (alerta) {
        alerta.style.transition = 'opacity 0.3s ease';
        alerta.style.opacity = '0';
        setTimeout(() => alerta.remove(), 200); // Eliminar del DOM
    }
}, 1000);
function confirmarEnvio(button) {
    Swal.fire({
        title: '¿Enviar asistencia?',
        text: "Esta acción no se puede deshacer.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, enviar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = button.closest('form');
            if (form) {
                form.submit();
            }
        }
    });
}

</script>

<script src="/js/dashboard_profesor.js"></script>

<?php require __DIR__ . '/../Componentes/footer.php'; ?>
