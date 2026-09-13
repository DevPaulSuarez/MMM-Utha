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

/* De dónde se pide el contenido, en orden. Primero la API; si no
   responde (el sitio servido sin PHP, la base caída), la copia
   estática js/datos.json, para que la página no quede vacía.
   Esa copia no se actualiza sola: puede estar atrasada. */
const DATOS_URLS = [`${RUTA}api/datos.php`, `${RUTA}js/datos.json`];

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

/* Rutas de imagen. Las del proyecto y las que sube la aplicación
   ("subidas/…") se escriben desde la raíz y necesitan el prefijo;
   las que ya son direcciones completas se dejan tal cual. */
function rutaImagen(src) {
  if (!src) return "";
  const absoluta = /^(https?:)?\/\//i.test(src) || src.startsWith("/") || src.startsWith("data:");
  return absoluta ? src : RUTA + src;
}

/* cache: "no-cache" hace que el navegador revalide en cada visita:
   lo que publique la aplicación se ve enseguida.
   Si el servidor no ejecuta PHP devuelve el código del archivo en
   vez de JSON; res.json() falla y se pasa a la siguiente fuente. */
async function cargarDatos() {
  for (const url of DATOS_URLS) {
    try {
      const res = await fetch(url, { cache: "no-cache" });
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
