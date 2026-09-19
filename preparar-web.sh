#!/usr/bin/env bash
# ============================================================
# MMM · Armar la web lista para publicar
#   ./preparar-web.sh
#
# Deja en _publico/ solo lo que va al hosting (index.html, paginas/,
# css/, js/, img/ y el .htaccess) y lo empaqueta en web-mmm.zip para
# subirlo con el File Manager. publicar-web.sh lo usa para el FTP.
#
# A cada .css y .js le agrega en los HTML ?v=<huella de su contenido>:
# si el archivo cambia, cambia su dirección y el navegador lo pide de
# nuevo en vez de mostrar el viejo que tenía guardado.
# ============================================================
set -euo pipefail
cd "$(dirname "$0")"

SALIDA=_publico
rm -rf "$SALIDA" web-mmm.zip
mkdir "$SALIDA"

# originales/: las fuentes de las fotos de portada, la web usa los .webp
rsync -aR --exclude .DS_Store --exclude .gitkeep --exclude img/fondos/originales \
  index.html paginas css js img "$SALIDA/"
cp web.htaccess "$SALIDA/.htaccess"

huella() {
  { md5 -q "$1" 2>/dev/null || md5sum "$1" | cut -d' ' -f1; } | cut -c1-8
}

for recurso in "$SALIDA"/css/*.css "$SALIDA"/js/*.js; do
  ruta=${recurso#"$SALIDA"/}              # css/style.css
  version=$(huella "$recurso")
  patron=${ruta//./\\.}                    # css/style\.css
  # El " del final evita que js/datos.js toque a js/datos.json
  find "$SALIDA" -name '*.html' -exec sed -i.bak -E \
    "s#${patron}\"#${ruta}?v=${version}\"#g" {} +
done
find "$SALIDA" -name '*.bak' -delete

( cd "$SALIDA" && zip -qr ../web-mmm.zip . )
echo "Listo: $SALIDA/ y web-mmm.zip ($(find "$SALIDA" -type f | wc -l | tr -d ' ') archivos)"
