<?php
/* ============================================================
   MMM · Registro de miembros (público)
   POST api/registro.php
   { "usuario": "fiorela", "clave": "...", "nombre": "Fiorela" }

   Crea la cuenta como "miembro" y su perfil de representante,
   oculto en la web hasta que el pastor lo apruebe. Responde igual
   que login.php: la persona entra enseguida.
   ============================================================ */

require __DIR__ . '/conexion.php';

exigirMetodo('POST');

/* Contra cuentas en masa: pocas por hora desde la misma conexión */
const REGISTROS_POR_HORA = 3;

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

$consulta = $db->prepare('SELECT COUNT(*) FROM usuarios WHERE ip_registro = ? AND creado > NOW() - INTERVAL 1 HOUR');
$consulta->execute([$ip]);
if ((int) $consulta->fetchColumn() >= REGISTROS_POR_HORA) {
    responder(['error' => 'Se crearon demasiadas cuentas desde esta conexión. Intenta más tarde.'], 429);
}

$pedido  = leerJson();
$usuario = mb_strtolower(trim((string) ($pedido['usuario'] ?? '')));
$clave   = (string) ($pedido['clave'] ?? '');
$nombre  = trim((string) ($pedido['nombre'] ?? ''));

$errores = [];

if (!preg_match('/^[a-z0-9._-]{3,50}$/', $usuario)) {
    $errores['usuario'] = 'De 3 a 50 caracteres: letras sin tilde, números, punto o guion';
} else {
    $consulta = $db->prepare('SELECT 1 FROM usuarios WHERE usuario = ?');
    $consulta->execute([$usuario]);
    if ($consulta->fetchColumn()) {
        $errores['usuario'] = 'Ese usuario ya existe, elige otro';
    }
}

if (mb_strlen($clave) < 8) {
    $errores['clave'] = 'Al menos 8 caracteres';
} elseif (strlen($clave) > 200) {
    $errores['clave'] = 'Demasiado larga';
}

if ($nombre === '') {
    $errores['nombre'] = 'Es obligatorio';
} elseif (mb_strlen($nombre) > 150) {
    $errores['nombre'] = 'Máximo 150 caracteres';
}

if ($errores) {
    responder(['error' => 'Datos inválidos', 'campos' => $errores], 422);
}

$db->beginTransaction();
try {
    $db->prepare("INSERT INTO usuarios (usuario, clave_hash, nombre, rol, ip_registro) VALUES (?, ?, ?, 'miembro', ?)")
       ->execute([$usuario, password_hash($clave, PASSWORD_DEFAULT), $nombre, $ip]);
    $usuarioId = (int) $db->lastInsertId();

    /* Su ficha de representante, al final y oculta en la web */
    $orden = (int) $db->query('SELECT COALESCE(MAX(orden), 0) + 1 FROM representantes')->fetchColumn();
    $db->prepare("INSERT INTO representantes (nombre, descripcion, orden, visible, usuario_id) VALUES (?, '', ?, 0, ?)")
       ->execute([$nombre, $orden, $usuarioId]);

    crearAviso($db, idsDeAdmins($db), 'miembro', "Nuevo miembro: $nombre",
        'Revisa su perfil en Representantes para mostrarlo en la web.');

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    /* Otro registro tomó el mismo usuario al mismo tiempo */
    if ($e->getCode() === '23000') {
        responder(['error' => 'Datos inválidos', 'campos' => ['usuario' => 'Ese usuario ya existe, elige otro']], 422);
    }
    throw $e;
}

$sesion = crearToken($db, $usuarioId, (int) $CONFIG['token_dias']);

responder([
    ...$sesion,
    'usuario' => [
        'id'      => $usuarioId,
        'usuario' => $usuario,
        'nombre'  => $nombre,
        'rol'     => 'miembro',
    ],
], 201);
