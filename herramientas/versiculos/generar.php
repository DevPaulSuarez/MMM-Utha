<?php
/* ============================================================
   MMM · Versículos iniciales
   Revisa la lista de lista/*.txt y arma la migración que los carga:

     php herramientas/versiculos/generar.php RUTA/spaRV1909_vpl.txt
     php herramientas/versiculos/generar.php RUTA/spaRV1909_vpl.txt --sql

   Cada línea de la lista es "Cita | temas | texto". El texto es de
   la Reina-Valera 1960 y se escribió a mano: para atrapar errores se
   compara palabra por palabra con la Reina-Valera 1909 (dominio
   público, https://ebible.org/Scriptures/spaRV1909_vpl.zip), que es
   casi igual. Los que se parecen poco se listan para revisarlos con
   una Biblia antes de usar --sql.

   Con --sql escribe api/migraciones/003-versiculos-iniciales.sql.
   ============================================================ */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !isset($argv[1]) || !is_file($argv[1])) {
    fwrite(STDERR, "Uso: php generar.php RUTA/spaRV1909_vpl.txt [--sql]\n");
    exit(1);
}

/* Debajo de esto, el versículo se marca para revisar */
const PARECIDO_MINIMO = 0.6;

const TEMAS = [
    'congregarse', 'alabanza', 'fe', 'animo', 'fuerza', 'paz', 'familia', 'gratitud',
    'esperanza', 'amor', 'perdon', 'provision', 'servicio', 'oracion', 'sabiduria',
    'palabra', 'proteccion',
];

const LIBROS = [
    'Génesis' => 'GEN', 'Éxodo' => 'EXO', 'Levítico' => 'LEV', 'Números' => 'NUM',
    'Deuteronomio' => 'DEU', 'Josué' => 'JOS', 'Jueces' => 'JDG', 'Rut' => 'RUT',
    '1 Samuel' => '1SA', '2 Samuel' => '2SA', '1 Reyes' => '1KI', '2 Reyes' => '2KI',
    '1 Crónicas' => '1CH', '2 Crónicas' => '2CH', 'Esdras' => 'EZR', 'Nehemías' => 'NEH',
    'Ester' => 'EST', 'Job' => 'JOB', 'Salmos' => 'PSA', 'Proverbios' => 'PRO',
    'Eclesiastés' => 'ECC', 'Cantares' => 'SOL', 'Isaías' => 'ISA', 'Jeremías' => 'JER',
    'Lamentaciones' => 'LAM', 'Ezequiel' => 'EZE', 'Daniel' => 'DAN', 'Oseas' => 'HOS',
    'Joel' => 'JOE', 'Amós' => 'AMO', 'Abdías' => 'OBA', 'Jonás' => 'JON', 'Miqueas' => 'MIC',
    'Nahúm' => 'NAH', 'Habacuc' => 'HAB', 'Sofonías' => 'ZEP', 'Hageo' => 'HAG',
    'Zacarías' => 'ZEC', 'Malaquías' => 'MAL', 'Mateo' => 'MAT', 'Marcos' => 'MAR',
    'Lucas' => 'LUK', 'Juan' => 'JOH', 'Hechos' => 'ACT', 'Romanos' => 'ROM',
    '1 Corintios' => '1CO', '2 Corintios' => '2CO', 'Gálatas' => 'GAL', 'Efesios' => 'EPH',
    'Filipenses' => 'PHI', 'Colosenses' => 'COL', '1 Tesalonicenses' => '1TH',
    '2 Tesalonicenses' => '2TH', '1 Timoteo' => '1TI', '2 Timoteo' => '2TI', 'Tito' => 'TIT',
    'Filemón' => 'PHM', 'Hebreos' => 'HEB', 'Santiago' => 'JAM', '1 Pedro' => '1PE',
    '2 Pedro' => '2PE', '1 Juan' => '1JO', '2 Juan' => '2JO', '3 Juan' => '3JO',
    'Judas' => 'JUD', 'Apocalipsis' => 'REV',
];

/* --- La 1909: "JOH 3:16 Porque de tal manera…" ----------------- */
$biblia = [];
foreach (file($argv[1], FILE_IGNORE_NEW_LINES) as $linea) {
    if (preg_match('/^(\w{3}) (\d+:\d+) (.*)$/u', $linea, $m)) {
        $biblia["$m[1] $m[2]"] = $m[3];
    }
}

/* Minúsculas, sin acentos ni signos: solo se comparan las palabras.
   En el versículo 1 de un salmo la 1909 trae el título ("Salmo de
   David.") antes de la primera palabra, escrita en mayúsculas. */
function palabras(string $texto, bool $quitarTitulo = false): array
{
    if ($quitarTitulo && preg_match('/\b\p{Lu}{2,}\b/u', $texto, $m, PREG_OFFSET_CAPTURE)) {
        $texto = substr($texto, $m[0][1]);
    }
    $texto = mb_strtolower($texto);
    $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
    return preg_split('/[^\p{L}\d]+/u', $texto, -1, PREG_SPLIT_NO_EMPTY);
}

