<?php
/* ============================================================
   MMM · Talento de los domingos (requiere token)
   GET  api/talentos.php              los que vienen y los últimos que pasaron
   GET  api/talentos.php?id=3         uno, con sus cobros (solo quien cobra)
   GET  api/talentos.php?cuentas=1    a quién se le puede dar la caja (admins)
   POST api/talentos.php
        { "accion": "crear",  "datos": { ... } }        administradores
        { "accion": "editar", "id": 3, "datos": { ... } }
        { "accion": "borrar", "id": 3 }
        { "accion": "cobrar", "talento_id": 3, "platos": 2,
          "metodo": "efectivo", "recibido": "20" }      quien cobra
        { "accion": "anular", "id": 15 }                un cobro mal hecho

   Cada domingo alguien prepara comida que se vende después del
   culto. El pastor (o un admin) elige quién cocina, qué plato y a
   cuánto; el día del talento el que cobra cuenta a las personas que
   comen y anota si pagaron en efectivo o por traspaso.
   ============================================================ */

require __DIR__ . '/conexion.php';

/* Además de estos, cobra la cuenta elegida como cobrador ese día */
const ROLES_COBRO_TALENTO = ['maestro', 'pastor', 'admin', 'tesorero'];
const METODOS_PAGO        = ['efectivo', 'traspaso'];
const MAX_PLATOS_POR_COBRO = 50;

$usuario      = exigirToken($db);
$organiza     = esAdministrador($usuario);
$hoy          = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedido = leerJson();
    $accion = (string) ($pedido['accion'] ?? '');

    match ($accion) {
        'crear', 'editar', 'borrar' => guardarTalento($db, $usuario, $pedido, $accion),
        'cobrar'                    => cobrar($db, $usuario, $pedido),
        'anular'                    => anular($db, $usuario, $pedido),
        default => responder(['error' => 'Acción desconocida: usar "crear", "editar", "borrar", "cobrar" o "anular"'], 400),
    };
}

exigirMetodo('GET');

if (isset($_GET['cuentas'])) {
    if (!$organiza) {
        responder(['error' => 'Solo el pastor o un administrador puede hacer esto'], 403);
    }
    $cuentas = $db->query(
        "SELECT id, usuario, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre, usuario"
    )->fetchAll();
    responder(['cuentas' => array_map(fn(array $c) => [
        'id'      => (int) $c['id'],
        'usuario' => $c['usuario'],
        'nombre'  => $c['nombre'] !== '' ? $c['nombre'] : $c['usuario'],
    ], $cuentas)]);
}

if (!isset($_GET['id'])) {
    /* Los que vienen, y los últimos 8 que pasaron para ver cómo les fue */
    $proximos = leerTalentos($db, $usuario, 't.fecha >= ?', [$hoy]);
    $pasados  = leerTalentos($db, $usuario, 't.fecha < ?', [$hoy], 'DESC', 8);
    responder(['talentos' => [...$proximos, ...$pasados], 'organiza' => $organiza]);
}

$id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    responder(['error' => 'El "id" no es válido'], 400);
}
$talento = leerTalentos($db, $usuario, 't.id = ?', [$id])[0] ?? responder(['error' => 'No existe ese talento'], 404);

responder([
    'talento' => $talento,
    'ventas'  => $talento['puede_cobrar'] ? leerVentas($db, $id) : [],
]);

/* --- Organizar --------------------------------------------------- */

