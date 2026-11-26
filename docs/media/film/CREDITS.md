# Film credits

Everything heard or seen in the film that did not come from the running app.

## Music and sound

Written for this film and synthesised in code (`tools/film/scripts/music.py`): the drone and heartbeat of the opening, the loop, the interface ticks, the turns between chapters and the notes that play when an answer arrives. No samples and no third-party recordings, so there is no licence to clear. Copyright © 2026 Md. Foisal.

## Picture

- All product footage is the real app, recorded from its own screens with the seeded demo data. The companies and people are made up; the faces are the app's own drawn avatars.
- Type: Geist and Geist Mono (SIL Open Font License), the app's own typefaces, from the `@fontsource-variable/geist` and `@fontsource-variable/geist-mono` packages (5.3.0). The font files are not stored in this repository; `tools/film` expects them in `public/fonts`.

## Tools

- [Remotion](https://github.com/remotion-dev/remotion/blob/main/LICENSE.md) 4 for the edit, under its Free License, which covers individuals.
- Playwright and Chrome for capture, FFmpeg for joining, measuring and encoding, NumPy and SciPy for the sound.
