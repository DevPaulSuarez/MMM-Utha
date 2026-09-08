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
  proximoCulto();
  animarAlDesplazar();
});

/* Prefijo de rutas: las páginas dentro de /paginas necesitan "../" */
const RUTA = location.pathname.includes("/paginas/") ? "../" : "";

/* --- Menú hamburguesa ---------------------------------------- */
function menuResponsive() {
  const boton = document.getElementById("navToggle");
  const nav = document.getElementById("nav");
  const header = document.querySelector(".header");
  if (!boton || !nav) return;

  const alternar = (abrir) => {
    const abierto = nav.classList.toggle("nav--abierto", abrir);
    header.classList.toggle("header--menu-abierto", abierto);
    boton.setAttribute("aria-expanded", String(abierto));
    boton.setAttribute("aria-label", abierto ? "Cerrar menú" : "Abrir menú");
  };

  boton.addEventListener("click", () => alternar());

  /* Al elegir una sección el panel se cierra solo */
  nav.addEventListener("click", (e) => {
    if (e.target.closest(".nav__link")) alternar(false);
  });

  /* Y también con Escape o al tocar fuera */
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && nav.classList.contains("nav--abierto")) alternar(false);
  });

  document.addEventListener("click", (e) => {
    if (!nav.classList.contains("nav--abierto")) return;
    if (!e.target.closest(".header__interior")) alternar(false);
  });
}

/* --- Año dinámico del footer --------------------------------- */
function anioActual() {
  const span = document.getElementById("anio");
  if (span) span.textContent = new Date().getFullYear();
}

/* --- Header: medida y estado ----------------------------------
   Guarda el alto real en --alto-header (el CSS lo usa para montar
   el hero debajo) y decide cómo se ve la barra:
     - cristal esmerilado en cuanto se baja del inicio
     - contenido blanco mientras el banner está detrás, oscuro
       sobre el contenido claro
   Se revisa también con ResizeObserver porque el alto cambia
   cuando termina de cargar el logo.
   -------------------------------------------------------------- */
function ajustarHeader() {
  const header = document.querySelector(".header");
  if (!header) return;

  const banner = document.getElementById("banner");
  const portada = document.querySelector(".encabezado-pagina");
  /* Fondo oscuro detrás de la barra: el hero del inicio o la
     portada de las páginas internas */
  const oscuro = banner || portada;

  const revisar = () => {
    const alto = header.offsetHeight;
    document.documentElement.style.setProperty("--alto-header", `${alto}px`);

    const caja = oscuro && oscuro.getBoundingClientRect();
    const sobreOscuro = caja && caja.top <= 1 && caja.bottom > alto;

    header.classList.toggle("header--sobre-claro", !sobreOscuro);
    header.classList.toggle("header--desplazado", window.scrollY > 40);
  };

  revisar();
  window.addEventListener("scroll", revisar, { passive: true });
  window.addEventListener("resize", revisar);
  window.addEventListener("load", revisar);

  if (window.ResizeObserver) new ResizeObserver(revisar).observe(header);
}

/* --- Slider del hero ------------------------------------------
   Pinta las diapositivas desde BANNERS (js/datos.js) y las mueve.
   Avanza solo; además se puede saltar con las flechas, los puntos,
   el teclado o deslizando en pantallas táctiles.
   -------------------------------------------------------------- */
