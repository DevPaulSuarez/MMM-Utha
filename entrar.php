<?php
// ============================================================
// MMM · Entrada privada a la web mientras está "en construcción"
//
// El botón "Visita" de construccion.html manda aquí el código.
// Si es correcto deja la misma cookie que /?vista=... (ver
// web.htaccess) y el navegador ya ve la web real por 30 días.
//
// El código no se guarda tal cual, solo su huella (password_hash).
// Para cambiarlo:  php -r 'echo password_hash("NUEVO", PASSWORD_DEFAULT);'
// ============================================================

const HUELLA_CODIGO = '$2y$12$aM71xyCjMk6fQSBGU4sFF.Hl.xNUix4M5Mrxz/rsjhTO0wbrlHX8O';
const COOKIE_NOMBRE = 'mmm_vista';
const COOKIE_VALOR  = 'pastor-99892c5a';   // el mismo de web.htaccess
const DIAS          = 30;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$codigo = (string) ($_POST['codigo'] ?? '');

if (!password_verify($codigo, HUELLA_CODIGO)) {
    sleep(1);   // frena a quien pruebe códigos al azar
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Código incorrecto']);
    exit;
}

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
setcookie(COOKIE_NOMBRE, COOKIE_VALOR, [
    'expires'  => time() + DIAS * 86400,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
echo json_encode(['ok' => true]);
