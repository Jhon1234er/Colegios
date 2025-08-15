<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';

$pdo = Database::conectar();

// Obtener ID del profesor logueado
$profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
if (!$profesor_id) {
    die("❌ No se ha identificado al profesor.");
}

// Fichas del profesor
$stmt = $pdo->prepare("SELECT f.id, f.nombre FROM fichas f 
    INNER JOIN profesor_ficha pf ON pf.ficha_id = f.id 
    WHERE pf.profesor_id = ?");
$stmt->execute([$profesor_id]);
$fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Materias del profesor
$materiasStmt = $pdo->prepare("
    SELECT m.id, m.nombre 
    FROM materias m
    INNER JOIN materia_profesor mp ON mp.materia_id = m.id
    WHERE mp.profesor_id = ?
");
$materiasStmt->execute([$profesor_id]);
$materias = $materiasStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require __DIR__ . '/../Componentes/encabezado.php'; ?>

<link rel="stylesheet" href="/css/Profesor/crear_tarea.css">
<div class="crear-tarea">

    <h2>Crear Tarea</h2>

    <form method="POST" action="/?page=guardar_tarea">
        <label>Título:</label><br>
        <input type="text" name="titulo" required><br><br>

        <label>Descripción:</label><br>
        <textarea name="descripcion"></textarea><br><br>

        <label>Materia:</label><br>
        <select name="materia_id" required>
            <?php foreach ($materias as $m): ?>
                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Ficha:</label><br>
        <select name="ficha_id" required>
            <?php foreach ($fichas as $f): ?>
                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nombre']) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Fecha de entrega:</label><br>
        <input type="date" name="fecha_entrega"><br><br>

        <button type="submit">Guardar tarea</button>
    </form>
</div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (!empty($_GET['success']) && $_GET['success'] === 'asistencia'): ?>
    <div class="alerta-exito">
        Asistencia registrada exitosamente.
    </div>
<?php elseif (!empty($_GET['error']) && $_GET['error'] === 'asistencia_ya_registrada'): ?>
    <div id="alerta-asistencia" class="alerta-error">
        Ya registraste asistencia hoy para esta ficha.
    </div>
<?php endif; ?>


<h3>Mis Fichas</h3>
<a href="/?page=crear_tarea" class="boton-tarea">Nueva Actividad</a>

<ul id="lista-fichas"></ul>

<div id="estudiantes-ficha">Seleccione una ficha para saber de sus Aprendices</div>

<div class="parent">
        <div class="div1"> <p style="color: #666;">Fichas</p> </div>
<?php
require_once '../config/db.php';
$pdo = Database::conectar();

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
    let fichasGlobal = [];

    // Cargar fichas del profesor
    fetch('index.php?page=profesorficha')
        .then(res => res.json())
        .then(fichas => {
            fichasGlobal = fichas;

            if (!Array.isArray(fichas) || !fichas.length) {
                ul.innerHTML = '<li>No tienes fichas asignadas.</li>';
                return;
            }

            fichas.forEach(f => {
                const li = document.createElement('li');
                li.innerHTML = `
                    ${f.nombre}
                    <div>
                        <button data-id="${f.id}" class="btn-ver-estudiantes">Ver Aprendices</button>
                        <a href="/?page=ver_notas&ficha_id=${f.id}">Ver notas</a>
                    </div>
                `;
                ul.appendChild(li);
            });
        })
        .catch(() => {
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
                            <table>
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
                .catch(() => {
                    estudiantesDiv.innerHTML = '<p>Error al cargar estudiantes.</p>';
                });
        }
    });

});

// Desaparecer alerta
setTimeout(() => {
    const alerta = document.getElementById('alerta-asistencia');
    if (alerta) {
        alerta.style.transition = 'opacity 0.3s ease';
        alerta.style.opacity = '0';
        setTimeout(() => alerta.remove(), 200);
    }
}, 3000);

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