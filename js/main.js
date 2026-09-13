/* ============================================================
   MMM · Lógica del sitio
   Depende de js/datos.js (debe cargarse antes que este archivo):
   de allí salen RUTA, rutaImagen() y el contenido del JSON.
   ============================================================ */

/* Lo que no depende del contenido se arma apenas está la página */
document.addEventListener("DOMContentLoaded", () => {
  menuResponsive();
  anioActual();
  ajustarHeader();
  encogerPortada();
  volverArriba();
});

/* Lo que se pinta desde el contenido espera a que termine de
   llegar. DATOS_LISTOS lo define js/datos.js y siempre
   se resuelve, aunque la carga falle. */
DATOS_LISTOS.then(() => {
  iniciarSlider();
  pintarRepresentantes();
  pintarHorarios();
  pintarAgenda();
  pintarNoticias();
  proximoCulto();
  enlazarMapa();

  /* lemas.js se carga después que este archivo, pero para cuando
     llegan los datos ya está disponible */
  if (typeof iniciarLemas === "function") iniciarLemas();

  /* Al final, con el contenido ya en su lugar: así los bloques se
     miden con su alto real y la aparición se ve como corresponde */
  animarAlDesplazar();
});

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

  /* Bloques de fondo oscuro: mientras uno de ellos esté detrás de la
     barra, su contenido va en blanco */
  const oscuros = [
    ...document.querySelectorAll(
      ".portada, .hero, .encabezado-pagina, .barra-info, .oracion, .footer"
    )
  ];

  const revisar = () => {
    const alto = header.offsetHeight;
    document.documentElement.style.setProperty("--alto-header", `${alto}px`);

    const sobreOscuro = oscuros.some((bloque) => {
      const caja = bloque.getBoundingClientRect();
      return caja.top <= 1 && caja.bottom > alto;
    });

    header.classList.toggle("header--sobre-claro", !sobreOscuro);
    header.classList.toggle("header--desplazado", window.scrollY > 40);
  };

  revisar();
  window.addEventListener("scroll", revisar, { passive: true });
  window.addEventListener("resize", revisar);
  window.addEventListener("load", revisar);

  if (window.ResizeObserver) new ResizeObserver(revisar).observe(header);
}

/* --- Portada que se encoge al bajar ---------------------------
   La portada abre a pantalla completa; durante el primer tramo de
   desplazamiento se va reduciendo y redondeando. Aquí solo se
   calcula cuánto se ha bajado (0 a 1) y el CSS hace el resto.
   En el celular no se aplica: allí la imagen se ve entera y el
   mensaje va debajo.
   -------------------------------------------------------------- */
function encogerPortada() {
  const portada = document.querySelector(".portada");
  if (!portada) return;

  const enMovil = window.matchMedia("(max-width: 820px)");
  const sinMovimiento = window.matchMedia("(prefers-reduced-motion: reduce)");

  let pedido = false;

  function calcular() {
    pedido = false;

    if (enMovil.matches || sinMovimiento.matches) {
      portada.style.removeProperty("--avance");
      return;
    }

    const recorrido = portada.offsetHeight || 1;
    const avance = Math.min(Math.max(window.scrollY / recorrido, 0), 1);
    portada.style.setProperty("--avance", avance.toFixed(3));
  }

  /* El cálculo se agrupa en el siguiente cuadro: así el
     desplazamiento no se entrecorta */
  function alDesplazar() {
    if (pedido) return;
    pedido = true;
    requestAnimationFrame(calcular);
  }

  window.addEventListener("scroll", alDesplazar, { passive: true });
  window.addEventListener("resize", alDesplazar);
  enMovil.addEventListener("change", calcular);
  calcular();
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
      <img class="slider__imagen" src="${rutaImagen(b.imagen)}" alt="${b.alt || ""}"
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

/* --- Enlaces al mapa ------------------------------------------
   Todo elemento con data-mapa apunta al templo. Si UBICACION trae
   coordenadas se mandan esas; si no, la dirección escrita. El href
   del HTML ya lleva la dirección, así que sigue funcionando aunque
   el navegador no ejecute este archivo.
   -------------------------------------------------------------- */
function enlazarMapa() {
  const enlaces = document.querySelectorAll("[data-mapa]");
  if (!enlaces.length || typeof UBICACION === "undefined") return;

  const destino = (UBICACION.coordenadas || UBICACION.direccion || "").trim();
  if (!destino) return;

  const url =
    "https://www.google.com/maps/search/?api=1&query=" +
    encodeURIComponent(destino);

  enlaces.forEach((enlace) => { enlace.href = url; });
}

/* --- Inicio estando ya en el inicio ---------------------------
   El logo y el enlace "Inicio" recargaban la página. Si ya estamos
   en ella basta con subir hasta arriba.
   -------------------------------------------------------------- */
function volverArriba() {
  if (location.pathname.includes("/paginas/")) return;

  const enlaces = document.querySelectorAll('a[href="index.html"]');
  if (!enlaces.length) return;

  const sinMovimiento = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  enlaces.forEach((enlace) => {
    enlace.addEventListener("click", (e) => {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: sinMovimiento ? "auto" : "smooth" });
      /* Quita el ancla de la barra de direcciones si quedó alguna */
      history.replaceState(null, "", location.pathname + location.search);
    });
  });
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
      <img src="${rutaImagen(r.foto)}" alt="${r.nombre}" loading="lazy"
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

  /* data-titulo lo usa el CSS: en el celular la tabla se convierte
     en fichas y cada dato necesita su rótulo al lado */
  cuerpo.innerHTML = HORARIOS.map(h => `
    <tr>
      <td data-titulo="Día">${h.dia}</td>
      <td data-titulo="Hora">${h.hora}</td>
      <td data-titulo="Actividad">${h.actividad}</td>
      <td data-titulo="Lugar">${h.lugar}</td>
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

  /* Sin publicaciones cargadas se avisa en vez de dejar un hueco */
  if (!lista.length) {
    cont.innerHTML = `
      <p class="vacio">
        Estamos preparando las próximas actividades.
        Escríbenos y te avisamos en cuanto haya novedades.
      </p>
    `;
    return;
  }

  cont.innerHTML = lista.map(n => `
    <article class="tarjeta">
      <img src="${rutaImagen(n.imagen)}" alt="${n.titulo}" loading="lazy"
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
