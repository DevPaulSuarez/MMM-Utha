/* ============================================================
   MMM · Lógica del sitio
   Depende de js/datos.js (debe cargarse antes que este archivo).
   ============================================================ */

document.addEventListener("DOMContentLoaded", () => {
  menuResponsive();
  anioActual();
  ajustarHeader();
  iniciarSlider();
  pintarRepresentantes();
  pintarHorarios();
  pintarAgenda();
  pintarNoticias();
});

/* Prefijo de rutas: las páginas dentro de /paginas necesitan "../" */
const RUTA = location.pathname.includes("/paginas/") ? "../" : "";

/* --- Menú hamburguesa ---------------------------------------- */
function menuResponsive() {
  const boton = document.getElementById("navToggle");
  const nav = document.getElementById("nav");
  if (!boton || !nav) return;

  boton.addEventListener("click", () => {
    const abierto = nav.classList.toggle("nav--abierto");
    /* La barra necesita saberlo para cuadrar sus esquinas con el
       panel que se despliega debajo */
    document.querySelector(".header")
      .classList.toggle("header--menu-abierto", abierto);
  });
}

/* --- Año dinámico del footer --------------------------------- */
function anioActual() {
  const span = document.getElementById("anio");
  if (span) span.textContent = new Date().getFullYear();
}

/* --- Header: medida y estado ----------------------------------
   Guarda el alto real en --alto-header (el CSS lo usa para montar
   el banner debajo) y decide como se ve la barra:
     - cristal esmerilado en cuanto se baja del inicio
     - contenido blanco mientras el banner esta detras, azul sobre
       el contenido claro
   Se revisa tambien con ResizeObserver porque el alto cambia
   cuando termina de cargar el logo, y con esa medida vieja el
   banner nunca llegaba a quedar detras.
   -------------------------------------------------------------- */
function ajustarHeader() {
  const header = document.querySelector(".header");
  if (!header) return;

  const banner = document.getElementById("banner");

  const revisar = () => {
    const alto = header.offsetHeight;
    document.documentElement.style.setProperty("--alto-header", `${alto}px`);

    /* El banner esta detras de la barra cuando ya llego al borde
       superior; en movil no se solapan hasta que se desplaza */
    const caja = banner && banner.getBoundingClientRect();
    const sobreBanner = caja && caja.top <= 1 && caja.bottom > alto;

    header.classList.toggle("header--sobre-claro", !sobreBanner);
    header.classList.toggle("header--desplazado", window.scrollY > 40);
  };

  revisar();
  window.addEventListener("scroll", revisar, { passive: true });
  window.addEventListener("resize", revisar);
  window.addEventListener("load", revisar);

  if (window.ResizeObserver) new ResizeObserver(revisar).observe(header);
}

/* --- Slider de banners ---------------------------------------
   Pinta las diapositivas desde BANNERS (js/datos.js) y las mueve
   Avanza solo; ademas se puede saltar con los puntos, el teclado
   o deslizando en pantallas tactiles.
   -------------------------------------------------------------- */
function iniciarSlider() {
  const slider = document.getElementById("banner");
  const pista = document.getElementById("bannerPista");
  const puntos = document.getElementById("bannerPuntos");
  if (!slider || !pista || typeof BANNERS === "undefined" || !BANNERS.length) return;

  /* Diapositivas */
  pista.innerHTML = BANNERS.map((b, i) => `
    <div class="slider__slide" role="group" aria-roledescription="diapositiva"
         aria-label="${i + 1} de ${BANNERS.length}">
      <img class="slider__imagen" src="${RUTA}${b.imagen}" alt="${b.alt || ""}"
           ${i === 0 ? 'fetchpriority="high"' : 'loading="lazy"'}>
    </div>
  `).join("");

  /* Puntos indicadores */
  puntos.innerHTML = BANNERS.map((_, i) => `
    <button class="slider__punto" type="button" role="tab"
            data-indice="${i}" aria-label="Ir al banner ${i + 1}"></button>
  `).join("");

  const listaPuntos = [...puntos.querySelectorAll(".slider__punto")];
  const total = BANNERS.length;
  let actual = 0;
  let temporizador = null;
  const INTERVALO = 5000;

  function mostrar(indice) {
    actual = (indice + total) % total;
    pista.style.transform = `translateX(-${actual * 100}%)`;
    listaPuntos.forEach((p, i) => {
      p.classList.toggle("slider__punto--activo", i === actual);
      p.setAttribute("aria-selected", i === actual);
    });
  }

  const siguiente = () => mostrar(actual + 1);
  const anterior  = () => mostrar(actual - 1);

  function arrancar() {
    if (total < 2) return;
    detener();
    temporizador = setInterval(siguiente, INTERVALO);
  }
  function detener() {
    clearInterval(temporizador);
    temporizador = null;
  }
  /* Reinicia el automatico despues de una accion manual */
  function tras(accion) {
    return () => { accion(); arrancar(); };
  }

  listaPuntos.forEach(p =>
    p.addEventListener("click", tras(() => mostrar(Number(p.dataset.indice))))
  );

  /* Pausa al pasar el mouse o al enfocar con el teclado */
  slider.addEventListener("mouseenter", detener);
  slider.addEventListener("mouseleave", arrancar);
  slider.addEventListener("focusin", detener);
  slider.addEventListener("focusout", arrancar);

  /* Flechas del teclado */
  slider.addEventListener("keydown", e => {
    if (e.key === "ArrowRight") tras(siguiente)();
    if (e.key === "ArrowLeft")  tras(anterior)();
  });

  /* Deslizamiento en pantallas tactiles */
  let inicioX = 0;
  slider.addEventListener("touchstart", e => {
    inicioX = e.touches[0].clientX;
    detener();
  }, { passive: true });

  slider.addEventListener("touchend", e => {
    const recorrido = e.changedTouches[0].clientX - inicioX;
    if (Math.abs(recorrido) > 50) (recorrido < 0 ? siguiente : anterior)();
    arrancar();
  }, { passive: true });

  mostrar(0);
  arrancar();
}

