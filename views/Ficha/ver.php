<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
require_once __DIR__ . '/../../helpers/estado_ficha_helper.php';
?>
<link rel="stylesheet" href="/css/Ficha/ver.css">

<div class="container ficha-ver-container">
    <div class="titulo-ficha-wrap">
        <h2>Ficha: <?= htmlspecialchars($ficha['nombre']) ?></h2>
        <div class="linea-verde"></div>
    </div>
    <div class="ficha-info">
        <div class="ficha-card">
            <div class="ficha-card-bar"></div>
            <div class="ficha-card-content">
                <strong>Número:</strong>
                <span class="ficha-card-value"><?= htmlspecialchars($ficha['numero'] ?? '-') ?></span>
            </div>
        </div>
        <div class="ficha-card">
            <div class="ficha-card-bar"></div>
            <div class="ficha-card-content">
                <strong>Estado:</strong>
                <span class="ficha-card-value">
                    <?php 
                    $estado_id = (int)($ficha['estado_id'] ?? 1);
                    $estado_descripcion = getEstadoFichaDescripcion($estado_id);
                    $color = getEstadoFichaColor($estado_id);
                    $dot_color = getEstadoFichaDotColor($estado_id);
                    ?>
                    <span class="estado-badge" style="<?= $color ?>">
                        <span class="dot" style="<?= $dot_color ?>"></span>
                        <?= htmlspecialchars($estado_descripcion) ?>
                    </span>
                </span>
            </div>
        </div>
        <div class="ficha-card">
            <div class="ficha-card-bar"></div>
            <div class="ficha-card-content">
                <strong>Cupo Total:</strong>
                <span class="ficha-card-value"><?= (int)($ficha['cupo_total'] ?? 0) ?></span>
            </div>
        </div>
        <div class="ficha-card">
            <div class="ficha-card-bar"></div>
            <div class="ficha-card-content">
                <strong>Cupo Usado:</strong>
                <span class="ficha-card-value"><?= (int)($ficha['cupo_usado'] ?? 0) ?></span>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['asignado'])): ?>
        <div id="alertAsignadoPendiente"
             style="position:fixed; bottom:24px; right:24px; z-index:1060; background:#dcfce7; color:#14532d; padding:10px 16px; border-radius:999px; box-shadow:0 16px 40px rgba(22,163,74,0.45); font-size:0.9rem; display:flex; align-items:center; gap:8px; transition:opacity .3s ease, transform .3s ease;">
            <span style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:999px; background:#22c55e; color:#ecfdf3; font-size:12px;">✓</span>
            <span>Se asignó correctamente el aprendiz desde la lista de pendientes a esta ficha.</span>
            <button type="button" id="cerrarAlertAsignado" style="margin-left:4px; border:none; background:transparent; color:#14532d; cursor:pointer; font-size:14px; line-height:1;">×</button>
        </div>
    <?php endif; ?>

    <?php if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>
    <?php $rol_actual = (int)($_SESSION['usuario']['rol_id'] ?? 0); ?>
    <?php $cupo_total = (int)($ficha['cupo_total'] ?? 0); $cupo_usado = (int)($ficha['cupo_usado'] ?? 0); $ficha_llena = ($cupo_total > 0 && $cupo_usado >= $cupo_total); ?>
    
    <?php 
    // Mostrar advertencias según el estado de la ficha
    $estado_id = (int)($ficha['estado_id'] ?? 1);
    if ($estado_id !== 1): 
        $estado_descripcion = getEstadoFichaDescripcion($estado_id);
        $color_alerta = getEstadoFichaColor($estado_id);
    ?>
        <div class="alert" style="margin:12px 0; <?= str_replace('background:', 'background:', $color_alerta) ?> padding:12px; border-radius:8px;">
            <strong style="display:block; margin-bottom:4px;">⚠️ Ficha <?= htmlspecialchars($estado_descripcion) ?></strong>
            <?php if ($estado_id === 2): ?>
                <span>La ficha está suspendida. No se permiten registrar nuevos aprendices ni realizar modificaciones.</span>
            <?php elseif ($estado_id === 3): ?>
                <span>La ficha está finalizada. No se permiten registrar nuevos aprendices ni realizar modificaciones.</span>
            <?php elseif ($estado_id === 4): ?>
                <span>La ficha está archivada. No se permiten registrar nuevos aprendices ni realizar modificaciones.</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($estado_id === 1 && $ficha_llena): ?>
        <div class="alert alert-warning" style="margin:12px 0;">
            <strong>⚠️ Ficha completa</strong><br>
            La ficha ha alcanzado su cupo máximo (<?= $cupo_usado ?>/<?= $cupo_total ?>). No se pueden registrar más aprendices.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['import_ok'])): ?>
        <?php 
          if (session_status() === PHP_SESSION_NONE) { session_start(); }
          $creados = (int)($_GET['creados'] ?? 0);
          $saltados = (int)($_GET['saltados'] ?? 0);
          $duplicados = (int)($_GET['duplicados'] ?? 0);
          $errores = $_SESSION['import_errores'] ?? [];
          unset($_SESSION['import_errores']);
        ?>
        <div class="alert alert-warning" style="margin:12px 0;">
            <strong>Resumen de importación:</strong>
            <div>✓ Creados: <strong><?= $creados ?></strong> · ⚠️ Saltados: <strong><?= $saltados ?></strong> · ⛔ Duplicados: <strong><?= $duplicados ?></strong></div>
            <?php if (!empty($errores)): ?>
                <details style="margin-top:8px;">
                    <summary style="cursor:pointer;">Ver detalles de errores (<?= count($errores) ?>)</summary>
                    <ul style="margin-top:6px;">
                        <?php foreach ($errores as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- 🔗 Link público -->
    <div class="link-publico">
        <label>Enlace de inscripción pública:</label>
        <input type="text" id="linkPublico" readonly class="form-control"
            value="<?= htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . "/?page=registro_estudiante&token=" . $ficha['token']) ?>">
        <small id="mensajeCopiado" style="display:none; color:green;">✅ Copiado al portapapeles</small>
    </div>

    <!-- ➕ Botón agregar estudiante -->
    <div class="acciones">
        <?php 
        $estado_id = (int)($ficha['estado_id'] ?? 1);
        $acciones_permitidas = ($estado_id === 1 && !$ficha_llena);
        ?>
        
        <?php if (!$acciones_permitidas): ?>
            <a href="#" class="btn btn-primary" style="opacity:.6; pointer-events:none;" aria-disabled="true" title="No disponible">
                <?php 
                if ($estado_id !== 1) {
                    echo '+ Agregar Estudiante';
                } else {
                    echo '+ Agregar Estudiante';
                }
                ?>
            </a>
            <a href="#" class="btn btn-light" style="margin-left:8px; opacity:.6; pointer-events:none;" aria-disabled="true" title="No disponible">
                <?php 
                if ($estado_id !== 1) {
                    echo 'Importar desde Excel';
                } else {
                    echo 'Importar desde Excel';
                }
                ?>
            </a>
            <a href="#" class="btn btn-light" style="margin-left:8px; opacity:.6; pointer-events:none;" aria-disabled="true" title="No disponible">
                <?php 
                if ($estado_id !== 1) {
                    echo 'Agregar desde pendientes';
                } else {
                    echo 'Agregar desde pendientes';
                }
                ?>
            </a>
            <div style="margin-top:8px; color:#a67c00;">
                <?php 
                if ($estado_id !== 1) {
                    echo '⚠️ La ficha está ' . getEstadoFichaDescripcion($estado_id) . '. No se permiten acciones.';
                } else {
                    echo '⚠️ El cupo de esta ficha está completo. Solo un administrador puede registrar más estudiantes.';
                }
                ?>
            </div>
        <?php else: ?>
            <a href="/?page=aprendices&action=crear&ficha_id=<?= urlencode($ficha['id']) ?>"
               class="btn btn-primary">+ Agregar Estudiante</a>
            <a href="/?page=aprendices&action=importar&ficha_id=<?= urlencode($ficha['id']) ?>"
               class="btn btn-light" style="margin-left:8px;">Importar desde Excel</a>
            <a href="/?page=aprendices&action=pendientes_ficha&ficha_id=<?= urlencode($ficha['id']) ?>"
               class="btn btn-light" style="margin-left:8px;">Agregar desde pendientes</a>
        <?php endif; ?>
    </div>

    <hr>

    <!-- 👨‍🎓 Listado de estudiantes -->
    <h3>Estudiantes registrados en esta ficha</h3>

    <?php if (empty($estudiantes)): ?>
        <p>No hay estudiantes registrados aún.</p>
    <?php else: ?>
        <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
            <table class="tabla-lista">
                <thead>
                    <tr>
                        <th>Nombre completo</th>
                        <th>Documento</th>
                        <th>Colegio</th>
                        <th>Grado</th>
                        <th>Jornada</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($estudiantes as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['nombres'] . " " . $e['apellidos']) ?></td>
                        <td><?= htmlspecialchars($e['numero_documento']) ?></td>
                        <td><?= htmlspecialchars($e['colegio'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($e['grado'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($e['jornada'] ?? '-') ?></td>
                        <td>
                            <?php
                              $estadoRaw = strtolower((string)($e['estado'] ?? 'Activo'));
                              $esSuspendido = ($estadoRaw === 'suspendido');
                            ?>
                            <span class="estado-chip <?= $esSuspendido ? 'estado-chip-suspendido' : 'estado-chip-activo' ?>">
                                <?= $esSuspendido ? 'Suspendido' : 'Activo' ?>
                            </span>
                        </td>
                        <td>
                            <?php
                              // Validar si se permiten acciones según el estado de la ficha
                              $acciones_aprendiz_permitidas = ($estado_id === 1);
                              $esSuspendido = strtolower((string)($e['estado'] ?? 'Activo')) === 'suspendido';
                              $rolActual = (int)($_SESSION['usuario']['rol_id'] ?? 0);
                            ?>
                            <?php if ($acciones_aprendiz_permitidas): ?>
                                <a href="/?page=aprendices&action=editar&id=<?= urlencode($e['id']) ?>&ficha_id=<?= urlencode($ficha['id']) ?>"
                                   class="btn-accion btn-warning">Editar</a>
                                <?php if ($esSuspendido): ?>
                                    <a href="/?page=aprendices&action=activar&id=<?= urlencode($e['id']) ?>&ficha_id=<?= urlencode($ficha['id']) ?>"
                                       class="btn-accion btn-warning btn-estado-aprendiz"
                                       data-action="activar"
                                       data-nombre="<?= htmlspecialchars($e['nombres'] . " " . $e['apellidos']) ?>">Activar</a>
                                <?php else: ?>
                                    <a href="/?page=aprendices&action=eliminar&id=<?= urlencode($e['id']) ?>&ficha_id=<?= urlencode($ficha['id']) ?>"
                                       class="btn-accion btn-danger btn-suspender-aprendiz btn-estado-aprendiz"
                                       data-action="suspender"
                                       data-nombre="<?= htmlspecialchars($e['nombres'] . " " . $e['apellidos']) ?>">Suspender</a>
                                <?php endif; ?>
                                <?php if ($rolActual === 1 && !empty($todasFichas)): ?>
                                    <form method="post" action="/?page=aprendices&action=mover_ficha" style="display:inline-block; margin-left:6px;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <input type="hidden" name="usuario_id" value="<?= htmlspecialchars((string)$e['id']) ?>">
                                        <input type="hidden" name="from_ficha_id" value="<?= htmlspecialchars((string)$ficha['id']) ?>">
                                        <select name="to_ficha_id" class="btn-accion" style="padding:6px 10px; border-radius:20px; border:1px solid #cbd5e1;">
                                            <?php foreach ($todasFichas as $fx): ?>
                                                <?php if ((int)($fx['id'] ?? 0) === (int)$ficha['id']) continue; ?>
                                                <option value="<?= htmlspecialchars((string)($fx['id'] ?? '')) ?>">
                                                    <?= htmlspecialchars((string)($fx['numero'] ?? $fx['id'] ?? '')) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn-accion btn-light" style="padding:6px 12px;">Mover</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn-accion btn-secondary" disabled title="No disponible: Ficha <?= getEstadoFichaDescripcion($estado_id) ?>">
                                    <?php 
                                    if ($esSuspendido) {
                                        echo 'Activar';
                                    } else {
                                        echo 'Editar';
                                    }
                                    ?>
                                </button>
                                <button class="btn-accion btn-secondary" disabled title="No disponible: Ficha <?= getEstadoFichaDescripcion($estado_id) ?>">
                                    <?php 
                                    if ($esSuspendido) {
                                        echo 'Suspender';
                                    } else {
                                        echo 'Eliminar';
                                    }
                                    ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Modal personalizado para suspender/activar aprendiz -->
    <div id="modalSuspenderAprendiz" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:1060; align-items:center; justify-content:center;">
        <div style="background:#ffffff; border-radius:20px; padding:18px 22px 16px; width:100%; max-width:360px; box-shadow:0 24px 60px rgba(15,23,42,.55); font-size:.9rem;">
            <h5 data-role="titulo-modal" style="margin:0 0 8px; font-size:1.05rem; font-weight:800; color:#0f172a;">Suspender aprendiz</h5>
            <p data-role="mensaje-modal" style="margin:0 0 16px; color:#4b5563;">¿Seguro que deseas
                <span data-role="verbo-accion">suspender</span>
                a <span data-role="nombre-suspender" style="font-weight:600; color:#0f172a;">este estudiante</span>
                de esta ficha?
            </p>
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" data-role="cancelar-susp" style="border-radius:999px; padding:6px 14px; font-size:.8rem; font-weight:600; border:1px solid #d1d5db; background:#ffffff; color:#374151; cursor:pointer;">Cancelar</button>
                <button type="button" data-role="confirmar-susp" style="border-radius:999px; padding:6px 16px; font-size:.8rem; font-weight:700; border:1px solid #dc2626; background:#ef4444; color:#fef2f2; box-shadow:0 8px 20px rgba(239,68,68,.45); cursor:pointer;">Suspender</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const inputLink = document.getElementById("linkPublico");
    const mensaje = document.getElementById("mensajeCopiado");
    const alertaAsignado = document.getElementById("alertAsignadoPendiente");
    const btnCerrarAlert = document.getElementById("cerrarAlertAsignado");

    const modalSusp = document.getElementById("modalSuspenderAprendiz");
    const spanNombreSusp = modalSusp ? modalSusp.querySelector('[data-role="nombre-suspender"]') : null;
    const tituloModal = modalSusp ? modalSusp.querySelector('[data-role="titulo-modal"]') : null;
    const verboAccion = modalSusp ? modalSusp.querySelector('[data-role="verbo-accion"]') : null;
    const btnCancelarSusp = modalSusp ? modalSusp.querySelector('[data-role="cancelar-susp"]') : null;
    const btnConfirmarSusp = modalSusp ? modalSusp.querySelector('[data-role="confirmar-susp"]') : null;
    let urlSuspender = null;

    if (inputLink && mensaje) {
        inputLink.addEventListener("click", function() {
            inputLink.select();
            inputLink.setSelectionRange(0, 99999); // para móviles
            document.execCommand("copy");

            mensaje.style.display = "inline";
            setTimeout(() => mensaje.style.display = "none", 2000);
        });
    }

    function ocultarToastAsignado() {
        if (!alertaAsignado) return;
        alertaAsignado.style.opacity = "0";
        alertaAsignado.style.transform = "translateY(6px)";
        setTimeout(() => {
            if (alertaAsignado && alertaAsignado.parentNode) {
                alertaAsignado.parentNode.removeChild(alertaAsignado);
            }
        }, 300);
    }

    if (alertaAsignado) {
        setTimeout(ocultarToastAsignado, 3200);
    }

    if (btnCerrarAlert) {
        btnCerrarAlert.addEventListener("click", ocultarToastAsignado);
    }

    // Modal para suspender / activar aprendiz
    function cerrarModalSuspender() {
        if (!modalSusp) return;
        modalSusp.style.display = "none";
        urlSuspender = null;
    }

    const botonesSuspender = document.querySelectorAll('.btn-estado-aprendiz');
    botonesSuspender.forEach(function(btn) {
        btn.addEventListener('click', function(ev) {
            ev.preventDefault();
            const href = this.getAttribute('href');
            const action = this.getAttribute('data-action') || 'suspender';
            if (!modalSusp || !href) {
                // Fallback: navegar directo si no hay modal
                if (href) {
                    window.location.href = href;
                }
                return;
            }
            urlSuspender = href;
            const nombre = this.getAttribute('data-nombre') || 'este estudiante';
            if (spanNombreSusp) spanNombreSusp.textContent = nombre;
            if (tituloModal) {
                tituloModal.textContent = (action === 'activar') ? 'Activar aprendiz' : 'Suspender aprendiz';
            }
            if (verboAccion) {
                verboAccion.textContent = (action === 'activar') ? 'activar' : 'suspender';
            }
            if (btnConfirmarSusp) {
                btnConfirmarSusp.textContent = (action === 'activar') ? 'Activar' : 'Suspender';
            }
            modalSusp.style.display = 'flex';
        });
    });

    if (btnCancelarSusp) {
        btnCancelarSusp.addEventListener('click', cerrarModalSuspender);
    }

    if (btnConfirmarSusp) {
        btnConfirmarSusp.addEventListener('click', function() {
            if (urlSuspender) {
                window.location.href = urlSuspender;
            }
        });
    }

    if (modalSusp) {
        modalSusp.addEventListener('click', function(ev) {
            if (ev.target === modalSusp) {
                cerrarModalSuspender();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
