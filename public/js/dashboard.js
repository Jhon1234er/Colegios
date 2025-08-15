document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.btn-ver-colegio').forEach(btn => {
        btn.addEventListener('click', function () {
            const colegioId = this.dataset.id;

            // 🔹 Mostrar Profesores
            fetch(`../index.php?page=profesores_por_colegio&colegio_id=${colegioId}`)
                .then(res => res.json())
                .then(data => {
                    const div1 = document.querySelector('.div1');
                    if (!data.length) {
                        div1.innerHTML = '<p>No hay facilitadores registrados para este colegio.</p>';
                        return;
                    }

                    let html = '<h3>Facilitadores</h3>';
                    html += '<div class="profesor-container">';
                    data.forEach(p => {
                        html += `
                            <div class="profesor-card">
                                <div class="profesor-name">${p.nombre}</div>
                                <p class="profesor-telefono">Numero de Contacto:${p.telefono}</p>
                                <p class="profesor-institucional">Institucional:${p.correo_institucional}</p>
                                <p class="profesor-personal">Correo Personal:${p.correo_electronico}</p>
                                <p class="profesor-contrato">Tipo de Contrato:${p.tip_contrato}</p>
                                <p class="profesor-materia">Materia: ${p.materia}</p>
                            </div>
                        `;
                    });
                    html += '</div>';
                    div1.innerHTML = html;

                })
                .catch(err => console.error('Error cargando profesores:', err));

            // 🔹 Mostrar Estudiantes
            fetch(`../index.php?page=estudiantes_por_colegio&colegio_id=${colegioId}`)
                .then(res => res.json())
                .then(data => {
                    const div2 = document.querySelector('.div2');
                    document.querySelector('.esta')?.remove();
                    if (!data.length) {
                        div2.innerHTML = '<p>No hay aprendices registrados para este colegio.</p>';
                        return;
                    }

                let html = '<h3>Aprendices</h3>';
                html += '<div class="students-container">'; // contenedor para todas las tarjetas

                data.forEach(e => {
                    html += `
                        <div class="student-card">
                            <div class="student-name">${e.nombre_completo}</div>
                            <div class="student-details">
                                Grado: ${e.grado}, Jornada: ${e.jornada}<br>
                                <strong>Acudiente:</strong> ${e.nombre_completo_acudiente}<br>
                                <strong>Celular:</strong> ${e.telefono_acudiente}<br>
                                <strong>Parentesco:</strong> ${e.parentesco}<br>
                                <strong>Ficha:</strong> ${e.ficha}
                            </div>
                        </div>`;
                });
                html += '</div>'; // cerrar contenedor
                div2.innerHTML = html;

                })
                .catch(err => console.error('Error cargando estudiantes:', err));

            fetch(`../ajax/asistencias_por_colegio.php?colegio_id=${colegioId}`)
            .then(res => res.json())
            .then(data => {
                const dom = document.getElementById('chart-container');
                const myChart = echarts.init(dom, 'white', { locale: 'ES' });

                // Todas las fichas con sus datos, para la leyenda
                const allFichas = data.fichas.map(f => ({
                name: `Ficha ${f.numero_ficha}`,
                total_fallas: f.total_fallas
                }));

                // Filtrar solo las fichas con fallas > 0 para mostrar en el gráfico
                const fichasConDatos = allFichas.filter(f => f.total_fallas > 0);

                // Preparar datos para el gráfico
                const seriesData = fichasConDatos.map(f => ({
                name: f.name,
                value: f.total_fallas
                }));

                // Preparar nombres para la leyenda (todos)
                const legendData = allFichas.map(f => f.name);
                const option = {
                    legend: {
                        bottom: 0,              // Leyenda más cerca del borde inferior
                        left: 'center',          // Centrada horizontalmente
                        data: legendData,
                        backgroundColor: '#fff', // Fondo blanco para destacar
                        borderRadius: 6,         // Bordes redondeados
                        padding: [8, 12, 8, 12], // Espaciado interno
                        itemGap: 15,             // Separación entre elementos
                        formatter: function (name) {
                            return name;
                        },
                        textStyle: {
                            color: function (params) {
                                const ficha = allFichas.find(f => f.name === params);
                                return ficha && ficha.total_fallas > 0 ? '#000' : '#ccc';
                            }
                        }
                    },
                    grid: {
                        bottom: 90 // Espacio extra para la leyenda
                    },
                    toolbox: {
                        show: true,
                        feature: {
                            mark: { show: true },
                            dataView: { show: true, readOnly: false },
                            restore: { show: true },
                            saveAsImage: { show: true }
                        }
                    },
                    series: [
                        {
                            name: 'Fallas por ficha',
                            type: 'pie',
                            radius: [20, 250],
                            center: ['50%', '42%'], // Sube el gráfico para dejar sitio abajo
                            roseType: 'area',
                            itemStyle: { borderRadius: 8 },
                            data: seriesData
                        }
                    ]
                };

                myChart.setOption(option);

                // Mostrar alertas
                if (data.alertas.length > 0) {
                let alertHtml = '<h4>⚠ Estudiantes con 3+ fallas</h4><ul>';
                data.alertas.forEach(e => {
                    alertHtml += `<li><strong>${e.nombres} ${e.apellidos}</strong> - Ficha ${e.numero_ficha} (${e.total_fallas} fallas)</li>`;
                });
                alertHtml += '</ul>';
                document.querySelector('.div3').insertAdjacentHTML('beforeend', alertHtml);
                }
            })
            .catch(err => console.error('Error cargando estadísticas:', err));


        });
    });
});
