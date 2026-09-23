# MMM · API

Backend en PHP + MySQL. La aplicación Flutter escribe el contenido y la web lo lee.

```
App Flutter ──(token)──> login / guardar / subir-imagen ──┐
                                                          ├── MySQL
Web pública ───────────> datos.php ───────────────────────┘
```

Requiere PHP 8.1 o superior con `pdo_mysql`, `mbstring` y `fileinfo`. Funciona con MySQL 5.7+ o MariaDB 10.3+.

## Instalación en el hosting

1. Crear una base de datos MySQL y un usuario con permisos sobre ella (en cPanel: *MySQL Databases*).
2. En phpMyAdmin, entrar a esa base e importar `api/base-de-datos.sql`. Crea las tablas con el contenido actual de `js/datos.json`.
3. Copiar `api/config.ejemplo.php` como `api/config.php` y completar los datos de la base y la clave de la cuenta `maestro`.
4. La cuenta maestra se crea sola en el primer pedido a la API (basta con abrir `api/datos.php`). Con ella se entra a la app y, en *Usuarios y roles*, se nombra pastor a quien corresponda después de que se registre. Si el usuario elegido para el maestro ya existe en la base, no se crea y queda anotado en el log de errores de PHP.
5. Subir la web, `api/` y `subidas/` (con su `.htaccess`). El código de la app (`app/MMM-Utha-App`) no se sube; lo único suyo que va al servidor es el APK ya compilado, que `publicar-apk.sh` deja en `app/mmm-utha.apk`.
6. Probar: abrir `https://tudominio.org/api/datos.php` tiene que mostrar el JSON.
7. La web ya pide el contenido a `api/datos.php` (ver `js/datos.js`). Si la API no responde, usa la copia `js/datos.json`, que no se actualiza sola.

### Web y API en servidores distintos

La web puede ir en un hosting gratuito (solo archivos estáticos) y el backend en un VPS:

| Servidor | Qué lleva |
|---|---|
| VPS (PHP + MySQL) | `api/`, `subidas/`, `img/` y el APK en `app/` |
| Hosting de la web | `index.html`, `paginas/`, `css/`, `js/` e `img/` |

1. En `js/datos.js`, poner la dirección del VPS en `SERVIDOR` (`"https://api.tudominio.org"`). La web pide ahí `api/datos.php` y las fotos `subidas/…`; las de `img/` las sigue sirviendo su propio hosting.
2. Compilar la app con `--dart-define=SITIO_URL=https://api.tudominio.org`. La app resuelve contra el VPS también las fotos de `img/`, por eso esa carpeta va en los dos.
3. El VPS tiene que tener **https**: una web con https no puede pedir datos a una dirección http.
4. `datos.php` ya permite que lo lea cualquier dominio. El resto de la API no lo usa la web, solo la app, que no pasa por esa restricción del navegador.
5. Los `.htaccess` son de Apache. Con Nginx hay que traducir sus reglas: bloquear `config.php`, `conexion.php`, `secciones.php`, `crear-usuario.php`, `migrar.php`, `version-app.php`, la carpeta `api/migraciones/`, los `.sql` y `.md` de `api/`, y no ejecutar PHP dentro de `subidas/`. El archivo ya traducido está en `servidor/nginx-mmm-utha.conf`.

## Respuestas

Todas son JSON. Si algo sale mal llega un código HTTP de error y `{ "error": "mensaje" }`.

| Código | Significado |
|---|---|
| 400 | Falta algo o está mal armado el pedido |
| 401 | Sin token, o token vencido: volver al login |
| 403 | La cuenta es de miembro y eso es solo para administradores |
| 404 | El registro no existe |
| 413 | Imagen demasiado pesada |
| 415 | El archivo no es JPG, PNG ni WebP |
| 422 | Datos inválidos: el detalle por campo viene en `campos` |
| 429 | Demasiados intentos de login: esperar 15 minutos |

## Endpoints

### `POST api/login.php`

```json
{ "usuario": "pastor", "clave": "••••••••" }
```

Respuesta:

