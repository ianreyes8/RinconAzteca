<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/conexion.php';
requiere_login();

function contar(mysqli $db, string $tabla, ?string $correo = null): int
{
    if ($correo !== null) {
        $st = mysqli_prepare($db, "SELECT COUNT(*) FROM `$tabla` WHERE correo = ?");
        mysqli_stmt_bind_param($st, 's', $correo);
    } else {
        $st = mysqli_prepare($db, "SELECT COUNT(*) FROM `$tabla`");
    }
    mysqli_stmt_execute($st);
    $n = (int)mysqli_fetch_row(mysqli_stmt_get_result($st))[0];
    mysqli_stmt_close($st);
    return $n;
}

$admin  = es_admin();
$propio = $admin ? null : $_SESSION['correo'];

$tarjetas = [
    ['gestion_menu.php',     'Menú',     contar($conexion, 'menu'),                      'platillos en el menú',                 $admin ? 'Agregar, modificar y eliminar platillos' : 'Consultar los platillos'],
    ['gestion_reservas.php', 'Reservas', contar($conexion, 'reserva', $propio),          $admin ? 'reservas registradas' : 'reservas suyas',  'Mantenimiento de reservaciones de mesa'],
    ['gestion_eventos.php',  'Eventos',  contar($conexion, 'tiporeserva', $propio),      $admin ? 'eventos registrados' : 'eventos suyos',    'Mantenimiento de reservaciones de eventos'],
];
if ($admin) {
    $tarjetas[] = ['usuarios.php', 'Usuarios', contar($conexion, 'usuario'), 'usuarios del sistema', 'Agregar, modificar, consultar y eliminar usuarios'];
}

cabecera('Panel');
?>
<h1>Bienvenido, <?= e($_SESSION['nombre']) ?></h1>
<p class="sub">Nivel de acceso: <strong><?= e($_SESSION['rol']) ?></strong>
   <?= $admin ? '— tiene acceso completo al sistema.' : '— puede consultar el menú y gestionar sus propias reservaciones.' ?></p>

<div class="rejilla">
    <?php foreach ($tarjetas as [$url, $titulo, $total, $texto, $desc]): ?>
        <a class="tarjeta-panel" href="<?= $url ?>">
            <span class="numero"><?= $total ?></span>
            <h3><?= e($titulo) ?></h3>
            <p class="etiqueta"><?= e($texto) ?></p>
            <p class="desc"><?= e($desc) ?></p>
        </a>
    <?php endforeach; ?>
</div>
<?php pie();
