<?php
/**
 * Motor de mantenimientos (CRUD) reutilizable.
 * Cada pantalla solo define su configuración y llama a render_crud().
 * Incluye: tabla de datos con búsqueda, formularios de agregar/modificar,
 * eliminación con confirmación, validaciones, permisos por rol, token CSRF
 * y consultas preparadas.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/layout.php';

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_ok(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = [$tipo, $mensaje];
}

function validar_campo(array $c, string &$v): ?string
{
    $requerido = $c['requerido'] ?? true;
    if ($v === '') {
        return $requerido ? 'es obligatorio.' : null;
    }
    $max = $c['max'] ?? 255;
    if (mb_strlen($v) > $max) {
        return "máximo $max caracteres.";
    }
    switch ($c['tipo']) {
        case 'email':
            if (!filter_var($v, FILTER_VALIDATE_EMAIL)) return 'correo no válido.';
            break;
        case 'tel':
            if (!preg_match('/^[0-9]{4}-?[0-9]{4}$/', $v)) return 'use el formato 2222-2222.';
            break;
        case 'date':
            $d = DateTime::createFromFormat('Y-m-d', $v);
            if (!$d || $d->format('Y-m-d') !== $v) return 'fecha no válida.';
            break;
        case 'time':
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $v)) return 'hora no válida.';
            if (strlen($v) === 5) $v .= ':00';
            break;
        case 'number':
            if (!is_numeric($v) || (float)$v < ($c['min'] ?? 0)) return 'número no válido.';
            break;
        case 'select':
            if (!in_array($v, $c['opciones'], true)) return 'opción no válida.';
            break;
        case 'password':
            if (!preg_match('/^(?=.*\d)(?=.*[A-Z]).{6,15}$/', $v)) {
                return 'de 6 a 15 caracteres, con una mayúscula y un número.';
            }
            break;
    }
    return null;
}

function campo_form(string $n, array $c, string $valor, bool $editando, bool $bloqueado): void
{
    $id  = 'f_' . $n;
    $req = ($c['requerido'] ?? true) && !($c['tipo'] === 'password' && $editando) ? 'required' : '';
    $ro  = $bloqueado ? 'readonly' : '';
    echo '<div class="campo' . (($c['tipo'] === 'textarea') ? ' ancho' : '') . '">';
    echo '<label for="' . $id . '">' . e($c['label']) . ($req ? ' *' : '') . '</label>';
    switch ($c['tipo']) {
        case 'textarea':
            echo "<textarea id=\"$id\" name=\"" . e($n) . "\" rows=\"3\" maxlength=\"" . ($c['max'] ?? 1000) . "\" $req>" . e($valor) . '</textarea>';
            break;
        case 'select':
            echo "<select id=\"$id\" name=\"" . e($n) . "\" $req>";
            foreach ($c['opciones'] as $o) {
                echo '<option value="' . e($o) . '"' . ($o === $valor ? ' selected' : '') . '>' . e($o) . '</option>';
            }
            echo '</select>';
            break;
        default:
            $tipo  = ['email' => 'email', 'tel' => 'tel', 'date' => 'date', 'time' => 'time',
                      'number' => 'number', 'password' => 'password'][$c['tipo']] ?? 'text';
            $extra = $c['tipo'] === 'number' ? ' step="' . ($c['paso'] ?? '1') . '" min="' . ($c['min'] ?? 0) . '"' : '';
            $extra .= $c['tipo'] === 'tel' ? ' pattern="[0-9]{4}-?[0-9]{4}" title="Formato 2222-2222"' : '';
            $ph = ($c['tipo'] === 'password' && $editando) ? ' placeholder="Dejar en blanco para no cambiarla"' : '';
            $val = $c['tipo'] === 'password' ? '' : $valor;
            echo "<input id=\"$id\" type=\"$tipo\" name=\"" . e($n) . "\" value=\"" . e($val) . "\" maxlength=\"" . ($c['max'] ?? 255) . "\" $extra $ph $req $ro>";
    }
    if (!empty($c['ayuda'])) {
        echo '<small>' . e($c['ayuda']) . '</small>';
    }
    echo '</div>';
}

function celda(array $c, ?string $valor): string
{
    $valor = (string)$valor;
    switch ($c['formato'] ?? '') {
        case 'dinero': return '$' . number_format((float)$valor, 2);
        case 'imagen': return $valor !== '' ? '<img class="mini" src="' . e($valor) . '" alt="">' : '';
        case 'rol':    return '<span class="rol rol-' . ($valor === 'Administrativo' ? 'admin' : 'normal') . '">' . e($valor) . '</span>';
        default:       return e($valor);
    }
}

function render_crud(array $cfg): void
{
    global $conexion;
    requiere_login();

    $rol   = $_SESSION['rol'];
    $perm  = $cfg['permisos'][$rol] ?? '';
    if ($perm === '') {
        acceso_denegado();
    }
    $puede = fn(string $letra): bool => str_contains($perm, $letra); // c=agregar r=consultar u=modificar d=eliminar

    $tabla  = $cfg['tabla'];
    $pk     = $cfg['pk'];
    $campos = $cfg['campos'];
    $pagina = basename($_SERVER['PHP_SELF']);
    $propio = (!es_admin() && !empty($cfg['propio'])) ? $cfg['propio'] : null; // un usuario Normal solo ve lo suyo
    $filtroPropio = $propio ? " AND `$propio` = ?" : '';

    $errores = [];
    $valores = [];
    $modoForm = null;
    $idForm = '';

    // ---------- Procesar formularios (POST) ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_ok()) {
            flash('error', 'El formulario expiró. Intente de nuevo.');
            header("Location: $pagina");
            exit();
        }
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'eliminar' && $puede('d')) {
            $id = (int)($_POST['id'] ?? 0);
            $motivo = isset($cfg['antes_borrar']) ? $cfg['antes_borrar']($conexion, $id) : null;
            if ($motivo) {
                flash('error', $motivo);
            } else {
                $st = mysqli_prepare($conexion, "DELETE FROM `$tabla` WHERE `$pk` = ?$filtroPropio");
                $propio ? mysqli_stmt_bind_param($st, 'is', $id, $_SESSION['correo']) : mysqli_stmt_bind_param($st, 'i', $id);
                mysqli_stmt_execute($st);
                flash('ok', mysqli_stmt_affected_rows($st) > 0 ? $cfg['singular'] . ' eliminado correctamente.' : 'No se encontró el registro.');
                mysqli_stmt_close($st);
            }
            header("Location: $pagina");
            exit();
        }

        if ($accion === 'guardar') {
            $id = trim($_POST['id'] ?? '');
            $editando = $id !== '';
            if (!$puede($editando ? 'u' : 'c')) {
                acceso_denegado();
            }

            $datos = [];
            foreach ($campos as $n => $c) {
                if (($c['editable'] ?? true) === false) continue;
                $v = ($c['tipo'] === 'password') ? (string)($_POST[$n] ?? '') : trim((string)($_POST[$n] ?? ''));
                if ($propio && $n === $propio) $v = $_SESSION['correo'];
                $valores[$n] = $v;

                if ($c['tipo'] === 'password' && $v === '' && $editando) continue; // conserva la clave actual
                $err = validar_campo($c, $v);
                if ($err) {
                    $errores[] = $c['label'] . ': ' . $err;
                } else {
                    $datos[$n] = $c['tipo'] === 'password' ? password_hash($v, PASSWORD_DEFAULT) : $v;
                }
            }
            if (!$errores && isset($cfg['antes_guardar'])) {
                $motivo = $cfg['antes_guardar']($datos, $editando ? (int)$id : null, $conexion);
                if ($motivo) $errores[] = $motivo;
            }

            if (!$errores) {
                try {
                    if ($editando) {
                        $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($datos)));
                        $vals = array_values($datos);
                        $vals[] = (int)$id;
                        $tipos = str_repeat('s', count($datos)) . 'i';
                        if ($propio) { $vals[] = $_SESSION['correo']; $tipos .= 's'; }
                        $st = mysqli_prepare($conexion, "UPDATE `$tabla` SET $sets WHERE `$pk` = ?$filtroPropio");
                    } else {
                        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($datos)));
                        $marcas = implode(', ', array_fill(0, count($datos), '?'));
                        $vals = array_values($datos);
                        $tipos = str_repeat('s', count($datos));
                        $st = mysqli_prepare($conexion, "INSERT INTO `$tabla` ($cols) VALUES ($marcas)");
                    }
                    mysqli_stmt_bind_param($st, $tipos, ...$vals);
                    mysqli_stmt_execute($st);
                    mysqli_stmt_close($st);
                    flash('ok', $cfg['singular'] . ($editando ? ' actualizado' : ' agregado') . ' correctamente.');
                    header("Location: $pagina");
                    exit();
                } catch (mysqli_sql_exception $ex) {
                    $errores[] = ($ex->getCode() === 1062)
                        ? 'Ya existe un registro con ese valor único (por ejemplo, el correo).'
                        : 'No se pudo guardar el registro.';
                }
            }
            $modoForm = $editando ? 'editar' : 'nuevo';
            $idForm = $id;
        }
    }

    // ---------- Pantallas (GET) ----------
    if ($modoForm === null && ($_GET['accion'] ?? '') === 'nuevo' && $puede('c')) {
        $modoForm = 'nuevo';
        foreach ($campos as $n => $c) {
            $valores[$n] = ($propio && $n === $propio) ? $_SESSION['correo'] : ($c['defecto'] ?? '');
        }
    }
    if ($modoForm === null && ($_GET['accion'] ?? '') === 'editar' && $puede('u')) {
        $idForm = (string)(int)($_GET['id'] ?? 0);
        $st = mysqli_prepare($conexion, "SELECT * FROM `$tabla` WHERE `$pk` = ?$filtroPropio");
        $propio ? mysqli_stmt_bind_param($st, 'is', $idForm, $_SESSION['correo']) : mysqli_stmt_bind_param($st, 'i', $idForm);
        mysqli_stmt_execute($st);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        if (!$fila) {
            flash('error', 'No se encontró el registro.');
            header("Location: $pagina");
            exit();
        }
        $modoForm = 'editar';
        $valores = $fila;
    }

    cabecera($cfg['titulo']);
    echo '<h1>' . e($cfg['titulo']) . '</h1>';

    if (!empty($_SESSION['flash'])) {
        [$tipo, $msg] = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="aviso ' . ($tipo === 'ok' ? 'aviso-ok' : 'aviso-error') . '">' . e($msg) . '</div>';
    }
    if ($errores) {
        echo '<div class="aviso aviso-error"><ul>';
        foreach ($errores as $er) echo '<li>' . e($er) . '</li>';
        echo '</ul></div>';
    }

    if ($modoForm !== null) {
        $editando = $modoForm === 'editar';
        ?>
        <section class="tarjeta">
            <h2><?= $editando ? 'Modificar' : 'Agregar' ?> <?= e(strtolower($cfg['singular'])) ?></h2>
            <form method="post" action="<?= e($pagina) ?>" class="formulario">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="<?= e($editando ? $idForm : '') ?>">
                <?php foreach ($campos as $n => $c):
                    if (($c['editable'] ?? true) === false) continue;
                    campo_form($n, $c, (string)($valores[$n] ?? ''), $editando, $propio === $n);
                endforeach; ?>
                <div class="acciones ancho">
                    <button type="submit" class="btn btn-principal"><?= $editando ? 'Guardar cambios' : 'Agregar' ?></button>
                    <a class="btn" href="<?= e($pagina) ?>">Cancelar</a>
                </div>
            </form>
        </section>
        <?php
        pie();
        return;
    }

    // ---------- Tabla de datos ----------
    $busqueda = trim($_GET['q'] ?? '');
    $listar = array_filter($campos, fn($c) => !empty($c['lista']));
    $columnas = array_map(fn($k) => "`$k`", array_keys($listar));
    $sql = "SELECT `$pk`, " . implode(', ', $columnas) . " FROM `$tabla` WHERE 1=1";
    $tipos = '';
    $params = [];
    if ($propio) { $sql .= $filtroPropio; $tipos .= 's'; $params[] = $_SESSION['correo']; }
    if ($busqueda !== '') {
        $textuales = array_keys(array_filter($listar, fn($c) => ($c['formato'] ?? '') !== 'imagen'));
        $sql .= " AND CONCAT_WS(' ', " . implode(', ', array_map(fn($k) => "`$k`", $textuales)) . ") LIKE ?";
        $tipos .= 's';
        $params[] = '%' . $busqueda . '%';
    }
    $sql .= " ORDER BY `$pk` DESC";
    $st = mysqli_prepare($conexion, $sql);
    if ($params) mysqli_stmt_bind_param($st, $tipos, ...$params);
    mysqli_stmt_execute($st);
    $filas = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
    mysqli_stmt_close($st);
    $hayAcciones = $puede('u') || $puede('d');
    ?>
    <section class="tarjeta">
        <div class="barra-herramientas">
            <form method="get" action="<?= e($pagina) ?>" class="buscador">
                <input type="search" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar...">
                <button class="btn" type="submit">Buscar</button>
                <?php if ($busqueda !== ''): ?><a class="btn" href="<?= e($pagina) ?>">Limpiar</a><?php endif; ?>
            </form>
            <?php if ($puede('c')): ?>
                <a class="btn btn-principal" href="<?= e($pagina) ?>?accion=nuevo">+ Agregar <?= e(strtolower($cfg['singular'])) ?></a>
            <?php endif; ?>
        </div>

        <div class="tabla-wrap">
        <table class="tabla">
            <thead>
                <tr>
                    <th>#</th>
                    <?php foreach ($listar as $c): ?><th><?= e($c['label']) ?></th><?php endforeach; ?>
                    <?php if ($hayAcciones): ?><th>Acciones</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (!$filas): ?>
                <tr><td colspan="<?= count($listar) + 2 ?>" class="vacio">No hay registros.</td></tr>
            <?php endif; ?>
            <?php foreach ($filas as $f): ?>
                <tr>
                    <td><?= (int)$f[$pk] ?></td>
                    <?php foreach ($listar as $n => $c): ?><td><?= celda($c, $f[$n]) ?></td><?php endforeach; ?>
                    <?php if ($hayAcciones): ?>
                    <td class="celda-acciones">
                        <?php if ($puede('u')): ?>
                            <a class="btn btn-chico" href="<?= e($pagina) ?>?accion=editar&amp;id=<?= (int)$f[$pk] ?>">Editar</a>
                        <?php endif; ?>
                        <?php if ($puede('d')): ?>
                            <form method="post" action="<?= e($pagina) ?>" onsubmit="return confirm('¿Eliminar este registro? Esta acción no se puede deshacer.');">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$f[$pk] ?>">
                                <button class="btn btn-chico btn-peligro" type="submit">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="total"><?= count($filas) ?> registro(s)</p>
    </section>
    <?php
    pie();
}
