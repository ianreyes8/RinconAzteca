<?php
require_once __DIR__ . '/crud.php';
requiere_rol(ROL_ADMIN); // Solo el Administrativo gestiona usuarios

function es_ultimo_admin(mysqli $db, int $id): bool
{
    $st = mysqli_prepare($db, "SELECT COUNT(*) FROM usuario WHERE rol = 'Administrativo' AND id_user <> ?");
    mysqli_stmt_bind_param($st, 'i', $id);
    mysqli_stmt_execute($st);
    $otros = (int)mysqli_fetch_row(mysqli_stmt_get_result($st))[0];
    mysqli_stmt_close($st);
    return $otros === 0;
}

render_crud([
    'titulo'   => 'Gestión de usuarios',
    'singular' => 'Usuario',
    'tabla'    => 'usuario',
    'pk'       => 'id_user',
    'permisos' => [ROL_ADMIN => 'crud'],
    'campos'   => [
        'nombre'         => ['label' => 'Nombre',        'tipo' => 'text',  'max' => 80,  'lista' => true],
        'correo'         => ['label' => 'Correo',        'tipo' => 'email', 'max' => 100, 'lista' => true],
        'telefono'       => ['label' => 'Teléfono',      'tipo' => 'tel',   'max' => 20,  'lista' => true, 'ayuda' => 'Formato 2222-2222'],
        'rol'            => ['label' => 'Rol',           'tipo' => 'select', 'opciones' => [ROL_ADMIN, ROL_NORMAL], 'defecto' => ROL_NORMAL, 'lista' => true, 'formato' => 'rol'],
        'clave'          => ['label' => 'Contraseña',    'tipo' => 'password', 'max' => 15, 'ayuda' => '6 a 15 caracteres, con una mayúscula y un número'],
        'fecha_registro' => ['label' => 'Registrado',    'tipo' => 'text',  'editable' => false, 'lista' => true],
    ],
    'antes_guardar' => function (array &$datos, ?int $id, mysqli $db): ?string {
        if ($id !== null && ($datos['rol'] ?? '') !== ROL_ADMIN && es_ultimo_admin($db, $id)) {
            return 'Debe existir al menos un usuario Administrativo; no se puede cambiar el rol de este usuario.';
        }
        return null;
    },
    'antes_borrar' => function (mysqli $db, int $id): ?string {
        if ($id === (int)$_SESSION['id_user']) {
            return 'No puede eliminar su propio usuario.';
        }
        $st = mysqli_prepare($db, 'SELECT rol FROM usuario WHERE id_user = ?');
        mysqli_stmt_bind_param($st, 'i', $id);
        mysqli_stmt_execute($st);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        if ($fila && $fila['rol'] === ROL_ADMIN && es_ultimo_admin($db, $id)) {
            return 'No se puede eliminar al último Administrativo.';
        }
        return null;
    },
]);
