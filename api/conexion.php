<?php
/* ============================================================
   MMM · Conexión y utilidades comunes
   Todos los archivos de la API empiezan incluyendo este. Deja
   lista la base de datos ($db) y las funciones para responder
   en JSON y para exigir el token de la aplicación.
   ============================================================ */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/* Un error inesperado no debe mostrar rutas ni contraseñas: se
   anota en el log del servidor y la app recibe un mensaje corto */
set_exception_handler(function (Throwable $e) {
    error_log('[MMM api] ' . $e);
    responder(['error' => 'Error interno del servidor'], 500);
});

$archivoConfig = __DIR__ . '/config.php';
if (!is_file($archivoConfig)) {
    responder(['error' => 'Falta api/config.php (copiar desde config.ejemplo.php)'], 500);
}
$CONFIG = require $archivoConfig;

$db = new PDO(
    "mysql:host={$CONFIG['db_host']};dbname={$CONFIG['db_nombre']};charset=utf8mb4",
    $CONFIG['db_usuario'],
    $CONFIG['db_clave'],
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);

/* --- Respuestas ---------------------------------------------- */
function responder(array $datos, int $estado = 200): never
{
    http_response_code($estado);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function exigirMetodo(string $metodo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
        header("Allow: $metodo");
        responder(['error' => "Método no permitido, usar $metodo"], 405);
    }
}

/* Cuerpo de la petición en JSON, como lo manda la app */
function leerJson(): array
{
    $cuerpo = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($cuerpo)) {
        responder(['error' => 'El cuerpo debe ser JSON válido'], 400);
    }
    return $cuerpo;
}

/* --- Token ----------------------------------------------------
   En la base se guarda solo el hash del token: si alguien llega a
   ver la tabla, no puede usar esos valores para entrar.
   -------------------------------------------------------------- */
function hashToken(string $token): string
{
    return hash('sha256', $token);
}

/* Algunos hostings (PHP como CGI/FastCGI) no pasan el encabezado
   Authorization tal cual; se busca en los lugares habituales */
function leerTokenDeCabecera(): ?string
{
    $cabecera = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? (function_exists('getallheaders') ? (getallheaders()['Authorization'] ?? '') : '');

    return preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim($cabecera), $m) ? strtolower($m[1]) : null;
}

/* Corta la petición con 401 si el token falta, no existe o venció.
   Devuelve el usuario dueño del token. */
function exigirToken(PDO $db): array
{
    $token = leerTokenDeCabecera();
    if ($token === null) {
        responder(['error' => 'Falta el token'], 401);
    }

    $consulta = $db->prepare(
        'SELECT u.id, u.usuario, u.nombre
           FROM tokens t
           JOIN usuarios u ON u.id = t.usuario_id
          WHERE t.token_hash = ? AND t.vence > NOW() AND u.activo = 1'
    );
    $consulta->execute([hashToken($token)]);
    $usuario = $consulta->fetch();

    if (!$usuario) {
        responder(['error' => 'Sesión vencida o inválida'], 401);
    }
    return $usuario;
}
