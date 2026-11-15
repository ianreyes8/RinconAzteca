<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexion.php';

function volver_al_registro(string $mensaje): void
{
    echo '<script>alert(' . json_encode($mensaje) . '); window.location.href = "index3.html";</script>';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['Registrarse'])) {
    header('Location: index3.html');
    exit();
}

$nombre   = trim($_POST['nombreuser'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$correo   = trim($_POST['email'] ?? '');
$clave    = $_POST['pass'] ?? '';

if ($nombre === '' || $telefono === '' || $correo === '' || $clave === '') {
    volver_al_registro('Todos los campos son obligatorios.');
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    volver_al_registro('El correo electrónico no es válido.');
}
if (!preg_match('/^(?=.*\d)(?=.*[A-Z]).{6,15}$/', $clave)) {
    volver_al_registro('La contraseña debe tener de 6 a 15 caracteres, una mayúscula y un número.');
}

// ¿El correo ya está registrado?
$stmt = mysqli_prepare($conexion, 'SELECT 1 FROM usuario WHERE correo = ?');
mysqli_stmt_bind_param($stmt, 's', $correo);
mysqli_stmt_execute($stmt);
$existe = mysqli_stmt_get_result($stmt)->num_rows > 0;
mysqli_stmt_close($stmt);
if ($existe) {
    volver_al_registro('Ese correo ya está registrado.');
}

// Todo registro público se crea con rol Normal; un Administrativo puede cambiarlo luego.
$hash = password_hash($clave, PASSWORD_DEFAULT);
$rol  = ROL_NORMAL;

$stmt = mysqli_prepare($conexion, 'INSERT INTO usuario (nombre, clave, correo, telefono, rol) VALUES (?, ?, ?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'sssss', $nombre, $hash, $correo, $telefono, $rol);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header('Location: principal.php');
exit();
