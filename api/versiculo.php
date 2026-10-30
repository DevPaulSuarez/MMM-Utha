<?php
/* ============================================================
   MMM · Versículo del día (requiere token)
   GET  api/versiculo.php              el de hoy y los de los próximos días
   GET  api/versiculo.php?hermanos=1   a quién se le puede mandar uno
   GET  api/versiculo.php?todos=1      todos (solo administradores)
   GET  api/versiculo.php?estadisticas=1&dias=30   totales (solo administradores)
   POST api/versiculo.php  { "accion": "visto", "fecha": "2026-10-30" }
   POST api/versiculo.php  { "accion": "me_hablo", "fecha": "2026-10-30" }
   POST api/versiculo.php  { "accion": "para_hermano", "fecha": "2026-10-30" }
   POST api/versiculo.php  { "accion": "compartir", "versiculo_id": 12, "para": [4, 9] }

   A cada cuenta le toca uno distinto: el servidor lo elige entre los
   que esa persona no vio (ni le mandaron) en el último año, con más
   chance para los temas en los que tocó "Sí, me habló", para los que
   van con el día (congregarse si hay culto, fuerza los lunes) y para
   los que más le hablan a toda la iglesia. Nunca repite el tema
   principal de ayer.

   Las estadísticas son solo totales: nadie ve lo que marcó cada
   persona. Se eligen por adelantado
   DIAS_ADELANTE días, para que el celular programe las
   notificaciones aunque no tenga internet.
   ============================================================ */

require __DIR__ . '/conexion.php';

const DIAS_ADELANTE = 7;

/* Sociedades Bíblicas Unidas permite citar hasta 500 versículos de la
   Reina-Valera 1960 sin pedir permiso por escrito */
const LIMITE_VERSICULOS = 500;

/* Cuántos versículos puede mandar una cuenta por día */
const COMPARTIDOS_POR_DIA = 30;

/* Lo que le habla a toda la iglesia se suaviza como si cada versículo
   ya tuviera estas aperturas con el promedio: uno abierto dos veces no
   pesa como uno abierto cien */
const APERTURAS_PREVIAS = 10;

/* En las estadísticas, un versículo entra en "los que más" o "los que
   menos" recién con estas aperturas en el período */
const APERTURAS_MINIMAS = 3;

const TEMAS = [
    'congregarse' => 'Congregarse', 'alabanza' => 'Alabanza', 'fe' => 'Fe', 'animo' => 'Ánimo',
    'fuerza' => 'Fuerza', 'paz' => 'Paz', 'familia' => 'Familia', 'gratitud' => 'Gratitud',
    'esperanza' => 'Esperanza', 'amor' => 'Amor', 'perdon' => 'Perdón', 'provision' => 'Provisión',
    'servicio' => 'Servicio', 'oracion' => 'Oración', 'sabiduria' => 'Sabiduría',
    'palabra' => 'Palabra de Dios', 'proteccion' => 'Protección',
];

$usuario = exigirToken($db);
$hoy     = new DateTimeImmutable('today');

/* "congregarse,alabanza" → ["congregarse", "alabanza"] */
function listaTemas(string $temas): array
{
    return array_values(array_filter(array_map('trim', explode(',', $temas))));
}

function formatoVersiculo(array $fila): array
{
    return [
        'id'    => (int) $fila['id'],
        'cita'  => $fila['cita'],
        'texto' => $fila['texto'],
        'temas' => listaTemas($fila['temas']),
    ];
}

/* Los cultos de cada fecha entre $desde y $hasta, los de cada semana y
   los extra, por hora: ["2026-11-01" => [["nombre" => "Escuela
   dominical", "hora" => "10:00"], …]]. El celular avisa una hora antes
   de cada uno. */
