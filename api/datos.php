<?php
/* ============================================================
   MMM · Contenido del sitio (público)
   GET api/datos.php

   Devuelve lo mismo que tenía js/datos.json, más el "id" de cada
   registro para que la aplicación pueda editarlo o borrarlo.
   ============================================================ */

require __DIR__ . '/conexion.php';

exigirMetodo('GET');

/* Es contenido público: cualquiera puede leerlo, y el navegador
   debe revalidarlo en cada visita para ver los cambios enseguida */
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

$SECCIONES = require __DIR__ . '/secciones.php';

function listar(PDO $db, array $seccion): array
{
    return $db->query("SELECT * FROM `{$seccion['tabla']}` ORDER BY {$seccion['orden']}")->fetchAll();
}

$ubicacion = $db->query('SELECT direccion, coordenadas FROM ubicacion WHERE id = 1')->fetch()
    ?: ['direccion' => '', 'coordenadas' => ''];

/* La web espera los siete días, de lunes a domingo, aunque no
   tengan eventos */
$agenda = [];
foreach (['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'] as $i => $nombre) {
    $agenda[$i + 1] = ['dia' => $nombre, 'eventos' => []];
}
foreach (listar($db, $SECCIONES['agenda']) as $evento) {
    $dia = (int) $evento['dia'];
    if (isset($agenda[$dia])) {
        $agenda[$dia]['eventos'][] = $evento;
    }
}

responder([
    'ubicacion'      => $ubicacion,
    'banners'        => listar($db, $SECCIONES['banners']),
    'lemas'          => listar($db, $SECCIONES['lemas']),
    'representantes' => listar($db, $SECCIONES['representantes']),
    'horarios'       => listar($db, $SECCIONES['horarios']),
    'agenda'         => array_values($agenda),
    'noticias'       => listar($db, $SECCIONES['noticias']),
]);
