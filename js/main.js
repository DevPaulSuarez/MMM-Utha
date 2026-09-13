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
  pintarAgenda();
  pintarPrograma();
  pintarActividades();
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

/* --- Cultos ---------------------------------------------------
   CULTOS se repiten cada semana (dia: 1 = lunes … 7 = domingo) y
   CULTOS_EXTRA caen en una fecha. De ahí salen las fechas concretas
   que usan la agenda, el próximo culto y la página del programa.
   -------------------------------------------------------------- */
const DIAS = ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];

/* Lo que llega de la aplicación se escribe como texto, nunca como HTML */
function escapar(texto) {
  return String(texto ?? "").replace(/[&<>"']/g, (c) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
  })[c]);
}

/* Fecha local como "AAAA-MM-DD" (toISOString pasa a UTC y de noche
   daría el día siguiente) */
function fechaISO(fecha) {
  const mes = String(fecha.getMonth() + 1).padStart(2, "0");
  const dia = String(fecha.getDate()).padStart(2, "0");
  return `${fecha.getFullYear()}-${mes}-${dia}`;
}

function sumarDias(fecha, dias) {
  return new Date(fecha.getFullYear(), fecha.getMonth(), fecha.getDate() + dias);
}

function aMinutos(hhmm) {
  const [h, m] = hhmm.split(":").map(Number);
  return h * 60 + m;
}

/* "19:00" → ["7:00", "p. m."] */
function partesHora(hhmm) {
  const [h, m] = hhmm.split(":").map(Number);
  return [`${h % 12 || 12}:${String(m).padStart(2, "0")}`, h < 12 ? "a. m." : "p. m."];
}

/* "7:00 - 9:00 p. m." o "10:00 a. m. - 1:00 p. m.", como se
   escribía antes a mano */
function formatearHorario(inicio, fin) {
  const [ti, si] = partesHora(inicio);
  if (!fin) return `${ti} ${si}`;
  const [tf, sf] = partesHora(fin);
  return si === sf ? `${ti} - ${tf} ${sf}` : `${ti} ${si} - ${tf} ${sf}`;
}

/* Cultos de una fecha ordenados por hora, cada uno con su programa
   si el pastor ya lo cargó */
function cultosDelDia(fecha) {
  const iso = fechaISO(fecha);
  const dia = fecha.getDay() || 7;

  const fijos = CULTOS
    .filter((c) => Number(c.dia) === dia)
    .map((c) => ({
      ...c,
      fecha: iso,
      extra: false,
      programa: PROGRAMAS.find((p) => Number(p.culto_id) === Number(c.id) && p.fecha === iso)
    }));

  const extras = CULTOS_EXTRA
    .filter((c) => c.fecha === iso)
    .map((c) => ({
      ...c,
      extra: true,
      programa: PROGRAMAS.find((p) => Number(p.culto_extra_id) === Number(c.id))
    }));

  return [...fijos, ...extras].sort((a, b) => a.hora_inicio.localeCompare(b.hora_inicio));
}

function enlacePrograma(culto) {
  const consulta = culto.extra ? `extra=${culto.id}` : `culto=${culto.id}&fecha=${culto.fecha}`;
  return `${RUTA}paginas/programa.html?${consulta}`;
}

/* --- Agenda ---------------------------------------------------
   Los próximos siete días, empezando por hoy. Los cultos que ya
   tienen programa llevan a la página de quiénes participan.
   -------------------------------------------------------------- */
function pintarAgenda() {
  const cont = document.getElementById("agendaSemanal");
  if (!cont) return;

  const hoy = new Date();

  cont.innerHTML = Array.from({ length: 7 }, (_, i) => {
    const fecha = sumarDias(hoy, i);
    const cultos = cultosDelDia(fecha);

    const clases = [
      "agenda__dia",
      cultos.length ? "" : "agenda__dia--libre",
      i === 0 ? "agenda__dia--hoy" : ""
    ].filter(Boolean).join(" ");

    const eventos = cultos.length
      ? cultos.map((c) => {
          const detalle = `
            <span class="agenda__hora">${formatearHorario(c.hora_inicio, c.hora_fin)}</span>
            <span class="agenda__evento">${escapar(c.nombre)}</span>
            ${c.extra ? `<span class="agenda__marca">Especial</span>` : ""}
            ${c.programa ? `<span class="agenda__programa">Ver programa</span>` : ""}
          `;
          return c.programa
            ? `<li><a class="agenda__enlace" href="${enlacePrograma(c)}">${detalle}</a></li>`
            : `<li>${detalle}</li>`;
        }).join("")
      : `<li class="agenda__sin">Sin actividades</li>`;

    return `
      <article class="${clases}">
        <h3 class="agenda__nombre">${DIAS[fecha.getDay()]} <span class="agenda__fecha">${fecha.getDate()}</span></h3>
        <ul>${eventos}</ul>
      </article>
    `;
  }).join("");
}