function cultosPorFecha(PDO $db, DateTimeImmutable $desde, DateTimeImmutable $hasta): array
{
    $semanales = [];
    foreach ($db->query('SELECT dia, nombre, hora_inicio FROM cultos') as $culto) {
        $semanales[(int) $culto['dia']][] = $culto;
    }

    $consulta = $db->prepare('SELECT fecha, nombre, hora_inicio FROM cultos_extra WHERE fecha BETWEEN ? AND ?');
    $consulta->execute([$desde->format('Y-m-d'), $hasta->format('Y-m-d')]);
    $extras = [];
    foreach ($consulta as $culto) {
        $extras[$culto['fecha']][] = $culto;
    }

    $cultos = [];
    for ($dia = $desde; $dia <= $hasta; $dia = $dia->modify('+1 day')) {
        $fecha  = $dia->format('Y-m-d');
        $delDia = [...($semanales[(int) $dia->format('N')] ?? []), ...($extras[$fecha] ?? [])];
        usort($delDia, fn($a, $b) => $a['hora_inicio'] <=> $b['hora_inicio']);
        foreach ($delDia as $culto) {
            $cultos[$fecha][] = ['nombre' => $culto['nombre'], 'hora' => substr($culto['hora_inicio'], 0, 5)];
        }
    }
    return $cultos;
}

/* Temas que van con el día */
function temasDelDia(DateTimeImmutable $dia, bool $hayCulto): array
{
    if ($hayCulto) {
        return ['congregarse', 'alabanza'];
    }
    if (in_array($dia->format('m-d'), ['12-31', '01-01'], true)) {
        return ['esperanza', 'gratitud'];
    }
    return $dia->format('N') === '1' ? ['fuerza', 'animo'] : [];
}

/* Elige y guarda los que falten de hoy a DIAS_ADELANTE días. Devuelve
   los días con su versículo, para la app. */
function versiculosDeLaCuenta(PDO $db, int $usuarioId, DateTimeImmutable $hoy): array
{
    $hasta  = $hoy->modify('+' . DIAS_ADELANTE . ' days');
    $cultos = cultosPorFecha($db, $hoy, $hasta);

    $leer = function () use ($db, $usuarioId, $hoy, $hasta): array {
        $consulta = $db->prepare(
            'SELECT d.fecha, d.me_hablo, v.id, v.cita, v.texto, v.temas
               FROM versiculos_diarios d
               JOIN versiculos v ON v.id = d.versiculo_id
              WHERE d.usuario_id = ? AND d.fecha BETWEEN ? AND ?
              ORDER BY d.fecha'
        );
        $consulta->execute([$usuarioId, $hoy->modify('-1 day')->format('Y-m-d'), $hasta->format('Y-m-d')]);
        return array_column($consulta->fetchAll(), null, 'fecha');
    };

    $dias = $leer();
    if (count(array_filter(array_keys($dias), fn($f) => $f >= $hoy->format('Y-m-d'))) <= DIAS_ADELANTE) {
        elegirFaltantes($db, $usuarioId, $hoy, $hasta, $dias, $cultos);
        $dias = $leer();
    }

    $respuesta = [];
    foreach ($dias as $fecha => $fila) {
        if ($fecha < $hoy->format('Y-m-d')) {
            continue;
        }
        $primero = $cultos[$fecha][0] ?? null;
        $respuesta[] = [
            'fecha'     => $fecha,
            'versiculo' => formatoVersiculo($fila),
            'me_hablo'  => (bool) $fila['me_hablo'],
            /* El primero, ya escrito: "Culto general a las 7:00 p. m." */
            'culto'     => $primero ? $primero['nombre'] . ' a las ' . horaLegible($primero['hora']) : null,
            'cultos'    => $cultos[$fecha] ?? [],
        ];
    }
    return $respuesta;
}