```json
{
  "token": "3f9c…(64 caracteres)",
  "vence": "2026-10-13 18:20:00",
  "usuario": { "id": 1, "usuario": "pastor", "nombre": "Nombre del Pastor" }
}
```

La app guarda el token (con `flutter_secure_storage`) y lo manda en cada pedido protegido:

```
Authorization: Bearer <token>
```

### `POST api/logout.php` 🔒

Sin cuerpo. Anula el token.

### `GET api/datos.php`

Público. Devuelve todo el contenido con la misma forma que tenía `js/datos.json`, más el `id` de cada registro:

```json
{
  "ubicacion": { "direccion": "…", "coordenadas": "" },
  "banners": [ { "id": 1, "imagen": "…", "alt": "…", "orden": 1 } ],
  "lemas": [ { "id": 1, "anio": "2026", "titulo": "…", "texto": "…", "verso": "…", "imagen": "…" } ],
  "representantes": [ { "id": 1, "nombre": "…", "cargo": "…", "descripcion": "…", "foto": "…", "orden": 1 } ],
  "cultos": [ { "id": 1, "dia": 3, "nombre": "Culto de oración", "hora_inicio": "19:00", "hora_fin": "20:00", "lugar": "Templo" } ],
  "cultos_extra": [ { "id": 1, "fecha": "2026-09-17", "nombre": "Culto de jóvenes", "hora_inicio": "19:00", "hora_fin": null, "lugar": "" } ],
  "programas": [ {
    "id": 1, "culto_id": 2, "culto_extra_id": null, "fecha": "2026-09-19",
    "participantes": [ { "rol": "presentador", "representante_id": 1, "nombre": "…", "foto": "…" } ]
  } ],
  "actividades": [ { "id": 1, "tipo": "comida", "titulo": "…", "fecha": "2026-09-26", "hora_inicio": "10:00", "hora_fin": null, "lugar": "…", "descripcion": "…", "platillo": "Ají de gallina", "pais": "Perú", "imagen": "" } ],
  "noticias": [ { "id": 1, "titulo": "…", "fecha": "2026-02-15", "resumen": "…", "imagen": "…" } ]
}
```

`cultos_extra` y `actividades` traen solo lo de hoy en adelante, según la `zona_horaria` de `config.php`. `programas` trae lo de hoy en adelante y, además, el último que ya pasó de cada culto fijo: la web no lo muestra y la app lo copia para armar el siguiente. En `programas`, un participante con `representante_id: null` es una visita y `nombre` dice a quién representa.

### `GET api/version.php`

Público. Qué versión de la app exige el servidor y de dónde se baja la nueva:

```json
{
  "minima": "1.1.0",
  "mensaje": "",
  "enlaces": {
    "android": "https://play.google.com/store/apps/details?id=…",
    "ios": "https://apps.apple.com/app/id…",
    "apk": "https://mmm.devpess.com/app/mmm-utha.apk"
  }
}
```

La app lo consulta al abrirse y cada vez que se vuelve a ella. Si la versión instalada es menor que `minima`, se queda en la pantalla *Actualiza la aplicación* y no se puede usar hasta instalar la nueva. Se comparan los números de a uno (`1.2.0` es anterior a `1.10.0`).

Se cambia en `api/version-app.php`, que no se sirve por web. Con `minima` vacía no se obliga a nadie. Si el servidor no responde, la app sigue funcionando con lo que tiene: el bloqueo es para versiones viejas, no para cortes de internet.

El paso a paso para publicar una versión está en `DESPLIEGUE.md` (*Pedir a todos que actualicen la app*).

### `POST api/guardar.php` 🔒

Un solo endpoint para crear, editar y borrar en cualquier sección.

```json
{ "seccion": "noticias", "accion": "crear", "datos": { "titulo": "…", "fecha": "2026-09-13", "resumen": "…" } }
{ "seccion": "noticias", "accion": "editar", "id": 3, "datos": { "titulo": "Nuevo título" } }
{ "seccion": "noticias", "accion": "borrar", "id": 3 }
{ "seccion": "ubicacion", "accion": "editar", "datos": { "direccion": "…" } }
```

