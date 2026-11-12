"""Original score and sound for the HearBack film. Synthesised from scratch, so there is no licence to clear.

90 BPM, so the edit's cuts sit on beats. The opening is the silence: a dark drone and a slow heartbeat.
From the logo on it is the answer: a warm F, C, G, A minor loop with soft plucks, a quiet pulse and a sub.
The sound effects (ticks, turns, the note when an answer arrives) are made here too, from the edit's cues.

Usage: python3 scripts/music.py   (reads out/cues.json, writes public/audio/mix.wav)
"""
import json, os
import numpy as np
from scipy.signal import butter, sosfilt, fftconvolve
from scipy.io import wavfile

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..')
cues = json.load(open(os.path.join(ROOT, 'out/cues.json')))
SR, DUR, BEAT = 44100, cues['total'], cues['beat']
N = int(SR * DUR)
t = np.arange(N) / SR
rng = np.random.default_rng(11)
B = lambda n: n * BEAT
bt = cues['beats']
groove_from = bt['home']


def hz(note):
    names = {'C': -9, 'C#': -8, 'D': -7, 'D#': -6, 'E': -5, 'F': -4, 'F#': -3, 'G': -2, 'G#': -1, 'A': 0, 'A#': 1, 'B': 2}
    n, o = note[:-1], int(note[-1])
    return 440 * 2 ** ((names[n] + (o - 4) * 12) / 12)


def lp(x, f, order=2):
    return sosfilt(butter(order, f, 'low', fs=SR, output='sos'), x)


def hp(x, f, order=2):
    return sosfilt(butter(order, f, 'high', fs=SR, output='sos'), x)


def env(a, b, att, rel):
    e = np.zeros(N)
    i0, i1 = max(0, int(a * SR)), min(N, int(b * SR))
    seg = np.arange(i1 - i0) / SR
    e[i0:i1] = np.minimum(1, seg / att) * np.minimum(1, (b - a - seg) / rel).clip(0)
    return e


def put(out, at, sound, amp=1.0):
    i0 = int(at * SR)
    if i0 >= N or i0 < 0:
        return
    seg = sound[: N - i0]
    out[i0:i0 + len(seg)] += seg * amp


def pad(notes, a, b, bright=1400, amp=.15):
    out = np.zeros(N)
    for nt in notes:
        f = hz(nt)
        for d in (-0.07, 0.0, 0.06):
            ph = rng.uniform(0, 2 * np.pi)
            out += np.sin(2 * np.pi * f * (1 + d / 12) * t + ph) * .6
            out += np.sin(2 * np.pi * 2 * f * (1 + d / 12) * t + ph) * .18
    return lp(out, bright) * env(a, b, 1.2, 1.4) * amp / len(notes)


def pluck_sound(note, length=2.2, decay=2.8):
    seg = np.arange(int(length * SR)) / SR
    f = hz(note)
    tone = np.sin(2 * np.pi * f * seg) + .35 * np.sin(2 * np.pi * 2 * f * seg) + .12 * np.sin(2 * np.pi * 3 * f * seg)
    return tone * np.exp(-seg * decay) * np.minimum(1, seg / .004)


def thump_sound(f0=52, amp=1.0):
    seg = np.arange(int(.5 * SR)) / SR
    f = f0 + 40 * np.exp(-seg * 30)
    return np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-seg * 9) * amp


music = np.zeros(N)

# The silence: a low drone, dark A minor, a heartbeat that stops before the logo.
logo = 4.85
music += np.sin(2 * np.pi * 55 * t) * env(0, logo + 1.0, 2.0, 1.6) * .10
music += pad(['A3', 'C4', 'E4'], 0, logo + .8, bright=520, amp=.16)
music += pad(['B3', 'E4'], 2.2, logo + .4, bright=700, amp=.07)
for at in np.arange(B(1), 4.2, B(2)):
    put(music, at, thump_sound(), .36)
    put(music, at + .22, thump_sound(), .2)

# The answer arrives with the logo.
for note, d in zip(['C4', 'G4', 'C5', 'E5', 'G5'], [0, .06, .12, .2, .3]):
    put(music, logo + d, pluck_sound(note, 3.2, 1.6), .13)
music += pad(['C3', 'G3', 'C4', 'E4', 'G4'], logo - .2, B(groove_from) + .6, bright=1500, amp=.16)

# The loop: eight beats a chord, from the first cut to the build.
chords = [(['F3', 'A3', 'C4', 'E4'], ['F4', 'A4', 'C5', 'E5']), (['C3', 'G3', 'C4', 'E4'], ['E4', 'G4', 'C5', 'G5']),
          (['G3', 'B3', 'D4', 'G4'], ['D4', 'G4', 'B4', 'D5']), (['A3', 'C4', 'E4', 'G4'], ['E4', 'A4', 'C5', 'E5'])]
