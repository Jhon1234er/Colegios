<?php
start_secure_session();
require_login();
require_role([1, 4]); // 1 para Admin, 4 para Asistente
include __DIR__ . '/../Componentes/encabezado.php';
$usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Asistente</title>
    <link rel="stylesheet" href="/css/Asistente/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<body>
<div id="dashboard-normal" class="dashboard-panel">
    <div class="parent">

        <!-- Bienvenida -->
        <div class="div9">
            <?php 
              $nombre_usuario = htmlspecialchars($usuario['nombres'] . ' ' . ($usuario['apellidos'] ?? ''));
              $genero = strtolower(trim((string)($usuario['genero'] ?? '')));
              $bienvenida = (in_array($genero, ['f','femenino','mujer'], true)) ? 'Bienvenida' : 'Bienvenido';
              $h = (int)date('H');
              $saludo = $h < 12 ? '¡Buenos días' : ($h < 18 ? '¡Buenas tardes' : '¡Buenas noches');
            ?>
            <p><strong><?= $bienvenida ?></strong> <?= $nombre_usuario ?></p>
            <div class="welcome-center">
                <span id="greeting-text"><?= $saludo ?>, <?= htmlspecialchars($usuario['nombres']) ?>!</span>
                <svg class="hand-outline" width="32" height="32" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
                    <path d="M14 22 V9 a2 2 0 1 1 4 0v11 M18 21 V7 a2 2 0 1 1 4 0v16 M22 21 V8 a2 2 0 1 1 4 0v15 M26 23 V12 a2 2 0 1 1 4 0v14 M30 26 V16 a2 2 0 1 1 4 0v13 C34 36 28 42 21 42 C15 42 12 37 12 32 V27" />
                    <path d="M36 10 l4 -4 M38 16 l6 -2" class="ho-accent" />
                </svg>
            </div>
            <p><strong>Rol:</strong> Asistente</p>
        </div>


        <!-- VISTA PRINCIPAL -->
        <div class="indicaciones-column view-main">
            <div id="facilitadores-container" class="div1"><p>Selecciona un colegio para ver sus Facilitadores.</p></div>
            <div id="aprendices-container" class="div2"><p>Selecciona un colegio para ver sus Aprendices.</p></div>
        </div>

        <div class="div3 view-main">
            <h3>Estadísticas</h3>
            <div id="chart-container"></div>
        </div>
        <div class="contadores-column view-main">
            <div class="div5"><h3>Clases Hoy</h3><p id="m_total">—</p></div>
            <div class="div6"><h3>En Curso</h3><p id="m_curso">—</p></div>
            <div class="div7"><h3>Ausencias</h3><p id="m_faltas">—</p></div>
            <div class="div8"><h3>Próxima Clase</h3><p id="m_proxima" style="font-size:1.4rem; padding-top:8px;">—</p></div>
            <div class="div10">
                <h3>Descargar Reporte Semana</h3>
                <button class="btn" data-bs-toggle="modal" data-bs-target="#modalReportesPDF" style="margin-top: 8px;">Descargar</button>
            </div>
        </div>
        <div class="div4 view-main">
            <h3 id="tabla-title">Colegios Gestionados</h3>
            <div id="tabla-controls" style="display:flex; gap:10px; align-items:center; justify-content:space-between; margin:10px 0 14px;">
                <div style="display:flex; align-items:center; gap:8px; flex:0 0 auto;">
                    <input id="tabla-search" class="input-buscar" type="text" placeholder="Buscar colegio..." style="width:260px; max-width: 320px;">
                    <button class="btn-icon" id="tabla-search-btn" aria-label="Buscar" type="button">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><line x1="20" y1="20" x2="16.5" y2="16.5" stroke="currentColor" stroke-width="2" /></svg>
                    </button>
                </div>
                <div id="tabla-pager" style="display:flex; flex-wrap:wrap; gap:6px; align-items:center; justify-content:flex-end; min-height:34px;"></div>
            </div>
            <table id="tabla-colegios">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Departamento</th>
                        <th>Municipio</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaColegiosListado">
                    <tr><td colspan="6" class="text-center py-4">Cargando…</td></tr>
                </tbody>
            </table>
        </div>

        <!-- VISTA SECUNDARIA -->
        <div class="indicaciones-column view-secondary">
            <!-- Lista base de ausentes -->
            <div class="div1">
                <h3 class="block-title">Ausentes de Hoy</h3>
                <div class="list-scroll">
                    <table class="table table-sm table-hover align-middle mb-0" id="tablaAusentes">
                        <tbody>
                            <tr><td class="text-center py-4">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Historial: No asistieron (pendientes de proceso) -->
            <div class="div2">
                <h3 class="block-title">No asistieron · Pendientes de proceso</h3>
                <div class="list-scroll">
                    <table class="table table-sm table-hover align-middle mb-0" id="tablaPendientesProceso">
                        <tbody>
                            <tr><td class="text-center py-4">Sin datos</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Historial: No asistieron (proceso realizado) -->
            <div class="div3">
                <h3 class="block-title">No asistieron · Proceso realizado</h3>
                <div class="list-scroll">
                    <table class="table table-sm table-hover align-middle mb-0" id="tablaProcesados">
                        <tbody>
                            <tr><td class="text-center py-4">Sin datos</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Reporte PDF -->