function guardarTalento(PDO $db, array $usuario, array $pedido, string $accion): never
{
    if (!esAdministrador($usuario)) {
        responder(['error' => 'Solo el pastor o un administrador organiza el talento'], 403);
    }

    $anterior = null;
    if ($accion !== 'crear') {
        $id = filter_var($pedido['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            responder(['error' => 'Falta un "id" válido'], 400);
        }
        $anterior = leerTalentos($db, $usuario, 't.id = ?', [$id])[0] ?? responder(['error' => 'No existe ese talento'], 404);
    }

    if ($accion === 'borrar') {
        if ($anterior['totales']['ventas'] > 0) {
            responder(['error' => 'Ya tiene cobros anotados: anúlalos primero si de verdad no se hizo'], 422);
        }
        $db->prepare('DELETE FROM talentos WHERE id = ?')->execute([$id]);
        responder(['ok' => true, 'id' => $id]);
    }

    [$datos, $cocineros] = validarTalento($db, $pedido['datos'] ?? null, $anterior['id'] ?? null);

    /* Con cobros anotados, cambiar el precio dejaría los totales raros */
    if ($anterior && $anterior['totales']['ventas'] > 0 && (float) $datos['precio'] !== $anterior['precio']) {
        responder(['error' => 'Datos inválidos', 'campos' => [
            'precio' => 'Ya se cobraron platos a ' . dinero($anterior['precio']) . ': no se puede cambiar',
        ]], 422);
    }

    $db->beginTransaction();
    if ($accion === 'crear') {
        $db->prepare('INSERT INTO talentos (fecha, platillo, descripcion, precio, cobrador_id) VALUES (?, ?, ?, ?, ?)')
           ->execute(array_values($datos));
        $id = (int) $db->lastInsertId();
    } else {
        $db->prepare('UPDATE talentos SET fecha = ?, platillo = ?, descripcion = ?, precio = ?, cobrador_id = ? WHERE id = ?')
           ->execute([...array_values($datos), $id]);
        $db->prepare('DELETE FROM talento_cocineros WHERE talento_id = ?')->execute([$id]);
    }
    $insertar = $db->prepare('INSERT INTO talento_cocineros (talento_id, representante_id, nombre, orden) VALUES (?, ?, ?, ?)');
    foreach ($cocineros as $orden => $cocinero) {
        $insertar->execute([$id, $cocinero['representante_id'], $cocinero['nombre'], $orden + 1]);
    }
    $db->commit();

    $talento = leerTalentos($db, $usuario, 't.id = ?', [$id])[0];
    avisarNuevos($db, $usuario, $talento, $anterior);

    responder(['ok' => true, 'talento' => $talento], $accion === 'crear' ? 201 : 200);
}

/* A quien recién le toca cocinar o cobrar (y tiene cuenta), un aviso.
   Si solo se corrigió el plato o el precio, no se repite. */
function avisarNuevos(PDO $db, array $usuario, array $talento, ?array $anterior): void
{
    $cuando = fechaLegible($talento['fecha']);
    $plato  = $talento['platillo'] . ' a ' . dinero($talento['precio']) . ' el plato';

    $antes = array_filter(array_column($anterior['cocineros'] ?? [], 'representante_id'));
    $nuevos = array_diff(array_filter(array_column($talento['cocineros'], 'representante_id')), $antes);
    if ($nuevos) {
        $marcas = implode(', ', array_fill(0, count($nuevos), '?'));
        $consulta = $db->prepare("SELECT usuario_id FROM representantes WHERE id IN ($marcas) AND usuario_id IS NOT NULL");
        $consulta->execute(array_values($nuevos));
        $ids = array_diff(array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN)), [(int) $usuario['id']]);
        crearAviso($db, $ids, 'talento', "Te toca preparar el talento del $cuando", "$plato. ¡Gracias por servir!");
    }

    $cobrador = $talento['cobrador']['id'] ?? null;
    if ($cobrador && $cobrador !== ($anterior['cobrador']['id'] ?? null) && $cobrador !== (int) $usuario['id']) {
        crearAviso($db, [$cobrador], 'talento', "Te toca cobrar el talento del $cuando",
            "$plato. En la app, Talento → Cobrar, cuenta a los que comen y anota si pagan en efectivo o por traspaso.");
    }
}

/* Los campos ya limpios, en el orden de las columnas, y los cocineros.
   Se mandan todos cada vez. */
