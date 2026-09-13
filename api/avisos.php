<?php
/* ============================================================
   MMM · Avisos dentro de la app (requiere token)
   GET  api/avisos.php                        los últimos 50
   POST api/avisos.php  { "accion": "leidos" }  marca todos como leídos

   Los de más de 60 días se borran solos.
   ============================================================ */

require __DIR__ . '/conexion.php';

$usuario = exigirToken($db);

$db->prepare('DELETE FROM avisos WHERE usuario_id = ? AND creado < NOW() - INTERVAL 60 DAY')
   ->execute([$usuario['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((leerJson()['accion'] ?? '') !== 'leidos') {
        responder(['error' => 'Acción desconocida: usar "leidos"'], 400);
    }
    $db->prepare('UPDATE avisos SET leido = 1 WHERE usuario_id = ? AND leido = 0')->execute([$usuario['id']]);
    responder(['ok' => true]);
}

exigirMetodo('GET');

$consulta = $db->prepare(
    'SELECT id, tipo, titulo, mensaje, programa_id, actividad_id, leido, creado
       FROM avisos
      WHERE usuario_id = ?
      ORDER BY id DESC
      LIMIT 50'
);
$consulta->execute([$usuario['id']]);

$avisos = array_map(function (array $aviso) {
    $aviso['leido'] = (bool) $aviso['leido'];
    return $aviso;
}, $consulta->fetchAll());

responder(['avisos' => $avisos]);
