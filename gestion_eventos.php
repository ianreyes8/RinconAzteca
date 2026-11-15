<?php
require_once __DIR__ . '/crud.php';

render_crud([
    'titulo'   => 'Reservaciones de eventos',
    'singular' => 'Evento',
    'tabla'    => 'tiporeserva',
    'pk'       => 'id_reserva',
    'permisos' => [ROL_ADMIN => 'crud', ROL_NORMAL => 'crud'],
    'propio'   => 'correo',
    'campos'   => [
        'nombre'      => ['label' => 'Nombre',   'tipo' => 'text',  'max' => 80,  'lista' => true],
        'correo'      => ['label' => 'Correo',   'tipo' => 'email', 'max' => 100, 'lista' => true],
        'telefono'    => ['label' => 'Teléfono', 'tipo' => 'tel',   'max' => 20,  'lista' => true],
        'fecha'       => ['label' => 'Fecha',    'tipo' => 'date',  'lista' => true],
        'hora'        => ['label' => 'Hora',     'tipo' => 'time',  'lista' => true],
        'tiporeserva' => ['label' => 'Tipo de evento', 'tipo' => 'text', 'max' => 100, 'lista' => true, 'ayuda' => 'Ej.: Cumpleaños, Boda, Reunión de empresa'],
    ],
]);
