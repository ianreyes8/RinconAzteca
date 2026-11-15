<?php
/**
 * Seguridad lógica: manejo de sesiones y control de acceso por rol.
 * Roles del sistema: "Administrativo" y "Normal".
 */
const ROL_ADMIN  = 'Administrativo';
const ROL_NORMAL = 'Normal';
const SESION_INACTIVIDAD = 1800; // 30 minutos

function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    // Cierra la sesión si estuvo inactiva demasiado tiempo
    if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso']) > SESION_INACTIVIDAD) {
        cerrar_sesion();
        session_start();
    }
    if (isset($_SESSION['id_user'])) {
        $_SESSION['ultimo_acceso'] = time();
    }
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function usuario_logueado(): bool
{
    return isset($_SESSION['id_user']);
}

function es_admin(): bool
{
    return usuario_logueado() && ($_SESSION['rol'] ?? '') === ROL_ADMIN;
}

/** Obliga a tener sesión iniciada; si no, manda al login. */
function requiere_login(): void
{
    if (!usuario_logueado()) {
        header('Location: index3.html');
        exit();
    }
}

/** Obliga a tener un rol específico; si no, muestra acceso denegado. */
function requiere_rol(string $rol): void
{
    requiere_login();
    if (($_SESSION['rol'] ?? '') !== $rol) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso denegado</title></head>'
           . '<body style="font-family:sans-serif;text-align:center;margin-top:15%">'
           . '<h1>Acceso denegado</h1><p>No tiene permisos para ver esta sección.</p>'
           . '<a href="indexuser.php">Volver al inicio</a></body></html>';
        exit();
    }
}

/** Escapa texto para imprimirlo seguro en HTML. */
function e(?string $texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

iniciar_sesion_segura();
