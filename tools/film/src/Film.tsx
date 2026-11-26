import { AbsoluteFill, Easing, Img, interpolate, Sequence, spring, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { capture, Caption, Device, FPS, reel, Key, Reel, Shot, shots, srcAt, T_CLOSE, T_ENG, TOTAL } from './timeline';

// The app's own palette: ink on white, and the Sunset gradient kept for the one thing that matters.
export const C = {
    paper: '#F5F4F2', surface: '#FFFFFF', ink: '#0A0A0A', muted: '#57534E', line: '#E7E5E4',
    night: '#0B0B0D', nightSurface: '#17171A', nightInk: '#FAFAF9', nightMuted: '#A8A29E', nightLine: '#2A2A2E',
    s1: '#C2410C', s2: '#DB2777', s3: '#7C3AED', l1: '#FB923C', l2: '#F472B6', l3: '#A78BFA',
};
const sunset = `linear-gradient(120deg, ${C.s1} 0%, ${C.s2} 55%, ${C.s3} 100%)`;
const sunsetLit = `linear-gradient(120deg, ${C.l1} 0%, ${C.l2} 55%, ${C.l3} 100%)`;
const ui = 'Geist', mono = 'Geist Mono';
const inOut = Easing.bezier(0.65, 0, 0.35, 1), out = Easing.bezier(0.22, 1, 0.36, 1);
export const fontCss = `
@font-face{font-family:'Geist';font-weight:100 900;src:url('${staticFile('fonts/geist-latin-wght-normal.woff2')}') format('woff2')}
@font-face{font-family:'Geist Mono';font-weight:100 900;src:url('${staticFile('fonts/geist-mono-latin-wght-normal.woff2')}') format('woff2')}`;
const clamp = { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' } as const;
export const gradText = (lit: boolean): React.CSSProperties => ({ backgroundImage: lit ? sunsetLit : sunset, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' });

function Screen({ r, t, w, h }: { r: Reel; t: number; w: number; h: number }) {
    const cap = capture(r.cam);
    const src = srcAt(r, t);
    let lo = 0, hi = cap.times.length - 1;
    while (lo < hi) { const mid = (lo + hi + 1) >> 1; if (cap.times[mid] <= src) lo = mid; else hi = mid - 1; }
    return <Img src={staticFile(`raw/${r.cam}/${String(lo).padStart(6, '0')}.jpg`)} style={{ position: 'absolute', inset: 0, width: w, height: h, display: 'block' }} />;
}

// Desktop pointer, rebuilt from the logged targets so it glides whatever the capture did.
function Pointer({ r, t }: { r: Reel; t: number }) {
    const cap = capture(r.cam);
    const src = srcAt(r, t);
    const ev = cap.events.filter((e) => e.type === 'move' || e.type === 'click');
    if (!ev.length) return null;
    const rest = { x: cap.size.width * 0.62, y: cap.size.height * 0.72 };
    const i = ev.reduce((found, e, n) => (e.t <= src ? n : found), -1);
    const to = i >= 0 ? ev[i] : rest;
    const from = i >= 1 ? ev[i - 1] : rest;
    const k = i >= 0 ? interpolate(src, [ev[i].t, ev[i].t + 0.34], [0, 1], { extrapolateRight: 'clamp', easing: inOut }) : 1;
    const x = from.x + (to.x - from.x) * k;
    const y = from.y + (to.y - from.y) * k;
    const click = cap.events.filter((e) => e.type === 'click' && e.t <= src).pop();
    const age = click ? src - click.t : 9;
    const press = age < 0.16 ? 0.9 : 1;
    return (
        <>
            {age < 0.55 && <div style={{ position: 'absolute', left: click!.x - 26, top: click!.y - 26, width: 52, height: 52, borderRadius: 99, border: `3px solid ${C.s2}`, opacity: 1 - age / 0.55, transform: `scale(${0.4 + age * 1.8})` }} />}
            <svg width="26" height="30" viewBox="0 0 26 30" style={{ position: 'absolute', left: x - 3, top: y - 2, transform: `scale(${press})`, filter: 'drop-shadow(0 3px 5px rgba(10,10,10,.4))' }}>
                <path d="M3 2 L3 24 L9 18.5 L13 27 L17.5 25 L13.5 16.8 L21.5 16.2 Z" fill={C.ink} stroke="#FFFFFF" strokeWidth="2" strokeLinejoin="round" />
            </svg>
        </>
    );
}

function Touch({ r, t }: { r: Reel; t: number }) {
    const cap = capture(r.cam);
    const src = srcAt(r, t);
    const near = cap.events.filter((e) => e.type === 'click').map((e) => ({ e, d: src - e.t })).find(({ d }) => d > -0.26 && d < 0.34);
    if (!near) return null;
    const { e, d } = near;
    const o = d < 0 ? interpolate(d, [-0.26, -0.06], [0, 0.85], clamp) : interpolate(d, [0, 0.34], [0.85, 0]);
    const s = d < 0 ? interpolate(d, [-0.26, 0], [1.25, 0.9], { extrapolateLeft: 'clamp' }) : interpolate(d, [0, 0.34], [0.9, 1.5]);
    return <div style={{ position: 'absolute', left: e.x - 23, top: e.y - 23, width: 46, height: 46, borderRadius: 99, background: 'rgba(219,39,119,.30)', border: '2px solid rgba(255,255,255,.92)', opacity: o, transform: `scale(${s})` }} />;
}

export function DeviceFrame({ d, t, dark }: { d: Device; t: number; dark: boolean }) {
    const { width: w, height: h } = capture(d.reel.cam).size;
    if (d.kind === 'phone') {
        const b = 13;
        return (
            <div style={{ position: 'absolute', left: d.x, top: d.y, width: w + b * 2, height: h + b * 2, transform: `scale(${d.s})`, transformOrigin: '0 0', borderRadius: 62, background: '#050506', boxShadow: dark ? '0 0 0 1.5px #3F3F46, 0 40px 90px rgba(0,0,0,.7)' : '0 0 0 1.5px #27272A, 0 36px 70px rgba(10,10,10,.26), 0 8px 18px rgba(10,10,10,.14)' }}>
                <div style={{ position: 'absolute', left: b, top: b, width: w, height: h, borderRadius: 50, overflow: 'hidden', background: '#fff' }}>
                    <Screen r={d.reel} t={t} w={w} h={h} />
                    <Touch r={d.reel} t={t} />
                </div>
            </div>
        );
    }
    const bar = 40;
    return (
        <div style={{ position: 'absolute', left: d.x, top: d.y, width: w, height: h + bar, transform: `scale(${d.s})`, transformOrigin: '0 0', borderRadius: 16, overflow: 'hidden', background: C.surface, boxShadow: dark ? '0 0 0 1px #2A2A2E, 0 40px 90px rgba(0,0,0,.7)' : '0 0 0 1px rgba(10,10,10,.1), 0 30px 70px rgba(10,10,10,.16), 0 6px 16px rgba(10,10,10,.08)' }}>
            <div style={{ height: bar, background: '#EFEEEC', display: 'flex', alignItems: 'center', gap: 8, padding: '0 16px' }}>
                {[0, 1, 2].map((i) => <div key={i} style={{ width: 11, height: 11, borderRadius: 9, background: '#D6D3D1' }} />)}
                <div style={{ marginLeft: 16, flex: 1, maxWidth: 560, height: 24, borderRadius: 8, background: '#FAFAF9', fontFamily: ui, fontSize: 13, color: C.muted, display: 'flex', alignItems: 'center', padding: '0 12px' }}>HearBack{d.path && d.path !== '/' ? ` · ${d.path}` : ''}</div>
            </div>
            <div style={{ position: 'relative', width: w, height: h, overflow: 'hidden' }}>
                <Screen r={d.reel} t={t} w={w} h={h} />
                <Pointer r={d.reel} t={t} />
            </div>
        </div>
    );
}

// A slow camera: zooms arrive on `at`, having eased in over the preceding 0.85 s.
function cameraAt(keys: Key[], devices: Device[], t: number) {
    const pt = (k: Key) => {
        const d = devices[k.dev ?? 0] ?? { x: 0, y: 0, s: 1 };
        const pad = d.kind === 'browser' ? 40 : d.kind ? 13 : 0;
        const px = d.x + k.fx * d.s + (d.kind === 'browser' ? 0 : pad * d.s);
        const py = d.y + (k.fy + pad) * d.s;
        return { z: k.z, px, py, tx: k.z === 1 ? px : k.tx ?? 960, ty: k.z === 1 ? py : k.ty ?? 540 };
    };
    let cur = pt(keys[0]);
    for (let i = 1; i < keys.length; i++) {
        const a = pt(keys[i - 1]), b = pt(keys[i]);
        const dur = 0.85;
        const k = interpolate(t, [keys[i].at - dur, keys[i].at], [0, 1], { ...clamp, easing: inOut });
        if (t >= keys[i].at - dur) cur = { z: a.z * Math.pow(b.z / a.z, k), px: a.px + (b.px - a.px) * k, py: a.py + (b.py - a.py) * k, tx: a.tx + (b.tx - a.tx) * k, ty: a.ty + (b.ty - a.ty) * k };
    }
    const sx = cur.tx - cur.px * cur.z, sy = cur.ty - cur.py * cur.z;
    return `translate(${sx}px, ${sy}px) scale(${cur.z})`;
}

function Rule({ k, lit, width = 150 }: { k: number; lit: boolean; width?: number }) {
    return <div style={{ width: width * k, height: 4, borderRadius: 4, backgroundImage: lit ? sunsetLit : sunset, backgroundSize: `${width}px 4px` }} />;
}

function CaptionView({ c, dark, t }: { c: Caption; dark: boolean; t: number }) {
    const { fps } = useVideoConfig();
    if (t < c.at - 0.02 || t > c.end + 0.35) return null;
    const enter = spring({ frame: Math.round((t - c.at) * fps), fps, config: { stiffness: 170, damping: 26, mass: 0.9 } });
    const leave = interpolate(t, [c.end, c.end + 0.3], [1, 0], { ...clamp, easing: inOut });
    const ink = dark ? C.nightInk : C.ink, muted = dark ? C.nightMuted : C.muted;
    const size = c.size ?? 70;
    return (
        <div style={{ position: 'absolute', left: c.x, top: c.y, width: c.card ? 'fit-content' : c.w, maxWidth: c.w, opacity: enter * leave, transform: `translateY(${(1 - enter) * 26}px)`, ...(c.card ? { background: dark ? C.nightSurface : C.surface, padding: '20px 34px 24px', borderRadius: 18, boxShadow: dark ? '0 0 0 1px #2A2A2E, 0 18px 44px rgba(0,0,0,.6)' : '0 0 0 1px rgba(10,10,10,.07), 0 18px 44px rgba(10,10,10,.18)' } : {}) }}>
            {c.label && <div style={{ fontFamily: mono, fontWeight: 500, fontSize: 22, letterSpacing: '0.1em', textTransform: 'uppercase', width: 'fit-content', marginBottom: c.card || size < 60 ? 8 : 18, ...gradText(dark) }}>{c.label}</div>}
            {c.title && <div style={{ fontFamily: ui, fontWeight: 700, whiteSpace: size < 60 ? 'nowrap' : undefined, fontSize: size, lineHeight: 1.06, letterSpacing: '-0.035em', color: ink, textWrap: 'balance' }}>{c.title}</div>}
            {size >= 60 && <div style={{ margin: '24px 0 22px' }}><Rule k={interpolate(t, [c.at + 0.15, c.at + 0.75], [0, 1], { ...clamp, easing: out })} lit={dark} /></div>}
            {c.body && <div style={{ fontFamily: ui, fontWeight: 450, fontSize: 31, lineHeight: 1.4, letterSpacing: '-0.01em', color: muted, marginTop: size >= 60 ? 0 : 12, textWrap: 'pretty' }}>{c.body}</div>}
        </div>
    );
}

// The HearBack mark: a rounded square in the Sunset gradient, a ring that almost closes, a dot at its centre.
export function Mark({ size, draw = 1 }: { size: number; draw?: number }) {
    return (
        <svg width={size} height={size} viewBox="0 0 26 26">
            <defs>
                <linearGradient id="hb-fill" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stopColor={C.s1} /><stop offset="0.55" stopColor={C.s2} /><stop offset="1" stopColor={C.s3} /></linearGradient>
                <mask id="hb-hole"><rect width="26" height="26" fill="white" /><circle cx="13" cy="13" r="2.2" fill="black" /></mask>
            </defs>
            <rect width="26" height="26" rx="7" fill="url(#hb-fill)" mask="url(#hb-hole)" />
            <circle cx="13" cy="13" r="6.5" fill="none" stroke="#fff" strokeWidth="2.5" strokeLinecap="round" strokeDasharray={`${30 * draw} 41`} transform="rotate(-90 13 13)" />
        </svg>
    );
}

// The opening: an application goes out, the days count up, nothing comes back. Then the answer.
function Open({ t }: { t: number }) {
    const { fps } = useVideoConfig();
    const sp = (d: number) => spring({ frame: Math.max(0, Math.round((t - d) * fps)), fps, config: { stiffness: 170, damping: 26, mass: 0.9 } });
    const first = interpolate(t, [0.35, 0.9], [0, 1], clamp);
    const second = interpolate(t, [1.75, 2.3], [0, 1], clamp);
    const gone = interpolate(t, [4.35, 4.85], [1, 0], clamp);
    const day = Math.round(interpolate(t, [1.9, 4.2], [1, 30], { ...clamp, easing: Easing.bezier(0.5, 0, 0.9, 0.6) }));
    const dots = [0, 1, 2].map((i) => 0.25 + 0.75 * Math.max(0, Math.sin((t * 2.2 - i * 0.45) * Math.PI)) ** 2);
    const draw = interpolate(t, [5.0, 5.9], [0, 1], { ...clamp, easing: out });
    return (
        <AbsoluteFill style={{ background: C.night }}>
            <AbsoluteFill style={{ alignItems: 'center', justifyContent: 'center', opacity: gone }}>
                <div style={{ fontFamily: ui, fontWeight: 700, fontSize: 104, letterSpacing: '-0.04em', color: C.nightInk, opacity: first, transform: `translateY(${(1 - first) * 18}px)` }}>You applied.</div>
                <div style={{ fontFamily: ui, fontWeight: 700, fontSize: 104, letterSpacing: '-0.04em', color: '#6B6B73', opacity: second, transform: `translateY(${(1 - second) * 18}px)` }}>Then nothing.</div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 22, marginTop: 54, opacity: second }}>
                    <div style={{ fontFamily: mono, fontWeight: 500, fontSize: 30, letterSpacing: '0.08em', color: C.nightMuted, width: 150, textAlign: 'right', fontVariantNumeric: 'tabular-nums' }}>DAY {day}</div>
                    <div style={{ display: 'flex', gap: 10 }}>{dots.map((o, i) => <div key={i} style={{ width: 12, height: 12, borderRadius: 9, background: C.nightMuted, opacity: o }} />)}</div>
                </div>
            </AbsoluteFill>
            <AbsoluteFill style={{ alignItems: 'center', justifyContent: 'center' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 34, opacity: sp(4.85), transform: `scale(${0.92 + 0.08 * sp(4.85)})` }}>
                    <Mark size={150} draw={draw} />
                    <div style={{ fontFamily: ui, fontWeight: 700, fontSize: 150, letterSpacing: '-0.045em', color: C.nightInk }}>HearBack</div>
                </div>
                <div style={{ fontFamily: ui, fontWeight: 500, fontSize: 44, letterSpacing: '-0.02em', marginTop: 34, opacity: sp(6.0), transform: `translateY(${(1 - sp(6.0)) * 14}px)`, color: C.nightInk }}>A job board where <span style={gradText(true)}>silence shows</span>.</div>
            </AbsoluteFill>
        </AbsoluteFill>
    );
}

function Themes({ t, start, end }: { t: number; start: number; end: number }) {
    const light = reel('look/light', start, [['end', 99, 'hold']]);
    const dark = reel('look/dark', start, [['end', 99, 'hold']]);
    const seam = interpolate(t, [start + 0.3, end - 0.4], [1300, 420], { ...clamp, easing: inOut });
    const s = 1.1, left = 168, top = 24;
    const W = 1440 * s, H = 900 * s;
    const box = { position: 'absolute' as const, left, top, width: W, height: H, borderRadius: 16, overflow: 'hidden' };
    const lean = 120;
    return (
        <AbsoluteFill>
            <div style={box}><Screen r={dark} t={start + 0.5} w={W} h={H} /></div>
            <div style={{ ...box, clipPath: `polygon(0 0, ${seam + lean}px 0, ${seam - lean}px 100%, 0 100%)` }}><Screen r={light} t={start + 0.5} w={W} h={H} /></div>
            <svg width="1920" height="1080" style={{ position: 'absolute', inset: 0 }}>
                <defs><linearGradient id="seam" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stopColor={C.l1} /><stop offset="0.55" stopColor={C.l2} /><stop offset="1" stopColor={C.l3} /></linearGradient></defs>
                <line x1={left + seam + lean} y1={top} x2={left + seam - lean} y2={top + H} stroke="url(#seam)" strokeWidth="4" />
            </svg>
        </AbsoluteFill>
    );
}

// Counted in the repository on the day of the film; see docs/media/film/NOTES.md.
const FACTS: [string, string][] = [
    ['1,590+', 'Pest tests, run by GitHub Actions'],
    ['19', 'policies: nobody can act above their role'],
    ['10 min', 'to undo a hire or a rejection before the email goes out'],
    ['9', 'architecture decisions written down as ADRs'],
];
const STACK = ['Laravel 13', 'Livewire 4', 'Flux UI', 'Filament 5', 'Tailwind 4', 'MySQL', 'Pest', 'GitHub Actions'];

function Eng({ t }: { t: number }) {
    const { fps } = useVideoConfig();
    const rel = t - T_ENG;
    const reveal = (d: number) => spring({ frame: Math.max(0, Math.round((rel - d) * fps)), fps, config: { stiffness: 170, damping: 26, mass: 0.9 } });
    return (
        <AbsoluteFill style={{ background: C.night, padding: '92px 130px' }}>
            <div style={{ fontFamily: mono, fontWeight: 500, fontSize: 22, letterSpacing: '0.1em', textTransform: 'uppercase', width: 'fit-content', opacity: reveal(0.2), ...gradText(true) }}>The build</div>
            <div style={{ fontFamily: ui, fontWeight: 700, fontSize: 76, lineHeight: 1.06, letterSpacing: '-0.04em', color: C.nightInk, marginTop: 16, opacity: reveal(0.3), transform: `translateY(${(1 - reveal(0.3)) * 22}px)` }}>Nothing just disappears.</div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '36px 70px', marginTop: 60 }}>
                {FACTS.map(([n, l], i) => {
                    const k = reveal(0.9 + i * 0.45);
                    return (
                        <div key={n} style={{ opacity: k, transform: `translateY(${(1 - k) * 22}px)`, borderTop: `1.5px solid ${C.nightLine}`, paddingTop: 20 }}>
                            <div style={{ fontFamily: ui, fontWeight: 700, fontSize: 86, lineHeight: 1, letterSpacing: '-0.04em', width: 'fit-content', fontVariantNumeric: 'tabular-nums', ...gradText(true) }}>{n}</div>
                            <div style={{ fontFamily: ui, fontSize: 31, lineHeight: 1.35, color: C.nightInk, marginTop: 12 }}>{l}</div>
                        </div>
                    );
                })}
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginTop: 54 }}>
                {STACK.map((s, i) => {
                    const k = reveal(2.9 + i * 0.09);
                    return <div key={s} style={{ opacity: k, transform: `scale(${0.96 + k * 0.04})`, fontFamily: ui, fontWeight: 600, fontSize: 26, color: C.nightInk, border: `1.5px solid ${C.nightLine}`, background: C.nightSurface, borderRadius: 10, padding: '11px 20px' }}>{s}</div>;
                })}
            </div>
        </AbsoluteFill>
    );
}