function validarTalento(PDO $db, mixed $datos, ?int $id): array
{
    if (!is_array($datos)) {
        responder(['error' => 'Falta el objeto "datos"'], 400);
    }

    $fecha       = trim((string) ($datos['fecha'] ?? ''));
    $platillo    = trim((string) ($datos['platillo'] ?? ''));
    $descripcion = trim((string) ($datos['descripcion'] ?? ''));
    $precio      = leerMonto($datos['precio'] ?? null);
    $cobradorId  = $datos['cobrador_id'] ?? null;
    $lista       = $datos['cocineros'] ?? [];

    $errores = [];
    $leida = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$leida || $leida->format('Y-m-d') !== $fecha) {
        $errores['fecha'] = 'Debe tener formato AAAA-MM-DD';
    } else {
        $consulta = $db->prepare('SELECT id FROM talentos WHERE fecha = ? AND id <> ?');
        $consulta->execute([$fecha, $id ?? 0]);
        if ($consulta->fetchColumn()) {
            $errores['fecha'] = 'Ese día ya tiene talento';
        }
    }
    if ($platillo === '') {
        $errores['platillo'] = 'Escribe qué se va a vender';
    } elseif (mb_strlen($platillo) > 150) {
        $errores['platillo'] = 'Máximo 150 caracteres';
    }
    if (mb_strlen($descripcion) > 2000) {
        $errores['descripcion'] = 'Máximo 2000 caracteres';
    }
    if ($precio === null || (float) $precio <= 0) {
        $errores['precio'] = 'Debe ser un monto mayor que cero, con hasta 2 decimales';
    }

    if ($cobradorId === null || $cobradorId === '') {
        $cobradorId = null;
    } else {
        $cobradorId = filter_var($cobradorId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $consulta = $db->prepare('SELECT 1 FROM usuarios WHERE id = ? AND activo = 1');
        $consulta->execute([$cobradorId === false ? 0 : $cobradorId]);
        if (!$consulta->fetchColumn()) {
            $errores['cobrador_id'] = 'Esa cuenta no existe';
        }
    }

    $cocineros = [];
    if (!is_array($lista) || !array_is_list($lista)) {
        $errores['cocineros'] = 'Debe ser una lista';
    } else {
        $vistos = [];
        foreach ($lista as $cocinero) {
            $repId  = is_array($cocinero) ? ($cocinero['representante_id'] ?? null) : null;
            $nombre = is_array($cocinero) ? trim((string) ($cocinero['nombre'] ?? '')) : '';
            if ($repId !== null) {
                $repId = filter_var($repId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $consulta = $db->prepare('SELECT nombre FROM representantes WHERE id = ?');
                $consulta->execute([$repId === false ? 0 : $repId]);
                if ($consulta->fetchColumn() === false) {
                    $errores['cocineros'] = 'Uno de los que cocinan ya no existe';
                    break;
                }
                if (isset($vistos[$repId])) {
                    continue;
                }
                $vistos[$repId] = true;
                $cocineros[] = ['representante_id' => $repId, 'nombre' => ''];
            } elseif ($nombre !== '' && mb_strlen($nombre) <= 150) {
                $cocineros[] = ['representante_id' => null, 'nombre' => $nombre];
            } else {
                $errores['cocineros'] = 'Cada uno necesita un representante o un nombre (hasta 150 letras)';
                break;
            }
        }
        if (!$cocineros && !isset($errores['cocineros'])) {
            $errores['cocineros'] = 'Elige quién prepara la comida';
        } elseif (count($cocineros) > 20) {
            $errores['cocineros'] = 'Máximo 20 personas';
        }
    }

    if ($errores) {
        responder(['error' => 'Datos inválidos', 'campos' => $errores], 422);
    }

    return [[
        'fecha'       => $fecha,
        'platillo'    => $platillo,
        'descripcion' => $descripcion,
        'precio'      => $precio,
        'cobrador_id' => $cobradorId,
    ], $cocineros];
}

/* --- Cobrar ------------------------------------------------------ */

function cobrar(PDO $db, array $usuario, array $pedido): never
{
    $talento = talentoParaCobrar($db, $usuario, $pedido['talento_id'] ?? null);

    if ($talento['fecha'] > date('Y-m-d')) {
        responder(['error' => 'Todavía no es el día de este talento'], 422);
    }

    $platos   = filter_var($pedido['platos'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => MAX_PLATOS_POR_COBRO]]);
    $metodo   = (string) ($pedido['metodo'] ?? '');
    $recibido = $pedido['recibido'] ?? null;

    $errores = [];
    if ($platos === false) {
        $errores['platos'] = 'Entre 1 y ' . MAX_PLATOS_POR_COBRO . ' platos por cobro';
    }
    if (!in_array($metodo, METODOS_PAGO, true)) {
        $errores['metodo'] = 'Debe ser "efectivo" o "traspaso"';
    }
    $monto = $platos === false ? 0.0 : round($platos * $talento['precio'], 2);

    /* Con cuánto pagó en efectivo, para calcular el vuelto. Opcional. */
    if ($metodo !== 'efectivo' || $recibido === null || $recibido === '') {
        $recibido = null;
    } else {
        $recibido = leerMonto($recibido);
        if ($recibido === null) {
            $errores['recibido'] = 'Debe ser un monto con hasta 2 decimales';
        } elseif ((float) $recibido < $monto) {
            $errores['recibido'] = 'Es menos de ' . dinero($monto);
        }
    }
    if ($errores) {
        responder(['error' => 'Datos inválidos', 'campos' => $errores], 422);
    }

    $db->prepare('INSERT INTO talento_ventas (talento_id, platos, metodo, monto, recibido, usuario_id) VALUES (?, ?, ?, ?, ?, ?)')
       ->execute([$talento['id'], $platos, $metodo, number_format($monto, 2, '.', ''), $recibido, $usuario['id']]);
    $id = (int) $db->lastInsertId();

    responder([
        'ok'      => true,
        'venta'   => leerVentas($db, $talento['id'], $id)[0],
        'talento' => leerTalentos($db, $usuario, 't.id = ?', [$talento['id']])[0],
    ], 201);
}

