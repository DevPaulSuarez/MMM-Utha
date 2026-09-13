<?php
/* ============================================================
   MMM · Programa de un culto (requiere token)
   POST api/programa.php

   Guardar: reemplaza el programa completo de ese culto y fecha.
   { "accion": "guardar", "culto_id": 2, "fecha": "2026-09-19",
     "participantes": [
       { "rol": "presentador",   "representante_id": 1 },
       { "rol": "participacion", "representante_id": 2 },
       { "rol": "participacion", "nombre": "Iglesia de Ogden" },
       { "rol": "predicacion",   "representante_id": 4 },
       { "rol": "alabanza",      "representante_id": 5 }
     ] }

   Borrar:
   { "accion": "borrar", "culto_id": 2, "fecha": "2026-09-19" }

   Para un culto extra se manda "culto_extra_id" en lugar de
   "culto_id" y "fecha": la fecha es la del culto extra.

   El orden de "participantes" es el orden en que salen en la web.
   Una lista vacía borra el programa.

   Cada vez que se guarda o borra se eliminan los programas de
   fechas pasadas, salvo el último de cada culto fijo: la app lo
   copia para armar el siguiente.
   ============================================================ */

require __DIR__ . '/conexion.php';

exigirMetodo('POST');
exigirAdmin($db);

/* Rol => cómo se nombra en los mensajes. Presentador y predicación
   son de una sola persona. */
const ROLES        = ['presentador' => 'presentador', 'participacion' => 'participaciones',
                      'predicacion' => 'predicación', 'alabanza' => 'alabanza'];
const ROLES_UNICOS = ['presentador', 'predicacion'];

$pedido = leerJson();
$accion = (string) ($pedido['accion'] ?? '');

if ($accion !== 'guardar' && $accion !== 'borrar') {
    responder(['error' => 'Acción desconocida: usar "guardar" o "borrar"'], 400);
}

function idOpcional(mixed $valor, string $campo): ?int
{
    if ($valor === null) {
        return null;
    }
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        responder(['error' => "\"$campo\" no es un id válido"], 400);
    }
    return $id;
}

/* Los programas que ya pasaron no se muestran en ningún lado: se
   borran, salvo el último de cada culto fijo */
function limpiarPasados(PDO $db): void
{
    $hoy = date('Y-m-d');
    $conservar = idsUltimosProgramasPasados($db, $hoy);

    $consulta = $db->prepare(
        'SELECT p.id
           FROM programas p
           LEFT JOIN cultos_extra ce ON ce.id = p.culto_extra_id
          WHERE COALESCE(ce.fecha, p.fecha) < ?'
    );
    $consulta->execute([$hoy]);

    $borrar = array_values(array_diff(
        array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN)),
        $conservar
    ));
    if ($borrar) {
        $marcas = implode(', ', array_fill(0, count($borrar), '?'));
        $db->prepare("DELETE FROM programas WHERE id IN ($marcas)")->execute($borrar);
    }
}

/* Aviso a quienes entran nuevos al programa y tienen cuenta en la app */
function avisarElegidos(PDO $db, array $nuevos, array $culto, string $fecha, int $programaId): void
{
    $frases = [
        'presentador'   => ['Serás el presentador', ''],
        'participacion' => ['Te eligieron para participar', 'Indica si harás música, lectura o testimonio.'],
        'predicacion'   => ['Te toca predicar', ''],
        'alabanza'      => ['Estarás en la alabanza', ''],
    ];

    $cuando = ucfirst(fechaLegible($fecha)) . ' · ' . horaLegible(substr((string) $culto['hora_inicio'], 0, 5));
    $cuenta = $db->prepare('SELECT usuario_id FROM representantes WHERE id = ? AND usuario_id IS NOT NULL');

    foreach ($nuevos as [$rol, $representanteId]) {
        $cuenta->execute([$representanteId]);
        $usuarioId = $cuenta->fetchColumn();
        if ($usuarioId === false) {
            continue;
        }
        [$frase, $pedido] = $frases[$rol];
        /* La hora ya termina en "p. m.": el pedido va a continuación */
        crearAviso($db, [(int) $usuarioId], 'programa', "$frase · {$culto['nombre']}", trim("$cuando $pedido"), $programaId);
    }
}

/* --- De qué culto es ------------------------------------------- */
$cultoId = idOpcional($pedido['culto_id'] ?? null, 'culto_id');
$extraId = idOpcional($pedido['culto_extra_id'] ?? null, 'culto_extra_id');

if (($cultoId === null) === ($extraId === null)) {
    responder(['error' => 'Mandar "culto_id" con "fecha", o "culto_extra_id"'], 400);
}

