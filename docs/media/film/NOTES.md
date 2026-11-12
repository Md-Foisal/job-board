# Film notes

How the presentation film was made, and why. Credits and licences are in `CREDITS.md`; the upload text is in `youtube.md`.

The method is the one worked out for the Tandoor House film; the research behind it (launch-film structure, pointer and zoom, licences) is listed in that repository's [`docs/media/film/NOTES.md`](https://github.com/Md-Foisal/tandoor-house/blob/main/docs/media/film/NOTES.md) and was not repeated here.

## Decisions

- **One application, not a feature list.** The film follows one application from the search box to the answer, then shows what sits around it: the company page, the CV builder, analytics and staff review. 2:07 is demo length, for a reader who has already chosen to look.
- **Captions, no voiceover.** The story reads with the sound off. The captions are also shipped as a subtitle file (`hearback-film.en.srt`), so the film has text that YouTube and screen readers can use.
- **Capture: CDP screencast, frame by frame, in slow motion.** Each camera saves JPEG frames with real timestamps and the edit picks the frame for each film time. The capture machine could not draw 4K-ready frames 60 times a second, so the page ran eight times slower while it was filmed (its animations, its timers, the typing and the scrolling) and every saved time was divided by eight. Played back, it is normal speed with enough frames.
- **True device pixels.** Desktop at 3x (4320 wide), phone at 4x (1560 wide), set as browser flags, because screencasts ignore emulated density. That is sharper than a 4K frame even inside a zoom.
- **Pointer rebuilt in the edit.** The capture logs targets and clicks, and the edit draws the pointer and touch marks. Zooms are placed by hand.
- **4K, 60 fps, H.264**, rendered one shot at a time and joined without re-encoding.
- **Music written for the film** at 90 beats a minute, so every chapter starts on a beat. The opening is a low drone and a heartbeat, the silence after applying; the loop that follows starts with the logo.
- **One encode.** At about 95 MB the film fits under GitHub's 100 MB limit as it is, so the repository copy is also the file to upload.

## What is real, and what was arranged

The app code is `main` at `97b3eac`, unchanged. Everything on screen is the running app answering real clicks.

Arranged for the camera, and only these:

- **Where it ran.** A Linux machine with PHP 8.5, SQLite instead of MySQL, the queue set to run at once and mail going to the log. The dependencies are the ones in `composer.lock`. AI features were off, as on the live site.
- **Data.** The seeded demo data (`php artisan migrate:fresh --seed`), plus `tools/film/scripts/stage.php`: seven more made-up applicants for the Senior Laravel Developer posting, some already moved along by the employer, so the applicant board is not empty. Every company and person is made up and the pictures are drawn avatars.
- **The typeface.** The app loads Geist from a font CDN, which the capture machine could not reach. The same typeface was served from the film's own copy.
- **The ten-minute undo window.** A hire or a rejection is shown to the candidate ten minutes after it is made. The film does not wait: after the employer's screen is filmed, the decision's time is moved eleven minutes into the past, then the candidate's page is loaded again.
- **The candidate's phone.** The application page does not update by itself. The camera reloads it after each change, as the candidate would by opening it.
- **Cuts and speed.** Waiting for a page is cut. Typing the cover letter and a few scrolls run slightly fast, up to 1.2 times. In the CV builder, the page goes back to its top when the template or colour changes; the film cuts to the redrawn page and scrolls down again for the next choice.
- **Staff sign-in** used the demo seeder's fixed two-factor secret.

## The numbers on the last card

- **1,590+ tests.** 1,593 passed on the last recorded run (27 October 2026, with PR #14). The suite was not run again for the film.
- **19 policies.** Counted in `app/Policies`.
- **10 minutes.** `Application::UNDO_MINUTES`.
- **9 architecture decisions.** `docs/adr`, 0001 to 0009.

## Audio

Written and mixed in `tools/film/scripts/music.py`: the music steps back a little under the note that plays when an answer arrives. Measured with FFmpeg's `ebur128` after normalising: -14.0 LUFS integrated, peak at -1.5 dBFS.

The mix was checked by measurement only, not by ear. Listen to it before publishing.

## Rebuilding

From `tools/film` after `npm install`, with the app set up in the repository root (`composer install`, `npm ci`, `npm run build`, a `.env` with `APP_URL=http://127.0.0.1:8030`, `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=file`, `SESSION_DRIVER=file`), Chrome installed, and Python with NumPy and SciPy:

1. Copy the two fonts named in `CREDITS.md` to `public/fonts`.
2. `scripts/serve.sh` in one terminal, then `scripts/shoot.sh`. It resets the database, films every scene and writes `src/data/captures.ts`. `DRY=1` runs a scene at full speed and saves stills instead of frames.
3. `node --experimental-strip-types scripts/cues.ts`, then `python3 scripts/music.py`, then normalise `public/audio/mix.wav` to `public/audio/mix-master.wav`.
4. `scripts/render.sh`. To change one shot, edit `src/timeline.ts`, delete that shot's file in `out/chunks` and run it again.