function Close({ t }: { t: number }) {
    const { fps } = useVideoConfig();
    const rel = t - T_CLOSE;
    const r = (d: number) => spring({ frame: Math.max(0, Math.round((rel - d) * fps)), fps, config: { stiffness: 170, damping: 26, mass: 0.9 } });
    const fadeOut = interpolate(t, [TOTAL - 1.3, TOTAL - 0.15], [1, 0], clamp);
    return (
        <AbsoluteFill style={{ background: C.paper, alignItems: 'center', justifyContent: 'center', textAlign: 'center' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 28, opacity: r(0.3), transform: `translateY(${(1 - r(0.3)) * 20}px)` }}>
                <Mark size={116} draw={interpolate(rel, [0.5, 1.3], [0, 1], { ...clamp, easing: out })} />
                <div style={{ fontFamily: ui, fontWeight: 700, fontSize: 118, letterSpacing: '-0.045em', color: C.ink }}>HearBack</div>
            </div>
            <div style={{ margin: '30px 0 30px', display: 'flex', justifyContent: 'center' }}><Rule k={interpolate(rel, [0.8, 1.5], [0, 1], { ...clamp, easing: out })} lit={false} width={240} /></div>
            <div style={{ fontFamily: ui, fontWeight: 600, fontSize: 42, letterSpacing: '-0.02em', width: 'fit-content', opacity: r(1.1), ...gradText(false) }}>github.com/Md-Foisal/job-board</div>
            <div style={{ fontFamily: ui, fontWeight: 450, fontSize: 30, color: C.ink, marginTop: 18, opacity: r(1.4) }}>Designed and built by Md. Foisal</div>
            <div style={{ fontFamily: ui, fontWeight: 450, fontSize: 22, color: C.muted, marginTop: 46, opacity: r(1.9) }}>A portfolio project, shown with demo data. Every company and person in this film is made up.</div>
            <AbsoluteFill style={{ background: C.night, opacity: 1 - fadeOut }} />
        </AbsoluteFill>
    );
}

