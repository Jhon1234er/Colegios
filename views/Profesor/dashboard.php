<?php require __DIR__ . '/../Componentes/encabezado.php'; ?>

<link rel="stylesheet" href="/css/Profesor/dashboard_profesor.css">

<h2>Bienvenido, Facilitador <?= htmlspecialchars($_SESSION['usuario']['nombres']) ?></h2>

<h3>Mis Fichas</h3>
<ul id="lista-fichas"></ul>

<div id="estudiantes-ficha" style="margin-top:2em;">Seleccione una ficha para saber de sus Aprendices</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ul = document.getElementById('lista-fichas');
    const estudiantesDiv = document.getElementById('estudiantes-ficha');

    // ✅ Cargar fichas del profesor
    fetch('index.php?page=profesorficha')
        .then(res => res.json())
        .then(fichas => {
            console.log('📚 Fichas recibidas:', fichas);

            if (!Array.isArray(fichas) || !fichas.length) {
                ul.innerHTML = '<li>No tienes fichas asignadas.</li>';
                return;
            }

            fichas.forEach(f => {
                const li = document.createElement('li');
                li.innerHTML = `
                    ${f.nombre}
                    <button data-id="${f.id}" class="btn-ver-estudiantes">Ver estudiantes</button>
                `;
                ul.appendChild(li);
            });
        })
        .catch(err => {
            console.error('❌ Error al cargar fichas:', err);
            ul.innerHTML = '<li>Error cargando fichas.</li>';
        });

    // ✅ Manejar clic en "Ver estudiantes"
    ul.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-ver-estudiantes')) {
            const fichaId = e.target.dataset.id;
            estudiantesDiv.innerHTML = '<p>Cargando estudiantes...</p>';

            fetch(`index.php?page=estudiantesporficha&ficha_id=${fichaId}`)
                .then(res => res.json())
                .then(estudiantes => {
                    console.log(`👨‍🎓 Estudiantes de ficha ${fichaId}:`, estudiantes);

                    if (!Array.isArray(estudiantes) || !estudiantes.length) {
                        estudiantesDiv.innerHTML = '<p>No hay estudiantes en esta ficha.</p>';
                        return;
                    }

                    let html = `
                        <h4>Estudiantes de la ficha</h4>
                        <table border="1" cellpadding="5">
                            <tr>
                                <th>Nombre</th>
                                <th>Grado</th>
                                <th>Jornada</th>
                                <th>Acudiente</th>
                                <th>Teléfono</th>
                                <th>Parentesco</th>
                            </tr>
                    `;
                    estudiantes.forEach(e => {
                        html += `
                            <tr>
                                <td>${e.nombres} ${e.apellidos}</td>
                                <td>${e.grado}</td>
                                <td>${e.jornada}</td>
                                <td>${e.nombre_completo_acudiente}</td>
                                <td>${e.telefono_acudiente}</td>
                                <td>${e.parentesco}</td>
                            </tr>
                        `;
                    });
                    html += '</table>';
                    estudiantesDiv.innerHTML = html;
                })
                .catch(err => {
                    console.error('❌ Error al cargar estudiantes:', err);
                    estudiantesDiv.innerHTML = '<p>Error al cargar estudiantes.</p>';
                });
        }
    });
});
</script>

<script src="/js/dashboard_profesor.js"></script>

<?php require __DIR__ . '/../Componentes/footer.php'; ?>
