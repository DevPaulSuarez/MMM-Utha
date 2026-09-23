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
4. Web en InfinityFree: `./preparar-web.sh` y subir `web-mmm.zip` con el File Manager a `htdocs` (extraer y reemplazar), o `./publicar-web.sh` por FTP. Siempre con el script: les pone a los `.css` y `.js` una versión nueva cuando cambian, así los visitantes ven la actualización sin borrar la caché.
5. App, si cambió: `./compilar.sh ios` o `./compilar.sh android` en `app/MMM-Utha-App`. Siempre usa `entornos/produccion.json`. Para que la gente la actualice, ver abajo.

**Orden:** primero el backend y después la app. Una app nueva que usa algo de la API que todavía no está en el servidor falla. Al revés no: la app vieja sigue funcionando con la API nueva.

## Pedir a todos que actualicen la app

Los celulares no se actualizan solos ni se enteran de que hay algo nuevo. Cada vez que se abre la app (y cada vez que se vuelve a ella), consulta `api/version.php`: si su versión es menor que la `minima` del servidor, se queda en la pantalla **Actualiza la aplicación**, con el botón para bajar la nueva. No se puede seguir usando la vieja.

Al publicar una versión nueva, en este orden:

1. Subir el número en `app/MMM-Utha-App/pubspec.yaml` (`version: 1.1.0+2`: el `+2` sube siempre, es el que exigen las tiendas).
2. Dejar la versión nueva **lista para bajar**:
   ```bash
   ./publicar-apk.sh
   ```
   Compila el APK, lo sube al VPS y lo deja en `https://mmm.devpess.com/app/mmm-utha.apk`. Con las tiendas: `./compilar.sh android` (o `ios`), publicar, y **esperar a que Google o Apple aprueben**; hasta que la gente no la pueda bajar, no seguir.
3. Recién entonces poner ese mismo número en `minima` de `api/version-app.php` y subirlo con `./desplegar.sh`.

Si se hace el paso 3 antes que el 2, todos quedan con la app detenida y sin nada nuevo que instalar. Para volver atrás: dejar el número anterior en `minima` y `./desplegar.sh` otra vez.

En `api/version-app.php` van también las direcciones de descarga (`android`, `ios`, `apk`), que es lo que abre el botón, y un `mensaje` opcional para explicar el cambio.

### Mientras no esté en las tiendas

El APK se reparte desde el propio servidor: `./publicar-apk.sh` lo sube y ese es el enlace que se le pasa a la gente y el que abre el botón de la pantalla. Cada celular Android tiene que permitir una vez *instalar apps de esta procedencia* (se lo pregunta solo al abrir el archivo).

En iPhone no se puede instalar un APK, así que ahí la pantalla no muestra botón: dice que pregunten en la iglesia. Para los iPhone hace falta TestFlight o la App Store; cuando haya, ese enlace va en `ios` de `api/version-app.php` y se usa solo.

Hay cambios que no obligan a nada: si solo cambió el contenido (cultos, noticias, fotos) no hace falta tocar `minima`, porque la app lo pide al servidor cada vez. Se sube `minima` cuando cambió la app misma.

Para ver cómo se ve esa pantalla sin publicar nada: en VS Code, *Run and Debug → Producción (versión vieja)*.

## Primera vez: la web en InfinityFree

Sitio: <https://mmmutha.great-site.net>

1. En el panel de InfinityFree, *SSL Certificates*: activar el certificado gratuito para que la web abra con https. Sin https, la web no puede pedir datos al VPS en https desde algunos navegadores y se ve "No seguro".
2. En el *File Manager* borrar de `htdocs` los archivos de ejemplo (`index2.html` y similares).
3. Copiar `web.ejemplo.env` como `.web.env` y poner la clave de FTP (panel → la cuenta → *FTP Details*).
4. `./publicar-web.sh`: arma la web (`preparar-web.sh`) y sube `index.html`, `paginas/`, `css/`, `js/`, `img/` y el `.htaccess` de caché a `htdocs`. Nunca `api/`. Sin FTP: `./preparar-web.sh` y subir `web-mmm.zip` con el File Manager.
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
