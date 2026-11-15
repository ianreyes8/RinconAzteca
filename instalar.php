<?php
/**
 * Instalador de la base de datos de Rincón Azteca.
 * Crea la base de datos, las tablas y las puebla con datos de prueba (5+ registros por tabla).
 *
 * - Es seguro ejecutarlo varias veces: solo crea lo que falta y solo puebla tablas vacías.
 * - "Reiniciar" borra todo y vuelve a crearlo; si ya existe un Administrativo, solo lo permite un Administrativo.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$log      = [];
$errores  = [];
$ejecutar = $_SERVER['REQUEST_METHOD'] === 'POST';
$reiniciar = $ejecutar && isset($_POST['reiniciar']);

function datos_menu(): array
{
    return [
        ['Pan Con Ajo', 'Entrada', '7 ordenes de Panes Con Ajo con aderezos adicionales', 4.90, 'menu/Entrads/entrada1.jpg'],
        ['Papas Locas', 'Entrada', 'Papas con Carne, queso Cheddar, queso, salsa de tomate, pollo', 2.50, 'menu/Entrads/entrada2.jpg'],
        ['Pan Con Carne', 'Entrada', 'Papa con Carne molida y queso Cheddar', 3.00, 'menu/Entrads/entrada3.jpg'],
        ['Alitas', 'Entrada', 'Alitas Bufalo, con Adereso ranch, BBQ', 3.25, 'menu/Entrads/entrada4.jpg'],
        ['Aros de Cebolla', 'Entrada', 'Aros de Cebolla con salsa Bufalo y BBQ', 2.15, 'menu/Entrads/entrada5.jpg'],
        ['Chile Con Queso', 'Entrada', 'Bowl con una crema de chile y queso con trozos de tocino y verduras', 2.85, 'menu/Entrads/entrada6.jpg'],
        ['Burritos', 'Platillo', 'Papas con Carne, queso Cheddar, queso, salsa de tomate, pollo', 3.50, 'menu/Platillo/menu1.jpg'],
        ['Tacos de Pastor', 'Platillo', 'Tacos de Pastor con salsa roja o verde', 2.75, 'menu/Platillo/menu2.jpg'],
        ['Sopa de Tortilla', 'Platillo', 'Aguacate, quesillo, tortilla frita', 2.25, 'menu/Platillo/menu3.jpg'],
        ['Flautas', 'Platillo', 'Flautas de pollo, pastor o chorizo', 1.50, 'menu/Platillo/menu4.jpg'],
        ['Tortas', 'Platillo', 'Tortas de pollo, carne o mixtas', 2.50, 'menu/Platillo/menu5.jpg'],
        ['Pozole', 'Platillo', 'Sopa a base de maiz, con carne e ingredientes complementarios', 3.25, 'menu/Platillo/menu6.jpg'],
        ['Horchata', 'Bebida', 'Horchata clasica, de mani y coco', 1.30, 'menu/Bebidas/Bebida1.jpg'],
        ['Cebada', 'Bebida', 'Fresco de Cebada', 1.25, 'menu/Bebidas/Bebida2.jpg'],
        ['Tamarindo', 'Bebida', 'Fresco de Tamarindo', 1.00, 'menu/Bebidas/Bebida3.jpg'],
        ['Michelada', 'Bebida', 'Tajin, Cerveza a eleccion, limon', 2.60, 'menu/Bebidas/Bebida4.jpg'],
        ['Gaseosas', 'Bebida', 'Fresa, Uva, Fanta, Pepsi, Sprite, Coca Cola', 1.35, 'menu/Bebidas/Bebida5.jpg'],
        ['Fresco de Jamaica', 'Bebida', 'Fresco de Jamaica', 1.20, 'menu/Bebidas/Bebida6.jpg'],
        ['Cheesecake', 'Postre', 'Tarta de Queso con Jalea de Fresa', 3.25, 'menu/Postre/postres1.jpg'],
        ['Brownie', 'Postre', 'Brownie de Chocolate con Helado de vainilla', 2.50, 'menu/Postre/postres2.jpg'],
        ['Flan', 'Postre', 'Flan de Caramelo', 1.50, 'menu/Postre/postres4.jpg'],
        ['Helado', 'Postre', 'Bowl de Helado de vainilla, fresa y chocolates con frutas', 3.75, 'menu/Postre/postres3.jpg'],
        ['Pastel de Oreo', 'Postre', 'Pastel de Helado con Galletas Oreo', 2.75, 'menu/Postre/postres5.jpg'],
        ['Crepas', 'Postre', 'Crepas con Chocolate o Caramelo y frutas', 3.25, 'menu/Postre/postres6.jpg'],
    ];
}

if ($ejecutar) {
    try {
        $cfg = db_config();
        $db  = mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], null, $cfg['port']);
        mysqli_set_charset($db, 'utf8mb4');
        $nombreBd = $cfg['name'];

        // 1) Crear la base de datos (en hostings donde no hay permiso, se usa la que ya existe)
        try {
            mysqli_query($db, "CREATE DATABASE IF NOT EXISTS `$nombreBd` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $log[] = "Base de datos «{$nombreBd}» lista.";
        } catch (mysqli_sql_exception $e) {
            $log[] = "No se pudo crear «{$nombreBd}» (se intentará usar la existente).";
        }
        mysqli_select_db($db, $nombreBd);

        // 2) ¿Se permite reiniciar? Solo si no hay usuarios todavía o quien lo pide es Administrativo
        if ($reiniciar) {
            $hayAdmin = false;
            try {
                $hayAdmin = (int)mysqli_fetch_row(mysqli_query($db, 'SELECT COUNT(*) FROM usuario WHERE rol = \'Administrativo\''))[0] > 0;
            } catch (mysqli_sql_exception $e) {
                // la tabla no existe todavía
            }
            if ($hayAdmin && !es_admin()) {
                $errores[] = 'Para reiniciar una base de datos con datos debe iniciar sesión como Administrativo.';
                $reiniciar = false;
            }
        }
        if ($reiniciar) {
            foreach (['pagos', 'tiporeserva', 'reserva', 'menu', 'usuario'] as $t) {
                mysqli_query($db, "DROP TABLE IF EXISTS `$t`");
            }
            $log[] = 'Tablas anteriores eliminadas.';
        }

        // 3) Crear tablas
        $tablas = [
            'usuario' => "CREATE TABLE IF NOT EXISTS usuario (
                id_user INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(80) NOT NULL,
                clave VARCHAR(255) NOT NULL,
                correo VARCHAR(100) NOT NULL UNIQUE,
                telefono VARCHAR(20) NOT NULL,
                rol ENUM('Administrativo','Normal') NOT NULL DEFAULT 'Normal',
                fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'menu' => "CREATE TABLE IF NOT EXISTS menu (
                id_plato INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(80) NOT NULL,
                categoria ENUM('Entrada','Platillo','Bebida','Postre') NOT NULL,
                descripcion VARCHAR(255) NOT NULL,
                precio DECIMAL(6,2) NOT NULL,
                imagen VARCHAR(150) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'reserva' => "CREATE TABLE IF NOT EXISTS reserva (
                id_reserva INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(80) NOT NULL,
                correo VARCHAR(100) NOT NULL,
                telefono VARCHAR(20) NOT NULL,
                fecha DATE NOT NULL,
                hora TIME NOT NULL,
                preferencias TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'tiporeserva' => "CREATE TABLE IF NOT EXISTS tiporeserva (
                id_reserva INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(80) NOT NULL,
                correo VARCHAR(100) NOT NULL,
                telefono VARCHAR(20) NOT NULL,
                fecha DATE NOT NULL,
                hora TIME NOT NULL,
                tiporeserva VARCHAR(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'pagos' => "CREATE TABLE IF NOT EXISTS pagos (
                id_pago INT AUTO_INCREMENT PRIMARY KEY,
                primerNombre VARCHAR(50) NOT NULL,
                primerApellido VARCHAR(50) NOT NULL,
                numeroTarjeta VARCHAR(16) NOT NULL,
                fechaExpiracion VARCHAR(5) NOT NULL,
                cvv VARCHAR(4) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($tablas as $nombre => $sql) {
            mysqli_query($db, $sql);
            $log[] = "Tabla «{$nombre}» lista.";
        }

        // Si la tabla usuario venía de la versión anterior, la actualiza (rol y clave más larga)
        $cols = array_column(mysqli_fetch_all(mysqli_query($db, 'SHOW COLUMNS FROM usuario'), MYSQLI_ASSOC), 'Field');
        if (!in_array('rol', $cols, true)) {
            mysqli_query($db, "ALTER TABLE usuario ADD rol ENUM('Administrativo','Normal') NOT NULL DEFAULT 'Normal'");
            $log[] = 'Se agregó la columna «rol» a usuario.';
        }
        mysqli_query($db, 'ALTER TABLE usuario MODIFY clave VARCHAR(255) NOT NULL');

        // 4) Poblar tablas vacías
        $vacia = fn(string $t): bool => (int)mysqli_fetch_row(mysqli_query($db, "SELECT COUNT(*) FROM `$t`"))[0] === 0;

        if ($vacia('usuario')) {
            $admin  = password_hash('Admin123', PASSWORD_DEFAULT);
            $normal = password_hash('Cliente123', PASSWORD_DEFAULT);
            $usuarios = [
                ['Administrador Rincón Azteca', $admin,  'admin@rinconazteca.com',   '7507-7621', 'Administrativo'],
                ['Gerente de Sala',             $admin,  'gerente@rinconazteca.com', '2222-0001', 'Administrativo'],
                ['Cliente de Prueba',           $normal, 'cliente@rinconazteca.com', '7000-0001', 'Normal'],
                ['Ana Martínez',                $normal, 'ana.martinez@correo.com',  '7000-0002', 'Normal'],
                ['Carlos Rivas',                $normal, 'carlos.rivas@correo.com',  '7000-0003', 'Normal'],
                ['Lucía Hernández',             $normal, 'lucia.hernandez@correo.com','7000-0004', 'Normal'],
            ];
            $st = mysqli_prepare($db, 'INSERT INTO usuario (nombre, clave, correo, telefono, rol) VALUES (?,?,?,?,?)');
            foreach ($usuarios as $u) {
                mysqli_stmt_bind_param($st, 'sssss', ...$u);
                mysqli_stmt_execute($st);
            }
            $log[] = count($usuarios) . ' usuarios insertados.';
        }

        if ($vacia('menu')) {
            $st = mysqli_prepare($db, 'INSERT INTO menu (nombre, categoria, descripcion, precio, imagen) VALUES (?,?,?,?,?)');
            $items = datos_menu();
            foreach ($items as $m) {
                mysqli_stmt_bind_param($st, 'sssds', ...$m);
                mysqli_stmt_execute($st);
            }
            $log[] = count($items) . ' platillos insertados en el menú.';
        }

        if ($vacia('reserva')) {
            $reservas = [
                ['Ana Martínez',    'ana.martinez@correo.com',   '7000-0002', '2026-12-06', '12:30:00', 'Mesa cerca de la ventana'],
                ['Carlos Rivas',    'carlos.rivas@correo.com',   '7000-0003', '2026-12-06', '19:00:00', 'Sin picante'],
                ['Lucía Hernández', 'lucia.hernandez@correo.com','7000-0004', '2026-12-07', '13:00:00', null],
                ['Cliente de Prueba','cliente@rinconazteca.com', '7000-0001', '2026-12-12', '20:00:00', 'Silla para bebé'],
                ['Mariano Alvarenga','mariano@correo.com',       '7000-0005', '2026-12-13', '14:15:00', 'Mesa para 6 personas'],
                ['Diego Guerra',    'diego@correo.com',          '7000-0006', '2026-12-14', '18:30:00', null],
            ];
            $st = mysqli_prepare($db, 'INSERT INTO reserva (nombre, correo, telefono, fecha, hora, preferencias) VALUES (?,?,?,?,?,?)');
            foreach ($reservas as $r) {
                mysqli_stmt_bind_param($st, 'ssssss', ...$r);
                mysqli_stmt_execute($st);
            }
            $log[] = count($reservas) . ' reservas insertadas.';
        }

        if ($vacia('tiporeserva')) {
            $eventos = [
                ['Ana Martínez',    'ana.martinez@correo.com',   '7000-0002', '2026-12-19', '15:00:00', 'Cumpleaños'],
                ['Carlos Rivas',    'carlos.rivas@correo.com',   '7000-0003', '2026-12-20', '18:00:00', 'Reunión de empresa'],
                ['Lucía Hernández', 'lucia.hernandez@correo.com','7000-0004', '2026-12-21', '12:00:00', 'Graduación'],
                ['Cesar Guardado',  'cesar@correo.com',          '7000-0007', '2026-12-26', '19:30:00', 'Aniversario'],
                ['Leonardo Vásquez','leonardo@correo.com',       '7000-0008', '2026-12-31', '20:00:00', 'Fiesta de fin de año'],
            ];
            $st = mysqli_prepare($db, 'INSERT INTO tiporeserva (nombre, correo, telefono, fecha, hora, tiporeserva) VALUES (?,?,?,?,?,?)');
            foreach ($eventos as $ev) {
                mysqli_stmt_bind_param($st, 'ssssss', ...$ev);
                mysqli_stmt_execute($st);
            }
            $log[] = count($eventos) . ' reservas de eventos insertadas.';
        }

        if ($vacia('pagos')) {
            // Solo datos de prueba (números de tarjeta de ejemplo)
            $pagos = [
                ['Ana',    'Martínez',  '4111111111111111', '12/28', '123'],
                ['Carlos', 'Rivas',     '4242424242424242', '11/27', '123'],
                ['Lucía',  'Hernández', '5555555555554444', '10/29', '123'],
                ['Mariano','Alvarenga', '5105105105105100', '09/28', '123'],
                ['Diego',  'Guerra',    '4012888888881881', '08/27', '123'],
            ];
            $st = mysqli_prepare($db, 'INSERT INTO pagos (primerNombre, primerApellido, numeroTarjeta, fechaExpiracion, cvv) VALUES (?,?,?,?,?)');
            foreach ($pagos as $p) {
                mysqli_stmt_bind_param($st, 'sssss', ...$p);
                mysqli_stmt_execute($st);
            }
            $log[] = count($pagos) . ' pagos de prueba insertados.';
        }

        $log[] = '¡Instalación completada!';
    } catch (mysqli_sql_exception $e) {
        $errores[] = 'Error durante la instalación: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - Rincón Azteca</title>
    <link rel="stylesheet" href="style5.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f4ece4; margin: 0; padding: 30px; }
        .caja { max-width: 640px; margin: 0 auto; background: #fff; padding: 28px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.15); }
        h1 { color: #ac4747; margin-top: 0; }
        button { background: #ac4747; color: #fff; border: 0; padding: 12px 22px; border-radius: 8px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #036b0c; }
        .ok { color: #036b0c; } .err { color: #b00020; font-weight: bold; }
        table { border-collapse: collapse; width: 100%; margin: 12px 0; }
        td, th { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
        label.check { display: block; margin: 14px 0; }
    </style>
</head>
<body>
<div class="caja">
    <h1>Instalación de la base de datos</h1>

    <?php foreach ($errores as $err): ?><p class="err"><?= htmlspecialchars($err) ?></p><?php endforeach; ?>
    <?php foreach ($log as $l): ?><p class="ok">✔ <?= htmlspecialchars($l) ?></p><?php endforeach; ?>

    <?php if ($ejecutar && !$errores): ?>
        <h3>Cuentas de prueba</h3>
        <table>
            <tr><th>Rol</th><th>Correo</th><th>Contraseña</th></tr>
            <tr><td>Administrativo</td><td>admin@rinconazteca.com</td><td>Admin123</td></tr>
            <tr><td>Normal</td><td>cliente@rinconazteca.com</td><td>Cliente123</td></tr>
        </table>
        <p><a href="index3.html">Ir a iniciar sesión</a></p>
    <?php else: ?>
        <p>Este proceso crea la base de datos <strong><?= htmlspecialchars(db_config()['name']) ?></strong>,
           sus tablas y las llena con datos de prueba (mínimo 5 registros por tabla).</p>
        <form method="post">
            <label class="check"><input type="checkbox" name="reiniciar"> Reiniciar: borrar todo y volver a crear (elimina los datos actuales)</label>
            <button type="submit">Crear base de datos</button>
        </form>
        <p><a href="index.html">Volver al sitio</a></p>
    <?php endif; ?>
</div>
</body>
</html>
