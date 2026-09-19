#!/usr/bin/env bash
# ============================================================
# MMM · Subir la web (solo lo que ve el público) al hosting gratuito
#   ./publicar-web.sh
#
# Sube index.html, paginas/, css/, js/ e img/ por FTP. Nunca sube
# api/, subidas/ ni app/: el backend vive en el VPS.
# Los datos de acceso están en .web.env (copiar web.ejemplo.env).
# ============================================================
set -euo pipefail
cd "$(dirname "$0")"

if [[ ! -f .web.env ]]; then
  echo "Falta .web.env: copiar web.ejemplo.env y completarlo." >&2
  exit 1
fi
source .web.env
[[ -n "${FTP_CLAVE:-}" ]] || { echo "Falta FTP_CLAVE en .web.env" >&2; exit 1; }

if [[ -n "$(git status --porcelain -- index.html paginas css js img)" ]]; then
  echo "Aviso: hay cambios sin commit. Se sube lo que está en la carpeta."
fi

ARCHIVOS=()
while IFS= read -r archivo; do ARCHIVOS+=("$archivo"); done < <(
  # originales/: las fuentes de las fotos de portada, la web usa los .webp
  find index.html paginas css js img -path img/fondos/originales -prune -o \
       -type f ! -name .DS_Store ! -name .gitkeep -print | sort
)

echo "Se suben ${#ARCHIVOS[@]} archivos a $FTP_HOST/$FTP_CARPETA"
read -r -p "¿Seguir? [s/N] " respuesta
[[ "$respuesta" == "s" || "$respuesta" == "S" ]] || { echo "No se subió nada."; exit 0; }

# La clave va en un archivo temporal y no en la línea de comandos,
# donde la vería cualquiera que liste los procesos
NETRC=$(mktemp)
trap 'rm -f "$NETRC"' EXIT
printf 'machine %s login %s password %s\n' "$FTP_HOST" "$FTP_USUARIO" "$FTP_CLAVE" > "$NETRC"

fallos=0
for archivo in "${ARCHIVOS[@]}"; do
  # --ssl: usa FTP cifrado si el hosting lo acepta
  if curl -sS --ssl --netrc-file "$NETRC" --ftp-create-dirs --retry 2 \
       -T "$archivo" "ftp://$FTP_HOST/$FTP_CARPETA/$archivo"; then
    echo "  ok  $archivo"
  else
    echo "  ERROR  $archivo" >&2
    fallos=$((fallos + 1))
  fi
done

if (( fallos > 0 )); then
  echo "$fallos archivos no se subieron: volver a correr el script." >&2
  exit 1
fi
echo "Listo: $WEB_URL"