function elegirFaltantes(
    PDO $db,
    int $usuarioId,
    DateTimeImmutable $hoy,
    DateTimeImmutable $hasta,
    array $dias,
    array $cultos
): void {
    $temas = [];
    foreach ($db->query('SELECT id, temas FROM versiculos WHERE activo = 1') as $fila) {
        $temas[(int) $fila['id']] = listaTemas($fila['temas']);
    }
    if (!$temas) {
        return;
    }

    /* La última vez que vio cada uno: le tocó o se lo mandaron */
    $consulta = $db->prepare(
        'SELECT versiculo_id, MAX(fecha) FROM versiculos_diarios WHERE usuario_id = ? GROUP BY versiculo_id
         UNION ALL
         SELECT versiculo_id, MAX(DATE(creado)) FROM versiculos_compartidos WHERE para_usuario_id = ? GROUP BY versiculo_id'
    );
    $consulta->execute([$usuarioId, $usuarioId]);
    $ultimaVez = [];
    foreach ($consulta->fetchAll(PDO::FETCH_NUM) as [$id, $fecha]) {
        $ultimaVez[(int) $id] = max($ultimaVez[(int) $id] ?? '', $fecha);
    }

    /* Lo que le habla en cada tema este año: suma 1 cada "Sí, me
       habló" y resta medio cada "Es para mi hermano" (no le habló a
       él, pero tampoco es un rechazo) */
    $consulta = $db->prepare(
        'SELECT v.temas, d.me_hablo, d.para_hermano
           FROM versiculos_diarios d
           JOIN versiculos v ON v.id = d.versiculo_id
          WHERE d.usuario_id = ? AND (d.me_hablo = 1 OR d.para_hermano = 1) AND d.fecha > ?'
    );
    $consulta->execute([$usuarioId, $hoy->modify('-1 year')->format('Y-m-d')]);
    $gustos = [];
    foreach ($consulta->fetchAll() as $fila) {
        foreach (listaTemas($fila['temas']) as $tema) {
            $gustos[$tema] = ($gustos[$tema] ?? 0) + (int) $fila['me_hablo'] - 0.5 * (int) $fila['para_hermano'];
        }
    }

    $iglesia = gustoDeLaIglesia($db);

    $insertar = $db->prepare(
        'INSERT IGNORE INTO versiculos_diarios (usuario_id, fecha, versiculo_id) VALUES (?, ?, ?)'
    );

    for ($dia = $hoy; $dia <= $hasta; $dia = $dia->modify('+1 day')) {
        $fecha = $dia->format('Y-m-d');
        if (isset($dias[$fecha])) {
            continue;
        }

        $ayer      = $dias[$dia->modify('-1 day')->format('Y-m-d')] ?? null;
        $temaAyer  = $ayer ? (listaTemas($ayer['temas'])[0] ?? '') : '';
        $delDia    = temasDelDia($dia, isset($cultos[$fecha]));
        $haceUnAno = $dia->modify('-1 year')->format('Y-m-d');

        /* Los que no vio en un año; si ya vio todos, los que vio hace más tiempo */
        $candidatos = array_filter(array_keys($temas), fn($id) => ($ultimaVez[$id] ?? '') < $haceUnAno);
        if (!$candidatos) {
            $orden = array_map(fn($id) => $ultimaVez[$id] ?? '', array_keys($temas));
            $candidatos = array_keys($temas);
            array_multisort($orden, $candidatos);
            $candidatos = array_slice($candidatos, 0, max(1, intdiv(count($candidatos), 10)));
        }
        $otroTema = array_filter($candidatos, fn($id) => ($temas[$id][0] ?? '') !== $temaAyer);
        if ($otroTema) {
            $candidatos = $otroTema;
        }

        $elegido = null;
        $mejor   = -1.0;
        foreach ($candidatos as $id) {
            $puntos = mt_rand(0, 3000) / 1000 + ($iglesia[$id] ?? 0);
            foreach ($temas[$id] as $i => $tema) {
                $principal = $i === 0;
                $puntos += max(-2, min($gustos[$tema] ?? 0, 5)) * ($principal ? 0.3 : 0.15);
                if (in_array($tema, $delDia, true)) {
                    $puntos += $principal ? 3 : 1.5;
                }
            }
            if ($puntos > $mejor) {
                [$elegido, $mejor] = [$id, $puntos];
            }
        }

        $insertar->execute([$usuarioId, $fecha, $elegido]);
        $ultimaVez[$elegido] = $fecha;
        $dias[$fecha] = ['temas' => implode(',', $temas[$elegido])];
    }
}

