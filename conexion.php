<?php
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $cfg = db_config();
    $conexion = mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], $cfg['port']);
    mysqli_set_charset($conexion, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die('No se pudo conectar a la base de datos. Si es la primera vez que usa el sistema, ejecute la '
        . '<a href="instalar.php">instalación de la base de datos</a>.');
}
