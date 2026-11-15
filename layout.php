<?php
/**
 * Plantilla común de las pantallas de administración (panel y mantenimientos).
 */
require_once __DIR__ . '/auth.php';

function cabecera(string $titulo): void
{
    $actual = basename($_SERVER['PHP_SELF']);
    $enlaces = [
        'panel.php'            => 'Panel',
        'gestion_menu.php'     => 'Menú',
        'gestion_reservas.php' => 'Reservas',
        'gestion_eventos.php'  => 'Eventos',
    ];
    if (es_admin()) {
        $enlaces['usuarios.php'] = 'Usuarios';
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> - Rincón Azteca</title>
    <link rel="icon" href="LOGOO.ico">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<header class="barra">
    <a class="marca" href="panel.php">🌮 Rincón Azteca</a>
    <nav>
        <?php foreach ($enlaces as $url => $texto): ?>
            <a href="<?= $url ?>" class="<?= $url === $actual ? 'activo' : '' ?>"><?= $texto ?></a>
        <?php endforeach; ?>
        <a href="indexuser.php">Ver sitio</a>
    </nav>
    <div class="sesion">
        <span><?= e($_SESSION['nombre'] ?? '') ?> <small class="rol rol-<?= es_admin() ? 'admin' : 'normal' ?>"><?= e($_SESSION['rol'] ?? '') ?></small></span>
        <a class="salir" href="logout.php">Cerrar sesión</a>
    </div>
</header>
<main class="contenedor">
<?php
}

function pie(): void
{
    ?>
</main>
<footer class="pie">Rincón Azteca · Sistema de Gestión · DPWEB</footer>
</body>
</html>
<?php
}