<div class="modal fade" id="modalReportesPDF" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Generar Reporte de Asistencias </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="selectColegioPDF" class="form-label">Seleccione Colegio</label>
          <select id="selectColegioPDF" class="form-select"></select>
        </div>
        <div class="mb-3">
          <label class="form-label">Seleccione Ficha(s)</label>
          <div id="fichasContainerPDF"></div>
          <div class="form-check mt-2">
            <input type="checkbox" class="form-check-input" id="checkAllFichasPDF">
            <label for="checkAllFichasPDF" class="form-check-label">Seleccionar todas</label>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="weekStartPDF" class="form-label">Fecha de inicio</label>
            <input type="text" id="weekStartPDF" class="form-control" placeholder="Selecciona fecha de inicio">
          </div>
          <div class="col-md-6">
            <label for="weekEndPDF" class="form-label">Fecha de fin</label>
            <input type="text" id="weekEndPDF" class="form-control" placeholder="Selecciona fecha de fin">
          </div>
        </div>
        <div id="previewContainerPDF" class="mt-4" style="display:none;">
          <h6>Vista previa:</h6>
          <div class="table-responsive">
            <table class="table table-bordered" id="previewTablePDF">
              <thead></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="btnPreviewPDF" class="btn btn-preview">Vista previa</button>
        <button type="button" id="btnDownloadPDF" class="btn btn-pdf">Descargar PDF</button>
        <button type="button" id="btnDownloadExcel" class="btn btn-excel">Descargar Excel</button>
      </div>
    </div>
  </div>
