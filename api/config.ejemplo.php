<?php
/* ============================================================
   MMM · Configuración del backend
   Copiar este archivo como config.php y completar los datos del
   hosting. config.php no se sube a git: tiene las contraseñas.
   ============================================================ */

return [
    /* Base de datos MySQL (los da el hosting, en cPanel > MySQL) */
    'db_host'    => 'localhost',
    'db_nombre'  => 'mmm_utha',
    'db_usuario' => 'usuario_mysql',
    'db_clave'   => 'clave_mysql',

    /* Cuántos días dura una sesión de la aplicación antes de
       pedir usuario y contraseña otra vez */
    'token_dias' => 30,

    /* Carpeta donde se guardan las imágenes que manda la app,
       relativa a la raíz del sitio (al lado de index.html) */
    'carpeta_subidas' => 'subidas',

    /* Tamaño máximo por imagen, en megas */
    'imagen_max_mb' => 5,

    /* Zona horaria de la iglesia: define qué es "hoy" para mostrar
       solo los cultos y programas que todavía no pasaron */
    'zona_horaria' => 'America/Denver',
];
