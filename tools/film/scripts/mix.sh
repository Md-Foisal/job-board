#!/bin/bash
# Everything that follows the edit but is not picture: cues, subtitles, chapters, then the sound, normalised to -14 LUFS.
set -e
cd "$(dirname "$0")/.."
node --experimental-strip-types --disable-warning=ExperimentalWarning scripts/cues.ts
python3 scripts/music.py
M=$(ffmpeg -hide_banner -nostats -i public/audio/mix.wav -af loudnorm=I=-14:TP=-1.5:LRA=7:print_format=json -f null - 2>&1 | sed -n '/^{/,/^}/p')
F=$(python3 -c "import json,sys; m=json.loads(sys.argv[1]); print(f\"loudnorm=I=-14:TP=-1.5:LRA=7:measured_I={m['input_i']}:measured_TP={m['input_tp']}:measured_LRA={m['input_lra']}:measured_thresh={m['input_thresh']}:offset={m['target_offset']}:linear=true\")" "$M")
ffmpeg -y -hide_banner -loglevel error -i public/audio/mix.wav -af "$F" -ar 48000 public/audio/mix-master.wav
ffmpeg -hide_banner -nostats -i public/audio/mix-master.wav -af ebur128=peak=true -f null - 2>&1 | grep -E "I:|Peak:" | tail -2
