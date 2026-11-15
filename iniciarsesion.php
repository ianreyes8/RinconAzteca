<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexion.php';

function volver_al_login(string $mensaje): void
{
    echo '<script>alert(' . json_encode($mensaje) . '); window.location.href = "index3.html";</script>';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login_button'])) {
    header('Location: index3.html');
    exit();
}

$correo   = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = mysqli_prepare($conexion, 'SELECT id_user, nombre, clave, rol FROM usuario WHERE correo = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $correo);
mysqli_stmt_execute($stmt);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$usuario || !password_verify($password, $usuario['clave'])) {
    volver_al_login('Usuario y/o contraseña incorrectos. Vuelve a intentarlo.');
}

// Evita fijación de sesión y guarda los datos del usuario
session_regenerate_id(true);
$_SESSION['id_user']       = (int)$usuario['id_user'];
$_SESSION['nombre']        = $usuario['nombre'];
$_SESSION['correo']        = $correo;
$_SESSION['rol']           = $usuario['rol'];
$_SESSION['ultimo_acceso'] = time();

header('Location: indexuser.php');
exit();
