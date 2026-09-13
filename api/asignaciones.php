<?php
/* ============================================================
   MMM · Mis participaciones (requiere token)
   GET  api/asignaciones.php
        Los cultos de hoy en adelante en los que me toca algo.
   POST api/asignaciones.php
        { "id": 12, "presentacion": "musica", "detalle": "Cuán grande es Él" }

   Solo quien está en "participaciones" elige qué presenta: musica,
   lectura o testimonio, con un detalle opcional (el canto, el
   pasaje). El pastor recibe un aviso con la respuesta.
   ============================================================ */

require __DIR__ . '/conexion.php';

$usuario = exigirToken($db);

const PRESENTACIONES = ['musica' => 'Música', 'lectura' => 'Lectura', 'testimonio' => 'Testimonio'];

$hoy = date('Y-m-d');

function leerAsignaciones(PDO $db, int $usuarioId, string $hoy, ?int $id = null): array
{
    $consulta = $db->prepare(
        'SELECT pa.id, pa.rol, pa.presentacion, pa.detalle, p.id AS programa_id,
                COALESCE(ce.fecha, p.fecha) AS fecha,
                COALESCE(c.nombre, ce.nombre) AS culto,
                COALESCE(c.hora_inicio, ce.hora_inicio) AS hora_inicio,
                COALESCE(c.hora_fin, ce.hora_fin) AS hora_fin,
                COALESCE(c.lugar, ce.lugar) AS lugar
           FROM participaciones pa
           JOIN programas p ON p.id = pa.programa_id
           JOIN representantes r ON r.id = pa.representante_id
           LEFT JOIN cultos c ON c.id = p.culto_id
           LEFT JOIN cultos_extra ce ON ce.id = p.culto_extra_id
          WHERE r.usuario_id = ?
            AND COALESCE(ce.fecha, p.fecha) >= ?'
        . ($id !== null ? ' AND pa.id = ?' : '')
        . ' ORDER BY COALESCE(ce.fecha, p.fecha), COALESCE(c.hora_inicio, ce.hora_inicio), pa.orden'
    );
    $consulta->execute($id !== null ? [$usuarioId, $hoy, $id] : [$usuarioId, $hoy]);
    return array_map('acortarHoras', $consulta->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedido = leerJson();

    $id = filter_var($pedido['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        responder(['error' => 'Falta un "id" válido'], 400);
    }

    $asignacion = leerAsignaciones($db, (int) $usuario['id'], $hoy, $id)[0] ?? null;
    if (!$asignacion) {
        responder(['error' => 'No existe esa participación o ya pasó'], 404);
    }
    if ($asignacion['rol'] !== 'participacion') {
        responder(['error' => 'Solo en participaciones se elige qué presentar'], 422);
    }

    $presentacion = (string) ($pedido['presentacion'] ?? '');
    $detalle      = trim((string) ($pedido['detalle'] ?? ''));

    $errores = [];
    if (!isset(PRESENTACIONES[$presentacion])) {
        $errores['presentacion'] = 'Elige música, lectura o testimonio';
    }
    if (mb_strlen($detalle) > 200) {
        $errores['detalle'] = 'Máximo 200 caracteres';
    }
    if ($errores) {
        responder(['error' => 'Datos inválidos', 'campos' => $errores], 422);
    }

    $db->prepare('UPDATE participaciones SET presentacion = ?, detalle = ? WHERE id = ?')
       ->execute([$presentacion, $detalle, $id]);

    crearAviso(
        $db,
        idsDeAdmins($db),
        'respuesta',
        "{$usuario['nombre']}: " . PRESENTACIONES[$presentacion] . ($detalle !== '' ? " · $detalle" : ''),
        $asignacion['culto'] . ', ' . fechaLegible($asignacion['fecha']),
        (int) $asignacion['programa_id']
    );

    responder(['ok' => true, 'asignacion' => leerAsignaciones($db, (int) $usuario['id'], $hoy, $id)[0]]);
}

exigirMetodo('GET');

responder(['asignaciones' => leerAsignaciones($db, (int) $usuario['id'], $hoy)]);