/* 1 = mismas palabras en el mismo orden; 0 = nada en común */
function parecido(array $a, array $b): float
{
    if (!$a || !$b) {
        return 0.0;
    }
    $previa = array_fill(0, count($b) + 1, 0);
    foreach ($a as $palabra) {
        $fila = [0];
        foreach ($b as $j => $otra) {
            $fila[] = $palabra === $otra ? $previa[$j] + 1 : max($previa[$j + 1], $fila[$j]);
        }
        $previa = $fila;
    }
    return 2 * end($previa) / (count($a) + count($b));
}

/* "Salmos 84:1-2" → ["PSA 84:1", "PSA 84:2"], o null si no se entiende */
function claves(string $cita): ?array
{
    if (!preg_match('/^(.+) (\d+):(\d+)(?:-(\d+))?$/u', $cita, $m) || !isset(LIBROS[$m[1]])) {
        return null;
    }
    $hasta = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : (int) $m[3];
    return array_map(fn($v) => LIBROS[$m[1]] . " $m[2]:$v", range((int) $m[3], $hasta));
}

/* --- La lista -------------------------------------------------- */
$versiculos = [];
$problemas  = 0;
$archivos   = glob(__DIR__ . '/lista/*.txt') ?: [];
sort($archivos, SORT_STRING);

foreach ($archivos as $archivo) {
    foreach (file($archivo, FILE_IGNORE_NEW_LINES) as $n => $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#') {
            continue;
        }
        $donde  = basename($archivo) . ':' . ($n + 1);
        $partes = array_map('trim', explode('|', $linea, 3));
        if (count($partes) !== 3) {
            echo "✗ $donde: no tiene \"cita | temas | texto\"\n";
            $problemas++;
            continue;
        }
        [$cita, $temas, $texto] = $partes;
        $temas = array_map('trim', explode(',', $temas));

        $claves = claves($cita);
        if ($claves === null) {
            echo "✗ $donde: cita desconocida \"$cita\"\n";
            $problemas++;
            continue;
        }
        if (isset($versiculos[$cita])) {
            echo "✗ $donde: $cita está repetido\n";
            $problemas++;
            continue;
        }
        if ($desconocidos = array_diff($temas, TEMAS)) {
            echo "✗ $donde: tema desconocido " . implode(', ', $desconocidos) . "\n";
            $problemas++;
        }
        if (mb_strlen($texto) > 1000) {
            echo "✗ $donde: $cita pasa de 1000 letras\n";
            $problemas++;
        }

        $antigua = [];
        foreach ($claves as $clave) {
            if (!isset($biblia[$clave])) {
                echo "✗ $donde: $clave no existe en la 1909\n";
                $problemas++;
                continue 2;
            }
            $antigua = [...$antigua, ...palabras($biblia[$clave], str_starts_with($clave, 'PSA') && str_ends_with($clave, ':1'))];
        }

        $nota = parecido(palabras($texto), $antigua);
        if ($nota < PARECIDO_MINIMO) {
            echo sprintf("? %s: %s se parece %d%% a la 1909\n    1960: %s\n    1909: %s\n",
                $donde, $cita, $nota * 100, $texto,
                implode(' ', array_map(fn($c) => $biblia[$c], $claves)));
            $problemas++;
        }

        $versiculos[$cita] = ['texto' => $texto, 'temas' => implode(',', $temas)];
    }
}

$porTema = array_fill_keys(TEMAS, 0);
foreach ($versiculos as $v) {
    $porTema[explode(',', $v['temas'])[0]]++;
}
echo "\n" . count($versiculos) . " versículos. Por tema principal:\n";
foreach ($porTema as $tema => $cantidad) {
    echo "  $tema: $cantidad\n";
}
echo $problemas ? "\n$problemas para revisar.\n" : "\nTodo coincide con la 1909.\n";

if (!in_array('--sql', $argv, true)) {
    exit(0);
}

$sql = "-- Versículos iniciales (Reina-Valera 1960). Generado por\n"
     . "-- herramientas/versiculos/generar.php: no editar a mano.\n";
foreach ($versiculos as $cita => $v) {
    $comillas = fn(string $s) => "'" . str_replace(["\\", "'"], ["\\\\", "''"], $s) . "'";
    $sql .= "INSERT INTO versiculos (cita, texto, temas) VALUES ("
          . $comillas($cita) . ', ' . $comillas($v['texto']) . ', ' . $comillas($v['temas']) . ");\n";
}
$destino = dirname(__DIR__, 2) . '/api/migraciones/003-versiculos-iniciales.sql';
file_put_contents($destino, $sql);
echo "Escrito: $destino\n";
