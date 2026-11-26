#!/bin/bash
# Renders the film in 4K60, one file per shot, then joins them and adds the sound.
# A shot whose file already exists is skipped, so after changing one shot delete its file and run again.
#   scripts/render.sh            all shots, then the join
#   scripts/render.sh cv review  only these shots, no join
set -e
cd "$(dirname "$0")/.."
CHROME="${FILM_CHROME:-/usr/bin/google-chrome}"
mkdir -p out/chunks
npx remotion bundle src/index.ts --public-dir=public --out-dir=build --log=error
# name first-frame last-frame, in film order (40 frames to a beat).
SHOTS="open:0:479 home:480:839 apply:840:2199 review:2200:3679 company:3680:4439 cv:4440:5399 analytics:5400:5839 staff:5840:6319 look:6320:6639 eng:6640:7159 close:7160:7639"
want="${*:-$(for s in $SHOTS; do echo -n "${s%%:*} "; done)}"
for name in $want; do
  for s in $SHOTS; do
    IFS=: read n a b <<< "$s"
    [ "$n" = "$name" ] || continue
    out="out/chunks/$(printf %05d "$a")-$n.mp4"
    [ -f "$out" ] && { echo "skip $n"; continue; }
    echo "render $n ($a-$b) $(date +%H:%M:%S)"
    npx remotion render build Film "out/chunks/tmp-$n.mp4" --frames="$a-$b" --scale=2 --concurrency=2 --jpeg-quality=95 \
      --crf=14 --x264-preset=veryfast --color-space=bt709 --muted --browser-executable="$CHROME" --log=error
    mv "out/chunks/tmp-$n.mp4" "$out"
  done
done
[ $# -eq 0 ] || exit 0
ls out/chunks/[0-9]*.mp4 | sed "s|^out/chunks/|file '|; s|$|'|" > out/chunks/list.txt
ffmpeg -y -hide_banner -loglevel error -f concat -safe 0 -i out/chunks/list.txt -c copy out/picture.mp4
ffmpeg -y -hide_banner -loglevel error -i out/picture.mp4 -i public/audio/mix-master.wav -map 0:v -map 1:a -c:v copy -c:a aac -b:a 256k -movflags +faststart out/hearback-film-4k-master.mp4
echo "done $(date +%H:%M:%S)"