function iniciarSlider() {
  const slider = document.getElementById("banner");
  const pista = document.getElementById("bannerPista");
  const puntos = document.getElementById("bannerPuntos");
  if (!slider || !pista || typeof BANNERS === "undefined" || !BANNERS.length) return;

  pista.innerHTML = BANNERS.map((b, i) => `
    <div class="slider__slide" role="group" aria-roledescription="diapositiva"
         aria-label="${i + 1} de ${BANNERS.length}">
      <img class="slider__imagen" src="${RUTA}${b.imagen}" alt="${b.alt || ""}"
           ${i === 0 ? 'fetchpriority="high"' : 'loading="lazy"'}>
    </div>
  `).join("");

  puntos.innerHTML = BANNERS.map((_, i) => `
    <button class="slider__punto" type="button" role="tab"
            data-indice="${i}" aria-label="Ir al banner ${i + 1}"></button>
  `).join("");

  const listaPuntos = [...puntos.querySelectorAll(".slider__punto")];
  const total = BANNERS.length;
  let actual = 0;
  let temporizador = null;
  const INTERVALO = 6000;

  function mostrar(indice) {
    actual = (indice + total) % total;
    pista.style.transform = `translateX(-${actual * 100}%)`;
    listaPuntos.forEach((p, i) => {
      p.classList.toggle("slider__punto--activo", i === actual);
      p.setAttribute("aria-selected", i === actual);
    });
  }

  const siguiente = () => mostrar(actual + 1);
  const anterior = () => mostrar(actual - 1);

  function arrancar() {
    if (total < 2) return;
    detener();
    temporizador = setInterval(siguiente, INTERVALO);
  }
  function detener() {
    clearInterval(temporizador);
    temporizador = null;
  }
  /* Reinicia el automático después de una acción manual */
  const tras = (accion) => () => { accion(); arrancar(); };

  listaPuntos.forEach((p) =>
    p.addEventListener("click", tras(() => mostrar(Number(p.dataset.indice))))
  );

  const prev = document.getElementById("bannerPrev");
  const next = document.getElementById("bannerNext");
  if (prev) prev.addEventListener("click", tras(anterior));
  if (next) next.addEventListener("click", tras(siguiente));

  /* Pausa al pasar el mouse o al enfocar con el teclado */
  slider.addEventListener("mouseenter", detener);
  slider.addEventListener("mouseleave", arrancar);
  slider.addEventListener("focusin", detener);
  slider.addEventListener("focusout", arrancar);

  /* Y también cuando la pestaña queda en segundo plano */
  document.addEventListener("visibilitychange", () =>
    document.hidden ? detener() : arrancar()
  );

  slider.addEventListener("keydown", (e) => {
    if (e.key === "ArrowRight") tras(siguiente)();
    if (e.key === "ArrowLeft") tras(anterior)();
  });

  /* Deslizamiento en pantallas táctiles */
  let inicioX = 0;
  slider.addEventListener("touchstart", (e) => {
    inicioX = e.touches[0].clientX;
    detener();
  }, { passive: true });

  slider.addEventListener("touchend", (e) => {
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
      <img src="${RUTA}${r.foto}" alt="${r.nombre}" loading="lazy"
           onerror="this.remove(); this.closest('.tarjeta').classList.add('tarjeta--sin-foto')">
      <p class="tarjeta__cargo">${r.cargo}</p>
      <h3>${r.nombre}</h3>
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
   La semana se repite igual, así que sale de AGENDA (js/datos.js).
   Se marca el día de hoy.
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

/* --- Próximo culto --------------------------------------------
   Recorre la semana desde hoy y muestra la primera actividad que
   encuentra en la barra de contacto del inicio.
   -------------------------------------------------------------- */
function proximoCulto() {
  const destino = document.getElementById("proximoCulto");
  if (!destino || typeof AGENDA === "undefined") return;

  const hoy = (new Date().getDay() + 6) % 7;

  for (let i = 0; i < AGENDA.length; i++) {
    const dia = AGENDA[(hoy + i) % AGENDA.length];
    if (!dia.eventos.length) continue;

    const cuando = i === 0 ? "Hoy" : dia.dia;
    destino.textContent = `${cuando} · ${dia.eventos[0].hora}`;

    const detalle = destino.nextElementSibling;
    if (detalle) detalle.textContent = dia.eventos[0].nombre;
    return;
  }
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
      <img src="${RUTA}${n.imagen}" alt="${n.titulo}" loading="lazy"
           onerror="this.remove(); this.closest('.tarjeta').classList.add('tarjeta--sin-foto')">
      <p class="tarjeta__fecha">${formatearFecha(n.fecha)}</p>
      <h3>${n.titulo}</h3>
      <p>${n.resumen}</p>
    </article>
  `).join("");
}

/* --- Aparición al desplazar -----------------------------------
   Los bloques con .reveal entran cuando se acercan a la pantalla.
   Si el visitante pidió menos movimiento, se muestran de una vez.
   -------------------------------------------------------------- */
function animarAlDesplazar() {
  const bloques = document.querySelectorAll(".reveal");
  if (!bloques.length) return;

  const sinMovimiento = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (sinMovimiento || !("IntersectionObserver" in window)) {
    bloques.forEach(b => b.classList.add("es-visible"));
    return;
  }

  const observador = new IntersectionObserver((entradas) => {
    entradas.forEach((entrada) => {
      if (!entrada.isIntersecting) return;
      entrada.target.classList.add("es-visible");
      observador.unobserve(entrada.target);
    });
  }, { threshold: .12, rootMargin: "0px 0px -60px" });

  bloques.forEach(b => observador.observe(b));
}