/* Puntos extra (o de menos) para cada versículo según el % de "me
   habló" de toda la iglesia entre los que lo abrieron, comparado con el
   promedio. Entre -1 y 1.5: pesa menos que lo que le gusta a la persona
   y que el día, pero ayuda con los recién llegados. */
function gustoDeLaIglesia(PDO $db): array
{
    $filas = $db->query(
        'SELECT versiculo_id, COUNT(*) AS vistos, SUM(me_hablo) AS hablo
           FROM versiculos_diarios
          WHERE visto IS NOT NULL
          GROUP BY versiculo_id'
    )->fetchAll();

    $vistos = array_sum(array_column($filas, 'vistos'));
    if (!$vistos) {
        return [];
    }
    $promedio = array_sum(array_column($filas, 'hablo')) / $vistos;

    $puntos = [];
    foreach ($filas as $fila) {
        $tasa = ($fila['hablo'] + $promedio * APERTURAS_PREVIAS) / ($fila['vistos'] + APERTURAS_PREVIAS);
        $puntos[(int) $fila['versiculo_id']] = max(-1.0, min(1.5, ($tasa - $promedio) * 6));
    }
    return $puntos;
}

/* Fecha de un versículo ya repartido: de hace una semana a hoy */
function fechaRepartida(mixed $valor, DateTimeImmutable $hoy): string
{
    $fecha = (string) $valor;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
        || $fecha > $hoy->format('Y-m-d')
        || $fecha < $hoy->modify('-' . DIAS_ADELANTE . ' days')->format('Y-m-d')) {
        responder(['error' => 'Fecha inválida'], 400);
    }
    return $fecha;
}

/* % de "me habló" entre los que lo abrieron; null si nadie lo abrió */
function porcentaje(int $hablo, int $vistos): ?int
{
    return $vistos ? (int) round($hablo * 100 / $vistos) : null;
}

/* Totales de los últimos $dias días: en general, por tema (un versículo
   cuenta en cada uno de sus temas) y por versículo */
function estadisticas(PDO $db, DateTimeImmutable $hoy, int $dias): array
{
    $desde = $hoy->modify('-' . ($dias - 1) . ' days');
    $rango = [$desde->format('Y-m-d'), $hoy->format('Y-m-d')];

    $versiculos = [];
    foreach ($db->query('SELECT id, cita, texto, temas FROM versiculos') as $fila) {
        $versiculos[(int) $fila['id']] = formatoVersiculo($fila)
            + ['repartidos' => 0, 'vistos' => 0, 'me_hablo' => 0, 'para_hermano' => 0, 'compartidos' => 0];
    }

    $consulta = $db->prepare(
        'SELECT versiculo_id, COUNT(*) AS repartidos, COUNT(visto) AS vistos, SUM(me_hablo) AS hablo,
                SUM(para_hermano) AS hermano
           FROM versiculos_diarios
          WHERE fecha BETWEEN ? AND ?
          GROUP BY versiculo_id'
    );
    $consulta->execute($rango);
    foreach ($consulta as $fila) {
        $id = (int) $fila['versiculo_id'];
        $versiculos[$id]['repartidos'] = (int) $fila['repartidos'];
        $versiculos[$id]['vistos']     = (int) $fila['vistos'];
        $versiculos[$id]['me_hablo']   = (int) $fila['hablo'];
        $versiculos[$id]['para_hermano'] = (int) $fila['hermano'];
    }

    $consulta = $db->prepare(
        'SELECT versiculo_id, COUNT(*) FROM versiculos_compartidos
          WHERE creado >= ? AND creado < ? + INTERVAL 1 DAY
          GROUP BY versiculo_id'
    );
    $consulta->execute($rango);
    foreach ($consulta->fetchAll(PDO::FETCH_NUM) as [$id, $cantidad]) {
        $versiculos[(int) $id]['compartidos'] = (int) $cantidad;
    }

    $consulta = $db->prepare(
        'SELECT COUNT(DISTINCT usuario_id) FROM versiculos_diarios WHERE visto IS NOT NULL AND fecha BETWEEN ? AND ?'
    );
    $consulta->execute($rango);
    $personas = (int) $consulta->fetchColumn();

    $campos  = ['repartidos', 'vistos', 'me_hablo', 'para_hermano', 'compartidos'];
    $totales = array_fill_keys($campos, 0);
    $temas   = [];
    foreach (TEMAS as $tema => $nombre) {
        $temas[$tema] = ['tema' => $tema, 'nombre' => $nombre] + array_fill_keys($campos, 0);
    }
    foreach ($versiculos as $versiculo) {
        foreach ($campos as $campo) {
            $totales[$campo] += $versiculo[$campo];
            foreach ($versiculo['temas'] as $tema) {
                if (isset($temas[$tema])) {
                    $temas[$tema][$campo] += $versiculo[$campo];
                }
            }
        }
    }

    $conPorcentaje = fn(array $fila) => $fila + ['porcentaje' => porcentaje($fila['me_hablo'], $fila['vistos'])];
    $temas = array_map($conPorcentaje, array_values($temas));
    usort($temas, fn($a, $b) => [$b['porcentaje'] ?? -1, $b['vistos']] <=> [$a['porcentaje'] ?? -1, $a['vistos']]);

    $medidos = array_map($conPorcentaje, array_values(array_filter(
        $versiculos, fn($v) => $v['vistos'] >= APERTURAS_MINIMAS
    )));
    usort($medidos, fn($a, $b) => [$b['porcentaje'], $b['vistos']] <=> [$a['porcentaje'], $a['vistos']]);
    $mas   = array_slice($medidos, 0, 10);
    $menos = array_slice(array_reverse(array_slice($medidos, count($mas))), 0, 10);

    return [
        'dias'    => $dias,
        'desde'   => $rango[0],
        'hasta'   => $rango[1],
        'minimo'  => APERTURAS_MINIMAS,
        'totales' => $totales + ['personas' => $personas,
                                 'porcentaje' => porcentaje($totales['me_hablo'], $totales['vistos'])],
        'temas'   => $temas,
        'mas'     => $mas,
        'menos'   => $menos,
    ];
}

