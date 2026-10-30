<?php
/* ============================================================
   MMM · Lo que se reunió para una meta (requiere token de
   tesorero, pastor o maestro)
   POST api/aportes.php
        { "accion": "crear",  "datos": { "meta_id": 3, "fecha": "2026-10-05",
                                         "diezmos": "450", "ofrendas": "120.50", "nota": "" } }
        { "accion": "editar", "id": 8, "datos": { ... } }
        { "accion": "borrar", "id": 8 }

   El tesorero cuenta el dinero de cada día y lo anota para la meta
   que corresponde. Cuando una meta llega a su monto, todos reciben
   un aviso.
   ============================================================ */

require __DIR__ . '/conexion.php';

exigirMetodo('POST');
$usuario = exigirToken($db);
if (!in_array($usuario['rol'], ROLES_TESORERIA, true)) {
    responder(['error' => 'Solo el tesorero o el pastor registran las ofrendas'], 403);
}

$pedido = leerJson();
$accion = (string) ($pedido['accion'] ?? '');

if ($accion === 'editar' || $accion === 'borrar') {
    $id = filter_var($pedido['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        responder(['error' => 'Falta un "id" válido'], 400);
    }
    $consulta = $db->prepare('SELECT meta_id FROM aportes WHERE id = ?');
    $consulta->execute([$id]);
    $metaAnterior = (int) ($consulta->fetchColumn() ?: responder(['error' => 'No existe ese registro'], 404));
} elseif ($accion !== 'crear') {
    responder(['error' => 'Acción desconocida: usar "crear", "editar" o "borrar"'], 400);
}

if ($accion === 'borrar') {
    $db->prepare('DELETE FROM aportes WHERE id = ?')->execute([$id]);
    responder(['ok' => true, 'id' => $id, 'meta' => leerMetas($db, 'm.id = ?', [$metaAnterior])[0] ?? null]);
}

$datos = validarAporte($db, $pedido['datos'] ?? null, $accion === 'crear');
$antes = leerMetas($db, 'm.id = ?', [$datos['meta_id']])[0];

if ($accion === 'crear') {
    $db->prepare('INSERT INTO aportes (meta_id, fecha, diezmos, ofrendas, nota, usuario_id) VALUES (?, ?, ?, ?, ?, ?)')
       ->execute([...array_values($datos), $usuario['id']]);
    $id = (int) $db->lastInsertId();
} else {
    $db->prepare('UPDATE aportes SET meta_id = ?, fecha = ?, diezmos = ?, ofrendas = ?, nota = ? WHERE id = ?')
       ->execute([...array_values($datos), $id]);
}

$meta = leerMetas($db, 'm.id = ?', [$datos['meta_id']])[0];

/* Recién se completó lo de este mes (o de la compra): ¡aviso a todos! */
if ($meta['activa'] && $antes['reunido'] < $meta['monto'] && $meta['reunido'] >= $meta['monto']) {
    crearAviso($db, idsDeTodos($db), 'meta',
        '¡Meta lograda: ' . $meta['titulo'] . '!',
        'Se reunieron ' . dinero($meta['monto'])
            . ($meta['tipo'] === 'mensual' ? ' para este mes' : '') . '. Gracias por dar.');
}

$consulta = $db->prepare('SELECT id, meta_id, fecha, diezmos, ofrendas, nota FROM aportes WHERE id = ?');
$consulta->execute([$id]);
$aporte = $consulta->fetch();

responder([
    'ok'     => true,
    'aporte' => [
        'id'       => (int) $aporte['id'],
        'meta_id'  => (int) $aporte['meta_id'],
        'fecha'    => $aporte['fecha'],
        'diezmos'  => (float) $aporte['diezmos'],
        'ofrendas' => (float) $aporte['ofrendas'],
        'nota'     => $aporte['nota'],
    ],
    'meta' => $meta,
], $accion === 'crear' ? 201 : 200);

/* --- Utilidades ------------------------------------------------ */

/* Los campos ya limpios, en el orden de las columnas. Se mandan todos
   cada vez. Lo nuevo va solo a metas que se ven; al corregir uno viejo
   se acepta también una meta oculta. */
function validarAporte(PDO $db, mixed $datos, bool $esNuevo): array
{
    if (!is_array($datos)) {
        responder(['error' => 'Falta el objeto "datos"'], 400);
    }

    $metaId   = filter_var($datos['meta_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $fecha    = trim((string) ($datos['fecha'] ?? ''));
    $diezmos  = leerMonto($datos['diezmos'] ?? '0');
    $ofrendas = leerMonto($datos['ofrendas'] ?? '0');
    $nota     = trim((string) ($datos['nota'] ?? ''));

    $errores = [];
    if ($metaId === false) {
        $errores['meta_id'] = 'Elige la meta';
    } else {
        $consulta = $db->prepare('SELECT activa FROM metas WHERE id = ?');
        $consulta->execute([$metaId]);
        $activa = $consulta->fetchColumn();
        if ($activa === false || ($esNuevo && !$activa)) {
            $errores['meta_id'] = 'Esa meta no existe o ya no está visible';
        }
    }

    $leida = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$leida || $leida->format('Y-m-d') !== $fecha) {
        $errores['fecha'] = 'Debe tener formato AAAA-MM-DD';
    } elseif ($fecha > date('Y-m-d')) {
        $errores['fecha'] = 'No puede ser una fecha que todavía no llegó';
    }

    if ($diezmos === null) {
        $errores['diezmos'] = 'Debe ser un monto con hasta 2 decimales';
    }
    if ($ofrendas === null) {
        $errores['ofrendas'] = 'Debe ser un monto con hasta 2 decimales';
    }
    if ($diezmos !== null && $ofrendas !== null && (float) $diezmos + (float) $ofrendas <= 0) {
        $errores['ofrendas'] = 'Anota cuánto se reunió';
    }
    if (mb_strlen($nota) > 200) {
        $errores['nota'] = 'Máximo 200 caracteres';
    }
    if ($errores) {
        responder(['error' => 'Datos inválidos', 'campos' => $errores], 422);
    }

    return [
        'meta_id'  => $metaId,
        'fecha'    => $fecha,
        'diezmos'  => $diezmos,
        'ofrendas' => $ofrendas,
        'nota'     => $nota,
    ];
}
