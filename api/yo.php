<?php
/* ============================================================
   MMM · Mi cuenta (requiere token)
   GET  api/yo.php   usuario, perfil y avisos sin leer
   POST api/yo.php   { "nombre": "...", "descripcion": "...", "foto": "subidas/…" }

   El perfil es la ficha de representante de la persona. El cargo,
   el orden y si aparece en la web los decide el pastor: no se
   cambian desde aquí.
   ============================================================ */

require __DIR__ . '/conexion.php';

$usuario = exigirToken($db);

function leerPerfil(PDO $db, int $usuarioId): ?array
{
    $consulta = $db->prepare(
        'SELECT id, nombre, cargo, descripcion, foto, visible FROM representantes WHERE usuario_id = ?'
    );
    $consulta->execute([$usuarioId]);
    $perfil = $consulta->fetch();
    if (!$perfil) {
        return null;
    }
    $perfil['visible'] = (bool) $perfil['visible'];
    return $perfil;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedido      = leerJson();
    $nombre      = trim((string) ($pedido['nombre'] ?? ''));
    $descripcion = trim((string) ($pedido['descripcion'] ?? ''));
    $foto        = trim((string) ($pedido['foto'] ?? ''));

    $errores = [];
    if ($nombre === '') {
        $errores['nombre'] = 'Es obligatorio';
    } elseif (mb_strlen($nombre) > 150) {
        $errores['nombre'] = 'Máximo 150 caracteres';
    }
    if (mb_strlen($descripcion) > 2000) {
        $errores['descripcion'] = 'Máximo 2000 caracteres';
    }
    if (mb_strlen($foto) > 500) {
        $errores['foto'] = 'Ruta demasiado larga';
    }
    if ($errores) {
        responder(['error' => 'Datos inválidos', 'campos' => $errores], 422);
    }

    if (leerPerfil($db, (int) $usuario['id'])) {
        $db->prepare('UPDATE representantes SET nombre = ?, descripcion = ?, foto = ? WHERE usuario_id = ?')
           ->execute([$nombre, $descripcion, $foto, $usuario['id']]);
    } else {
        /* Una cuenta sin ficha (p. ej. si el pastor la borró): se crea oculta */
        $orden = (int) $db->query('SELECT COALESCE(MAX(orden), 0) + 1 FROM representantes')->fetchColumn();
        $db->prepare('INSERT INTO representantes (nombre, descripcion, foto, orden, visible, usuario_id) VALUES (?, ?, ?, ?, 0, ?)')
           ->execute([$nombre, $descripcion, $foto, $orden, $usuario['id']]);
    }

    $db->prepare('UPDATE usuarios SET nombre = ? WHERE id = ?')->execute([$nombre, $usuario['id']]);
    $usuario['nombre'] = $nombre;
} else {
    exigirMetodo('GET');
}

$consulta = $db->prepare('SELECT COUNT(*) FROM avisos WHERE usuario_id = ? AND leido = 0');
$consulta->execute([$usuario['id']]);

$respuesta = [
    'usuario' => [
        'id'      => (int) $usuario['id'],
        'usuario' => $usuario['usuario'],
        'nombre'  => $usuario['nombre'],
        'rol'     => $usuario['rol'],
    ],
    'perfil'          => leerPerfil($db, (int) $usuario['id']),
    'avisos_sin_leer' => (int) $consulta->fetchColumn(),
];

/* Al pastor le sirve saber cuántos perfiles esperan su aprobación */
if (esAdministrador($usuario)) {
    $respuesta['por_aprobar'] = (int) $db->query(
        'SELECT COUNT(*) FROM representantes WHERE visible = 0 AND usuario_id IS NOT NULL'
    )->fetchColumn();
}

responder($respuesta);