/* --- Pedidos --------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedido = leerJson();

    switch ($pedido['accion'] ?? '') {
        case 'visto':
            $fecha = fechaRepartida($pedido['fecha'] ?? '', $hoy);
            $db->prepare('UPDATE versiculos_diarios SET visto = COALESCE(visto, NOW()) WHERE usuario_id = ? AND fecha = ?')
               ->execute([$usuario['id'], $fecha]);
            responder(['ok' => true]);

        case 'para_hermano':
            /* Funciona como cerrar: se anota y nada más. Los próximos días
               no se vuelven a elegir: es una señal suave. */
            $fecha = fechaRepartida($pedido['fecha'] ?? '', $hoy);
            $db->prepare(
                'UPDATE versiculos_diarios SET para_hermano = 1, visto = COALESCE(visto, NOW())
                  WHERE usuario_id = ? AND fecha = ?'
            )->execute([$usuario['id'], $fecha]);
            responder(['ok' => true]);

        case 'me_hablo':
            $fecha = fechaRepartida($pedido['fecha'] ?? '', $hoy);
            $db->prepare(
                'UPDATE versiculos_diarios SET me_hablo = 1, visto = COALESCE(visto, NOW())
                  WHERE usuario_id = ? AND fecha = ?'
            )->execute([$usuario['id'], $fecha]);

            /* Los de los próximos días se vuelven a elegir con este gusto */
            $db->prepare('DELETE FROM versiculos_diarios WHERE usuario_id = ? AND fecha > ?')
               ->execute([$usuario['id'], $hoy->format('Y-m-d')]);

            responder(['dias' => versiculosDeLaCuenta($db, (int) $usuario['id'], $hoy)]);

        case 'compartir':
            $versiculoId = filter_var($pedido['versiculo_id'] ?? null, FILTER_VALIDATE_INT);
            $para = is_array($pedido['para'] ?? null)
                ? array_values(array_unique(array_filter(
                    array_map(fn($id) => filter_var($id, FILTER_VALIDATE_INT), $pedido['para']),
                    fn($id) => $id !== false && $id !== (int) $usuario['id']
                )))
                : [];
            if ($versiculoId === false || !$para) {
                responder(['error' => 'Falta el versículo o a quién mandarlo'], 400);
            }
            if (count($para) > 20) {
                responder(['error' => 'Se puede mandar a 20 hermanos por vez'], 400);
            }

            $consulta = $db->prepare('SELECT id, cita, texto, temas FROM versiculos WHERE id = ?');
            $consulta->execute([$versiculoId]);
            $versiculo = $consulta->fetch() ?: responder(['error' => 'El versículo no existe'], 404);

            $consulta = $db->prepare(
                'SELECT COUNT(*) FROM versiculos_compartidos WHERE de_usuario_id = ? AND creado >= CURDATE()'
            );
            $consulta->execute([$usuario['id']]);
            if ((int) $consulta->fetchColumn() + count($para) > COMPARTIDOS_POR_DIA) {
                responder(['error' => 'Por hoy ya mandaste muchos versículos. Mañana puedes seguir.'], 429);
            }

            $marcas   = implode(', ', array_fill(0, count($para), '?'));
            $consulta = $db->prepare("SELECT id FROM usuarios WHERE activo = 1 AND id IN ($marcas)");
            $consulta->execute($para);
            $destinos = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
            if (!$destinos) {
                responder(['error' => 'Esas cuentas no existen'], 404);
            }

            $insertar = $db->prepare(
                'INSERT INTO versiculos_compartidos (versiculo_id, de_usuario_id, para_usuario_id) VALUES (?, ?, ?)'
            );
            foreach ($destinos as $destino) {
                $insertar->execute([$versiculoId, $usuario['id'], $destino]);
            }

            $mensaje = $versiculo['cita'] . "\n" . $versiculo['texto'];
            if (mb_strlen($mensaje) > 500) {
                $mensaje = mb_substr($mensaje, 0, 499) . '…';
            }
            crearAviso($db, $destinos, 'versiculo',
                ($usuario['nombre'] ?: $usuario['usuario']) . ' te compartió un versículo', $mensaje);

            responder(['ok' => true, 'enviados' => count($destinos)]);

        default:
            responder(['error' => 'Acción desconocida: usar "visto", "me_hablo", "para_hermano" o "compartir"'], 400);
    }
}

