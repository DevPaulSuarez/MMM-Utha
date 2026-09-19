/* ============================================================
   MMM · Carga del contenido
   El contenido del sitio vive en la base de datos y lo sirve
   api/datos.php; este archivo lo trae y lo deja listo para
   main.js y lemas.js.

   La aplicación del pastor escribe en esa base, así que lo que
   cargue desde el celular aparece aquí sin tocar el código.
   ============================================================ */

/* Prefijo de rutas: las páginas dentro de /paginas necesitan "../" */
const RUTA = location.pathname.includes("/paginas/") ? "../" : "";

/* Servidor con la API y las fotos que sube la aplicación, sin barra
   final. Se elige solo según dónde se abre la página, así el mismo
   archivo sirve en la computadora y en internet sin tocarlo:

   - En la computadora (localhost, 127.0.0.1, la red de la casa o el
     archivo abierto directo) es el backend local: php -S en el puerto
     PUERTO_API_LOCAL. Si la página la sirve otro programa (Live Server,
     puerto 5500) los datos se piden igual a ese puerto.
   - En internet es SERVIDOR_PRODUCCION: la dirección del VPS, con https
     ("https://api.tudominio.org"). Vacío si la web y la API están en el
     mismo hosting. */
const SERVIDOR_PRODUCCION = "https://mmm.devpess.com";
const PUERTO_API_LOCAL = "8765";

const ES_LOCAL =
  location.protocol === "file:" ||
  /^(localhost|127\.0\.0\.1|\[::1\]|10\.\d+\.\d+\.\d+|192\.168\.\d+\.\d+|.+\.local)$/.test(location.hostname);

const SERVIDOR = !ES_LOCAL
  ? SERVIDOR_PRODUCCION
  : location.port === PUERTO_API_LOCAL
    ? ""
    : `http://${location.hostname || "127.0.0.1"}:${PUERTO_API_LOCAL}`;
const RUTA_SERVIDOR = SERVIDOR ? SERVIDOR.replace(/\/+$/, "") + "/" : RUTA;

/* De dónde se pide el contenido, en orden. Primero la API; si no
   responde (el sitio servido sin PHP, la base caída), la copia
   estática js/datos.json, para que la página no quede vacía.
   Esa copia no se actualiza sola: puede estar atrasada. */
const DATOS_URLS = [`${RUTA_SERVIDOR}api/datos.php`, `${RUTA}js/datos.json`];

/* Contenido del sitio. Empiezan vacíos y se llenan al terminar la
   carga; main.js pinta recién entonces. */
let UBICACION = { direccion: "", coordenadas: "" };
let BANNERS = [];
let LEMAS = [];
let REPRESENTANTES = [];
let CULTOS = [];        /* se repiten cada semana */
let CULTOS_EXTRA = [];  /* en una fecha puntual, de hoy en adelante */
let PROGRAMAS = [];     /* quiénes participan en cada culto */
let ACTIVIDADES = [];   /* ventas, paseos, visitas… de hoy en adelante */
let NOTICIAS = [];

/* false cuando ninguna fuente respondió. Los que pintan lo consultan
   para avisar en la página en vez de mostrar una sección vacía, que se
   lee como "no hay nada" cuando en realidad no se pudo cargar. */
let DATOS_OK = true;

/* Rutas de imagen. Las del proyecto ("img/…") y las que sube la
   aplicación ("subidas/…") se escriben desde la raíz y necesitan
   el prefijo; las subidas viven en el SERVIDOR de la API. Las que
   ya son direcciones completas se dejan tal cual. */
function rutaImagen(src) {
  if (!src) return "";
  const absoluta = /^(https?:)?\/\//i.test(src) || src.startsWith("/") || src.startsWith("data:");
  if (absoluta) return src;
  return (src.startsWith("subidas/") ? RUTA_SERVIDOR : RUTA) + src;
}

/* cache: "no-cache" hace que el navegador revalide en cada visita:
   lo que publique la aplicación se ve enseguida.
   Si el servidor no ejecuta PHP devuelve el código del archivo en
   vez de JSON; res.json() falla y se pasa a la siguiente fuente.

   El timeout corta la espera: sin él, un servidor que acepta la
   conexión y no contesta deja la página en blanco hasta que el
   navegador se rinde, en vez de pasar a la copia estática. */
const ESPERA_MAXIMA = 6000;

async function cargarDatos() {
  for (const url of DATOS_URLS) {
    try {
      const res = await fetch(url, {
        cache: "no-cache",
        signal: AbortSignal.timeout(ESPERA_MAXIMA),
      });
      if (!res.ok) throw new Error(`respondió ${res.status}`);
      return await res.json();
    } catch (error) {
      console.warn(`No se pudo cargar ${url}.`, error);
    }
  }
  throw new Error("Ninguna fuente de contenido respondió");
}

/* La carga se dispara al leer este archivo, antes de que termine
   de armarse la página, para no perder tiempo.

   Si todo falla, la promesa igual se resuelve para que el sitio
   siga funcionando: el menú, el mapa y los textos fijos no
   dependen del contenido. */
const DATOS_LISTOS = cargarDatos()
  .then((datos) => {
    UBICACION = datos.ubicacion || UBICACION;
    BANNERS = datos.banners || [];
    LEMAS = datos.lemas || [];
    REPRESENTANTES = datos.representantes || [];
    CULTOS = datos.cultos || [];
    CULTOS_EXTRA = datos.cultos_extra || [];
    PROGRAMAS = datos.programas || [];
    ACTIVIDADES = datos.actividades || [];
    NOTICIAS = datos.noticias || [];
    return datos;
  })
  .catch((error) => {
    DATOS_OK = false;
    console.error("El sitio se muestra sin contenido.", error);
    /* Abrir el index con doble clic (file://) no sirve: el navegador
       no deja leer archivos vecinos. Hay que levantar un servidor
       con PHP para que responda también la API. */
    if (location.protocol === "file:") {
      console.error(
        "El sitio se abrió como archivo local. Servilo con PHP " +
        "(php -S localhost:5500) y entrá por http://localhost:5500"
      );
    }
    return null;
  });