- **crear** responde `201` con `{ "ok": true, "registro": { … } }`.
- **editar** solo cambia los campos que se manden y responde con el registro completo.
- **borrar** responde `{ "ok": true, "id": 3 }`.
- **ubicacion** es un único registro: solo acepta `editar`, sin `id`.

Campos por sección (✱ obligatorio al crear):

| Sección | Campos |
|---|---|
| `ubicacion` | direccion✱, coordenadas |
| `banners` | imagen✱, alt, orden |
| `lemas` | anio✱ (4 cifras), titulo✱, texto, verso, imagen |
| `representantes` | nombre✱, cargo, descripcion, foto, orden |
| `cultos` | dia✱ (1 = lunes … 7 = domingo), nombre✱, hora_inicio✱ (HH:MM), hora_fin, lugar |
| `cultos_extra` | fecha✱ (AAAA-MM-DD), nombre✱, hora_inicio✱ (HH:MM), hora_fin, lugar |
| `actividades` | tipo✱ (`comida`, `paseo`, `hospital`, `evangelismo`, `otra`), titulo✱, fecha✱, hora_inicio, hora_fin, lugar, descripcion, platillo, pais, imagen |
| `noticias` | titulo✱, fecha✱ (AAAA-MM-DD), resumen, imagen |

Ejemplo de error de validación (`422`):

```json
{ "error": "Datos inválidos", "campos": { "fecha": "Debe tener formato AAAA-MM-DD" } }
```

### `POST api/programa.php` 🔒

Quién participa en un culto y en qué rol. Se guarda completo cada vez: reemplaza al anterior.

```json
{
  "accion": "guardar",
  "culto_id": 2,
  "fecha": "2026-09-19",
  "participantes": [
    { "rol": "presentador",   "representante_id": 1 },
    { "rol": "participacion", "representante_id": 2 },
    { "rol": "participacion", "nombre": "Iglesia de Ogden" },
    { "rol": "predicacion",   "representante_id": 4 },
    { "rol": "alabanza",      "representante_id": 5 }
  ]
}
```

- **Culto fijo:** `culto_id` y `fecha`, que tiene que caer en el día de ese culto. **Culto extra:** solo `culto_extra_id`.
- **Roles:** `presentador` (una persona), `participacion`, `predicacion` (una persona) y `alabanza`.
- **Participante:** un representante (`representante_id`) o una visita (`nombre`, a quién representa).
- El orden de la lista es el orden en la web.
- Responde `{ "ok": true, "programa": { … } }` con la misma forma que `datos.php`. Una lista vacía borra el programa.
- Si algo no cumple responde `422` con el primer problema en `error` y todos en `detalle`.
- Cada vez que se guarda o borra, se eliminan los programas de fechas pasadas, salvo el último de cada culto fijo.

Para borrar: `{ "accion": "borrar", "culto_id": 2, "fecha": "2026-09-19" }`.

### `POST api/subir-imagen.php` 🔒

`multipart/form-data` con el archivo en el campo `imagen` (JPG, PNG o WebP, hasta 5 MB).

```json
{ "ok": true, "ruta": "subidas/2026/09/a1b2c3d4e5f6a7b8.webp" }
```

La `ruta` es relativa a la raíz del sitio. Para mostrar la imagen en la app, se le antepone la dirección del sitio (`https://tudominio.org/`). Para asignarla a un registro, se manda tal cual en `guardar.php`:

```json
{ "seccion": "noticias", "accion": "editar", "id": 3, "datos": { "imagen": "subidas/2026/09/a1b2c3d4e5f6a7b8.webp" } }
```

## Cuentas de miembros y avisos

| Rol | Quién es | Qué puede |
|---|---|---|
| `maestro` | La cuenta del servidor, creada desde `config.php` | Todo, y nombrar pastores |
| `pastor` | Lo nombra el maestro | Todo, y elegir quién es `admin` o `miembro` |
| `admin` | Lo elige el pastor (o `crear-usuario.php`) | Cambiar el contenido del sitio |
| `miembro` | Se registra desde la app | Editar su perfil y responder sus participaciones |