if ($cultoId !== null) {
    $fecha = (string) ($pedido['fecha'] ?? '');
    $dia   = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$dia || $dia->format('Y-m-d') !== $fecha) {
        responder(['error' => '"fecha" debe tener formato AAAA-MM-DD'], 400);
    }

    $consulta = $db->prepare('SELECT dia, nombre, hora_inicio FROM cultos WHERE id = ?');
    $consulta->execute([$cultoId]);
    $culto = $consulta->fetch();
    if (!$culto) {
        responder(['error' => 'No existe el culto'], 404);
    }
    /* format('N'): 1 = lunes … 7 = domingo, igual que la tabla */
    if ((int) $dia->format('N') !== (int) $culto['dia']) {
        responder(['error' => 'Ese culto no se hace el día de la semana de esa fecha'], 422);
    }

    $buscar = $db->prepare('SELECT id FROM programas WHERE culto_id = ? AND fecha = ?');
    $buscar->execute([$cultoId, $fecha]);
} else {
    $consulta = $db->prepare('SELECT fecha, nombre, hora_inicio FROM cultos_extra WHERE id = ?');
    $consulta->execute([$extraId]);
    $culto = $consulta->fetch();
    if (!$culto) {
        responder(['error' => 'No existe el culto extra'], 404);
    }
    $fecha = $culto['fecha'];

    $buscar = $db->prepare('SELECT id FROM programas WHERE culto_extra_id = ?');
    $buscar->execute([$extraId]);
}

$programaId = $buscar->fetchColumn() ?: null;

/* --- Participantes --------------------------------------------- */
$lista = $accion === 'guardar' ? ($pedido['participantes'] ?? null) : [];

if (!is_array($lista) || !array_is_list($lista)) {
    responder(['error' => 'Falta la lista "participantes"'], 400);
}

if (!$lista) {
    if ($programaId) {
        $db->prepare('DELETE FROM programas WHERE id = ?')->execute([$programaId]);
    }
    limpiarPasados($db);
    responder(['ok' => true, 'programa' => null]);
}

$representantes = array_map('intval', $db->query('SELECT id FROM representantes')->fetchAll(PDO::FETCH_COLUMN));

$errores = [];
$limpios = [];
$porRol  = [];

foreach ($lista as $i => $participante) {
    $n = $i + 1;
    $rol = is_array($participante) ? ($participante['rol'] ?? '') : '';

    if (!is_string($rol) || !isset(ROLES[$rol])) {
        $errores[] = "Participante $n: rol inválido";
        continue;
    }

    $representanteId = $participante['representante_id'] ?? null;
    $nombre = trim((string) ($participante['nombre'] ?? ''));

    if ($representanteId !== null) {
        $representanteId = filter_var($representanteId, FILTER_VALIDATE_INT);
        if ($representanteId === false || !in_array($representanteId, $representantes, true)) {
            $errores[] = "Participante $n: el representante no existe";
            continue;
        }
        $nombre = '';
    } elseif ($nombre === '') {
        $errores[] = "Participante $n: falta el representante o el nombre de la visita";
        continue;
    } elseif (mb_strlen($nombre) > 150) {
        $errores[] = "Participante $n: el nombre tiene más de 150 caracteres";
        continue;
    }

    $porRol[$rol] = ($porRol[$rol] ?? 0) + 1;
    $limpios[] = [$rol, $representanteId, $nombre];
}

foreach (ROLES_UNICOS as $rol) {
    if (($porRol[$rol] ?? 0) > 1) {
        $errores[] = 'Solo puede haber una persona en ' . ROLES[$rol];
    }
}

if ($errores) {
    responder(['error' => $errores[0], 'detalle' => $errores], 422);
}

/* --- Guardar --------------------------------------------------- */

/* Lo que ya había: se conserva lo que cada participante eligió
   presentar, y solo se avisa a quienes entran nuevos */
$anteriores = [];
if ($programaId) {
    $consulta = $db->prepare(
        'SELECT rol, representante_id, presentacion, detalle
           FROM participaciones
          WHERE programa_id = ? AND representante_id IS NOT NULL'
    );
    $consulta->execute([$programaId]);
    foreach ($consulta->fetchAll() as $fila) {
        $anteriores[$fila['rol'] . '|' . $fila['representante_id']] = $fila;
    }
}
$nuevos = [];

$db->beginTransaction();
try {
    if ($programaId) {
        /* La fecha se actualiza por si el culto extra cambió de día */
        $db->prepare('UPDATE programas SET fecha = ? WHERE id = ?')->execute([$fecha, $programaId]);
        $db->prepare('DELETE FROM participaciones WHERE programa_id = ?')->execute([$programaId]);
    } else {
        $db->prepare('INSERT INTO programas (culto_id, culto_extra_id, fecha) VALUES (?, ?, ?)')
           ->execute([$cultoId, $extraId, $fecha]);
        $programaId = (int) $db->lastInsertId();
    }

    $insertar = $db->prepare(
        'INSERT INTO participaciones (programa_id, rol, representante_id, nombre, presentacion, detalle, orden)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($limpios as $i => [$rol, $representanteId, $nombre]) {
        $previa = $representanteId ? ($anteriores["$rol|$representanteId"] ?? null) : null;
        $insertar->execute([
            $programaId, $rol, $representanteId, $nombre,
            $previa['presentacion'] ?? '', $previa['detalle'] ?? '', $i + 1,
        ]);
        if ($representanteId && !$previa) {
            $nuevos[] = [$rol, $representanteId];
        }
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

/* Se lee antes de limpiar: si se guardó una fecha pasada que no es la
   última de su culto, la limpieza la borra */
$programa = leerProgramas($db, 'p.id = ?', [$programaId])[0];

if ($nuevos && $fecha >= date('Y-m-d')) {
    avisarElegidos($db, $nuevos, $culto, $fecha, $programaId);
}
limpiarPasados($db);

responder(['ok' => true, 'programa' => $programa]);
