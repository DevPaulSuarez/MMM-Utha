<?php
/* ============================================================
   MMM · Contenido del sitio (público)
   GET api/datos.php

   Todo el contenido con el "id" de cada registro, para que la
   aplicación pueda editarlo o borrarlo. De los cultos extra y las
   actividades llega solo lo de hoy en adelante; de los programas, lo de hoy en
   adelante más el último que ya pasó de cada culto fijo.
   ============================================================ */

require __DIR__ . '/conexion.php';

exigirMetodo('GET');

/* Es contenido público: cualquiera puede leerlo, y el navegador
   debe revalidarlo en cada visita para ver los cambios enseguida */
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

$SECCIONES = require __DIR__ . '/secciones.php';

/* Según la zona horaria de config.php */
$hoy = date('Y-m-d');

function listar(PDO $db, array $seccion, string $donde = '', array $valores = []): array
{
    $consulta = $db->prepare("SELECT * FROM `{$seccion['tabla']}` $donde ORDER BY {$seccion['orden']}");
    $consulta->execute($valores);
    return array_map('acortarHoras', $consulta->fetchAll());
}

$ubicacion = $db->query('SELECT direccion, coordenadas FROM ubicacion WHERE id = 1')->fetch()
    ?: ['direccion' => '', 'coordenadas' => ''];

/* Los perfiles que el pastor todavía no aprobó no salen en la web. La
   app de un administrador sí los recibe, con la cuenta de cada uno. */
$usuario = usuarioDelToken($db);
header('Vary: Authorization');

if (($usuario['rol'] ?? '') === 'admin') {
    $representantes = $db->query(
        'SELECT r.id, r.nombre, r.cargo, r.descripcion, r.foto, r.orden, r.visible, u.usuario
           FROM representantes r
           LEFT JOIN usuarios u ON u.id = r.usuario_id
          ORDER BY r.orden, r.id'
    )->fetchAll();
} else {
    $representantes = $db->query(
        'SELECT id, nombre, cargo, descripcion, foto, orden
           FROM representantes
          WHERE visible = 1
          ORDER BY orden, id'
    )->fetchAll();
}

/* El último programa pasado de cada culto fijo: la web no lo muestra
   (solo pinta fechas de hoy en adelante) y la app lo usa de base */
$pasados = idsUltimosProgramasPasados($db, $hoy);
$filtroProgramas = 'COALESCE(ce.fecha, p.fecha) >= ?';
if ($pasados) {
    $filtroProgramas .= ' OR p.id IN (' . implode(', ', array_fill(0, count($pasados), '?')) . ')';
}

responder([
    'ubicacion'      => $ubicacion,
    'banners'        => listar($db, $SECCIONES['banners']),
    'lemas'          => listar($db, $SECCIONES['lemas']),
    'representantes' => $representantes,
    'cultos'         => listar($db, $SECCIONES['cultos']),
    'cultos_extra'   => listar($db, $SECCIONES['cultos_extra'], 'WHERE fecha >= ?', [$hoy]),
    'programas'      => leerProgramas($db, $filtroProgramas, [$hoy, ...$pasados]),
    'actividades'    => listar($db, $SECCIONES['actividades'], 'WHERE fecha >= ?', [$hoy]),
    'noticias'       => listar($db, $SECCIONES['noticias']),
]);
