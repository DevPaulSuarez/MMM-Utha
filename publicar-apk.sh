#!/usr/bin/env bash
# ============================================================
# MMM · Publicar el APK de Android en el VPS
#   ./publicar-apk.sh           compila y sube el APK
#   ./publicar-apk.sh --subir   sube el que ya está compilado
#
# Mientras la app no esté en Google Play, así se reparte: queda en
#   https://<tu dominio>/app/mmm-utha.apk
# que es el enlace que abre el botón de "Actualiza la aplicación"
# (api/version-app.php) y el que se le pasa a la gente.
#
# Después de subirlo, para que los celulares con la versión anterior
# pidan actualizarse: poner esa versión en 'minima' de
# api/version-app.php y correr ./desplegar.sh.
# ============================================================
set -euo pipefail
cd "$(dirname "$0")"

if [[ ! -f .desplegar.env ]]; then
  echo "Falta .desplegar.env: copiar desplegar.ejemplo.env y completarlo." >&2
  exit 1
fi
source .desplegar.env

APP=app/MMM-Utha-App
APK="$APP/build/app/outputs/flutter-apk/app-release.apk"

# --- 1. Compilar ----------------------------------------------------
if [[ "${1:-}" != "--subir" ]]; then
  "$APP/compilar.sh" apk
fi

if [[ ! -f "$APK" ]]; then
  echo "No está el APK compilado ($APK). Correr ./publicar-apk.sh sin --subir." >&2
  exit 1
fi

VERSION=$(sed -n 's/^version:[[:space:]]*\([^[:space:]+]*\).*/\1/p' "$APP/pubspec.yaml")
PESO=$(du -h "$APK" | cut -f1)

echo
echo "APK: versión $VERSION, $PESO, compilado $(date -r "$APK" '+%d/%m/%Y %H:%M')"
read -r -p "¿Subir a $VPS:$RUTA/app/mmm-utha.apk? [s/N] " respuesta
[[ "$respuesta" == "s" || "$respuesta" == "S" ]] || { echo "No se subió nada."; exit 0; }

# --- 2. Subir -------------------------------------------------------
# Se sube con otro nombre y se renombra al final: así nadie baja un
# archivo a medias mientras se está copiando.
PUERTO="${PUERTO_SSH:-22}"
ssh -p "$PUERTO" "$VPS" "mkdir -p '$RUTA/app'"
scp -P "$PUERTO" "$APK" "$VPS:$RUTA/app/mmm-utha.apk.nuevo"
ssh -p "$PUERTO" "$VPS" \
  "cd '$RUTA/app' && mv mmm-utha.apk.nuevo mmm-utha.apk && chmod 644 mmm-utha.apk"

# --- 3. Probar ------------------------------------------------------
if curl -fsS -o /dev/null --max-time 60 "$URL/app/mmm-utha.apk"; then
  echo
  echo "Listo: $URL/app/mmm-utha.apk"
  echo
  echo "Para que los celulares con una versión anterior pidan actualizarse:"
  echo "  1. poner 'minima' => '$VERSION' en api/version-app.php"
  echo "  2. ./desplegar.sh"
else
  echo "Cuidado: $URL/app/mmm-utha.apk no se pudo bajar. Revisar Nginx." >&2
  exit 1
fi