/* --- Próximo culto --------------------------------------------
   El primero, desde ahora, que todavía no terminó. Sin hora de fin
   se da por terminado a las dos horas de empezar.
   -------------------------------------------------------------- */
function proximoCulto() {
  const destino = document.getElementById("proximoCulto");
  if (!destino) return;

  const ahora = new Date();
  const minutos = ahora.getHours() * 60 + ahora.getMinutes();

  for (let i = 0; i < 14; i++) {
    const fecha = sumarDias(ahora, i);
    const culto = cultosDelDia(fecha).find((c) =>
      i > 0 || (c.hora_fin ? aMinutos(c.hora_fin) : aMinutos(c.hora_inicio) + 120) > minutos
    );
    if (!culto) continue;

    const cuando = i === 0 ? "Hoy" : i === 1 ? "Mañana" : DIAS[fecha.getDay()];
    destino.textContent = `${cuando} · ${formatearHorario(culto.hora_inicio, culto.hora_fin)}`;

    const detalle = destino.nextElementSibling;
    if (detalle) detalle.textContent = culto.nombre;
    return;
  }
}

/* --- Programa de un culto -------------------------------------
   paginas/programa.html?culto=2&fecha=2026-09-19   culto fijo
   paginas/programa.html?extra=5                     culto extra
   -------------------------------------------------------------- */
const ROLES_PROGRAMA = [
  { rol: "presentador", titulo: "Presentador" },
  { rol: "participacion", titulo: "Participaciones", numerada: true },
  { rol: "predicacion", titulo: "Predicación" },
  { rol: "alabanza", titulo: "Alabanza" }
];

function buscarCultoDePagina() {
  const params = new URLSearchParams(location.search);

  if (params.has("extra")) {
    const extra = CULTOS_EXTRA.find((c) => String(c.id) === params.get("extra"));
    return extra && cultosDelDia(new Date(`${extra.fecha}T00:00:00`))
      .find((c) => c.extra && c.id === extra.id);
  }

  const fecha = params.get("fecha") || "";
  if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha)) return undefined;
  return cultosDelDia(new Date(`${fecha}T00:00:00`))
    .find((c) => !c.extra && String(c.id) === params.get("culto"));
}

function iniciales(nombre) {
  return nombre.trim().split(/\s+/).slice(0, 2).map((p) => p[0] || "").join("").toUpperCase();
}

const PRESENTACIONES = { musica: "Música", lectura: "Lectura", testimonio: "Testimonio" };

/* Sin foto, o si no carga, quedan las iniciales del fondo. Debajo del
   nombre va lo que el participante eligió presentar, si ya lo dijo. */
function personaPrograma(p) {
  const foto = p.foto
    ? `<img src="${escapar(rutaImagen(p.foto))}" alt="" loading="lazy" onerror="this.remove()">`
    : "";
  const presentacion = PRESENTACIONES[p.presentacion]
    ? [PRESENTACIONES[p.presentacion], p.detalle].filter(Boolean).join(" · ")
    : "";
  return `
    <li class="programa__persona">
      <span class="programa__avatar" data-iniciales="${escapar(iniciales(p.nombre))}">${foto}</span>
      <span class="programa__texto">
        <span class="programa__nombre">
          ${escapar(p.nombre)}
          ${p.representante_id ? "" : `<span class="programa__visita">Visita</span>`}
        </span>
        ${presentacion ? `<span class="programa__detalle">${escapar(presentacion)}</span>` : ""}
      </span>
    </li>
  `;
}