/* Un cobro mal anotado (se tocó dos veces, no pagó al final) */
function anular(PDO $db, array $usuario, array $pedido): never
{
    $id = filter_var($pedido['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        responder(['error' => 'Falta un "id" válido'], 400);
    }
    $consulta = $db->prepare('SELECT talento_id FROM talento_ventas WHERE id = ?');
    $consulta->execute([$id]);
    $talentoId = $consulta->fetchColumn() ?: responder(['error' => 'No existe ese cobro'], 404);

    talentoParaCobrar($db, $usuario, $talentoId);
    $db->prepare('DELETE FROM talento_ventas WHERE id = ?')->execute([$id]);

    responder([
        'ok'      => true,
        'id'      => $id,
        'talento' => leerTalentos($db, $usuario, 't.id = ?', [(int) $talentoId])[0],
    ]);
}

/* El talento, si existe y la cuenta puede cobrar en él */
function talentoParaCobrar(PDO $db, array $usuario, mixed $id): array
{
    $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        responder(['error' => 'Falta un "talento_id" válido'], 400);
    }
    $talento = leerTalentos($db, $usuario, 't.id = ?', [$id])[0] ?? responder(['error' => 'No existe ese talento'], 404);
    if (!$talento['puede_cobrar']) {
        responder(['error' => 'Solo quien cobra el talento, la tesorería o un administrador'], 403);
    }
    return $talento;
}

/* --- Lectura ----------------------------------------------------- */

/* Talentos con sus cocineros y su cobrador. Los totales solo le llegan
   a quien puede cobrar: el resto ve qué se vende, a cuánto y quién
   cocina. $donde filtra sobre t (talentos). */
