#!/usr/bin/env bash
# ============================================================
# MMM · Subir los cambios al VPS
#   ./desplegar.sh
#
# 1. Revisa que el PHP no tenga errores de sintaxis
# 2. Muestra qué archivos van a cambiar y pide confirmación
# 3. Sube api/ e img/ (y la web si SUBIR_WEB="si")
# 4. Aplica en la base del servidor las migraciones que falten
# 5. Prueba que api/datos.php responda
#
# Nunca toca en el servidor: api/config.php (sus contraseñas) ni las
# fotos de subidas/ (las sube la app allá, no están en la computadora).
# ============================================================
set -euo pipefail
cd "$(dirname "$0")"

if [[ ! -f .desplegar.env ]]; then
  echo "Falta .desplegar.env: copiar desplegar.ejemplo.env y completarlo." >&2
  exit 1
fi
source .desplegar.env

SSH=(ssh -p "${PUERTO_SSH:-22}" "$VPS")

# --- 1. Revisar ---------------------------------------------------
for archivo in api/*.php; do
  php -l "$archivo" >/dev/null || { echo "Error de sintaxis en $archivo: no se sube nada." >&2; exit 1; }
done

if [[ -n "$(git status --porcelain -- api img js css paginas index.html)" ]]; then
  echo "Aviso: hay cambios sin commit. Se sube lo que está en la carpeta, no lo del último commit."
fi

# --- 2. Qué se sube -------------------------------------------------
# -rlptz: carpetas, enlaces, permisos y fechas, comprimido. --delete
# solo en api/: quita del servidor lo que ya no existe aquí, menos
# config.php, que está excluido y por eso rsync no lo borra.
RSYNC=(rsync -rlptz --exclude .DS_Store -e "ssh -p ${PUERTO_SSH:-22}")

subir() {
  "${RSYNC[@]}" "$@" --delete --exclude config.php api/ "$VPS:$RUTA/api/"
  "${RSYNC[@]}" "$@" img/ "$VPS:$RUTA/img/"
  "${RSYNC[@]}" "$@" subidas/.htaccess "$VPS:$RUTA/subidas/"
  if [[ "${SUBIR_WEB:-no}" == "si" ]]; then
    "${RSYNC[@]}" "$@" index.html "$VPS:$RUTA/"
    "${RSYNC[@]}" "$@" --delete paginas/ "$VPS:$RUTA/paginas/"
    "${RSYNC[@]}" "$@" --delete css/ "$VPS:$RUTA/css/"
    "${RSYNC[@]}" "$@" --delete js/ "$VPS:$RUTA/js/"
  fi
}

"${SSH[@]}" "mkdir -p '$RUTA/api' '$RUTA/img' '$RUTA/subidas'"

echo "== Archivos que cambian en $VPS:$RUTA =="
CAMBIOS=$(subir --dry-run --itemize-changes | grep -v '^\.' || true)
if [[ -z "$CAMBIOS" ]]; then
  echo "(ninguno)"
else
  echo "$CAMBIOS"
fi

echo
echo "== Migraciones pendientes en el servidor =="
"${SSH[@]}" "cd '$RUTA' && [ -f api/migrar.php ] && php api/migrar.php --estado || echo '(migrar.php todavía no está en el servidor)'"

echo
read -r -p "¿Subir? [s/N] " respuesta
[[ "$respuesta" == "s" || "$respuesta" == "S" ]] || { echo "No se subió nada."; exit 0; }

# --- 3 y 4. Subir y migrar ------------------------------------------
subir
echo "Archivos subidos."

"${SSH[@]}" "cd '$RUTA' && php api/migrar.php"

# --- 5. Probar ----------------------------------------------------
if curl -fsS -o /dev/null --max-time 20 "$URL/api/datos.php"; then
  echo "Listo: $URL/api/datos.php responde."
else
  echo "Cuidado: $URL/api/datos.php no respondió bien. Revisar el log de errores del servidor." >&2
  exit 1
fi