function pintarPrograma() {
  const cont = document.getElementById("programaCulto");
  if (!cont) return;

  const culto = buscarCultoDePagina();
  if (!culto) {
    cont.innerHTML = `
      <p class="vacio">
        No encontramos este culto: puede que ya haya pasado.
        Revisa los próximos en la agenda.
      </p>
    `;
    return;
  }

  const dia = new Date(`${culto.fecha}T00:00:00`).toLocaleDateString("es-ES", {
    weekday: "long", day: "numeric", month: "long"
  });
  const cuando = [
    dia.charAt(0).toUpperCase() + dia.slice(1),
    formatearHorario(culto.hora_inicio, culto.hora_fin),
    culto.lugar
  ].filter(Boolean).join(" · ");

  document.title = `MMM Utah | ${culto.nombre}`;
  const titulo = document.getElementById("programaTitulo");
  const subtitulo = document.getElementById("programaCuando");
  if (titulo) titulo.textContent = culto.nombre;
  if (subtitulo) subtitulo.textContent = cuando;

  const participantes = culto.programa ? culto.programa.participantes : [];
  if (!participantes.length) {
    cont.innerHTML = `
      <p class="vacio">
        Todavía no se publicó el programa de este culto. Vuelve a
        mirar más cerca de la fecha.
      </p>
    `;
    return;
  }

  cont.innerHTML = ROLES_PROGRAMA.map(({ rol, titulo: nombreRol, numerada }) => {
    const personas = participantes.filter((p) => p.rol === rol);
    if (!personas.length) return "";

    const lista = numerada ? "ol" : "ul";
    return `
      <section class="programa__bloque">
        <h2 class="programa__rol">${nombreRol}</h2>
        <${lista} class="programa__lista${numerada ? " programa__lista--numerada" : ""}">
          ${personas.map(personaPrograma).join("")}
        </${lista}>
      </section>
    `;
  }).join("");
}

/* --- Actividades ----------------------------------------------
   Las próximas, en la página Actividades. Cada tipo nombra el lugar
   a su manera; platillo y país solo los usan las ventas de comida.
   -------------------------------------------------------------- */
const TIPOS_ACTIVIDAD = {
  comida: { nombre: "Venta de comida", lugar: "Lugar" },
  paseo: { nombre: "Paseo", lugar: "Destino" },
  hospital: { nombre: "Visita a hospitales", lugar: "Hospital" },
  evangelismo: { nombre: "Evangelismo", lugar: "Punto de encuentro" },
  otra: { nombre: "Actividad", lugar: "Lugar" }
};

function pintarActividades() {
  const cont = document.getElementById("proximasActividades");
  if (!cont) return;

  /* La API ya manda solo las próximas; se filtra igual por si la
     página se abre días después o se usa la copia datos.json */
  const hoy = fechaISO(new Date());
  const lista = ACTIVIDADES
    .filter((a) => a.fecha >= hoy)
    .sort((a, b) => a.fecha.localeCompare(b.fecha) || (a.hora_inicio || "").localeCompare(b.hora_inicio || ""));

  if (!lista.length) {
    cont.innerHTML = `
      <p class="vacio">
        Pronto anunciaremos las próximas actividades. Escríbenos y te
        avisamos en cuanto haya novedades.
      </p>
    `;
    return;
  }

  cont.innerHTML = lista.map((a) => {
    const tipo = TIPOS_ACTIVIDAD[a.tipo] || TIPOS_ACTIVIDAD.otra;
    const dia = new Date(`${a.fecha}T00:00:00`).toLocaleDateString("es-ES", {
      weekday: "long", day: "numeric", month: "long"
    });
    const cuando = [
      dia.charAt(0).toUpperCase() + dia.slice(1),
      a.hora_inicio ? formatearHorario(a.hora_inicio, a.hora_fin) : ""
    ].filter(Boolean).join(" · ");

    const datos = [
      ["Cuándo", cuando],
      [tipo.lugar, a.lugar],
      ["Platillo", a.platillo],
      ["País", a.pais]
    ].filter(([, valor]) => valor);

    const imagen = a.imagen
      ? `<img src="${escapar(rutaImagen(a.imagen))}" alt="" loading="lazy"
             onerror="this.remove(); this.closest('.tarjeta').classList.add('tarjeta--sin-foto')">`
      : "";

    return `
      <article class="tarjeta${a.imagen ? "" : " tarjeta--sin-foto"}">
        ${imagen}
        <p class="tarjeta__cargo">${tipo.nombre}</p>
        <h3>${escapar(a.titulo)}</h3>
        <dl class="tarjeta__datos">
          ${datos.map(([rotulo, valor]) => `
            <div><dt>${rotulo}</dt><dd>${escapar(valor)}</dd></div>
          `).join("")}
        </dl>
        ${a.descripcion ? `<p>${escapar(a.descripcion)}</p>` : ""}
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
