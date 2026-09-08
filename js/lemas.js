/* ============================================================
   MMM · Lemas por año
   Adaptación del bloque del sitio internacional. Alli el cambio
   de año se hacia por AJAX contra WordPress (?anio=XXXX); aqui
   los datos estan en LEMAS (js/datos.js) y el cambio es local.
   ============================================================ */

document.addEventListener("DOMContentLoaded", iniciarLemas);

function iniciarLemas() {
  const vista = document.getElementById("gl-main");
  if (!vista || typeof LEMAS === "undefined" || !LEMAS.length) return;

  const fondo = document.getElementById("gl-bg-target");
  const ficha = document.getElementById("gl-ajax-container");
  const scroller = document.getElementById("gl-scroller");

  /* Botones de año */
  scroller.innerHTML = LEMAS.map(
    (l, i) => `
      <button type="button" class="gl-year-trigger" data-indice="${i}">
        <strong>${l.anio}</strong>
        <span>${l.titulo}</span>
      </button>
    `
  ).join("");

  const botones = [...scroller.querySelectorAll(".gl-year-trigger")];
  let actual = -1;

  function mostrar(indice, desplazar = true) {
    if (indice === actual) return;
    actual = indice;
    const lema = LEMAS[indice];

    vista.classList.add("gl-loading");

    fondo.style.backgroundImage = `url("${lema.imagen}")`;
    ficha.innerHTML = `
      <div class="gl-header-tag">
        <img src="https://mmmoficial.org/wp-content/uploads/2026/01/mmm_oficial_03.png"
             width="15" alt="">
        <span class="gl-tag-txt">Lemas</span>
      </div>
      <h1 class="gl-main-h1">
        ${lema.titulo}
        <span class="gl-year-light">${lema.anio}</span>
      </h1>
      <div class="gl-body-p">${lema.texto.replace(/\n/g, "<br>")}</div>
      <div class="gl-verse-box">${lema.verso}</div>
    `;

    botones.forEach((b, i) => b.classList.toggle("active", i === indice));
    if (desplazar) {
      botones[indice].scrollIntoView({
        behavior: "smooth",
        inline: "center",
        block: "nearest",
      });
    }

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
  /* Centra el activo una vez que el navegador ya midio la tira */
  requestAnimationFrame(() =>
    botones[0].scrollIntoView({ inline: "center", block: "nearest" })
  );
}
