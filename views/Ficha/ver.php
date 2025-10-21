<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
?>
<link rel="stylesheet" href="/css/Ficha/ver.css">

<div class="container">
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
                    <?php $estado = strtolower($ficha['estado'] ?? 'pendiente'); ?>
                    <?php if ($estado === 'activa' || $estado === 'activo'): ?>
                        <span class="estado-badge"><span class="dot"></span>ACTIVA</span>
                    <?php else: ?>
                        <span class="estado-badge" style="background:#fbeee0;color:#a67c00;"><span class="dot" style="background:#e1b000;"></span><?= strtoupper(htmlspecialchars($ficha['estado'] ?? 'PENDIENTE')) ?></span>
                    <?php endif; ?>
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
        <a href="/?page=estudiantes&action=crear&ficha_id=<?= urlencode($ficha['id']) ?>"
           class="btn btn-primary">+ Agregar Estudiante</a>
        <a href="/?page=estudiantes&action=importar&ficha_id=<?= urlencode($ficha['id']) ?>"
           class="btn btn-light" style="margin-left:8px;">Importar desde Excel</a>
    </div>

    <hr>

    <!-- 👨‍🎓 Listado de estudiantes -->
    <h3>Estudiantes registrados en esta ficha</h3>

    <?php if (empty($estudiantes)): ?>
        <p>No hay estudiantes registrados aún.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabla-lista">
                <thead>
                    <tr>
                        <th>Nombre completo</th>
                        <th>Documento</th>
                        <th>Colegio</th>
                        <th>Grado</th>
                        <th>Jornada</th>
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
                            <a href="/?page=estudiantes&action=editar&id=<?= urlencode($e['id']) ?>"
                               class="btn-accion btn-warning">Editar</a>
                            <a href="/?page=estudiantes&action=eliminar&id=<?= urlencode($e['id']) ?>"
                               class="btn-accion btn-danger"
                               onclick="return confirm('¿Suspender este estudiante?')">Suspender</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const inputLink = document.getElementById("linkPublico");
    const mensaje = document.getElementById("mensajeCopiado");

    inputLink.addEventListener("click", function() {
        inputLink.select();
        inputLink.setSelectionRange(0, 99999); // para móviles
        document.execCommand("copy");

        mensaje.style.display = "inline";
        setTimeout(() => mensaje.style.display = "none", 2000);
    });
});
</script>

<?php require_once __DIR__ . '/../Componentes/footer.php'; ?>
