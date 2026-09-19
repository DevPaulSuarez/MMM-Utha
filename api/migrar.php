<?php
/* ============================================================
   MMM · Cambios en la estructura de la base (migraciones)
   Se usa desde la terminal, en la computadora y en el servidor:

     php api/migrar.php            aplica las que falten
     php api/migrar.php --estado   solo muestra cuáles faltan

   base-de-datos.sql crea la base inicial y no se vuelve a tocar.
   Cada cambio posterior (una columna, una tabla) va en un archivo
   nuevo de api/migraciones, con número adelante para el orden:

     api/migraciones/001-noticias-contenido.sql

   La tabla "migraciones" anota las que ya se aplicaron en esa base,
   así cada una corre una sola vez en cada lugar. Un archivo ya
   aplicado no se edita: si hay que corregir algo, va en otro nuevo.
   ============================================================ */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$CONFIG = require __DIR__ . '/config.php';

$db = new PDO(
    "mysql:host={$CONFIG['db_host']};dbname={$CONFIG['db_nombre']};charset=utf8mb4",
    $CONFIG['db_usuario'],
    $CONFIG['db_clave'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$db->exec(
    'CREATE TABLE IF NOT EXISTS migraciones (
       nombre   VARCHAR(190) PRIMARY KEY,
       aplicada DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$aplicadas = $db->query('SELECT nombre FROM migraciones')->fetchAll(PDO::FETCH_COLUMN);
$archivos  = glob(__DIR__ . '/migraciones/*.sql') ?: [];
sort($archivos, SORT_STRING);

$pendientes = array_values(array_filter(
    $archivos,
    fn($ruta) => !in_array(basename($ruta), $aplicadas, true)
));

if (!$pendientes) {
    echo "La base está al día (" . count($aplicadas) . " migraciones aplicadas).\n";
    exit(0);
}

if (in_array('--estado', $argv, true)) {
    echo "Faltan " . count($pendientes) . ":\n";
    foreach ($pendientes as $ruta) {
        echo "  " . basename($ruta) . "\n";
    }
    exit(0);
}

foreach ($pendientes as $ruta) {
    $nombre = basename($ruta);
    echo "Aplicando $nombre... ";

    /* Una instrucción por vez: si una falla se sabe cuál. Se separa en
       cada ";" al final de una línea; los comentarios "--" se quitan */
    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($ruta));
    $instrucciones = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)));

    try {
        foreach ($instrucciones as $instruccion) {
            $db->exec($instruccion);
        }
    } catch (PDOException $e) {
        /* MySQL confirma solo cada ALTER/CREATE: las instrucciones de
           antes de la que falló ya quedaron hechas */
        echo "FALLÓ\n\n" . $e->getMessage() . "\n\n";
        echo "No se anotó como aplicada. Revisar la base, corregir el archivo\n";
        echo "(o quitar de él lo que ya quedó hecho) y volver a correr.\n";
        exit(1);
    }

    $db->prepare('INSERT INTO migraciones (nombre) VALUES (?)')->execute([$nombre]);
    echo "listo\n";
}

echo "La base está al día.\n";
