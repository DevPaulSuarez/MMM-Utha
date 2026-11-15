/* ============================================================
   MMM · Lemas por año
   Los datos están en LEMAS (api/datos.php): anio, titulo, texto,
   verso (la cita con su referencia al final) e imagen. Dos piezas:
   - Inicio (#lemaDelAnio): solo el lema de este año y un botón a
     la página de todos.
   - paginas/lemas.html (#vitrinaLemas): la vitrina a pantalla
     completa. Un solo estado (el lema elegido) mueve a la vez el
     fondo, el título, la descripción, la tira de tarjetas, el riel
     y el contador. Las animaciones son de GSAP; si no cargó, o si
     la persona pidió menos movimiento, todo cambia sin animar.
   ============================================================ */

/* Lo llama main.js cuando termina de llegar el contenido */
function iniciarLemas() {
  if (typeof LEMAS === "undefined") return;
  pintarLemaDelAnio();
  iniciarVitrinaLemas();
}

/* Del más nuevo al más viejo */
function lemasOrdenados() {
  return [...LEMAS].sort((a, b) => String(b.anio).localeCompare(String(a.anio)));
}

/* Lo que escribe el pastor es texto: solo los saltos de línea se
   vuelven HTML, con el resto ya escapado */
function textoConSaltos(texto) {
  return escapar(texto).replace(/\n/g, "<br>");
}

/* El verso llega como "“Yo y mi casa serviremos a Jehová. Josué 24:15”":
   se separa la cita de la referencia, que va en <cite>. Reconoce
   "Salmos 133:1", "1 Pedro 2:12" y "Filipenses 2:9-10". Si no hay
   referencia al final, todo queda como cita. */
