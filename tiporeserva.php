<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexion.php';
requiere_login();

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['tiporeserva'])) {
    $nombre      = trim($_POST['nombreuser']);
    $correo      = trim($_POST['email']);
    $telefono    = trim($_POST['telefono']);
    $fecha       = $_POST['date'];
    $hora        = $_POST['time'];
    $tiporeserva = $_POST['tipo_evento'];

    $stmt = mysqli_prepare($conexion, 'INSERT INTO tiporeserva (nombre, correo, telefono, fecha, hora, tiporeserva) VALUES (?,?,?,?,?,?)');
    mysqli_stmt_bind_param($stmt, 'ssssss', $nombre, $correo, $telefono, $fecha, $hora, $tiporeserva);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo '<script>alert("Reserva Exitosa"); window.location.href="indexuser.php";</script>';
}
