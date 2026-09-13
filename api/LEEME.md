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
3. Copiar `api/config.ejemplo.php` como `api/config.php` y completar los datos de la base.
4. Crear un usuario para la app desde la computadora:
   ```bash
   php api/crear-usuario.php pastor "Nombre del Pastor"
   ```
   El script pide la contraseña e imprime un `INSERT`. Pegarlo en phpMyAdmin, en la pestaña *SQL*.
5. Subir la web, `api/` y `subidas/` (con su `.htaccess`). La carpeta `app/` no se sube.
6. Probar: abrir `https://tudominio.org/api/datos.php` tiene que mostrar el JSON.
7. La web ya pide el contenido a `api/datos.php` (ver `js/datos.js`). Si la API no responde, usa la copia `js/datos.json`, que no se actualiza sola.

## Respuestas

Todas son JSON. Si algo sale mal llega un código HTTP de error y `{ "error": "mensaje" }`.

| Código | Significado |
|---|---|
| 400 | Falta algo o está mal armado el pedido |
| 401 | Sin token, o token vencido: volver al login |
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
  "horarios": [ { "id": 1, "dia": "Miércoles", "hora": "…", "actividad": "…", "lugar": "…", "orden": 1 } ],
  "agenda": [ { "dia": "Lunes", "eventos": [ { "id": 1, "dia": 1, "nombre": "…", "hora": "…", "orden": 1 } ] } ],
  "noticias": [ { "id": 1, "titulo": "…", "fecha": "2026-02-15", "resumen": "…", "imagen": "…" } ]
}
```

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
| `horarios` | dia✱, hora✱, actividad✱, lugar, orden |
| `agenda` | dia✱ (1 = lunes … 7 = domingo), nombre✱, hora✱, orden |
| `noticias` | titulo✱, fecha✱ (AAAA-MM-DD), resumen, imagen |

Ejemplo de error de validación (`422`):

```json
{ "error": "Datos inválidos", "campos": { "fecha": "Debe tener formato AAAA-MM-DD" } }
```

### `POST api/subir-imagen.php` 🔒

`multipart/form-data` con el archivo en el campo `imagen` (JPG, PNG o WebP, hasta 5 MB).

```json
{ "ok": true, "ruta": "subidas/2026/09/a1b2c3d4e5f6a7b8.webp" }
```

La `ruta` es relativa a la raíz del sitio. Para mostrar la imagen en la app, se le antepone la dirección del sitio (`https://tudominio.org/`). Para asignarla a un registro, se manda tal cual en `guardar.php`:

```json
{ "seccion": "noticias", "accion": "editar", "id": 3, "datos": { "imagen": "subidas/2026/09/a1b2c3d4e5f6a7b8.webp" } }
```

## Agregar un campo nuevo

1. Agregar la columna en MySQL (`ALTER TABLE noticias ADD contenido TEXT NOT NULL;`).
2. Agregar la línea en `api/secciones.php`.

`datos.php` lo empieza a devolver solo y `guardar.php` lo acepta.