/* --- Formato de fecha en español ----------------------------- */
function formatearFecha(iso) {
  return new Date(iso + "T00:00:00").toLocaleDateString("es-ES", {
    day: "numeric", month: "long", year: "numeric"
  });
}

/* --- Representantes ------------------------------------------ */
function pintarRepresentantes() {
  const cont = document.getElementById("listaRepresentantes");
  if (!cont || typeof REPRESENTANTES === "undefined") return;

  cont.innerHTML = REPRESENTANTES.map(r => `
    <article class="tarjeta">
      <img src="${RUTA}${r.foto}" alt="${r.nombre}">
      <h3>${r.nombre}</h3>
      <p class="tarjeta__cargo">${r.cargo}</p>
      <p>${r.descripcion}</p>
    </article>
  `).join("");
}

/* --- Horarios ------------------------------------------------- */
function pintarHorarios() {
  const cuerpo = document.getElementById("tablaHorarios");
  if (!cuerpo || typeof HORARIOS === "undefined") return;

  cuerpo.innerHTML = HORARIOS.map(h => `
    <tr>
      <td>${h.dia}</td>
      <td>${h.hora}</td>
      <td>${h.actividad}</td>
      <td>${h.lugar}</td>
    </tr>
  `).join("");
}

/* --- Agenda semanal -------------------------------------------
   Almanaque del inicio: la semana se repite igual, asi que sale
   de AGENDA (js/datos.js). Se marca el dia de hoy.
   -------------------------------------------------------------- */
function pintarAgenda() {
  const cont = document.getElementById("agendaSemanal");
  if (!cont || typeof AGENDA === "undefined") return;

  /* getDay() empieza en domingo; AGENDA empieza en lunes */
  const hoy = (new Date().getDay() + 6) % 7;

  cont.innerHTML = AGENDA.map((d, i) => {
    const clases = [
      "agenda__dia",
      d.eventos.length ? "" : "agenda__dia--libre",
      i === hoy ? "agenda__dia--hoy" : ""
    ].filter(Boolean).join(" ");

    const eventos = d.eventos.length
      ? d.eventos.map(e => `
          <li>
            <span class="agenda__hora">${e.hora}</span>
            <span class="agenda__evento">${e.nombre}</span>
          </li>
        `).join("")
      : `<li class="agenda__sin">Sin actividades</li>`;

    return `
      <article class="${clases}">
        <h3 class="agenda__nombre">${d.dia}</h3>
        <ul>${eventos}</ul>
      </article>
    `;
  }).join("");
}

/* --- Noticias -------------------------------------------------
   Rellena la lista completa (noticias.html) o las destacadas
   del inicio, según el contenedor que exista en la página.
   -------------------------------------------------------------- */
function pintarNoticias() {
  const cont = document.getElementById("listaNoticias")
            || document.getElementById("noticiasDestacadas");
  if (!cont || typeof NOTICIAS === "undefined") return;

  const ordenadas = [...NOTICIAS].sort((a, b) => b.fecha.localeCompare(a.fecha));
  const lista = cont.id === "noticiasDestacadas" ? ordenadas.slice(0, 3) : ordenadas;

  cont.innerHTML = lista.map(n => `
    <article class="tarjeta">
      <img src="${RUTA}${n.imagen}" alt="${n.titulo}">
      <h3>${n.titulo}</h3>
      <p class="tarjeta__fecha">${formatearFecha(n.fecha)}</p>
      <p>${n.resumen}</p>
    </article>
  `).join("");
}
