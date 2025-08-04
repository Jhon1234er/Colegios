document.addEventListener('DOMContentLoaded', () => {
  const fichasContainer = document.getElementById('fichasContainer');
  const estudiantesContainer = document.getElementById('estudiantesContainer');

  // Cargar fichas del profesor
  fetch('?page=profesor_ficha')
    .then(response => {
      if (!response.ok) throw new Error("Error en la respuesta");
      return response.json();
    })
    .then(fichas => {
      if (Array.isArray(fichas) && fichas.length > 0) {
        fichas.forEach(ficha => {
          const btn = document.createElement('button');
          btn.textContent = ficha.nombre;
          btn.classList.add('btn-ficha');
          btn.dataset.fichaId = ficha.id;
          fichasContainer.appendChild(btn);
        });
      } else {
        fichasContainer.innerHTML = '<p>No hay fichas asignadas.</p>';
      }
    })
    .catch(err => {
      console.error('Error al cargar fichas:', err);
    });

  // Cargar estudiantes al hacer clic en una ficha
  fichasContainer.addEventListener('click', (e) => {
    if (e.target.matches('.btn-ficha')) {
      const fichaId = e.target.dataset.fichaId;

      fetch(`?page=estudiantes_por_ficha&ficha_id=${encodeURIComponent(fichaId)}`)
        .then(response => {
          if (!response.ok) throw new Error("Error en la respuesta de estudiantes");
          return response.json();
        })
        .then(estudiantes => {
          estudiantesContainer.innerHTML = '';
          if (Array.isArray(estudiantes) && estudiantes.length > 0) {
            estudiantes.forEach(est => {
              const div = document.createElement('div');
              div.classList.add('estudiante');
              div.innerHTML = `
                <p><strong>${est.nombres} ${est.apellidos}</strong> - ${est.grado}° (${est.jornada})</p>
                <p>Acudiente: ${est.nombre_completo_acudiente} (${est.parentesco}) - 📞 ${est.telefono_acudiente}</p>
              `;
              estudiantesContainer.appendChild(div);
            });
          } else {
            estudiantesContainer.innerHTML = '<p>No hay estudiantes en esta ficha.</p>';
          }
        })
        .catch(err => {
          console.error('Error al cargar estudiantes:', err);
        });
    }
  });
});
