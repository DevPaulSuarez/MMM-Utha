# Trabajar en local y subir al servidor

El mismo código funciona en la computadora y en el servidor sin tocarlo. Lo único que cambia entre uno y otro está en archivos que no se suben a git:

| Qué | Local | Servidor |
|---|---|---|
| Base de datos y clave del maestro | `api/config.php` (AMPPS) | `api/config.php` del servidor, escrito una vez allá |
| Dónde busca la web la API | Sola: detecta `localhost` / `127.0.0.1` | `SERVIDOR_PRODUCCION` en `js/datos.js` |
| Dónde busca la app la API | Sola: `127.0.0.1:8765` | `app/MMM-Utha-App/entornos/produccion.json` |
| Datos para subir | — | `.desplegar.env` |

## Todos los días, en local

1. Abrir AMPPS (MySQL).
2. Levantar el backend y la web, desde `MMM-Utha`:
   ```bash
   php -d upload_max_filesize=6M -d post_max_size=8M -S 0.0.0.0:8765
   ```
   Los `-d` dejan subir fotos de hasta 5 MB desde la app (PHP trae 2 MB).
3. Web: <http://127.0.0.1:8765/>. Live Server (puerto 5500) también funciona: los datos se piden solos al 8765.
4. App: `flutter run` en `app/MMM-Utha-App`, o *Run and Debug → Local* en VS Code.
   En un celular de verdad: `flutter run --dart-define=SITIO_URL=http://<IP de la Mac>:8765` (`ipconfig getifaddr en0`).

### Si cambia la estructura de la base

No se edita `base-de-datos.sql`. Cada cambio va en un archivo nuevo de `api/migraciones/` (`001-…sql`, `002-…sql`) y se aplica con:

```bash
php api/migrar.php
```

Así el mismo cambio llega después al servidor sin borrar sus datos.

## Subir cambios

1. Probar en local.
2. Hacer commit.
3. Backend (y web si va en el mismo VPS):
   ```bash
   ./desplegar.sh
   ```
   Muestra qué archivos cambian, pide confirmación, sube, aplica las migraciones pendientes y prueba `api/datos.php`. Nunca toca el `config.php` ni las fotos de `subidas/` del servidor.
4. Web en InfinityFree: `./publicar-web.sh`.
5. App, si cambió: `./compilar.sh ios` o `./compilar.sh android` en `app/MMM-Utha-App`. Siempre usa `entornos/produccion.json`.

**Orden:** primero el backend y después la app. Una app nueva que usa algo de la API que todavía no está en el servidor falla. Al revés no: la app vieja sigue funcionando con la API nueva.

## Primera vez: la web en InfinityFree

Sitio: <https://mmmutha.great-site.net>

1. En el panel de InfinityFree, *SSL Certificates*: activar el certificado gratuito para que la web abra con https. Sin https, la web no puede pedir datos al VPS en https desde algunos navegadores y se ve "No seguro".
2. En el *File Manager* borrar de `htdocs` los archivos de ejemplo (`index2.html` y similares).
3. Copiar `web.ejemplo.env` como `.web.env` y poner la clave de FTP (panel → la cuenta → *FTP Details*).
4. `./publicar-web.sh`: sube `index.html`, `paginas/`, `css/`, `js/` e `img/` a `htdocs`. Nunca `api/`.
5. Mientras no haya backend, la web muestra la copia `js/datos.json`.

## Primera vez en el servidor

Una sola vez, cuando el VPS esté listo (PHP 8.1+ con `pdo_mysql`, `mbstring` y `fileinfo`, MySQL y https):

1. Crear la base y su usuario en MySQL.
2. Elegir con qué contenido empieza:
   - **El inicial** (el de `js/datos.json`): importar `api/base-de-datos.sql`.
   - **El que ya está cargado en local**:
     ```bash
     /Applications/AMPPS/apps/mysql/bin/mysqldump -h127.0.0.1 -uroot -p mmm_utha > respaldo-local.sql
     ```
     Importar ese archivo en la base del servidor. Trae también las cuentas locales (entre ellas el maestro) y las rutas de fotos: copiar además la carpeta `subidas/` al servidor.
3. En el servidor, crear `api/config.php` a partir de `api/config.ejemplo.php` con los datos de esa base, la zona horaria y la clave del maestro.
4. Dar permiso de escritura a `subidas/` para el usuario del servidor web (en Ubuntu con Apache: `sudo chown -R www-data subidas`).
   Y en el `php.ini` de Apache (`/etc/php/*/apache2/php.ini`): `upload_max_filesize = 6M` y `post_max_size = 8M`; si no, las fotos de más de 2 MB fallan.
5. Copiar `desplegar.ejemplo.env` como `.desplegar.env` y completarlo.
6. `./desplegar.sh`
7. Abrir `https://…/api/datos.php`: tiene que mostrar el JSON. Ese primer pedido crea la cuenta maestra.
8. Poner la dirección del servidor en `SERVIDOR_PRODUCCION` (`js/datos.js`) si la web va en otro hosting, y en `app/MMM-Utha-App/entornos/produccion.json`.

Con Nginx en vez de Apache, los `.htaccess` no se aplican: ver *Web y API en servidores distintos* en `api/LEEME.md`.
