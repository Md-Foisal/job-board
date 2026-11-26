#!/bin/bash
# Draws single frames of the edit for checking: scripts/stills.sh <frame> <frame> ...
cd "$(dirname "$0")/.."
mkdir -p out/stills
for f in "$@"; do
  npx remotion still build Film "out/stills/$(printf %05d "$f").jpg" --frame="$f" --image-format=jpeg --jpeg-quality=85 --scale="${SCALE:-1}" --browser-executable="${FILM_CHROME:-/usr/bin/google-chrome}" --log=error
done
