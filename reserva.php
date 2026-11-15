<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexion.php';
requiere_login();

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['reserva'])) {
    $nombre   = trim($_POST['name']);
    $correo   = trim($_POST['email']);
    $telefono = trim($_POST['tel']);
    $fecha    = $_POST['date'];
    $hora     = $_POST['time'];

    $stmt = mysqli_prepare($conexion, 'INSERT INTO reserva (nombre, correo, telefono, fecha, hora) VALUES (?,?,?,?,?)');
    mysqli_stmt_bind_param($stmt, 'sssss', $nombre, $correo, $telefono, $fecha, $hora);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo '<script>alert("Reserva Exitosa"); location.href="indexuser.php";</script>';
}
