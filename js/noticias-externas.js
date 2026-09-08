/* ============================================================
   MMM · Noticias del sitio internacional
   Trae las ultimas entradas de mmmoficial.org y las reparte:
   la primera ocupa la columna izquierda en grande y las cuatro
   siguientes van apiladas a la derecha en formato compacto.
   ============================================================ */

const NOTICIAS_URL =
  "https://mmmoficial.org/wp-json/wp/v2/posts?per_page=5&_embed";

function fechaLegible(iso) {
  return new Date(iso).toLocaleDateString("es-ES", {
    day: "numeric",
    month: "long",
    year: "numeric",
  });
}

/* El pais viene como categoria de la entrada */
function paisDe(post) {
  const categorias = post._embedded?.["wp:term"]?.[0] || [];
  const pais = categorias.find((t) => t.taxonomy === "category");
  return pais ? pais.name : "";
}

/* Toda la tarjeta es un enlace: la imagen de fondo y encima, en
   la franja inferior, la fecha y el titular en negrita */
function plantilla(post, imagen, clase) {
  const pais = paisDe(post);
  return `
    <a class="tarjeta-noticia ${clase}" href="${post.link}"
       target="_blank" rel="noopener">
      ${imagen ? `<img src="${imagen}" alt="">` : ""}
      ${pais ? `<span class="tarjeta-noticia__pais">${pais}</span>` : ""}
      <div class="tarjeta-noticia__texto">
        <p class="tarjeta-noticia__fecha">${fechaLegible(post.date)}</p>
        <h3>${post.title.rendered}</h3>
      </div>
    </a>
  `;
}

async function cargarNoticiasExternas() {
  const contenedor = document.getElementById("lista-noticias-externas");
  if (!contenedor) return;

  try {
    const res = await fetch(NOTICIAS_URL);
    if (!res.ok) throw new Error("Respuesta no válida del servidor");

    const posts = await res.json();
    if (!posts.length) throw new Error("Sin entradas");

    contenedor.innerHTML = posts
      .map((post, i) => {
        const imagen = post._embedded?.["wp:featuredmedia"]?.[0]?.source_url;
        return plantilla(
          post,
          imagen,
          i === 0 ? "tarjeta-noticia--destacada" : "tarjeta-noticia--compacta"
        );
      })
      .join("");
  } catch (error) {
    console.error("Error al cargar noticias externas:", error);
    contenedor.innerHTML =
      "<p>No se pudieron cargar las noticias en este momento.</p>";
  }
}

cargarNoticiasExternas();