// The ring of the mark, opening until it clears the frame.
const ringClip = (k: number) => `circle(${interpolate(k, [0, 1], [0, 1250])}px at 960px 540px)`;

function ShotView({ shot }: { shot: Shot }) {
    const frame = useCurrentFrame();
    const { fps } = useVideoConfig();
    const t = frame / fps + shot.start;
    const dark = shot.bg === 'ink';
    const rel = t - shot.start;
    const enterK = shot.enter === 'ring' ? interpolate(rel, [0, 0.95], [0, 1], { extrapolateRight: 'clamp', easing: inOut })
        : shot.enter === 'rise' ? spring({ frame, fps, config: { stiffness: 150, damping: 24, mass: 0.9 } })
        : shot.enter === 'fade' ? interpolate(rel, [0, 0.45], [0, 1], { extrapolateRight: 'clamp' }) : 1;
    const style: React.CSSProperties = {};
    if (shot.enter === 'ring') style.clipPath = enterK < 1 ? ringClip(enterK) : undefined;
    if (shot.enter === 'rise') { style.transform = `translateY(${(1 - enterK) * 1100}px)`; style.boxShadow = '0 -30px 80px rgba(0,0,0,.28)'; }
    if (shot.enter === 'fade') style.opacity = enterK;
    const cam = shot.keys?.length ? cameraAt(shot.keys, shot.devices, t) : undefined;
    return (
        <AbsoluteFill style={{ background: dark ? C.night : C.paper, ...style }}>
            {shot.custom === 'open' && <Open t={t} />}
            {shot.custom === 'themes' && <Themes t={t} start={shot.start} end={shot.end} />}
            {shot.custom === 'eng' && <Eng t={t} />}
            {shot.custom === 'close' && <Close t={t} />}
            <AbsoluteFill style={{ transform: cam, transformOrigin: '0 0' }}>
                {shot.devices.map((d, i) => <DeviceFrame key={i} d={d} t={t} dark={dark} />)}
            </AbsoluteFill>
            {shot.captions?.map((c, i) => <CaptionView key={i} c={c} dark={dark} t={t} />)}
        </AbsoluteFill>
    );
}

export function Film() {
    return (
        <AbsoluteFill style={{ background: C.night }}>
            <style>{fontCss}</style>
            {shots.map((s, i) => {
                const next = shots[i + 1];
                const stop = next ? next.start + (next.enter === 'cut' ? 0 : 1.1) : TOTAL;
                return (
                    <Sequence key={s.id} from={Math.round(s.start * FPS)} durationInFrames={Math.round((stop - s.start) * FPS)} layout="none">
                        <ShotView shot={s} />
                    </Sequence>
                );
            })}
        </AbsoluteFill>
    );
}
