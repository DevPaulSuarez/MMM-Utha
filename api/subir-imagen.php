<?php
/* ============================================================
   MMM · Subir una imagen (requiere token)
   POST api/subir-imagen.php   multipart/form-data, campo "imagen"

   Acepta JPG, PNG y WebP. Responde con la ruta de la imagen
   relativa a la raíz del sitio (p. ej. "subidas/2026/09/a1b2.webp"),
   que es lo que después se guarda en el campo imagen/foto con
   guardar.php. La web ya sabe resolver esa ruta.
   ============================================================ */

require __DIR__ . '/conexion.php';

exigirMetodo('POST');
exigirToken($db);

$archivo = $_FILES['imagen'] ?? null;

if (!$archivo || is_array($archivo['error'])) {
    responder(['error' => 'Falta el archivo en el campo "imagen"'], 400);
}

$maxBytes = (int) $CONFIG['imagen_max_mb'] * 1024 * 1024;

if (in_array($archivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $archivo['size'] > $maxBytes) {
    responder(['error' => "La imagen pesa más de {$CONFIG['imagen_max_mb']} MB"], 413);
}
if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
    responder(['error' => 'No se pudo recibir la imagen'], 400);
}

/* El tipo se mira en el contenido real del archivo, no en el
   nombre ni en lo que diga el celular. La extensión la ponemos
   nosotros: así nunca se guarda un .php disfrazado. */
$extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$tipo = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);

if (!isset($extensiones[$tipo]) || @getimagesize($archivo['tmp_name']) === false) {
    responder(['error' => 'Solo se aceptan imágenes JPG, PNG o WebP'], 415);
}

$carpeta   = trim($CONFIG['carpeta_subidas'], '/') . '/' . date('Y/m');
$carpetaFs = dirname(__DIR__) . '/' . $carpeta;

if (!is_dir($carpetaFs) && !mkdir($carpetaFs, 0755, true)) {
    throw new RuntimeException("No se pudo crear $carpetaFs");
}

$nombre = bin2hex(random_bytes(8)) . '.' . $extensiones[$tipo];

if (!move_uploaded_file($archivo['tmp_name'], "$carpetaFs/$nombre")) {
    throw new RuntimeException("No se pudo mover la imagen a $carpetaFs");
}

responder(['ok' => true, 'ruta' => "$carpeta/$nombre"], 201);
