<?php
require_once __DIR__ . '/../helpers/auth.php';

class ReporteController {
    public function index(): void {
        require_login();
        // La verificación de rol ya se realiza en public/index.php antes de invocar este controlador
        $viewPath = __DIR__ . '/../views/Reportes/index.php';
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            // Fallback simple si la vista no existe
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Reportes</title></head><body>';
            echo '<h2>Reportes</h2><p>La vista de reportes no existe aún. Crea el archivo views/Reportes/index.php.</p>';
            echo '</body></html>';
        }
    }
}
