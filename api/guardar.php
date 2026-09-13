<?php
/* ============================================================
   MMM · Guardar contenido (requiere token)
   POST api/guardar.php

   { "seccion": "noticias", "accion": "crear",  "datos": { ... } }
   { "seccion": "noticias", "accion": "editar", "id": 3, "datos": { ... } }
   { "seccion": "noticias", "accion": "borrar", "id": 3 }
   { "seccion": "ubicacion", "accion": "editar", "datos": { ... } }

   Al crear o editar responde con el registro tal como quedó.
   Las secciones y sus campos están en secciones.php.
   ============================================================ */

require __DIR__ . '/conexion.php';

exigirMetodo('POST');
$autor = exigirAdmin($db);

$SECCIONES = require __DIR__ . '/secciones.php';
$pedido    = leerJson();

$nombreSeccion = (string) ($pedido['seccion'] ?? '');
$accion        = (string) ($pedido['accion'] ?? '');

if (!isset($SECCIONES[$nombreSeccion])) {
    responder(['error' => 'Sección desconocida', 'secciones' => array_keys($SECCIONES)], 400);
}
$seccion = $SECCIONES[$nombreSeccion];
$tabla   = $seccion['tabla'];

/* --- Qué registro se toca -------------------------------------- */
if (!empty($seccion['unica'])) {
    if ($accion !== 'editar') {
        responder(['error' => 'En esta sección solo se puede "editar"'], 400);
    }
    $id = 1;
    $db->exec("INSERT IGNORE INTO `$tabla` (id) VALUES (1)");
} elseif ($accion === 'editar' || $accion === 'borrar') {
    $id = filter_var($pedido['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        responder(['error' => 'Falta un "id" válido'], 400);
    }
    $anterior = leerRegistro($db, $tabla, $id);
    if (!$anterior) {
        responder(['error' => 'No existe el registro'], 404);
    }
} elseif ($accion !== 'crear') {
    responder(['error' => 'Acción desconocida: usar "crear", "editar" o "borrar"'], 400);
}

/* --- Acción ---------------------------------------------------- */
switch ($accion) {
    case 'crear':
        $campos   = validarCampos($seccion['campos'], $pedido['datos'] ?? null, true);
        $columnas = implode(', ', array_map(fn($c) => "`$c`", array_keys($campos)));
        $marcas   = implode(', ', array_fill(0, count($campos), '?'));

        $db->prepare("INSERT INTO `$tabla` ($columnas) VALUES ($marcas)")
           ->execute(array_values($campos));

        $registro = leerRegistro($db, $tabla, (int) $db->lastInsertId());
        if ($nombreSeccion === 'actividades') {
            avisarActividad($db, $registro, (int) $autor['id']);
        }
        responder(['ok' => true, 'registro' => $registro], 201);

    case 'editar':
        $campos = validarCampos($seccion['campos'], $pedido['datos'] ?? null, false);
        if (!$campos) {
            responder(['error' => 'No hay campos para editar'], 400);
        }
        $asignaciones = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($campos)));

        $db->prepare("UPDATE `$tabla` SET $asignaciones WHERE id = ?")
           ->execute([...array_values($campos), $id]);

        $registro = leerRegistro($db, $tabla, $id);

        /* El pastor aprobó el perfil de un miembro: se le avisa */
        if ($nombreSeccion === 'representantes' && $registro['usuario_id']
            && !$anterior['visible'] && $registro['visible']) {
            crearAviso($db, [(int) $registro['usuario_id']], 'perfil',
                'Tu perfil ya aparece en la web', 'El pastor aprobó tu perfil de representante.');
        }
        responder(['ok' => true, 'registro' => $registro]);

    case 'borrar':
        $db->prepare("DELETE FROM `$tabla` WHERE id = ?")->execute([$id]);
        responder(['ok' => true, 'id' => $id]);
}

/* --- Utilidades ------------------------------------------------ */

/* Actividad nueva de hoy en adelante: aviso a todas las cuentas
   activas, menos a quien la creó */