Los tres primeros son administradores: pueden usar `guardar.php` y `programa.php` (con otra cuenta responden `403`) y reciben los avisos para administradores.

`datos.php` sin sesión (la web) solo trae los representantes con `visible = 1`. Con el token de un administrador trae todos, con `visible` y el `usuario` de cada cuenta. En `programas`, cada participante trae además `presentacion` y `detalle`.

### `GET` / `POST api/usuarios.php` 🔒

Solo para `maestro` y `pastor`; con otra cuenta responde `403`.

- `GET`: `{ "usuarios": [ { "id", "usuario", "nombre", "rol", "creado", "editable" } ], "asignables": ["admin", "miembro"] }`. `editable` dice si quien pide le puede cambiar el rol a esa cuenta.
- `POST` con `{ "id": 5, "rol": "pastor" }`: cambia el rol y le deja un aviso (`rol`) a esa persona. Responde `{ "ok": true, "usuario": { … } }`.

Nadie cambia su propio rol. El maestro da `pastor`, `admin` o `miembro` a cualquier cuenta menos a sí mismo. El pastor da `admin` o `miembro`, y solo a cuentas que tienen uno de esos dos roles: no puede tocar a otro pastor ni al maestro.

### `POST api/registro.php`

```json
{ "usuario": "fiorela", "clave": "••••••••", "nombre": "Fiorela" }
```

Crea la cuenta y su perfil de representante, **oculto en la web** hasta que el pastor lo apruebe (`visible: 1` con `guardar.php`). Responde igual que `login.php`, con `"rol": "miembro"`. Permite hasta 3 cuentas por hora desde la misma conexión.

### `GET` / `POST api/yo.php` 🔒

`GET` responde:

```json
{
  "usuario": { "id": 5, "usuario": "fiorela", "nombre": "Fiorela", "rol": "miembro" },
  "perfil": { "id": 2, "nombre": "Fiorela", "cargo": "", "descripcion": "…", "foto": "…", "visible": false },
  "avisos_sin_leer": 2,
  "por_aprobar": 1
}
```

`por_aprobar` solo llega a los admins. `POST` con `{ "nombre", "descripcion", "foto" }` actualiza el perfil propio; el cargo y la visibilidad los decide el pastor.

### `GET` / `POST api/asignaciones.php` 🔒

- `GET`: los cultos de hoy en adelante en los que participa la persona (`id`, `rol`, `presentacion`, `detalle`, `fecha`, `culto`, `hora_inicio`, `hora_fin`, `lugar`).
- `POST` con `{ "id": 12, "presentacion": "musica", "detalle": "Cuán grande es Él" }`: solo en el rol `participacion`. `presentacion` es `musica`, `lectura` o `testimonio`. Si el pastor vuelve a guardar el programa, la elección se conserva.

### `GET` / `POST api/avisos.php` 🔒

- `GET`: `{ "avisos": [ { "id", "tipo", "titulo", "mensaje", "programa_id", "actividad_id", "leido", "creado" } ] }`, los últimos 50.
- `POST` con `{ "accion": "leidos" }`: marca todos como leídos.

Se crean avisos cuando:

| Pasa esto | Le llega a |
|---|---|
| Alguien se registra (`miembro`) | Los admins |
| El pastor aprueba un perfil (`perfil`) | Ese miembro |
| Le cambian el rol a alguien (`rol`) | Esa persona |
| Alguien entra nuevo a un programa (`programa`) | Esa persona, si tiene cuenta |
| Un participante elige qué presentará (`respuesta`) | Los admins |
| Se crea una actividad (`actividad`) | Todas las cuentas, menos quien la creó |

Los avisos de más de 60 días se borran solos.

## Agregar un campo nuevo

1. Crear `api/migraciones/001-noticias-contenido.sql` (el número sigue al último) con `ALTER TABLE noticias ADD contenido TEXT NOT NULL;`.
2. Aplicarla en la base local: `php api/migrar.php`.
3. Agregar la línea en `api/secciones.php`.
4. Al subir, `desplegar.sh` la aplica también en el servidor (ver `DESPLIEGUE.md`).

`datos.php` lo empieza a devolver solo y `guardar.php` lo acepta.
