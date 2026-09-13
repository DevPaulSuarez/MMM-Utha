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

/* "Hoy" es el de la iglesia, no el del servidor: decide qué cultos
   y programas ya pasaron */
date_default_timezone_set($CONFIG['zona_horaria'] ?? 'America/Denver');

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

/* Usuario dueño del token, o null si falta, no existe o venció.
   Sirve para lo que funciona con y sin sesión (datos.php). */
function usuarioDelToken(PDO $db): ?array
{
    $token = leerTokenDeCabecera();
    if ($token === null) {
        return null;
    }

    $consulta = $db->prepare(
        'SELECT u.id, u.usuario, u.nombre, u.rol
           FROM tokens t
           JOIN usuarios u ON u.id = t.usuario_id
          WHERE t.token_hash = ? AND t.vence > NOW() AND u.activo = 1'
    );
    $consulta->execute([hashToken($token)]);
    return $consulta->fetch() ?: null;
}

/* Corta la petición con 401 si no hay una sesión válida */
function exigirToken(PDO $db): array
{
    if (leerTokenDeCabecera() === null) {
        responder(['error' => 'Falta el token'], 401);
    }
    return usuarioDelToken($db) ?? responder(['error' => 'Sesión vencida o inválida'], 401);
}

/* Cambiar el contenido del sitio es solo para administradores (el
   pastor). Los miembros editan su perfil y sus participaciones. */
function exigirAdmin(PDO $db): array
{
    $usuario = exigirToken($db);
    if ($usuario['rol'] !== 'admin') {
        responder(['error' => 'Solo el pastor o un administrador puede hacer esto'], 403);
    }
    return $usuario;
}

/* Token nuevo para una cuenta (login y registro) */
function crearToken(PDO $db, int $usuarioId, int $dias): array
{
    $token = bin2hex(random_bytes(32));

    $db->prepare('INSERT INTO tokens (usuario_id, token_hash, vence) VALUES (?, ?, NOW() + INTERVAL ? DAY)')
       ->execute([$usuarioId, hashToken($token), $dias]);

    $consulta = $db->prepare('SELECT vence FROM tokens WHERE token_hash = ?');
    $consulta->execute([hashToken($token)]);

    return ['token' => $token, 'vence' => $consulta->fetchColumn()];
}

/* --- Avisos dentro de la app ----------------------------------- */

function crearAviso(
    PDO $db,
    array $usuarioIds,
    string $tipo,
    string $titulo,
    string $mensaje = '',
    ?int $programaId = null,
    ?int $actividadId = null
): void {
    $insertar = $db->prepare(
        'INSERT INTO avisos (usuario_id, tipo, titulo, mensaje, programa_id, actividad_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach (array_unique($usuarioIds) as $usuarioId) {
        $insertar->execute([
            $usuarioId, $tipo, mb_substr($titulo, 0, 200), mb_substr($mensaje, 0, 500),
            $programaId, $actividadId,
        ]);
    }
}

function idsDeAdmins(PDO $db): array
{
    return array_map('intval', $db->query(
        "SELECT id FROM usuarios WHERE rol = 'admin' AND activo = 1"
    )->fetchAll(PDO::FETCH_COLUMN));
}

/* "2026-09-19" → "viernes 19 de septiembre" */
function fechaLegible(string $iso): string
{
    $dias  = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
              'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $fecha = new DateTimeImmutable($iso);

    return $dias[(int) $fecha->format('N') - 1] . ' ' . $fecha->format('j')
        . ' de ' . $meses[(int) $fecha->format('n') - 1];
}

/* "19:00" → "7:00 p. m." */
function horaLegible(?string $hhmm): string
{
    if (!$hhmm) {
        return '';
    }
    [$hora, $minuto] = array_map('intval', explode(':', $hhmm));
    return ($hora % 12 ?: 12) . ':' . str_pad((string) $minuto, 2, '0', STR_PAD_LEFT)
        . ($hora < 12 ? ' a. m.' : ' p. m.');
}

/* --- Cultos ---------------------------------------------------- */

/* MySQL entrega las columnas TIME como "19:00:00"; la app y la web
   trabajan con "19:00" */
function acortarHoras(array $fila): array
{
    foreach (['hora_inicio', 'hora_fin'] as $campo) {
        if (isset($fila[$campo])) {
            $fila[$campo] = substr($fila[$campo], 0, 5);
        }
    }
    return $fila;
}

/* Programas con sus participantes ya resueltos: nombre y foto del
   representante, o el nombre de la visita. $donde filtra sobre
   p (programas) y ce (cultos_extra). Para un culto extra la fecha
   es siempre la del extra, aunque se haya cambiado después. */
function leerProgramas(PDO $db, string $donde, array $valores): array
{
    $consulta = $db->prepare(
        "SELECT p.id, p.culto_id, p.culto_extra_id, COALESCE(ce.fecha, p.fecha) AS fecha
           FROM programas p
           LEFT JOIN cultos_extra ce ON ce.id = p.culto_extra_id
          WHERE $donde
          ORDER BY COALESCE(ce.fecha, p.fecha), p.id"
    );
    $consulta->execute($valores);
    $programas = $consulta->fetchAll();
    if (!$programas) {
        return [];
    }

    $ids    = array_column($programas, 'id');
    $marcas = implode(', ', array_fill(0, count($ids), '?'));

    $consulta = $db->prepare(
        "SELECT pa.programa_id, pa.rol, pa.representante_id,
                pa.presentacion, pa.detalle,
                COALESCE(r.nombre, pa.nombre) AS nombre,
                COALESCE(r.foto, '') AS foto
           FROM participaciones pa
           LEFT JOIN representantes r ON r.id = pa.representante_id
          WHERE pa.programa_id IN ($marcas)
          ORDER BY pa.programa_id, pa.orden, pa.id"
    );
    $consulta->execute($ids);

    $porPrograma = [];
    foreach ($consulta->fetchAll() as $fila) {
        $programaId = $fila['programa_id'];
        unset($fila['programa_id']);
        $porPrograma[$programaId][] = $fila;
    }

    foreach ($programas as &$programa) {
        $programa['participantes'] = $porPrograma[$programa['id']] ?? [];
    }
    return $programas;
}

/* De cada culto fijo, el último programa anterior a $hoy. No se borra:
   la app lo copia para armar el siguiente de ese mismo culto. */
function idsUltimosProgramasPasados(PDO $db, string $hoy): array
{
    $consulta = $db->prepare(
        'SELECT p.id
           FROM programas p
          WHERE p.culto_id IS NOT NULL
            AND p.fecha < ?
            AND p.fecha = (SELECT MAX(p2.fecha) FROM programas p2
                            WHERE p2.culto_id = p.culto_id AND p2.fecha < ?)'
    );
    $consulta->execute([$hoy, $hoy]);
    return array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
}
