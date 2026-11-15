<?php
/**
 * Configuración de la base de datos.
 * - En Railway/Docker se toman las variables de entorno MYSQL*.
 * - En local (XAMPP) se usan los valores por defecto: root, sin contraseña, base "ra".
 */
function db_config(): array
{
    $pass = getenv('MYSQLPASSWORD');

    return [
        'host' => getenv('MYSQLHOST') ?: 'localhost',
        'user' => getenv('MYSQLUSER') ?: 'root',
        'pass' => ($pass === false) ? '' : $pass,
        'name' => getenv('MYSQLDATABASE') ?: 'ra',
        'port' => (int)(getenv('MYSQLPORT') ?: 3306),
    ];
}