const REFERENCIA_FINAL =
  /\s*((?:[1-3]\s?)?[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s(?:de\s)?[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?\s\d+:\d+(?:\s?[-–]\s?\d+)?)\s*["”»]?\s*\.?\s*$/;

function partirVerso(verso) {
  const limpio = String(verso || "").trim();
  const encontrada = limpio.match(REFERENCIA_FINAL);
  const referencia = encontrada ? encontrada[1] : "";
  let cita = (encontrada ? limpio.slice(0, encontrada.index) : limpio)
    .replace(/^["“«\s]+/, "")
    .replace(/["”»\s]*\.?["”»\s]*$/, "")
    .trim();
  /* Si al quitar las comillas de afuera quedó una de adentro sin
     cerrar ("Heme aquí, envíame a mí), se cierra */
  if ((cita.match(/"/g) || []).length % 2) cita += '"';
  return { cita, referencia };
}

/* <blockquote> con la referencia en <cite> */
function versoHTML(verso, clase) {
  const { cita, referencia } = partirVerso(verso);
  if (!cita && !referencia) return "";
  return `
    <blockquote class="${clase}">
      ${cita ? `<p>“${escapar(cita)}”</p>` : ""}
      ${referencia ? `<footer><cite>${escapar(referencia)}</cite></footer>` : ""}
    </blockquote>`;
}

/* --- Inicio: el lema de este año ------------------------------
   El del año en curso (hora de Utah); si todavía no lo cargaron,
   el más reciente.
   -------------------------------------------------------------- */
function pintarLemaDelAnio() {
  const destino = document.getElementById("lemaDelAnio");
  if (!destino) return;

  const lemas = lemasOrdenados();
  if (!lemas.length) {
    pintarVacio(destino, "Estamos preparando esta sección. Vuelve pronto.");
    return;
  }

  const anio = String(ahoraEnLaIglesia().getFullYear());
  const lema = lemas.find((l) => String(l.anio) === anio) || lemas[0];
  const imagen = rutaImagen(lema.imagen);

  destino.innerHTML = `
    <figure class="lema-anio__foto">
      ${imagen ? `<img src="${escapar(imagen)}" alt="Lema ${escapar(lema.anio)}: ${escapar(lema.titulo)}" loading="lazy" decoding="async">` : ""}
    </figure>
    <div class="lema-anio__texto">
      <p class="eyebrow">Lema ${escapar(lema.anio)}</p>
      <h2 class="lema-anio__titulo">${escapar(lema.titulo)}</h2>
      <!-- En el inicio solo la cita; el texto completo está en la página de lemas -->
      ${versoHTML(lema.verso, "lema-anio__verso")}
      <a class="btn btn--oscuro" href="paginas/lemas.html#${encodeURIComponent(lema.anio)}">Ver todos los lemas</a>
    </div>
  `;
}

/* --- Página de lemas: la vitrina ------------------------------ */
function iniciarVitrinaLemas() {
  const vitrina = document.getElementById("vitrinaLemas");
  if (!vitrina) return;

  const $ = (id) => document.getElementById(id);
  const lemas = lemasOrdenados();
  const total = lemas.length;

  const el = {
    fondo: $("vitrinaFondo"),
    capas: [...vitrina.querySelectorAll(".vitrina__capa")],
    principal: $("vitrinaPrincipal"),
    eyebrow: $("vitrinaEyebrow"),
    titulo: $("vitrinaTitulo"),
    verso: $("vitrinaVerso"),
    arriba: $("vitrinaFantasmaArriba"),
    abajo: $("vitrinaFantasmaAbajo"),
    ventana: vitrina.querySelector(".vitrina__ventana"),
    tira: $("vitrinaTira"),
    actual: $("vitrinaActual"),
    totalTxt: $("vitrinaTotal"),
    progreso: $("vitrinaProgreso"),
    puntos: $("vitrinaPuntos"),
    rielTxt: $("vitrinaRielTxt")
  };

  if (!total) {
    pintarVacio(el.principal, "Estamos preparando esta sección. Vuelve pronto.");
    vitrina.querySelector(".vitrina__lado").hidden = true;
    return;
  }

  const G = window.gsap;
  const sinMovimiento = window.matchMedia("(prefers-reduced-motion: reduce)");
  const animar = () => Boolean(G) && !sinMovimiento.matches;
  const dosCifras = (n) => String(n).padStart(2, "0");

  el.totalTxt.textContent = dosCifras(total);

  /* Tarjetas: se pintan una vez, todas; al cambiar de lema la tira
     se corre y la elegida crece. El año va sobre la foto y la cita
     bíblica debajo del nombre. */
  el.tira.innerHTML = lemas.map((lema, i) => {
    const imagen = rutaImagen(lema.imagen);
    const { referencia } = partirVerso(lema.verso);
    return `
      <button class="vitrina__tarjeta" type="button" data-ir="${i}"
              aria-label="Lema ${escapar(lema.anio)}: ${escapar(lema.titulo)}">
        <span class="vitrina__tarjeta-cabeza">
          <strong>${escapar(lema.titulo)}</strong>
          ${referencia ? `<cite>${escapar(referencia)}</cite>` : ""}
        </span>
        <span class="vitrina__tarjeta-foto">
          ${imagen ? `<img src="${escapar(imagen)}" alt="" loading="${i < 4 ? "eager" : "lazy"}" decoding="async">` : ""}
          <span class="vitrina__tarjeta-sombra" aria-hidden="true"></span>
          <span class="vitrina__tarjeta-anio">${escapar(lema.anio)}</span>
        </span>
      </button>`;
  }).join("");
  const tarjetas = [...el.tira.children];

  /* Riel: un punto por lema sobre la línea vertical; el elegido se
     agranda y muestra su número. Tocar un punto lleva a ese lema */
  el.puntos.innerHTML = lemas.map((lema, i) => `
    <li>
      <button class="vitrina__punto" type="button" data-ir="${i}"
              aria-label="Lema ${escapar(lema.anio)}: ${escapar(lema.titulo)}">
        <span>${i + 1}</span>
      </button>
    </li>`).join("");
  const puntos = [...el.puntos.querySelectorAll(".vitrina__punto")];

  let actual = -1;
  let capaVisible = 0;
  let lineaTexto = null;

  /* --- La pila de tarjetas ------------------------------------
     Como discos en una caja vista de lado: la del frente entera y
     detrás asoman las que siguen, cada una más chica (ESCALAS). En
     el celular se ven 3, en pantallas grandes 4. Gira sin fin: la
     del frente sale por la izquierda y vuelve a entrar al fondo. */
  const ESCALAS = [1, .8, .5, .35];
  const ASOMA = .22;          /* cuánto se ve cada una detrás de la anterior (del ancho del frente) */
  /* La del frente a todo color; las de atrás, cada vez más
     difuminadas (px de desenfoque) y apenas más oscuras */
  const DIFUMINADO = [0, 2.5, 5, 7];
  const SOMBRA = [0, .1, .2, .3];
  const enCelular = window.matchMedia("(max-width: 820px)");
  const visibles = () => Math.min(enCelular.matches ? 3 : 4, total);

  /* style.css define el ancho del frente con clamp y vw: una sonda
     invisible lo convierte en píxeles */
  const sonda = document.createElement("span");
  sonda.className = "vitrina__medida";
  sonda.setAttribute("aria-hidden", "true");
  el.ventana.appendChild(sonda);

  /* Lugar de cada puesto de la pila: el borde derecho de cada una
     asoma ASOMA más allá del de la anterior */
  function puestos() {
    const ancho = sonda.offsetWidth;
    const lista = [];
    let derecha = 0;
    ESCALAS.slice(0, visibles()).forEach((escala, r) => {
      derecha = r === 0 ? ancho : derecha + ancho * ASOMA;
      lista.push({ x: derecha - ancho * escala, scale: escala, sombra: SOMBRA[r], blur: DIFUMINADO[r] });
    });
    return { ancho, lista };
  }

  /* direccion: 1 adelante, -1 atrás, 0 sin animar */
  /* salto: cuántos puestos avanzó (tocar la 3.ª de la pila salta 2) */
  function colocarTira(i, conAnimacion, direccion = 1, salto = 1) {
    const { ancho, lista } = puestos();
    const fondo = lista[lista.length - 1];

    tarjetas.forEach((tarjeta, k) => {
      const r = (k - i + total) % total;          /* puesto en la pila */
      const enPila = r < lista.length;
      const saliendo = conAnimacion && direccion > 0 && r >= total - salto;
      const entrando = conAnimacion && direccion < 0 && r === 0;

      const destino = enPila
        ? { x: lista[r].x, scale: lista[r].scale, opacity: 1, zIndex: total - r }
        : { x: fondo.x + ancho * ASOMA * .5, scale: fondo.scale * .8, opacity: 0, zIndex: 0 };

      tarjeta.classList.toggle("vitrina__tarjeta--actual", r === 0);
      tarjeta.toggleAttribute("aria-current", r === 0);
      tarjeta.tabIndex = enPila && r > 0 ? 0 : -1;
      tarjeta.style.pointerEvents = enPila ? "" : "none";

      const sombra = tarjeta.querySelector(".vitrina__tarjeta-sombra");
      const cabeza = tarjeta.querySelector(".vitrina__tarjeta-cabeza");
      const sombraDestino = enPila ? lista[r].sombra : .4;
      const foto = tarjeta.querySelector("img");
      const filtro = `blur(${enPila ? lista[r].blur : 8}px)`;

      if (!conAnimacion) {
        if (G) {
          G.set(tarjeta, { ...destino, transformOrigin: "left center" });
          G.set(sombra, { opacity: sombraDestino });
          G.set(cabeza, { opacity: r === 0 ? 1 : 0 });
          if (foto) G.set(foto, { filter: filtro });
        } else {
          tarjeta.style.transformOrigin = "left center";
          tarjeta.style.transform = `translateX(${destino.x}px) scale(${destino.scale})`;
          tarjeta.style.opacity = destino.opacity;
          tarjeta.style.zIndex = destino.zIndex;
          sombra.style.opacity = sombraDestino;
          cabeza.style.opacity = r === 0 ? 1 : 0;
          if (foto) foto.style.filter = filtro;
        }
        return;
      }

      const animacion = { duration: 1.1, ease: "power3.inOut", overwrite: "auto" };

      /* Al fondo de la pila: el puesto de la última, un poco más allá */
      const atras = { x: fondo.x + ancho * ASOMA * .5, scale: fondo.scale * .9 };

      if (saliendo) {
        /* La del frente se levanta un poco, pasa por detrás de todas y
           viaja a la última fila, achicándose y difuminándose, como un
           disco que se devuelve al fondo de la caja */
        G.killTweensOf([tarjeta, foto, sombra, cabeza]);
        G.to(cabeza, { opacity: 0, duration: .25 });
        G.timeline()
          .to(tarjeta, { x: -ancho * .14, y: -ancho * .05, scale: 1.03, duration: .35, ease: "power2.out" })
          .set(tarjeta, { zIndex: 0 })
          .to(tarjeta, { x: atras.x, y: 0, scale: atras.scale, duration: .85, ease: "power3.inOut" })
          .to(foto, { filter: "blur(8px)", duration: .85, ease: "power3.inOut" }, "<")
          .to(sombra, { opacity: .4, duration: .85 }, "<")
          .to(tarjeta, { opacity: destino.opacity, duration: .35 }, ">-.25")
          .set(tarjeta, destino);
        return;
      }

      if (entrando) {
        /* Hacia atrás, al revés: sale del fondo, pasa por delante y
           queda al frente */
        G.killTweensOf([tarjeta, foto, sombra, cabeza]);
        G.timeline()
          .set(tarjeta, { x: atras.x, y: 0, scale: atras.scale, opacity: 0, zIndex: 0 })
          .to(tarjeta, { x: -ancho * .14, y: -ancho * .05, scale: 1.03, opacity: 1, duration: .75, ease: "power3.inOut" })
          .to(foto, { filter: "blur(0px)", duration: .75, ease: "power3.inOut" }, "<")
          .to(sombra, { opacity: 0, duration: .75 }, "<")
          .set(tarjeta, { zIndex: destino.zIndex })
          .to(tarjeta, { x: destino.x, y: 0, scale: destino.scale, duration: .45, ease: "power2.out" });
        G.to(cabeza, { opacity: 1, duration: .5, delay: .9 });
        return;
      } else if (!enPila && G.getProperty(tarjeta, "opacity") === 0) {
        G.set(tarjeta, destino);
      } else {
        G.to(tarjeta, { ...destino, ...animacion });
      }
      G.to(sombra, { opacity: sombraDestino, ...animacion });
      if (foto) G.to(foto, { filter: filtro, duration: 1.1, ease: "power3.inOut", overwrite: "auto" });
      G.to(cabeza, { opacity: r === 0 ? 1 : 0, duration: r === 0 ? .6 : .25, delay: r === 0 ? .5 : 0, overwrite: "auto" });

      /* La foto que pasa al frente entra con un leve acercamiento */
      if (r === 0 && foto) G.fromTo(foto, { scale: 1.15 }, { scale: 1, duration: 1.3, ease: "power3.out" });
    });
  }

  /* Al pasar de computadora a celular cambia cuántas se ven */
  enCelular.addEventListener("change", () => colocarTira(actual, false));

  /* Fondo: la foto nueva se carga antes de cruzarla, así no parpadea */
  function cargar(src) {
    return new Promise((listo) => {
      if (!src) return listo();
      const img = new Image();
      img.onload = img.onerror = () => listo();
      img.src = src;
      setTimeout(listo, 1500);
    });
  }

  function cambiarFondo(lema, conAnimacion) {
    const src = rutaImagen(lema.imagen).replace(/["\\()]/g, "");
    const nueva = el.capas[1 - capaVisible];
    const vieja = el.capas[capaVisible];
    capaVisible = 1 - capaVisible;

    return cargar(src).then(() => {
      nueva.style.backgroundImage = src ? `url("${src}")` : "";
      if (conAnimacion) {
        G.fromTo(nueva, { opacity: 0, scale: 1.14 }, { opacity: 1, scale: 1.04, duration: 1.4, ease: "power2.out", overwrite: "auto" });
        G.to(vieja, { opacity: 0, duration: 1.4, ease: "power2.out", overwrite: "auto" });
      } else {
        nueva.style.opacity = 1;
        vieja.style.opacity = 0;
        if (G) G.set([nueva, vieja], { scale: 1.04 });
      }
    });
  }

  function escribirTexto(i) {
    const lema = lemas[i];
    const anterior = lemas[i - 1];
    const siguiente = lemas[i + 1];

    el.eyebrow.textContent = `Lema ${lema.anio}`;
    el.titulo.textContent = lema.titulo;
    /* Para que el título largo ("Responsabilidad") se achique y no se parta */
    el.titulo.parentElement.style.setProperty("--letras", String(lema.titulo).trim().length || 8);
    el.verso.innerHTML = versoHTML(lema.verso, "vitrina__cita");

    /* El anterior y el siguiente asoman arriba y abajo, y llevan a él */
    const fantasma = (boton, otro, indice) => {
      boton.hidden = !otro;
      if (!otro) return;
      boton.textContent = otro.titulo;
      boton.dataset.ir = indice;
      boton.setAttribute("aria-label", `Ir al lema ${otro.anio}: ${otro.titulo}`);
    };
    fantasma(el.arriba, anterior, i - 1);
    fantasma(el.abajo, siguiente, i + 1);
  }

  function contar(i, conAnimacion) {
    el.actual.textContent = dosCifras(i + 1);
    puntos.forEach((punto, k) => {
      punto.classList.toggle("vitrina__punto--actual", k === i);
      punto.toggleAttribute("aria-current", k === i);
    });
    el.rielTxt.textContent = `${dosCifras(i + 1)} / ${dosCifras(total)}`;
    const avance = (i + 1) / total;
    if (conAnimacion) G.to(el.progreso, { scaleX: avance, duration: .9, ease: "power3.inOut", overwrite: "auto" });
    else el.progreso.style.transform = `scaleX(${avance})`;
  }

  /* Las fotos del anterior y el siguiente se piden antes de tiempo */
  function precargar(i) {
    [i - 1, i + 1].forEach((k) => {
      const lema = lemas[(k + total) % total];
      if (lema && lema.imagen) new Image().src = rutaImagen(lema.imagen);
    });
  }

  /* El estado: un solo lema elegido mueve todo lo demás */
  function mostrar(i, { cambiarDireccion = true } = {}) {
    /* Hacia atrás solo si se pidió el anterior; todo lo demás (incluido
       tocar una tarjeta de la pila) gira hacia adelante */
    const direccion = i === actual - 1 ? -1 : 1;
    i = ((i % total) + total) % total;
    if (i === actual) return;
    const primera = actual === -1;
    const salto = primera ? 1 : (i - actual + total) % total;
    const conAnimacion = animar() && !primera;
    actual = i;

    cambiarFondo(lemas[i], conAnimacion);
    colocarTira(i, conAnimacion, direccion, salto);
    contar(i, conAnimacion);
    precargar(i);

    const piezas = [el.eyebrow, el.verso];
    const fantasmas = [el.arriba, el.abajo];

    if (lineaTexto) lineaTexto.kill();
    if (!conAnimacion) {
      escribirTexto(i);
      if (G) G.set([el.titulo, ...piezas, ...fantasmas], { clearProps: "transform,opacity" });
    } else {
      /* Sale lo de antes (sube y se apaga), se cambia, y entra lo
         nuevo: el título sube desde abajo de su línea y el resto lo
         sigue escalonado */
      lineaTexto = G.timeline()
        .to(el.titulo, { yPercent: -110, duration: .45, ease: "power3.in" }, 0)
        .to(piezas, { y: -18, opacity: 0, duration: .35, ease: "power2.in", stagger: .04 }, 0)
        .to(fantasmas, { opacity: 0, duration: .3 }, 0)
        .add(() => escribirTexto(i))
        .fromTo(el.titulo, { yPercent: 110 }, { yPercent: 0, duration: .9, ease: "power4.out" })
        .fromTo(piezas, { y: 26, opacity: 0 }, { y: 0, opacity: 1, duration: .7, ease: "power3.out", stagger: .08 }, "<.15")
        .fromTo(fantasmas, { opacity: 0 }, { opacity: 1, duration: .8 }, "<.1");
    }

    /* La dirección lleva el año: lemas.html#2024 abre ese lema */
    if (cambiarDireccion) history.replaceState(null, "", `#${encodeURIComponent(lemas[i].anio)}`);
  }

  /* --- Controles ---------------------------------------------- */
  vitrina.addEventListener("click", (e) => {
    const destino = e.target.closest("[data-ir]");
    if (destino) mostrar(Number(destino.dataset.ir));
  });
  $("vitrinaPrev").addEventListener("click", () => mostrar(actual - 1));
  $("vitrinaNext").addEventListener("click", () => mostrar(actual + 1));

  /* Flechas del teclado mientras la vitrina está en pantalla y no se
     está escribiendo en otro lado */
  document.addEventListener("keydown", (e) => {
    if (e.key !== "ArrowLeft" && e.key !== "ArrowRight") return;
    if (e.target.closest("input, textarea, select, [contenteditable]")) return;
    const caja = vitrina.getBoundingClientRect();
    if (caja.bottom < window.innerHeight * .4 || caja.top > window.innerHeight * .6) return;
    e.preventDefault();
    mostrar(actual + (e.key === "ArrowRight" ? 1 : -1));
  });

  /* Deslizar con el dedo: solo si el gesto es más horizontal que
     vertical, para no robarle el desplazamiento a la página */
  let toque = null;
  vitrina.addEventListener("touchstart", (e) => {
    toque = { x: e.touches[0].clientX, y: e.touches[0].clientY };
  }, { passive: true });
  vitrina.addEventListener("touchend", (e) => {
    if (!toque) return;
    const dx = e.changedTouches[0].clientX - toque.x;
    const dy = e.changedTouches[0].clientY - toque.y;
    toque = null;
    if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.3) mostrar(actual + (dx < 0 ? 1 : -1));
  });

  /* Parallax sutil: el fondo se mueve un poco con el ratón y la tira
     un poco al revés. Solo con ratón y con movimiento permitido. */
  if (G && window.matchMedia("(hover: hover) and (pointer: fine)").matches) {
    vitrina.addEventListener("pointermove", (e) => {
      if (sinMovimiento.matches) return;
      const nx = e.clientX / window.innerWidth - .5;
      const ny = e.clientY / window.innerHeight - .5;
      G.to(el.fondo, { x: -nx * 28, y: -ny * 18, duration: 1.4, ease: "power2.out", overwrite: "auto" });
      G.to(el.ventana, { x: nx * 10, duration: 1.4, ease: "power2.out", overwrite: "auto" });
    });
    vitrina.addEventListener("pointerleave", () => {
      G.to([el.fondo, el.ventana], { x: 0, y: 0, duration: 1.2, ease: "power2.out", overwrite: "auto" });
    });
  }

  /* Al cambiar el ancho, la tira se vuelve a medir (sin animar) */
  let espera;
  window.addEventListener("resize", () => {
    clearTimeout(espera);
    espera = setTimeout(() => colocarTira(actual, false), 120);
  });

  /* Empieza en el año de la dirección (#2024), si viene uno */
  const pedido = decodeURIComponent(location.hash.slice(1));
  const inicial = Math.max(lemas.findIndex((l) => String(l.anio) === pedido), 0);
  mostrar(inicial, { cambiarDireccion: Boolean(pedido) });
  vitrina.classList.add("vitrina--lista");
}
