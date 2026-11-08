#!/usr/bin/env bash
# ============================================================
# MMM · Armar la web lista para publicar
#   ./preparar-web.sh
#
# Deja en _publico/ solo lo que va al hosting (index.html, construccion.html, paginas/,
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
  index.html construccion.html entrar.php paginas css js img "$SALIDA/"
cp web.htaccess "$SALIDA/.htaccess"
# Música de fondo de la página de construcción (opcional)
[ -d musica ] && rsync -a --exclude .DS_Store musica "$SALIDA/"

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

# Código de la vista privada: vive en .vista.env, fuera del repo
[ -f .vista.env ] || { echo "Falta .vista.env (CODIGO_VISTA=pastor-...)" >&2; exit 1; }
CODIGO_VISTA=$(sed -n 's/^CODIGO_VISTA=//p' .vista.env)
[[ "$CODIGO_VISTA" =~ ^[A-Za-z0-9-]+$ ]] || { echo "CODIGO_VISTA inválido en .vista.env" >&2; exit 1; }
sed -i.bak "s/__CODIGO_VISTA__/$CODIGO_VISTA/g" "$SALIDA/.htaccess" "$SALIDA/entrar.php"
rm -f "$SALIDA/.htaccess.bak" "$SALIDA/entrar.php.bak"

( cd "$SALIDA" && zip -qr ../web-mmm.zip . )
echo "Listo: $SALIDA/ y web-mmm.zip ($(find "$SALIDA" -type f | wc -l | tr -d ' ') archivos)"
