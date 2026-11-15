<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexion.php';
requiere_login();

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['primerNombre'])) {
    $primerNombre    = trim($_POST['primerNombre']);
    $primerApellido  = trim($_POST['primerApellido']);
    $numeroTarjeta   = $_POST['numeroTarjeta'];
    $fechaExpiracion = $_POST['fechaExpiracion'];
    $cvv             = $_POST['cvv'];

    $stmt = mysqli_prepare($conexion, 'INSERT INTO pagos (primerNombre, primerApellido, numeroTarjeta, fechaExpiracion, cvv) VALUES (?,?,?,?,?)');
    mysqli_stmt_bind_param($stmt, 'sssss', $primerNombre, $primerApellido, $numeroTarjeta, $fechaExpiracion, $cvv);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo '<script>alert("Pago Exitoso"); window.location.href="indexuser.php";</script>';
}
