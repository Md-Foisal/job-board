// Capture helpers: every camera records a CDP screencast with real timestamps and logs
// its pointer and marks, so the cursor, zooms and cuts can be rebuilt smoothly in the edit.
import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import { createHmac } from 'node:crypto';
import { chromium } from 'playwright';

export const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
export const APP = join(ROOT, '../..');
export const BASE = process.env.FILM_BASE ?? 'http://127.0.0.1:8030';
export const RAW = join(ROOT, 'public/raw');
const DRY = process.env.DRY === '1';
const CHROME = process.env.FILM_CHROME ?? '/usr/bin/google-chrome';
const PHP = process.env.PHP ?? 'php';
// Desktop at 3x (4320 wide) and phones at 4x (1560 wide): sharper than a 4K frame even inside a zoom.
export const DESK_DSF = Number(process.env.FILM_DESK_DSF ?? 3);
export const PHONE_DSF = Number(process.env.FILM_PHONE_DSF ?? 4);

// The capture machine cannot draw this many pixels 60 times a second, so everything is filmed
// in slow motion (holds, scrolls, typing and the page's own animations) and the saved times
// are divided back. The edit then reads normal speed with SLOW times the frames.
export const SLOW = DRY ? 1 : Number(process.env.FILM_SLOW ?? 8);
const T0 = Date.now() / 1000;
const norm = (t) => (t - T0) / SLOW;

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const ease = 'function(k){return k<.5?4*k*k*k:1-Math.pow(-2*k+2,3)/2}';

// The app loads Geist from a font CDN. The capture machine may be offline, so the same
// typeface is served from the film's own copy (SIL Open Font License, from @fontsource).
const geist = readFileSync(join(ROOT, 'public/fonts/geist-latin-wght-normal.woff2'));
const mono = readFileSync(join(ROOT, 'public/fonts/geist-mono-latin-wght-normal.woff2'));
const fontCss = `@font-face{font-family:'Geist';font-weight:100 900;font-display:block;src:url('${BASE}/__film/geist.woff2') format('woff2')}@font-face{font-family:'Geist Mono';font-weight:100 900;font-display:block;src:url('${BASE}/__film/mono.woff2') format('woff2')}`;

// Screencasts ignore emulated density, so density is set as a browser flag: that yields true device pixels.
export async function launch() {
    const flags = ['--hide-scrollbars', '--force-color-profile=srgb', '--disable-features=TranslateUI', '--disable-renderer-backgrounding', '--disable-backgrounding-occluded-windows', '--disable-background-timer-throttling', '--no-sandbox'];
    const start = (dsf, w, h) => chromium.launch({ executablePath: CHROME, args: [...flags, `--force-device-scale-factor=${dsf}`, `--window-size=${w},${h + 87}`] });
    const [desk, phone] = await Promise.all([start(DESK_DSF, 1440, 900), start(PHONE_DSF, 500, 844)]);
    return { desk, phone, close: () => Promise.all([desk.close(), phone.close()]) };
}

