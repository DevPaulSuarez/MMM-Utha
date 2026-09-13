<?php
/* ============================================================
   MMM · Secciones del contenido
   Qué tablas puede leer y escribir la API y qué campos tiene cada
   una. guardar.php solo acepta los campos que figuran aquí, así
   que agregar un campo nuevo es: columna en MySQL + línea aquí.

   Tipos: texto (con max), entero, fecha (AAAA-MM-DD),
          hora (HH:MM, 24 horas), anio (4 cifras),
          dia (1 = lunes … 7 = domingo), opcion (una de "opciones")

   El programa de cada culto no está aquí: tiene su propio
   endpoint (programa.php) porque se guarda completo de una vez.
   ============================================================ */

return [
    /* Un único registro (id = 1): solo se edita */
    'ubicacion' => [
        'tabla'  => 'ubicacion',
        'unica'  => true,
        'campos' => [
            'direccion'   => ['tipo' => 'texto', 'max' => 255, 'requerido' => true],
            'coordenadas' => ['tipo' => 'texto', 'max' => 100],
        ],
    ],

    'banners' => [
        'tabla'  => 'banners',
        'orden'  => 'orden, id',
        'campos' => [
            'imagen' => ['tipo' => 'texto', 'max' => 500, 'requerido' => true],
            'alt'    => ['tipo' => 'texto', 'max' => 255],
            'orden'  => ['tipo' => 'entero'],
        ],
    ],

    'lemas' => [
        'tabla'  => 'lemas',
        'orden'  => 'anio DESC, id',
        'campos' => [
            'anio'   => ['tipo' => 'anio', 'requerido' => true],
            'titulo' => ['tipo' => 'texto', 'max' => 150, 'requerido' => true],
            'texto'  => ['tipo' => 'texto', 'max' => 5000],
            'verso'  => ['tipo' => 'texto', 'max' => 255],
            'imagen' => ['tipo' => 'texto', 'max' => 500],
        ],
    ],

    'representantes' => [
        'tabla'  => 'representantes',
        'orden'  => 'orden, id',
        'campos' => [
            'nombre'      => ['tipo' => 'texto', 'max' => 150, 'requerido' => true],
            'cargo'       => ['tipo' => 'texto', 'max' => 150],
            'descripcion' => ['tipo' => 'texto', 'max' => 2000],
            'foto'        => ['tipo' => 'texto', 'max' => 500],
            'orden'       => ['tipo' => 'entero'],
            /* 1 = aparece en la web. Los que crea el pastor salen
               visibles; los perfiles de miembros nacen ocultos
               (registro.php) hasta que él los apruebe. */
            'visible'     => ['tipo' => 'entero', 'defecto' => 1],
        ],
    ],

    /* Cultos que se repiten cada semana. Borrar uno borra también
       sus programas. */
    'cultos' => [
        'tabla'  => 'cultos',
        'orden'  => 'dia, hora_inicio, id',
        'campos' => [
            'dia'         => ['tipo' => 'dia', 'requerido' => true],
            'nombre'      => ['tipo' => 'texto', 'max' => 150, 'requerido' => true],
            'hora_inicio' => ['tipo' => 'hora', 'requerido' => true],
            'hora_fin'    => ['tipo' => 'hora'],
            'lugar'       => ['tipo' => 'texto', 'max' => 150],
        ],
    ],

    /* Cultos que se agregan en una fecha puntual (p. ej. un culto de
       jóvenes algún jueves). datos.php solo muestra los que no pasaron. */
    'cultos_extra' => [
        'tabla'  => 'cultos_extra',
        'orden'  => 'fecha, hora_inicio, id',
        'campos' => [
            'fecha'       => ['tipo' => 'fecha', 'requerido' => true],
            'nombre'      => ['tipo' => 'texto', 'max' => 150, 'requerido' => true],
            'hora_inicio' => ['tipo' => 'hora', 'requerido' => true],
            'hora_fin'    => ['tipo' => 'hora'],
            'lugar'       => ['tipo' => 'texto', 'max' => 150],
        ],
    ],

    /* Eventos con fecha: ventas de comida, paseos, visitas a hospitales,
       evangelismo… Platillo y país se usan en las ventas de comida.
       datos.php solo muestra las que no pasaron. */
    'actividades' => [
        'tabla'  => 'actividades',
        'orden'  => 'fecha, hora_inicio, id',
        'campos' => [
            'tipo'        => ['tipo' => 'opcion', 'requerido' => true,
                              'opciones' => ['comida', 'paseo', 'hospital', 'evangelismo', 'otra']],
            'titulo'      => ['tipo' => 'texto', 'max' => 150, 'requerido' => true],
            'fecha'       => ['tipo' => 'fecha', 'requerido' => true],
            'hora_inicio' => ['tipo' => 'hora'],
            'hora_fin'    => ['tipo' => 'hora'],
            'lugar'       => ['tipo' => 'texto', 'max' => 200],
            'descripcion' => ['tipo' => 'texto', 'max' => 2000],
            'platillo'    => ['tipo' => 'texto', 'max' => 150],
            'pais'        => ['tipo' => 'texto', 'max' => 100],
            'imagen'      => ['tipo' => 'texto', 'max' => 500],
        ],
    ],

    'noticias' => [
        'tabla'  => 'noticias',
        'orden'  => 'fecha DESC, id DESC',
        'campos' => [
            'titulo'  => ['tipo' => 'texto', 'max' => 200, 'requerido' => true],
            'fecha'   => ['tipo' => 'fecha', 'requerido' => true],
            'resumen' => ['tipo' => 'texto', 'max' => 2000],
            'imagen'  => ['tipo' => 'texto', 'max' => 500],
        ],
    ],
];