function avisarActividad(PDO $db, array $actividad, int $autorId): void
{
    if ($actividad['fecha'] < date('Y-m-d')) {
        return;
    }

    $tipos = ['comida' => 'Venta de comida', 'paseo' => 'Paseo', 'hospital' => 'Visita a hospitales',
              'evangelismo' => 'Evangelismo', 'otra' => 'Actividad'];

    $consulta = $db->prepare('SELECT id FROM usuarios WHERE activo = 1 AND id <> ?');
    $consulta->execute([$autorId]);

    $cuando = ucfirst(fechaLegible($actividad['fecha']))
        . ($actividad['hora_inicio'] ? ' · ' . horaLegible($actividad['hora_inicio']) : '');

    crearAviso(
        $db,
        array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN)),
        'actividad',
        'Nueva actividad: ' . $actividad['titulo'],
        ($tipos[$actividad['tipo']] ?? 'Actividad') . ' · ' . $cuando,
        null,
        (int) $actividad['id']
    );
}
function leerRegistro(PDO $db, string $tabla, int $id): ?array
{
    $consulta = $db->prepare("SELECT * FROM `$tabla` WHERE id = ?");
    $consulta->execute([$id]);
    $fila = $consulta->fetch();
    return $fila ? acortarHoras($fila) : null;
}

/* Deja solo los campos permitidos, ya limpios. Al crear, los
   opcionales que no llegan se guardan vacíos (o en 0). Si algo no
   cumple, corta con 422 y el detalle por campo para la app. */
function validarCampos(array $definicion, mixed $datos, bool $esNuevo): array
{
    if (!is_array($datos)) {
        responder(['error' => 'Falta el objeto "datos"'], 400);
    }

    $desconocidos = array_diff(array_keys($datos), array_keys($definicion));
    if ($desconocidos) {
        responder(['error' => 'Campos no permitidos: ' . implode(', ', $desconocidos)], 400);
    }

    $limpios = [];
    $errores = [];

    foreach ($definicion as $campo => $regla) {
        if (!array_key_exists($campo, $datos)) {
            if ($esNuevo && !empty($regla['requerido'])) {
                $errores[$campo] = 'Es obligatorio';
            } elseif ($esNuevo) {
                $limpios[$campo] = $regla['defecto'] ?? match ($regla['tipo']) {
                    'entero' => 0,
                    'hora'   => null,
                    default  => '',
                };
            }
            continue;
        }

        [$valor, $error] = validarValor($datos[$campo], $regla);
        if ($error !== null) {
            $errores[$campo] = $error;
        } else {
            $limpios[$campo] = $valor;
        }
    }

    if ($errores) {
        responder(['error' => 'Datos inválidos', 'campos' => $errores], 422);
    }
    return $limpios;
}

/* Devuelve [valor limpio, null] o [null, mensaje de error] */
function validarValor(mixed $valor, array $regla): array
{
    if ($regla['tipo'] === 'entero') {
        $n = filter_var($valor, FILTER_VALIDATE_INT);
        return $n === false ? [null, 'Debe ser un número entero'] : [$n, null];
    }
    if ($regla['tipo'] === 'dia') {
        $n = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 7]]);
        return $n === false ? [null, 'Debe ser un número del 1 (lunes) al 7 (domingo)'] : [$n, null];
    }

    if ($valor === null) {
        $valor = '';
    }
    if (!is_string($valor) && !is_int($valor)) {
        return [null, 'Debe ser texto'];
    }

    $texto = trim((string) $valor);
    if ($texto === '') {
        /* Una hora opcional vacía se guarda como NULL, no como texto */
        $vacio = $regla['tipo'] === 'hora' ? null : '';
        return empty($regla['requerido']) ? [$vacio, null] : [null, 'Es obligatorio'];
    }

    switch ($regla['tipo']) {
        case 'hora':
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $texto)) {
                return [null, 'Debe tener formato HH:MM (24 horas)'];
            }
            break;
        case 'fecha':
            $fecha = DateTime::createFromFormat('!Y-m-d', $texto);
            if (!$fecha || $fecha->format('Y-m-d') !== $texto) {
                return [null, 'Debe tener formato AAAA-MM-DD'];
            }
            break;
        case 'anio':
            if (!preg_match('/^\d{4}$/', $texto)) {
                return [null, 'Debe ser un año de 4 cifras'];
            }
            break;
        case 'opcion':
            if (!in_array($texto, $regla['opciones'], true)) {
                return [null, 'Debe ser una de: ' . implode(', ', $regla['opciones'])];
            }
            break;
        default:
            if (mb_strlen($texto) > $regla['max']) {
                return [null, "Máximo {$regla['max']} caracteres"];
            }
    }
    return [$texto, null];
}
