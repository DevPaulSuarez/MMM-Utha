<?php
/* ============================================================
   MMM · Cierre de sesión (requiere token)
   POST api/logout.php

   Anula el token en la base: aunque quede guardado en algún
   celular, ya no sirve para entrar.
   ============================================================ */

require __DIR__ . '/conexion.php';

exigirMetodo('POST');
exigirToken($db);

$db->prepare('DELETE FROM tokens WHERE token_hash = ?')
   ->execute([hashToken(leerTokenDeCabecera())]);

responder(['ok' => true]);
