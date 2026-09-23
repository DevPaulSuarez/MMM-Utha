<?php
/* ============================================================
   MMM · Versión mínima de la aplicación (público)
   GET api/version.php

   La app lo consulta al abrirse y cada vez que se vuelve a ella.
   Si la versión instalada es menor que "minima", se queda en la
   pantalla "Actualiza la aplicación" con el enlace para bajarla.

   Qué versión se exige y desde dónde se baja se cambia en
   api/version-app.php.
   ============================================================ */

require __DIR__ . '/conexion.php';

exigirMetodo('GET');

/* Lo puede leer cualquiera, y sin guardar copias: el día que sube la
   versión mínima, los celulares tienen que enterarse enseguida */
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

$app = require __DIR__ . '/version-app.php';
$enlaces = $app['enlaces'] ?? [];

responder([
    'minima'  => (string) ($app['minima'] ?? ''),
    'mensaje' => (string) ($app['mensaje'] ?? ''),
    'enlaces' => [
        'android' => (string) ($enlaces['android'] ?? ''),
        'ios'     => (string) ($enlaces['ios'] ?? ''),
        'apk'     => (string) ($enlaces['apk'] ?? ''),
    ],
]);
