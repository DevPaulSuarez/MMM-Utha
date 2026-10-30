<?php
/* ============================================================
   MMM · Metas de la iglesia (requiere token)
   GET  api/metas.php                      todas, con lo reunido
   GET  api/metas.php?id=3                 una, con sus aportes
   GET  api/metas.php?id=3&mes=2026-09     una mensual, en otro mes
   POST api/metas.php  (solo maestro y pastor)
        { "accion": "crear",  "datos": { ... } }
        { "accion": "editar", "id": 3, "datos": { ... } }
        { "accion": "borrar", "id": 3 }

   Una meta es lo que hace falta reunir con los diezmos y ofrendas:
   un gasto fijo de cada mes (el alquiler) o una compra (una cámara).
   Todos la ven; lo reunido lo registra el tesorero (aportes.php).
   ============================================================ */

require __DIR__ . '/conexion.php';

$usuario     = exigirToken($db);
$editaMetas  = in_array($usuario['rol'], ROLES_METAS, true);
$esTesoreria = in_array($usuario['rol'], ROLES_TESORERIA, true);

/* Las que el pastor ocultó solo las ve él */
$soloActivas = $editaMetas ? '1' : 'm.activa = 1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$editaMetas) {
        responder(['error' => 'Solo el pastor puede cambiar las metas'], 403);
    }

    $pedido = leerJson();
    $accion = (string) ($pedido['accion'] ?? '');

    if ($accion === 'editar' || $accion === 'borrar') {
        $id = filter_var($pedido['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            responder(['error' => 'Falta un "id" válido'], 400);
        }
        $anterior = leerMetas($db, 'm.id = ?', [$id])[0] ?? responder(['error' => 'No existe la meta'], 404);
    } elseif ($accion !== 'crear') {
        responder(['error' => 'Acción desconocida: usar "crear", "editar" o "borrar"'], 400);
    }

    if ($accion === 'borrar') {
        $db->prepare('DELETE FROM metas WHERE id = ?')->execute([$id]);
        responder(['ok' => true, 'id' => $id]);
    }

    $datos = validarMeta($pedido['datos'] ?? null);

    if ($accion === 'crear') {
        $datos['orden'] ??= (int) $db->query('SELECT COALESCE(MAX(orden), 0) + 1 FROM metas')->fetchColumn();
        $columnas = array_keys($datos);
        $db->prepare('INSERT INTO metas (' . implode(', ', $columnas) . ') VALUES ('
                     . implode(', ', array_fill(0, count($columnas), '?')) . ')')
           ->execute(array_values($datos));
        $id = (int) $db->lastInsertId();
    } else {
        $asignaciones = implode(', ', array_map(fn($c) => "$c = ?", array_keys($datos)));
        $db->prepare("UPDATE metas SET $asignaciones WHERE id = ?")
           ->execute([...array_values($datos), $id]);
    }

    $meta = leerMetas($db, 'm.id = ?', [$id])[0];

    /* Una meta que empieza a verse: aviso a todos, menos a quien la puso */
    if ($meta['activa'] && ($accion === 'crear' || !$anterior['activa'])) {
        crearAviso($db, idsDeTodos($db, (int) $usuario['id']), 'meta',
            'Nueva meta: ' . $meta['titulo'],
            'Hay que reunir ' . dinero($meta['monto'])
                . ($meta['tipo'] === 'mensual' ? ' cada mes' : '')
                . ($meta['fecha_limite'] ? ' hasta el ' . fechaLegible($meta['fecha_limite']) : '') . '.');
    }

    responder(['ok' => true, 'meta' => $meta], $accion === 'crear' ? 201 : 200);
}

exigirMetodo('GET');

if (!isset($_GET['id'])) {
    responder(['metas' => leerMetas($db, $soloActivas)]);
}

$id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    responder(['error' => 'El "id" no es válido'], 400);
}