groove_to, last = bt['look'], bt['close']
kick, kick_lo = thump_sound(46, 1.0), thump_sound(40, 1.0)
hat = hp(rng.standard_normal(int(.05 * SR)), 6000) * np.exp(-np.arange(int(.05 * SR)) / SR * 90)
chapter_beats = sorted(round(c['t'] / BEAT) for c in cues['chapters'])
for k, b0 in enumerate(range(groove_from, last, 8)):
    low, high = chords[k % 4]
    music += pad(low, B(b0) - .3, B(b0 + 8) + .5, amp=.15)
    music += np.sin(2 * np.pi * hz(low[0]) / 2 * t) * env(B(b0), B(b0 + 8), .05, 1.2) * .07
    for i in range(8):
        beat = b0 + i
        if beat >= last:
            break
        quiet = beat >= groove_to                       # the build: pads only, room for the numbers
        early = beat < bt['apply']                               # the home page: the pulse has not started yet
        if not quiet and not early and i % 2 == 0:
            put(music, B(beat), kick if i % 4 == 0 else kick_lo, .26 if i % 4 == 0 else .15)
        if not quiet and not early:
            put(music, B(beat) + BEAT / 2, hat, .018)
        # An eighth-note figure over the chord, thinner in the busiest chapter so it does not fight the story.
        thin = bt['review'] <= beat < bt['company']
        for j, half in enumerate((0, .5)):
            step = i * 2 + j
            if quiet or (early and step % 4) or (thin and step % 2):
                continue
            note = high[[0, 2, 1, 3, 2, 1, 3, 2][step % 8]]
            put(music, B(beat + half), pluck_sound(note, 1.6, 4.2), .045 if step % 4 else .07)

# The build and the close: C major held, a few slow notes, then out.
music += pad(['C3', 'G3', 'C4', 'E4', 'G4'], B(last) - .4, DUR, bright=1500, amp=.17)
music += np.sin(2 * np.pi * hz('C2') * t) * env(B(last), DUR, .3, 3.0) * .07
for note, at in zip(['C5', 'E5', 'G5', 'C6'], [B(last) + .5, B(last) + 1.3, B(last + 3), B(last + 5)]):
    put(music, at, pluck_sound(note, 3.0, 1.8), .12)
# A lift into each chapter.
for b in chapter_beats[1:]:
    put(music, B(b), pluck_sound('G5', 2.4, 2.2), .06)
    put(music, B(b), pluck_sound('C5', 2.4, 2.2), .07)

# Room.
ir_len = int(2.6 * SR)
irt = np.arange(ir_len) / SR
wetL = lp(fftconvolve(music, rng.standard_normal(ir_len) * np.exp(-irt * 2.4), 'full')[:N], 6000)
wetR = lp(fftconvolve(music, rng.standard_normal(ir_len) * np.exp(-irt * 2.4), 'full')[:N], 6000)
left, right = music + .02 * wetL, music + .02 * wetR
fade = np.minimum(1, t / 1.0) * np.minimum(1, (DUR - t) / 2.6).clip(0)
bed = np.stack([left * fade, right * fade], 1)
bed = bed / np.max(np.abs(bed)) * .8

# Sound effects, dry and small, centred.
fx = np.zeros(N)
seg = np.arange(int(.03 * SR)) / SR
tick = np.sin(2 * np.pi * 1900 * seg) * np.exp(-seg * 240)
key = hp(rng.standard_normal(len(seg)), 3500) * np.exp(-seg * 320)
for at in cues['clicks']:
    put(fx, at, tick, .16)
for at in cues['keys']:
    put(fx, at, key, .03)
wseg = np.arange(int(.7 * SR)) / SR
for at in cues['turns']:
    noise = rng.standard_normal(len(wseg))
    whoosh = lp(hp(noise, 300), 2400) * np.sin(np.pi * wseg / .7) ** 2
    put(fx, at - .12, whoosh, .05)
for at, notes in zip(cues['answers'], [['E5', 'G5'], ['G5', 'B5'], ['G5', 'C6'], ['C5', 'E5', 'G5', 'C6']]):
    # The music steps back a little under the note when an answer arrives.
    duck = 1 - .3 * np.exp(-((t - at - .25) / .5) ** 2)
    bed *= duck[:, None]
    for i, note in enumerate(notes):
        put(fx, at + i * .11, pluck_sound(note, 2.4, 2.4), .11)

mix = bed + np.stack([fx, fx], 1)
mix = mix / max(1.0, np.max(np.abs(mix)) / .89)
os.makedirs(os.path.join(ROOT, 'public/audio'), exist_ok=True)
wavfile.write(os.path.join(ROOT, 'public/audio/mix.wav'), SR, (mix * 32767).astype(np.int16))
print('mix.wav', round(DUR, 2), 's, peak', round(float(np.max(np.abs(mix))), 3), 'rms', round(float(np.sqrt(np.mean(mix ** 2))), 3))
