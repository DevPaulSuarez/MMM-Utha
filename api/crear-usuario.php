<?php
/* ============================================================
   MMM · Crear un usuario de la aplicación
   Se usa desde la terminal, nunca por web:

     php api/crear-usuario.php <usuario> "<Nombre visible>"

   Pide la contraseña sin mostrarla y escribe la instrucción SQL
   lista para pegar en phpMyAdmin (pestaña SQL). No se conecta a
   la base, así que funciona desde tu computadora aunque el hosting
   no tenga terminal.
   ============================================================ */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

[, $usuario, $nombre] = $argv + [null, '', ''];

if (!preg_match('/^[a-z0-9._-]{3,50}$/', (string) $usuario)) {
    fwrite(STDERR, "Uso: php api/crear-usuario.php <usuario> \"<Nombre visible>\"\n");
    fwrite(STDERR, "El usuario va en minúsculas: letras, números, punto, guion (3 a 50).\n");
    exit(1);
}

function pedirClave(string $mensaje): string
{
    fwrite(STDOUT, $mensaje);
    system('stty -echo');
    $clave = rtrim((string) fgets(STDIN), "\r\n");
    system('stty echo');
    fwrite(STDOUT, "\n");
    return $clave;
}

$clave = pedirClave('Contraseña: ');
if (strlen($clave) < 8) {
    fwrite(STDERR, "La contraseña debe tener al menos 8 caracteres.\n");
    exit(1);
}
if (pedirClave('Repetir contraseña: ') !== $clave) {
    fwrite(STDERR, "Las contraseñas no coinciden.\n");
    exit(1);
}

$sql = fn(string $texto) => "'" . str_replace(["\\", "'"], ["\\\\", "''"], $texto) . "'";

echo "\nPegar esto en phpMyAdmin > SQL:\n\n";
echo 'INSERT INTO usuarios (usuario, clave_hash, nombre) VALUES ('
    . $sql($usuario) . ', '
    . $sql(password_hash($clave, PASSWORD_DEFAULT)) . ', '
    . $sql($nombre) . ");\n\n";