</div>


    <?php include __DIR__ . '/../Componentes/footer.php'; ?>

    <!-- Librerías JS -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

    <script src="/js/Asistente/dashboard.js"></script>
    <script src="/js/Asistente/view-toggle.js"></script>
    <script>
      // Saludo dinámico (alineado con Admin), usando zona horaria de Bogotá
      (function(){
        const el = document.getElementById('greeting-text');
        if (!el) return;
        const nombre = <?= json_encode(explode(' ', trim($usuario['nombres']))[0] ?? '') ?>;
        const RETURN_THRESHOLD = 120000; // 2 min
        const RETURN_GREETINGS = [
          '¡Hola de nuevo!',
          `¡Qué bueno verte, ${nombre}!`,
          '¡Listo para continuar!',
          '¡Seguimos!'
        ];
        function saludoPorHora(){
          try {
            const tz = 'America/Bogota';
            const parts = new Intl.DateTimeFormat('es-CO', { hour: 'numeric', hour12: false, timeZone: tz }).formatToParts(new Date());
            const hourPart = parts.find(p=>p.type==='hour');
            const h = hourPart ? parseInt(hourPart.value,10) : new Date().getHours();
            if (h < 12) return '¡Buenos días!';
            if (h < 19) return '¡Buenas tardes!';
            return '¡Buenas noches!';
          } catch(_) {
            const h = new Date().getHours();
            if (h < 12) return '¡Buenos días!';
            if (h < 19) return '¡Buenas tardes!';
            return '¡Buenas noches!';
          }
        }
        function siguienteSaludo(){
          let idx = Number(localStorage.getItem('asist_greetIdx')||0);
          const txt = RETURN_GREETINGS[idx % RETURN_GREETINGS.length];
          localStorage.setItem('asist_greetIdx', String((idx+1) % RETURN_GREETINGS.length));
          return txt;
        }
        function setSaludoInicial(){
          const last = Number(localStorage.getItem('asist_lastActiveTs')||0);
          const now = Date.now();
          if (last && (now - last) > RETURN_THRESHOLD) el.textContent = siguienteSaludo();
          else el.textContent = saludoPorHora();
          localStorage.setItem('asist_lastActiveTs', String(now));
        }
        function handleVisibility(){
          if (!document.hidden){
            const last = Number(localStorage.getItem('asist_lastActiveTs')||0);
            const now = Date.now();
            if (last && (now - last) > RETURN_THRESHOLD) el.textContent = siguienteSaludo();
            localStorage.setItem('asist_lastActiveTs', String(now));
          }
        }
        setSaludoInicial();
        document.addEventListener('visibilitychange', handleVisibility);
        window.addEventListener('beforeunload', () => localStorage.setItem('asist_lastActiveTs', String(Date.now())));
      })();
    </script>
    <!-- Lógica del Dashboard del Asistente -->
    <script>
    async function cargarResumen() {
        try {
            const r = await fetch('?page=asistente_resumen');
            const j = await r.json();
            if (!j.success) throw new Error(j.error || 'Error');
            document.getElementById('m_total').textContent = j.total_clases;
            document.getElementById('m_curso').textContent = j.en_curso;
            document.getElementById('m_faltas').textContent = j.faltas;
            if (j.proxima_clase) {
                const dt = new Date(j.proxima_clase.fecha_inicio.replace(' ', 'T'));
                document.getElementById('m_proxima').textContent = `${dt.toLocaleDateString()} ${String(dt.getHours()).padStart(2, '0')}:${String(dt.getMinutes()).padStart(2, '0')}`;
            } else {
                document.getElementById('m_proxima').textContent = '—';
            }
        } catch (e) { console.error(e); }
    }

    // Historial: Pendientes de proceso y Proceso realizado (scope global)
    async function cargarHistorialPendientes() {
        const tb = document.querySelector('#tablaPendientesProceso tbody');
        if (!tb) return;
        try {
            tb.innerHTML = '<tr><td class="text-center py-4">Cargando…</td></tr>';
            const r = await fetch('?page=asistente&action=historial_pendientes');
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const j = await r.json();
            const rows = (j && (j.data || j.rows || [])) || [];
            if (rows.length === 0) {
                tb.innerHTML = '<tr><td class="text-center py-4">Sin datos</td></tr>';
                return;
            }
            tb.innerHTML = rows.map(x => {
                const nombre = ((x.nombres||'') + ' ' + (x.apellidos||'')).trim();
                const ficha = x.ficha_nombre || x.ficha || '';
                const fecha = (x.fecha || '').split('T')[0] || '';
                const url = `/?page=seguimiento_ausencia_iniciar&ficha_id=${encodeURIComponent(x.ficha_id||'')}&estudiante_id=${encodeURIComponent(x.estudiante_id||'')}&fecha=${encodeURIComponent(fecha)}`;
                return `<tr>
                    <td><strong>${nombre}</strong><br><small>${ficha}</small></td>
                    <td><small>${fecha||''}</small></td>
                    <td style="text-align:right"><a class="btn btn-sm btn-primary" href="${url}">Iniciar</a></td>
                </tr>`;
            }).join('');
        } catch (e) {
            console.error('historial pendientes', e);
            tb.innerHTML = '<tr><td class="text-center py-4">Sin datos</td></tr>';
        }
    }

    async function cargarHistorialProcesados() {
        const tb = document.querySelector('#tablaProcesados tbody');
        if (!tb) return;
        try {
            tb.innerHTML = '<tr><td class="text-center py-4">Cargando…</td></tr>';
            const r = await fetch('?page=asistente&action=historial_procesados');
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const j = await r.json();
            const rows = (j && (j.data || j.rows || [])) || [];
            if (rows.length === 0) {
                tb.innerHTML = '<tr><td class="text-center py-4">Sin datos</td></tr>';
                return;
            }
            tb.innerHTML = rows.map(x => {
                const nombre = ((x.nombres||'') + ' ' + (x.apellidos||'')).trim();
                const ficha = x.ficha_nombre || x.ficha || '';
                const fecha = (x.fecha || '').split('T')[0] || '';
                const url = `/?page=seguimiento_ausencia_detalle&proceso_id=${encodeURIComponent(x.proceso_id||'')}`;
                return `<tr>
                    <td><strong>${nombre}</strong><br><small>${ficha}</small></td>
                    <td><small>${fecha||''}</small></td>
                    <td style="text-align:right"><a class="btn btn-sm btn-secondary" href="${url}">Ver</a></td>
                </tr>`;
            }).join('');
        } catch (e) {
            console.error('historial procesados', e);
            tb.innerHTML = '<tr><td class="text-center py-4">Sin datos</td></tr>';
        }
    }

    async function cargarAusentes() {
        const tb = document.querySelector('#tablaAusentes tbody');
        try {
            tb.innerHTML = '<tr><td class="text-center py-4">Cargando…</td></tr>';
            const r = await fetch('?page=asistente_ausentes');
            const j = await r.json();
            if (!j.success) throw new Error(j.error || 'Error');
            const rows = j.data || [];
            if (rows.length === 0) {
                tb.innerHTML = '<tr><td class="text-center py-4">No hay ausentes hoy.</td></tr>';
                return;
            }
            tb.innerHTML = rows.map(x => {
                const url = `/?page=seguimiento_ausencia_iniciar&ficha_id=${encodeURIComponent(x.ficha_id)}&estudiante_id=${encodeURIComponent(x.estudiante_id)}&fecha=${new Date().toISOString().split('T')[0]}`;
                return `
                <tr data-estudiante="${x.estudiante_id}" data-ficha="${x.ficha_id}">
                    <td><strong>${(x.nombres || '') + ' ' + (x.apellidos || '')}</strong><br><small>${x.ficha_nombre || ''}</small></td>
                    <td><a class="btn btn-sm btn-primary" href="${url}">Iniciar</a></td>
                </tr>`;
            }).join('');
        } catch (e) {
            console.error(e);
            tb.innerHTML = '<tr><td class="text-center py-4 text-danger">Error</td></tr>';
        }
    }

    let allColegios = [];
    let currentPage = 1;
    const itemsPerPage = 5;

    function renderTable(page = 1, searchTerm = '') {
        const tb = document.querySelector('#tablaColegiosListado');
        const pager = document.querySelector('#tabla-pager');
        if (!tb || !pager) return;

        currentPage = page;
        const filteredData = allColegios.filter(c => 
            c.nombre.toLowerCase().includes(searchTerm.toLowerCase()) ||
            (c.municipio && c.municipio.toLowerCase().includes(searchTerm.toLowerCase()))
        );

        const totalPages = Math.max(1, Math.ceil(filteredData.length / itemsPerPage));
        if (page > totalPages) page = totalPages;
        const start = (page - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const paginatedData = filteredData.slice(start, end);

        // Renderizar filas de la tabla
        if (paginatedData.length > 0) {
            tb.innerHTML = paginatedData.map(c => `
                <tr>
                    <td>${c.id || ''}</td>
                    <td>${c.nombre || ''}</td>
                    <td>${c.tipo_institucion || ''}</td>
                    <td>${c.departamento || ''}</td>
                    <td>${c.municipio || ''}</td>
                    <td><button class="btn-ver-colegio" data-id="${c.id}">Ver</button></td>
                </tr>
            `).join('');
        } else {
            tb.innerHTML = '<tr><td colspan="6" class="text-center py-4">No se encontraron colegios.</td></tr>';
        }

        // Renderizar paginador
        pager.innerHTML = '';
        const createButton = (label, targetPage, isDisabled = false, isActive = false) => {
            const btn = document.createElement('button');
            btn.innerHTML = label;
            btn.type = 'button';
            btn.disabled = isDisabled;
            btn.style.cssText = 'min-width:34px; height:34px; border:1px solid #d1d5db; border-radius:10px; background:#fff; cursor:pointer; padding:0 10px; margin: 0 2px;';
            if (isActive) {
                btn.style.background = '#e5f5e8';
                btn.style.borderColor = '#39A900';
                btn.style.fontWeight = '700';
            }
            if (!isDisabled) {
                btn.onclick = () => renderTable(targetPage, searchTerm);
            }
            return btn;
        };

        // Botón Anterior
        pager.appendChild(createButton('&laquo;', currentPage - 1, currentPage === 1));

        // Botones de página
        for (let i = 1; i <= totalPages; i++) {
            pager.appendChild(createButton(i, i, false, i === currentPage));
        }

        // Botón Siguiente
        pager.appendChild(createButton('&raquo;', currentPage + 1, currentPage === totalPages));

        // Mensaje si no hay resultados
        if (filteredData.length === 0) {
            const info = document.createElement('div');
            info.textContent = 'Sin resultados';
            info.style.cssText = 'color:#6b7280; margin-left:8px;';
            pager.appendChild(info);
        }
    }

    async function renderColegiosListado() {
        const tb = document.querySelector('#tablaColegiosListado');
        try {
            tb.innerHTML = '<tr><td colspan="6" class="text-center py-4">Cargando…</td></tr>';
            const r = await fetch('?page=asistente_colegios');
            const j = await r.json();
            if (!j.success) throw new Error(j.error || 'Error');
            allColegios = j.data || [];
            renderTable(1, ''); // Renderizar la primera página sin filtro
            return allColegios;
        } catch (e) {
            console.error(e);
            tb.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Error al cargar los colegios.</td></tr>';
            return [];
        }
    }

    document.addEventListener('DOMContentLoaded', async function() {
        cargarResumen();
        cargarAusentes();
        cargarHistorialPendientes();
        cargarHistorialProcesados();
        const colegios = await renderColegiosListado();
        setupReportModal('PDF', 'generar_pdf', colegios);
        setInterval(cargarResumen, 60000);

        // Lógica de búsqueda para la tabla de colegios
        const searchInput = document.getElementById('tabla-search');
        const searchButton = document.getElementById('tabla-search-btn');

        function performSearch() {
            const searchTerm = searchInput.value;
            renderTable(1, searchTerm);
        }

        if (searchButton) {
            searchButton.addEventListener('click', performSearch);
        }
        if (searchInput) {
            searchInput.addEventListener('keyup', performSearch);
        }

        // Lógica para los modales de reporte
        function setupReportModal(suf, downloadPage, colegios = []) {
            const modal = document.getElementById('modalReportes' + suf);
            if (!modal) return;

            const selectColegio = document.getElementById('selectColegio' + suf);
            const fichasContainer = document.getElementById('fichasContainer' + suf);
            const checkAllFichas = document.getElementById('checkAllFichas' + suf);
            const btnDownload = document.getElementById('btnDownload' + suf);
            const btnPreview = document.getElementById('btnPreview' + suf);
            const previewContainer = document.getElementById('previewContainer' + suf);
            const previewTableBody = document.getElementById('previewTable' + suf).querySelector('tbody');
            const previewTableHead = document.getElementById('previewTable' + suf).querySelector('thead');

            // Poblar selector de colegios
            selectColegio.innerHTML = '<option value="">-- Seleccionar --</option>';
            colegios.forEach(c => {
                const option = document.createElement('option');
                option.value = c.id;
                option.textContent = c.nombre;
                selectColegio.appendChild(option);
            });

            // Cargar fichas al seleccionar colegio
            selectColegio.addEventListener('change', async () => {
                const colegioId = selectColegio.value;
                fichasContainer.innerHTML = '';
                checkAllFichas.checked = false;
                previewContainer.style.display = 'none';
                if (!colegioId) return;

                try {
                    const r = await fetch(`/?page=fichas_por_colegio&colegio_id=${colegioId}`);
                    const fichas = await r.json();
                    if (fichas.length > 0) {
                        fichas.forEach(f => {
                            const div = document.createElement('div');
                            div.className = 'form-check';
                            div.innerHTML = `
                                <input class="form-check-input ficha-check-${suf}" type="checkbox" value="${f.id}" id="ficha${suf}${f.id}">
                                <label class="form-check-label" for="ficha${suf}${f.id}">${f.numero}</label>
                            `;
                            fichasContainer.appendChild(div);
                        });
                    } else {
                        fichasContainer.innerHTML = '<p class="text-muted">No hay fichas para este colegio.</p>';
                    }
                } catch (e) {
                    console.error('Error cargando fichas:', e);
                    fichasContainer.innerHTML = '<p class="text-danger">Error al cargar fichas.</p>';
                }
            });

            checkAllFichas.addEventListener('change', () => {
                fichasContainer.querySelectorAll('.ficha-check-' + suf).forEach(cb => cb.checked = checkAllFichas.checked);
            });

            async function doPreview() {
                const colegioId = selectColegio.value;
                const weekStart = document.getElementById('weekStart' + suf).value;
                const weekEnd = document.getElementById('weekEnd' + suf).value;
                const fichas = Array.from(fichasContainer.querySelectorAll('.ficha-check-' + suf + ':checked')).map(cb => cb.value);

                if (!colegioId || fichas.length === 0 || !weekStart || !weekEnd) {
                    alert('Por favor, complete todos los campos para la vista previa.');
                    return;
                }

                previewTableBody.innerHTML = '<tr><td colspan="9">Cargando...</td></tr>';
                previewContainer.style.display = 'block';

                try {
                    const body = new URLSearchParams();
                    body.append('colegio_id', colegioId);
                    body.append('week_start', weekStart);
                    body.append('week_end', weekEnd);
                    fichas.forEach(ficha => body.append('fichas[]', ficha));

                    const r = await fetch('/?page=preview_v2', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body
                    });
                    const html = await r.text();
                    previewTableHead.innerHTML = '<tr><th>Ficha</th><th>Documento</th><th>Lunes</th><th>Martes</th><th>Miércoles</th><th>Jueves</th><th>Viernes</th><th>Jornada</th><th>Estado</th></tr>';
                    previewTableBody.innerHTML = html;
                } catch (e) {
                    console.error('Error en la vista previa:', e);
                    previewTableBody.innerHTML = '<tr><td colspan="9" class="text-danger">Error al cargar la vista previa.</td></tr>';
                }
            }

            btnPreview.addEventListener('click', doPreview);

            btnDownload.addEventListener('click', () => {
                const colegioId = selectColegio.value;
                const weekStart = document.getElementById('weekStart' + suf).value;
                const weekEnd = document.getElementById('weekEnd' + suf).value;
                const fichas = Array.from(fichasContainer.querySelectorAll('.ficha-check-' + suf + ':checked')).map(cb => cb.value);

                if (!colegioId || fichas.length === 0 || !weekStart || !weekEnd) {
                    alert('Por favor, complete todos los campos.');
                    return;
                }
                const url = `/?page=${downloadPage}&colegio_id=${colegioId}&fichas=${fichas.join(',')}&week_start=${weekStart}&week_end=${weekEnd}`;
                window.open(url, '_blank');
            });

            // Flatpickr
            if (typeof flatpickr !== 'undefined') {
                const makeFpOptions = (side, alignTargetSelector = null) => ({
                    dateFormat: 'Y-m-d',
                    locale: 'es',
                    static: true,
                    monthSelectorType: 'static',
                    prevArrow: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>',
                    nextArrow: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>',
                    allowInput: true,
                    clickOpens: true,
                    onOpen: function(_, __, inst) {
                        const cal = inst.calendarContainer;
                        const modalContent = inst.input.closest('.modal-content');
                        if (!modalContent) return;
                        if (cal.parentNode !== modalContent) modalContent.appendChild(cal);
                        if (getComputedStyle(modalContent).position === 'static') modalContent.style.position = 'relative';
                        cal.style.zIndex = '9999';
                        cal.style.position = 'absolute';
                        const modalRect = modalContent.getBoundingClientRect();
                        const targetEl = alignTargetSelector ? document.querySelector(alignTargetSelector) : inst.input;
                        const targetRect = targetEl.getBoundingClientRect();
                        const calRect = cal.getBoundingClientRect();
                        const top = (targetRect.top - modalRect.top) + targetRect.height + 8;
                        let left = (targetRect.left - modalRect.left) + (targetRect.width / 2) - (calRect.width / 2);
                        cal.style.top = `${top}px`;
                        const maxLeft = modalRect.width - calRect.width - 8;
                        left = Math.max(8, Math.min(left, maxLeft));
                        cal.style.left = `${left}px`;
                    }
                });

                const fpStart = flatpickr('#weekStart' + suf, makeFpOptions('left', '#weekStart' + suf));
                const fpEnd = flatpickr('#weekEnd' + suf, makeFpOptions('right', '#weekEnd' + suf));

                fpStart.config.onChange.push((selectedDates) => {
                    if (selectedDates[0]) fpEnd.set('minDate', selectedDates[0]);
                });
                fpEnd.config.onChange.push((selectedDates) => {
                    if (selectedDates[0]) fpStart.set('maxDate', selectedDates[0]);
                });
            }
        }

    });
    </script>
</body>
</html>
