<?php
/* ============================================================
   MMM · Cuentas y roles (requiere token de maestro o pastor)
   GET  api/usuarios.php               todas las cuentas
   POST api/usuarios.php { "id": 5, "rol": "pastor" }

   El maestro nombra pastores; el pastor elige quién es admin y
   quién miembro. Nadie cambia su propio rol ni el de alguien de
   su mismo nivel o superior.
   ============================================================ */

require __DIR__ . '/conexion.php';

$yo = exigirToken($db);
$asignables = rolesAsignables($yo['rol']);
if (!$asignables) {
    responder(['error' => 'Solo el pastor puede cambiar los roles'], 403);
}

/* Se puede tocar una cuenta si su rol actual es uno de los que el que
   pide puede dar: el pastor no le cambia el rol a otro pastor */
$editable = fn(array $cuenta) => (int) $cuenta['id'] !== (int) $yo['id']
    && in_array($cuenta['rol'], $asignables, true);

function formatoCuenta(array $cuenta, callable $editable): array
{
    return [
        'id'       => (int) $cuenta['id'],
        'usuario'  => $cuenta['usuario'],
        'nombre'   => $cuenta['nombre'],
        'rol'      => $cuenta['rol'],
        'creado'   => $cuenta['creado'],
        'editable' => $editable($cuenta),
    ];
}

const NOMBRES_ROL = [
    'maestro' => 'Maestro',
    'pastor'  => 'Pastor',
    'admin'   => 'Administrador',
    'miembro' => 'Miembro',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedido = leerJson();
    $id     = filter_var($pedido['id'] ?? null, FILTER_VALIDATE_INT);
    $rol    = (string) ($pedido['rol'] ?? '');

    if ($id === false) {
        responder(['error' => 'Falta el id de la cuenta'], 400);
    }
    if (!in_array($rol, $asignables, true)) {
        responder(['error' => 'Datos inválidos', 'campos' => ['rol' => 'No puedes dar ese rol']], 422);
    }

    $consulta = $db->prepare('SELECT id, usuario, nombre, rol, creado FROM usuarios WHERE id = ? AND activo = 1');
    $consulta->execute([$id]);
    $cuenta = $consulta->fetch() ?: responder(['error' => 'La cuenta no existe'], 404);

    if (!$editable($cuenta)) {
        responder(['error' => 'No puedes cambiar el rol de esta cuenta'], 403);
    }

    if ($cuenta['rol'] !== $rol) {
        $db->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')->execute([$rol, $id]);
        crearAviso($db, [$id], 'rol', 'Ahora eres ' . NOMBRES_ROL[$rol],
            match ($rol) {
                'pastor'  => 'Puedes cambiar el contenido del sitio y elegir el rol de las demás cuentas.',
                'admin'   => 'Puedes cambiar el contenido del sitio desde la aplicación.',
                'miembro' => 'Ves tus participaciones, tu perfil y los avisos de la iglesia.',
            });
        $cuenta['rol'] = $rol;
    }

    responder(['ok' => true, 'usuario' => formatoCuenta($cuenta, $editable)]);
}

exigirMetodo('GET');

/* Primero los de más responsabilidad; dentro de cada rol, los más nuevos */
$cuentas = $db->query(
    "SELECT id, usuario, nombre, rol, creado
       FROM usuarios
      WHERE activo = 1
      ORDER BY FIELD(rol, 'maestro', 'pastor', 'admin', 'miembro'), creado DESC, id DESC"
)->fetchAll();

responder([
    'usuarios'   => array_map(fn($cuenta) => formatoCuenta($cuenta, $editable), $cuentas),
    'asignables' => $asignables,
]);
