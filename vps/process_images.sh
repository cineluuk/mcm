#!/bin/bash
# Läuft auf dem Infomaniak-VPS, nicht auf dem Webhosting.
# Sony legt .ARW in Incoming ab. Dieses Script macht JPEG
# und lädt es per FTP nach uploads/processed auf mcm.cineluuk.ch.
#
# Zugangsdaten liegen in /home/ubuntu/.mcm-ftp.env (nicht ins Git).

set -euo pipefail
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

INPUT="/var/www/cineluuk-mcm/uploads/incomming"
OUTPUT="/var/www/cineluuk-mcm/uploads/processed"
LOCK="/tmp/cineluuk-mcm-processing.lock"
ENV_FILE="/home/ubuntu/.mcm-ftp.env"

mkdir -p "$OUTPUT"

if [ -f "$LOCK" ]; then
    echo "Prozess läuft bereits. Abbruch."
    exit 0
fi

touch "$LOCK"
trap 'rm -f "$LOCK"' EXIT

if [ -f "$ENV_FILE" ]; then
    # shellcheck disable=SC1090
    source "$ENV_FILE"
fi

file=""
while IFS= read -r candidate; do
    file="$candidate"
    break
done < <(find "$INPUT" -maxdepth 1 \( -iname "*.ARW" -o -iname "*.arw" \) -type f -mmin +1 | sort)

if [ -z "$file" ]; then
    echo "Keine fertige RAW-Datei gefunden."
    exit 0
fi

filename=$(basename "$file")
name="${filename%.*}"
out="$OUTPUT/$name.jpg"

if [ -f "$out" ]; then
    echo "Bereits verarbeitet: $filename"
    rm -f "$file" || true
    exit 0
fi

echo "Starte Verarbeitung: $filename"

darktable-cli "$file" "$out" \
    --core --conf plugins/imageio/format/jpeg/quality=92

if [ ! -f "$out" ]; then
    echo "Fehler: JPG wurde nicht erstellt."
    exit 1
fi

convert "$out" \
    -enhance \
    -despeckle \
    -statistic Median 3x3 \
    -blur 0x0.6 \
    -unsharp 0x0.9 \
    -quality 90 \
    "$out"

echo "Fertig verarbeitet: $out"

if [ -n "${FTP_HOST:-}" ] && [ -n "${FTP_USER:-}" ] && [ -n "${FTP_PASS:-}" ]; then
    remote_dir="${FTP_REMOTE_DIR:-/uploads/processed}"
    lftp -u "$FTP_USER","$FTP_PASS" "$FTP_HOST" -e "cd $remote_dir; put '$out'; bye"
    echo "Upload zur Gallery: $name.jpg"
else
    echo "Kein FTP konfiguriert. JPG bleibt nur auf dem VPS."
fi

rm -f "$file" || true
echo "Original RAW gelöscht: $filename"
