<?php
require_once __DIR__ . '/crud.php';

render_crud([
    'titulo'   => 'Mantenimiento del menú',
    'singular' => 'Platillo',
    'tabla'    => 'menu',
    'pk'       => 'id_plato',
    // El Administrativo hace el mantenimiento completo; el usuario Normal solo consulta
    'permisos' => [ROL_ADMIN => 'crud', ROL_NORMAL => 'r'],
    'campos'   => [
        'imagen'      => ['label' => 'Imagen',      'tipo' => 'text',   'max' => 150, 'lista' => true, 'formato' => 'imagen', 'ayuda' => 'Ruta de la imagen, por ejemplo: menu/Platillo/menu1.jpg'],
        'nombre'      => ['label' => 'Nombre',      'tipo' => 'text',   'max' => 80,  'lista' => true],
        'categoria'   => ['label' => 'Categoría',   'tipo' => 'select', 'opciones' => ['Entrada', 'Platillo', 'Bebida', 'Postre'], 'lista' => true],
        'descripcion' => ['label' => 'Descripción', 'tipo' => 'textarea', 'max' => 255, 'lista' => true],
        'precio'      => ['label' => 'Precio (USD)', 'tipo' => 'number', 'paso' => '0.01', 'min' => 0, 'lista' => true, 'formato' => 'dinero'],
    ],
]);