$mes = (string) ($_GET['mes'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
    responder(['error' => 'El mes debe tener formato AAAA-MM'], 400);
}

$meta = leerMetas($db, "m.id = ? AND $soloActivas", [$id], $mes)[0]
    ?? responder(['error' => 'No existe la meta'], 404);

/* Los aportes del período que se está mirando, del más nuevo al más
   viejo. Quién los registró solo le importa a la tesorería. */
$consulta = $db->prepare(
    'SELECT a.id, a.meta_id, a.fecha, a.diezmos, a.ofrendas, a.nota, COALESCE(u.nombre, \'\') AS registrado_por
       FROM aportes a
       LEFT JOIN usuarios u ON u.id = a.usuario_id
      WHERE a.meta_id = ?' . ($meta['periodo'] ? ' AND a.fecha BETWEEN ? AND ?' : '') . '
      ORDER BY a.fecha DESC, a.id DESC'
);
$consulta->execute($meta['periodo']
    ? [$id, $meta['periodo']['desde'], $meta['periodo']['hasta']]
    : [$id]);

$aportes = array_map(function (array $aporte) use ($esTesoreria) {
    $aporte['id']       = (int) $aporte['id'];
    $aporte['meta_id']  = (int) $aporte['meta_id'];
    $aporte['diezmos']  = (float) $aporte['diezmos'];
    $aporte['ofrendas'] = (float) $aporte['ofrendas'];
    if (!$esTesoreria) {
        unset($aporte['registrado_por']);
    }
    return $aporte;
}, $consulta->fetchAll());

/* De una mensual, cuánto se reunió en cada uno de los últimos 12 meses */
$meses = [];
if ($meta['periodo']) {
    $consulta = $db->prepare(
        "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, SUM(diezmos + ofrendas) AS reunido
           FROM aportes
          WHERE meta_id = ?
          GROUP BY mes
          ORDER BY mes DESC
          LIMIT 12"
    );
    $consulta->execute([$id]);
    $meses = array_map(fn(array $fila) => [
        'mes'     => $fila['mes'],
        'reunido' => round((float) $fila['reunido'], 2),
    ], $consulta->fetchAll());
}

responder(['meta' => $meta, 'aportes' => $aportes, 'meses' => $meses]);

/* --- Utilidades ------------------------------------------------ */

/* Los campos de una meta, ya limpios. Se mandan todos cada vez. Si
   algo no cumple, corta con 422 y el detalle por campo. */
function validarMeta(mixed $datos): array
{
    if (!is_array($datos)) {
        responder(['error' => 'Falta el objeto "datos"'], 400);
    }

    $titulo      = trim((string) ($datos['titulo'] ?? ''));
    $descripcion = trim((string) ($datos['descripcion'] ?? ''));
    $tipo        = (string) ($datos['tipo'] ?? '');
    $monto       = leerMonto($datos['monto'] ?? null);
    $fecha       = trim((string) ($datos['fecha_limite'] ?? ''));

    $errores = [];
    if ($titulo === '') {
        $errores['titulo'] = 'Es obligatorio';
    } elseif (mb_strlen($titulo) > 150) {
        $errores['titulo'] = 'Máximo 150 caracteres';
    }
    if (mb_strlen($descripcion) > 2000) {
        $errores['descripcion'] = 'Máximo 2000 caracteres';
    }
    if (!in_array($tipo, ['mensual', 'unica'], true)) {
        $errores['tipo'] = 'Debe ser "mensual" o "unica"';
    }
    if ($monto === null || (float) $monto <= 0) {
        $errores['monto'] = 'Debe ser un monto mayor que cero, con hasta 2 decimales';
    }
    if ($fecha !== '') {
        $leida = DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$leida || $leida->format('Y-m-d') !== $fecha) {
            $errores['fecha_limite'] = 'Debe tener formato AAAA-MM-DD';
        }
    }
    if ($errores) {
        responder(['error' => 'Datos inválidos', 'campos' => $errores], 422);
    }

    $limpios = [
        'titulo'       => $titulo,
        'descripcion'  => $descripcion,
        'tipo'         => $tipo,
        'monto'        => $monto,
        /* Una mensual vence cada fin de mes: no lleva fecha propia */
        'fecha_limite' => $tipo === 'unica' && $fecha !== '' ? $fecha : null,
        'activa'       => filter_var($datos['activa'] ?? true, FILTER_VALIDATE_BOOL) ? 1 : 0,
    ];
    if (array_key_exists('orden', $datos)) {
        $limpios['orden'] = (int) $datos['orden'];
    }
    return $limpios;
}
