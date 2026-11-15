<?php
require_once __DIR__ . '/auth.php';
cerrar_sesion();
header('Location: index.html');
exit();