export class Cam {
    static async open(browsers, name, { width = 1440, height = 900, phone = false, theme = 'light', login = null, scene }) {
        const cam = new Cam();
        cam.name = name;
        cam.scene = scene;
        const dsf = phone ? PHONE_DSF : DESK_DSF;
        cam.size = { width, height, dsf, phone };
        cam.ctx = await (phone ? browsers.phone : browsers.desk).newContext({
            viewport: null, hasTouch: phone,
            locale: 'en-GB', timezoneId: 'Europe/London', colorScheme: theme, reducedMotion: 'no-preference',
            userAgent: phone ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1' : undefined,
        });
        await cam.ctx.route('https://fonts.bunny.net/**', (r) => r.fulfill({ contentType: 'text/css', body: fontCss }));
        await cam.ctx.route(`${BASE}/__film/*`, (r) => r.fulfill({ contentType: 'font/woff2', body: r.request().url().includes('mono') ? mono : geist }));
        // The app keeps its theme in localStorage; set it before the first paint.
        await cam.ctx.addInitScript(`try{localStorage.setItem('flux.appearance','${theme}')}catch(e){}`);
        // Slow motion has to cover the page's own timers as well as its animations, or a toast that
        // stays five seconds would be gone in under one. The untouched timer is kept for the camera.
        await cam.ctx.addInitScript(`(()=>{const S=${SLOW},st=window.setTimeout,si=window.setInterval;window.__filmTimeout=st;if(S===1)return;window.setTimeout=(f,ms,...a)=>st(f,(ms||0)*S,...a);window.setInterval=(f,ms,...a)=>si(f,(ms||0)*S,...a)})()`);
        cam.page = await cam.ctx.newPage();
        cam.cdp = await cam.ctx.newCDPSession(cam.page);
        await cam.cdp.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 0, mobile: phone });
        cam.page.setDefaultTimeout(15000);
        cam.events = [];
        cam.marks = {};
        cam.frames = [];
        cam.pointer = { x: width / 2, y: height * 0.6 };
        cam.pending = [];
        if (login) await cam.login(login);
        return cam;
    }

    async login(email, code = null) {
        await this.page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
        await this.page.fill('input[name="email"]', email);
        await this.page.fill('input[name="password"]', 'password');
        await Promise.all([this.page.waitForURL((u) => u.pathname !== '/login'), this.page.click('button[type="submit"]')]);
        // Staff accounts need two-factor; the demo seeder gives them a fixed secret.
        if (this.page.url().includes('two-factor')) {
            await this.page.locator('input:not([type=hidden])').first().click();
            await this.page.keyboard.type(totp(code ?? 'JBSWY3DPEHPK3PXP'));
            await this.page.waitForURL((u) => !u.pathname.includes('two-factor'), { timeout: 8000 }).catch(async () => {
                await this.page.keyboard.press('Enter');
                await this.page.waitForURL((u) => !u.pathname.includes('two-factor'));
            });
        }
    }

    async goto(path) {
        await this.page.goto(path.startsWith('http') ? path : BASE + path, { waitUntil: 'networkidle' });
        await this.settle();
    }

    async settle() {
        // Never longer than two seconds: an image that fails to decode must not hold the film up.
        await this.page.evaluate(() => Promise.race([
            Promise.all([document.fonts.ready, ...[...document.images].filter((i) => !i.complete && i.loading !== 'lazy').map((i) => i.decode().catch(() => {}))]),
            new Promise((r) => (window.__filmTimeout ?? setTimeout)(r, 2000)),
        ]));
    }

    async record() {
        if (DRY) return;
        this.dir = join(RAW, this.scene, this.name);
        rmSync(this.dir, { recursive: true, force: true });
        mkdirSync(this.dir, { recursive: true });
        this.cdp.on('Page.screencastFrame', (f) => {
            const n = String(this.frames.length).padStart(6, '0') + '.jpg';
            this.frames.push([n, f.metadata.timestamp]);
            this.pending.push(writeFile(join(this.dir, n), Buffer.from(f.data, 'base64')));
            this.cdp.send('Page.screencastFrameAck', { sessionId: f.sessionId }).catch(() => {});
        });
        await this.cdp.send('Animation.enable');
        await this.cdp.send('Animation.setPlaybackRate', { playbackRate: 1 / SLOW });
        await this.cdp.send('Page.startScreencast', { format: 'jpeg', quality: 92, maxWidth: 7000, maxHeight: 7000, everyNthFrame: 1 });
        // Repaint once so an idle page still yields a first frame.
        await this.page.evaluate(() => { const d = document.createElement('div'); d.style.cssText = 'position:fixed;width:1px;height:1px;opacity:.01;top:0;left:0'; document.body.append(d); requestAnimationFrame(() => requestAnimationFrame(() => d.remove())); });
        await sleep(250);
        this.mark('start');
    }

    async stop() {
        this.mark('end');
        if (!DRY && this.dir) {
            await sleep(150);
            await this.cdp.send('Page.stopScreencast');
            await Promise.all(this.pending);
            const frames = this.frames.map(([n, t]) => [n, norm(t)]);
            const events = this.events.map((e) => ({ ...e, t: norm(e.t), ...(e.dur ? { dur: e.dur / SLOW } : {}) }));
            const marks = Object.fromEntries(Object.entries(this.marks).map(([k, t]) => [k, norm(t)]));
            writeFileSync(join(this.dir, 'index.json'), JSON.stringify({ size: this.size, frames, events, marks }));
            const span = frames.length ? frames.at(-1)[1] - frames[0][1] : 0;
            console.log(`  ${this.scene}/${this.name}: ${frames.length} frames over ${span.toFixed(1)} s of film time (${(frames.length / Math.max(span, 0.1)).toFixed(0)} fps), ${Object.keys(marks).length} marks`);
        }
        await this.ctx.close();
    }

    mark(name) {
        this.marks[name] = Date.now() / 1000;
    }

    // A mark that also leaves a still in a dry run, to check the scene before filming it.
    async snap(name) {
        this.mark(name);
        if (DRY) {
            mkdirSync(join(ROOT, 'out/dry'), { recursive: true });
            await this.page.screenshot({ path: join(ROOT, `out/dry/${this.scene}-${this.name}-${name}.jpg`), quality: 60 });
        }
    }

    log(type, x, y, extra = {}) {
        this.events.push({ t: Date.now() / 1000, type, x, y, ...extra });
    }

    // Film-time wait: real time is SLOW times longer.
    async hold(ms) {
        await sleep(DRY ? Math.min(ms, 60) : ms * SLOW);
    }

    async point(target) {
        const box = await target.boundingBox();
        if (!box) throw new Error(`no box for ${target}`);
        return { x: box.x + box.width / 2, y: box.y + box.height / 2, box };
    }

    // Brings a target into view with an eased scroll rather than Playwright's jump.
    async reveal(target, { margin = 110, dur = 700 } = {}) {
        const box = await target.boundingBox();
        const h = this.size.height;
        if (box && box.y >= margin && box.y + box.height <= h - margin) return;
        if (!box) return target.scrollIntoViewIfNeeded();
        // Longer travel takes longer, so a long scroll does not turn into a blur.
        const dy = box.y - h * 0.42 + box.height / 2;
        await this.scrollBy(dy, Math.min(1400, Math.max(dur, Math.abs(dy) * 0.9)));
    }

    async move(target, { before = 380 } = {}) {
        await target.waitFor({ state: 'visible' });
        const { x, y, box } = await this.point(target);
        this.log('move', x, y, { w: box.width, h: box.height });
        this.pointer = { x, y };
        if (!this.size.phone) await this.page.mouse.move(x, y);
        await this.hold(before);
    }

    async tap(target, { before = 420, after = 320, reveal = true, force = false, mark = null } = {}) {
        await target.waitFor({ state: 'visible' });
        if (reveal) await this.reveal(target);
        const { x, y, box } = await this.point(target);
        this.log('move', x, y, { w: box.width, h: box.height });
        this.pointer = { x, y };
        if (!this.size.phone) await this.page.mouse.move(x, y);
        await this.hold(before);
        this.log('click', x, y, { w: box.width, h: box.height });
        if (mark) this.mark(mark);
        await target.click({ force, timeout: 8000 });
        await this.hold(after);
    }

    async type(target, text, { cps = 14, before = 300, after = 260, click = true } = {}) {
        if (click) await this.tap(target, { before, after: 120 });
        for (const ch of text) {
            await this.page.keyboard.type(ch);
            this.log('key', this.pointer.x, this.pointer.y);
            await this.hold(1000 / cps * (0.75 + Math.random() * 0.5));
        }
        await this.hold(after);
    }

    async scrollBy(dy, dur = 1400) {
        if (DRY) return this.page.evaluate((d) => window.scrollBy(0, d), dy);
        this.log('scroll', this.pointer.x, this.pointer.y, { dy, dur: dur * SLOW });
        // The clock is read directly: under a slowed animation rate the frame callback's own timestamp runs slow too.
        await this.page.evaluate(`new Promise(r=>{const e=${ease},a=window.scrollY,s=performance.now(),d=${dy},D=${dur * SLOW};const f=()=>{const k=Math.min((performance.now()-s)/D,1);window.scrollTo(0,a+d*e(k));k<1?requestAnimationFrame(f):r()};requestAnimationFrame(f)})`);
    }

    async scrollInto(target, opts = {}) {
        const box = await target.boundingBox();
        await this.scrollBy(box.y - (opts.top ?? 90), opts.dur ?? 1300);
    }

    // Livewire answers over the network; wait until the page is quiet again.
    async idle(ms = 250) {
        await this.page.waitForLoadState('networkidle').catch(() => {});
        await this.settle().catch(() => {});
        await this.hold(ms);
    }
}

// RFC 6238, for the seeded staff accounts.
export function totp(secret, at = Date.now()) {
    const bits = [...secret.replace(/=+$/, '')].map((c) => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'.indexOf(c).toString(2).padStart(5, '0')).join('');
    const key = Buffer.from(bits.match(/.{8}/g).map((b) => parseInt(b, 2)));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(at / 30000)));
    const mac = createHmac('sha1', key).update(counter).digest();
    const o = mac[19] & 15;
    return String(((mac.readUInt32BE(o) & 0x7fffffff) % 1e6)).padStart(6, '0');
}

export function artisan(code) {
    return execFileSync(PHP, ['-d', 'error_reporting=24575', 'artisan', 'tinker', `--execute=${code}`], { cwd: APP, encoding: 'utf8' });
}

export const slugOf = (title, company = 'fernhill-software') => artisan(`echo App\\Models\\Company::where('slug','${company}')->first()->jobPostings()->where('title','${title}')->value('slug');`).trim().split('\n').pop().trim();
