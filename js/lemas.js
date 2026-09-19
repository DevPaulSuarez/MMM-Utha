/* ============================================================
   MMM · Lemas por año
   Adaptación del bloque del sitio internacional. Alli el cambio
   de año se hacia por AJAX contra WordPress (?anio=XXXX); aqui
   los datos estan en LEMAS (api/datos.php) y el cambio es local.
   ============================================================ */

/* Lo llama main.js cuando termina de llegar el contenido */
function iniciarLemas() {
  const vista = document.getElementById("gl-main");
  if (!vista || typeof LEMAS === "undefined") return;

  /* Sin lemas el visor se queda como un recuadro blanco vacío al lado
     de un fondo sin imagen. Se reemplaza por el aviso de main.js. */
  if (!LEMAS.length) {
    const aviso = document.createElement("div");
    vista.replaceWith(aviso);
    pintarVacio(aviso, "Estamos preparando esta sección. Vuelve pronto.");
    return;
  }

  const fondo = document.getElementById("gl-bg-target");
  const ficha = document.getElementById("gl-ajax-container");
  const scroller = document.getElementById("gl-scroller");

  /* Botones de año */
  scroller.innerHTML = LEMAS.map(
    (l, i) => `
      <button type="button" class="gl-year-trigger" data-indice="${i}">
        <strong>${escapar(l.anio)}</strong>
        <span>${escapar(l.titulo)}</span>
      </button>
    `
  ).join("");

  const botones = [...scroller.querySelectorAll(".gl-year-trigger")];
  let actual = -1;

  /* Centra un año dentro de la tira moviendo solo su barra
     horizontal. Antes se usaba scrollIntoView, que además arrastraba
     la página hasta esta sección nada más abrir el inicio. */
  function centrar(boton, comportamiento = "auto") {
    const destino =
      boton.offsetLeft - (scroller.clientWidth - boton.offsetWidth) / 2;

    scroller.scrollTo({ left: Math.max(destino, 0), behavior: comportamiento });
  }

  function mostrar(indice, desplazar = true) {
    if (indice === actual) return;
    actual = indice;
    const lema = LEMAS[indice];

    vista.classList.add("gl-loading");

    /* Las comillas romperían el url(...) de la declaración */
    const imagen = rutaImagen(lema.imagen).replace(/["\\]/g, "");
    fondo.style.backgroundImage = `url("${imagen}")`;

    /* Lo que escribe el pastor desde la aplicación es texto: solo los
       saltos de línea se convierten en HTML, ya escapado el resto. */
    ficha.innerHTML = `
      <div class="gl-header-tag">
        <img src="https://mmmoficial.org/wp-content/uploads/2026/01/mmm_oficial_03.png"
             width="15" height="15" alt="">
        <span class="gl-tag-txt">Lemas</span>
      </div>
      <h3 class="gl-main-h1">
        ${escapar(lema.titulo)}
        <span class="gl-year-light">${escapar(lema.anio)}</span>
      </h3>
      <div class="gl-body-p">${escapar(lema.texto).replace(/\n/g, "<br>")}</div>
      <div class="gl-verse-box">${escapar(lema.verso)}</div>
    `;

    botones.forEach((b, i) => b.classList.toggle("active", i === indice));
    if (desplazar) centrar(botones[indice], "smooth");

    requestAnimationFrame(() => vista.classList.remove("gl-loading"));
  }

  scroller.addEventListener("click", (e) => {
    const boton = e.target.closest(".gl-year-trigger");
    if (boton) mostrar(Number(boton.dataset.indice));
  });

  /* Flechas: avanzan una pantalla de la tira */
  const paso = () => scroller.offsetWidth;
  document.getElementById("gl-prev").addEventListener("click", () =>
    scroller.scrollBy({ left: -paso(), behavior: "smooth" })
  );
  document.getElementById("gl-next").addEventListener("click", () =>
    scroller.scrollBy({ left: paso(), behavior: "smooth" })
  );

  mostrar(0, false);
  /* Centra el activo una vez que el navegador ya midió la tira */
  requestAnimationFrame(() => centrar(botones[0]));
}
