<?php
/* ============================================================
   MMM · Inicio de sesión de la aplicación
   POST api/login.php   { "usuario": "...", "clave": "..." }

   Si los datos son correctos responde con un token nuevo. La app
   lo guarda y lo manda en cada petición protegida:
   Authorization: Bearer <token>
   ============================================================ */

require __DIR__ . '/conexion.php';

exigirMetodo('POST');

/* Tras varios intentos fallidos desde la misma IP se bloquea un
   rato, para que no se pueda probar contraseñas sin fin */
const INTENTOS_MAXIMOS = 10;
const MINUTOS_BLOQUEO  = 15;

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

$consulta = $db->prepare(
    'SELECT COUNT(*) FROM intentos_login WHERE ip = ? AND fecha > NOW() - INTERVAL ? MINUTE'
);
$consulta->execute([$ip, MINUTOS_BLOQUEO]);
if ((int) $consulta->fetchColumn() >= INTENTOS_MAXIMOS) {
    responder(['error' => 'Demasiados intentos. Intente de nuevo en ' . MINUTOS_BLOQUEO . ' minutos'], 429);
}

$pedido  = leerJson();
$usuario = trim((string) ($pedido['usuario'] ?? ''));
$clave   = (string) ($pedido['clave'] ?? '');

if ($usuario === '' || $clave === '') {
    responder(['error' => 'Usuario y contraseña son obligatorios'], 400);
}

$consulta = $db->prepare('SELECT id, usuario, nombre, clave_hash FROM usuarios WHERE usuario = ? AND activo = 1');
$consulta->execute([$usuario]);
$fila = $consulta->fetch();

/* Si el usuario no existe igual se verifica contra un hash falso:
   así la respuesta tarda lo mismo y no delata qué usuarios hay */
$hash = $fila['clave_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

if (!password_verify($clave, $hash) || !$fila) {
    $db->prepare('INSERT INTO intentos_login (ip) VALUES (?)')->execute([$ip]);
    responder(['error' => 'Usuario o contraseña incorrectos'], 401);
}

/* Si PHP recomienda un cifrado más fuerte, se actualiza al vuelo */
if (password_needs_rehash($fila['clave_hash'], PASSWORD_DEFAULT)) {
    $db->prepare('UPDATE usuarios SET clave_hash = ? WHERE id = ?')
       ->execute([password_hash($clave, PASSWORD_DEFAULT), $fila['id']]);
}

/* Limpieza: intentos de esta IP y tokens ya vencidos de todos */
$db->prepare('DELETE FROM intentos_login WHERE ip = ?')->execute([$ip]);
$db->exec('DELETE FROM tokens WHERE vence <= NOW()');
$db->exec('DELETE FROM intentos_login WHERE fecha <= NOW() - INTERVAL 1 DAY');

$token = bin2hex(random_bytes(32));

$db->prepare('INSERT INTO tokens (usuario_id, token_hash, vence) VALUES (?, ?, NOW() + INTERVAL ? DAY)')
   ->execute([$fila['id'], hashToken($token), (int) $CONFIG['token_dias']]);

$consulta = $db->prepare('SELECT vence FROM tokens WHERE token_hash = ?');
$consulta->execute([hashToken($token)]);

responder([
    'token'   => $token,
    'vence'   => $consulta->fetchColumn(),
    'usuario' => [
        'id'      => (int) $fila['id'],
        'usuario' => $fila['usuario'],
        'nombre'  => $fila['nombre'],
    ],
]);
