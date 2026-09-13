<?php
/* ============================================================
   MMM · Secciones del contenido
   Qué tablas puede leer y escribir la API y qué campos tiene cada
   una. guardar.php solo acepta los campos que figuran aquí, así
   que agregar un campo nuevo es: columna en MySQL + línea aquí.

   Tipos: texto (con max), entero, fecha (AAAA-MM-DD),
          anio (4 cifras), dia (1 = lunes … 7 = domingo)
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
        ],
    ],

    'horarios' => [
        'tabla'  => 'horarios',
        'orden'  => 'orden, id',
        'campos' => [
            'dia'       => ['tipo' => 'texto', 'max' => 30, 'requerido' => true],
            'hora'      => ['tipo' => 'texto', 'max' => 50, 'requerido' => true],
            'actividad' => ['tipo' => 'texto', 'max' => 150, 'requerido' => true],
            'lugar'     => ['tipo' => 'texto', 'max' => 150],
            'orden'     => ['tipo' => 'entero'],
        ],
    ],

    /* Cada fila es un evento; datos.php los agrupa por día */
    'agenda' => [
        'tabla'  => 'agenda_eventos',
        'orden'  => 'dia, orden, id',
        'campos' => [
            'dia'    => ['tipo' => 'dia', 'requerido' => true],
            'nombre' => ['tipo' => 'texto', 'max' => 150, 'requerido' => true],
            'hora'   => ['tipo' => 'texto', 'max' => 50, 'requerido' => true],
            'orden'  => ['tipo' => 'entero'],
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