exigirMetodo('GET');

if (isset($_GET['hermanos'])) {
    $consulta = $db->prepare(
        "SELECT u.id, COALESCE(NULLIF(u.nombre, ''), u.usuario) AS nombre, COALESCE(r.foto, '') AS foto
           FROM usuarios u
           LEFT JOIN representantes r ON r.usuario_id = u.id
          WHERE u.activo = 1 AND u.id <> ?
          ORDER BY nombre"
    );
    $consulta->execute([$usuario['id']]);
    responder(['hermanos' => array_map(fn($h) => ['id' => (int) $h['id']] + $h, $consulta->fetchAll())]);
}

if (isset($_GET['estadisticas'])) {
    if (!esAdministrador($usuario)) {
        responder(['error' => 'Solo el pastor o un administrador puede hacer esto'], 403);
    }
    $dias = (int) ($_GET['dias'] ?? 30);
    responder(estadisticas($db, $hoy, in_array($dias, [7, 30, 90, 365], true) ? $dias : 30));
}

if (isset($_GET['todos'])) {
    if (!esAdministrador($usuario)) {
        responder(['error' => 'Solo el pastor o un administrador puede hacer esto'], 403);
    }
    $versiculos = $db->query('SELECT id, cita, texto, temas, activo FROM versiculos ORDER BY id')->fetchAll();
    responder([
        'versiculos' => array_map(
            fn($v) => formatoVersiculo($v) + ['activo' => (bool) $v['activo']],
            $versiculos
        ),
        'temas'  => TEMAS,
        'limite' => LIMITE_VERSICULOS,
    ]);
}

responder(['dias' => versiculosDeLaCuenta($db, (int) $usuario['id'], $hoy)]);
