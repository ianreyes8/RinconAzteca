<?php
require_once __DIR__ . '/crud.php';

render_crud([
    'titulo'   => 'Reservaciones de mesa',
    'singular' => 'Reservación',
    'tabla'    => 'reserva',
    'pk'       => 'id_reserva',
    // Administrativo: todas las reservas. Normal: solo las suyas (filtradas por su correo).
    'permisos' => [ROL_ADMIN => 'crud', ROL_NORMAL => 'crud'],
    'propio'   => 'correo',
    'campos'   => [
        'nombre'       => ['label' => 'Nombre',   'tipo' => 'text',  'max' => 80,  'lista' => true],
        'correo'       => ['label' => 'Correo',   'tipo' => 'email', 'max' => 100, 'lista' => true],
        'telefono'     => ['label' => 'Teléfono', 'tipo' => 'tel',   'max' => 20,  'lista' => true],
        'fecha'        => ['label' => 'Fecha',    'tipo' => 'date',  'lista' => true],
        'hora'         => ['label' => 'Hora',     'tipo' => 'time',  'lista' => true],
        'preferencias' => ['label' => 'Preferencias', 'tipo' => 'textarea', 'requerido' => false, 'max' => 500, 'lista' => true],
    ],
]);