function leerTalentos(PDO $db, array $usuario, string $donde, array $valores, string $orden = 'ASC', ?int $limite = null): array
{
    $consulta = $db->prepare(
        "SELECT t.id, t.fecha, t.platillo, t.descripcion, t.precio, t.cobrador_id,
                COALESCE(NULLIF(u.nombre, ''), u.usuario) AS cobrador_nombre,
                COUNT(v.id) AS ventas,
                COALESCE(SUM(v.platos), 0) AS platos,
                COALESCE(SUM(CASE WHEN v.metodo = 'efectivo' THEN v.monto END), 0) AS efectivo,
                COALESCE(SUM(CASE WHEN v.metodo = 'traspaso' THEN v.monto END), 0) AS traspaso
           FROM talentos t
           LEFT JOIN usuarios u ON u.id = t.cobrador_id
           LEFT JOIN talento_ventas v ON v.talento_id = t.id
          WHERE $donde
          GROUP BY t.id
          ORDER BY t.fecha $orden"
        . ($limite ? ' LIMIT ' . (int) $limite : '')
    );
    $consulta->execute($valores);
    $filas = $consulta->fetchAll();
    if (!$filas) {
        return [];
    }

    $ids    = array_column($filas, 'id');
    $marcas = implode(', ', array_fill(0, count($ids), '?'));
    $consulta = $db->prepare(
        "SELECT c.talento_id, c.representante_id,
                COALESCE(r.nombre, c.nombre) AS nombre, COALESCE(r.foto, '') AS foto
           FROM talento_cocineros c
           LEFT JOIN representantes r ON r.id = c.representante_id
          WHERE c.talento_id IN ($marcas)
          ORDER BY c.talento_id, c.orden, c.id"
    );
    $consulta->execute($ids);
    $cocineros = [];
    foreach ($consulta->fetchAll() as $fila) {
        $cocineros[$fila['talento_id']][] = [
            'representante_id' => $fila['representante_id'] === null ? null : (int) $fila['representante_id'],
            'nombre'           => $fila['nombre'],
            'foto'             => $fila['foto'],
        ];
    }

    $tesoreria = in_array($usuario['rol'], ROLES_COBRO_TALENTO, true);

    return array_map(function (array $t) use ($cocineros, $tesoreria, $usuario) {
        $puedeCobrar = $tesoreria || (int) $t['cobrador_id'] === (int) $usuario['id'];
        $efectivo = round((float) $t['efectivo'], 2);
        $traspaso = round((float) $t['traspaso'], 2);
        return [
            'id'           => (int) $t['id'],
            'fecha'        => $t['fecha'],
            'platillo'     => $t['platillo'],
            'descripcion'  => $t['descripcion'],
            'precio'       => (float) $t['precio'],
            'cocineros'    => $cocineros[$t['id']] ?? [],
            'cobrador'     => $t['cobrador_id'] === null ? null
                : ['id' => (int) $t['cobrador_id'], 'nombre' => $t['cobrador_nombre']],
            'puede_cobrar' => $puedeCobrar,
            'totales'      => $puedeCobrar ? [
                'ventas'   => (int) $t['ventas'],
                'platos'   => (int) $t['platos'],
                'efectivo' => $efectivo,
                'traspaso' => $traspaso,
                'total'    => round($efectivo + $traspaso, 2),
            ] : null,
        ];
    }, $filas);
}

/* Los cobros de un talento, del más nuevo al más viejo */
function leerVentas(PDO $db, int $talentoId, ?int $soloId = null): array
{
    $consulta = $db->prepare(
        "SELECT v.id, v.platos, v.metodo, v.monto, v.recibido, v.creado,
                COALESCE(NULLIF(u.nombre, ''), u.usuario, '') AS cobrado_por
           FROM talento_ventas v
           LEFT JOIN usuarios u ON u.id = v.usuario_id
          WHERE v.talento_id = ?" . ($soloId ? ' AND v.id = ?' : '') . '
          ORDER BY v.creado DESC, v.id DESC'
    );
    $consulta->execute($soloId ? [$talentoId, $soloId] : [$talentoId]);

    return array_map(function (array $v) {
        $monto    = (float) $v['monto'];
        $recibido = $v['recibido'] === null ? null : (float) $v['recibido'];
        return [
            'id'          => (int) $v['id'],
            'platos'      => (int) $v['platos'],
            'metodo'      => $v['metodo'],
            'monto'       => $monto,
            'recibido'    => $recibido,
            'vuelto'      => $recibido === null ? null : round($recibido - $monto, 2),
            'cobrado_por' => $v['cobrado_por'],
            'creado'      => $v['creado'],
        ];
    }, $consulta->fetchAll());
}
