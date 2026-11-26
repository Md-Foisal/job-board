// The edit decision list: pure data, read by the renderer (Film.tsx) and the sound and caption scripts.
import { captures } from './data/captures.ts';

export type Capture = { size: { width: number; height: number; dsf: number; phone: boolean }; times: number[]; marks: Record<string, number>; events: { t: number; type: string; x: number; y: number; w?: number; h?: number }[] };
const caps = captures as unknown as Record<string, Capture>;
export const capture = (cam: string) => caps[cam];

export const FPS = 60;
// The music is written for the film at 90 BPM, so every cut can sit on a beat.
export const BEAT = 60 / 90;
export const B = (n: number) => n * BEAT;
export const TOTAL = B(191);

export type Seg = { film0: number; film1: number; src0: number; src1: number };
export type Reel = { cam: string; segs: Seg[] };

// 'sent+0.5' reads a mark in the camera's own clock.
const at = (cam: string, s: string | number) => {
    if (typeof s === 'number') return s;
    if (caps[cam].marks[s] !== undefined) return caps[cam].marks[s];
    const m = s.match(/^([a-z0-9-]+?)([+-][\d.]+)?$/i)!;
    const base = caps[cam].marks[m[1]];
    if (base === undefined) throw new Error(`no mark ${s} on ${cam}`);
    return base + Number(m[2] ?? 0);
};

// A stretch of one camera, played so that it fills the film window it is given.
export function fit(cam: string, start: number, end: number, from: string | number = 'start', to: string | number = 'end', clock = cam): Reel {
    return { cam, segs: [{ film0: start, film1: end, src0: at(clock, from), src1: at(clock, to) }] };
}

// A cut list for one camera. [from, to, rate] plays that stretch at that speed; [moment, seconds, 'hold']
// rests on one frame. Waiting that nobody needs to watch is simply left out between two parts.
export type Part = [string | number, string | number, number?] | [string | number, number, 'hold'];
export function reel(cam: string, start: number, parts: Part[], clock = cam): Reel {
    let film = start;
    const segs = parts.map((p) => {
        const src0 = at(clock, p[0]);
        const hold = p[2] === 'hold';
        const src1 = hold ? src0 : at(clock, p[1]);
        const seg = { film0: film, film1: film + (hold ? (p[1] as number) : (src1 - src0) / ((p[2] as number) ?? 1)), src0, src1 };
        film = seg.film1;
        return seg;
    });
    return { cam, segs };
}

export const reelEnd = (r: Reel) => r.segs[r.segs.length - 1].film1;
export const join = (...reels: Reel[]): Reel => ({ cam: reels[0].cam, segs: reels.flatMap((r) => r.segs) });

export function srcAt(r: Reel, t: number) {
    const s = r.segs.find((x) => t < x.film1) ?? r.segs[r.segs.length - 1];
    const k = Math.min(1, Math.max(0, (t - s.film0) / Math.max(1e-6, s.film1 - s.film0)));
    return s.src0 + (s.src1 - s.src0) * k;
}

// Film time of a moment in a camera's clock: inside the part that plays it, or at the start of the next part.
export function when(r: Reel, mark: string, clock = r.cam) {
    const src = at(clock, mark);
    const s = r.segs.find((x) => src <= x.src1 + 1e-6) ?? r.segs[r.segs.length - 1];
    const span = s.src1 - s.src0;
    return s.film0 + (span > 0 ? Math.min(Math.max((src - s.src0) / span, 0), 1) * (s.film1 - s.film0) : 0);
}

// Film times of a reel's real clicks and key presses: these drive the UI ticks in the mix.
export function eventsInFilm(r: Reel, type: string) {
    const out: number[] = [];
    for (const s of r.segs) for (const e of caps[r.cam].events) {
        if (e.type === type && s.src1 > s.src0 && e.t >= s.src0 && e.t <= s.src1) out.push(s.film0 + ((e.t - s.src0) / (s.src1 - s.src0)) * (s.film1 - s.film0));
    }
    return out;
}

// The n-th pointer target of a camera, for aiming a zoom at what was clicked.
const target = (cam: string, type: string, n: number) => caps[cam].events.filter((e) => e.type === type)[n];

export type Device = { kind: 'phone' | 'browser'; reel: Reel; x: number; y: number; s: number; path?: string };
export type Key = { at: number; z: number; fx: number; fy: number; tx?: number; ty?: number; dev?: number };
export type Caption = { at: number; end: number; label?: string; title?: string; body?: string; x: number; y: number; w: number; size?: number; card?: boolean };
export type Shot = { id: string; chapter?: string; start: number; end: number; bg: 'paper' | 'ink'; enter: 'ring' | 'rise' | 'fade' | 'cut'; devices: Device[]; keys?: Key[]; captions?: Caption[]; custom?: string };

const phoneRight = { x: 1236, y: 66, s: 1.08 };
const browserFull = { x: 182, y: 30, s: 1.08 };

// Chapter starts, in beats.
export const BEATS = { home: 12, apply: 21, review: 55, company: 92, cv: 111, analytics: 135, staff: 146, look: 158, eng: 166, close: 179 };
const T = Object.fromEntries(Object.entries(BEATS).map(([k, b]) => [k, B(b)])) as Record<keyof typeof BEATS, number>;
export const T_ENG = T.eng;
export const T_CLOSE = T.close;

// The guest types a search; the wait for the results page is cut.
const home = reel('home/desk', T.home, [['start+0.3', 'search+0.35'], ['results', 9, 'hold']]);

// Rafi on his phone. Each part is one thing he does; reading pauses are kept, loading waits are not.
const apply = reel('apply/rafi', T.apply, [
    ['start+0.1', 'open+0.3'],
    ['job+0.9', 'match+2.0'],
    ['match2-1.5', 'match2+0.9'],
    ['top-1.9', 'apply+0.4', 1.2],
    ['form+1.2', 'typed+0.2', 1.15],
    ['submit-0.6', 'submit+0.5'],
    ['sent', 'follow+0.25'],
    ['timeline-0.6', 'end'],
]);

// Hannah's desktop and Rafi's phone were filmed together, so they share one clock and one cut list.
const H = 'review/hannah', P = 'review/rafi';
const together: Part[] = [
    ['start+0.6', 'to-board+0.5'],
    ['board+0.6', 'to-detail+0.4'],
    ['detail+0.6', 'saw-shortlisted+1.9'],
    ['menu-2-0.6', 'saw-interview+1.6'],
    ['do-hire-0.7', 'undo-0.5'],
];
const hannah = reel(H, T.review, together);
const tAnswer = reelEnd(hannah);
const rafi = join(reel(P, T.review, together, H), reel(P, tAnswer, [['reload-3', 'end']]));

const company = reel('company/desk', T.company, [['start+0.4', 'mark+0.05'], ['mark+0.05', 1.6, 'hold'], ['mark+0.05', 'reviews+2.25'], ['reply-1.6', 'end']]);

// The CV builder. Choosing a template redraws the page from its top; the film cuts to the settled result.
const cv = reel('cv/rafi', T.cv, [
    ['start+0.9', 'to-design+0.5'],
    ['design+0.05', 'pick-modern+0.45'],
    ['tpl-modern', 0.9, 'hold'],
    ['pick-executive-1.25', 'pick-executive+0.45'],
    ['tpl-executive', 1.0, 'hold'],
    ['pick-creative-1.15', 'pick-creative+0.45'],
    ['tpl-creative', 1.1, 'hold'],
    ['pick-colour-2-1.65', 'pick-colour-2+0.45', 1.15],
    ['colour-2', 1.0, 'hold'],
    ['pick-colour-5-1.75', 'pick-colour-5+0.5', 1.15],
    ['colour-5', 9, 'hold'],
]);

const analytics = reel('analytics/hannah', T.analytics, [['start+0.5', 'end']]);
const staff = reel('staff/mod', T.staff, [['start+0.7', 'to-queue+0.4'], ['queue+0.3', 'to-view+0.5'], ['to-view+0.5', 'view+2.2']]);

const mark = target('company/desk', 'move', 0);
const viewBtn = target('staff/mod', 'click', 1);
const board = { x: 56, y: 178, s: 0.9 };
const top = { x: 60, y: 38, w: 1320, size: 50 };

export const shots: Shot[] = [
    { id: 'open', chapter: 'Opening', start: 0, end: T.home, bg: 'ink', enter: 'cut', devices: [], custom: 'open' },
    {
        id: 'home', start: T.home, end: T.apply, bg: 'paper', enter: 'ring',
        devices: [{ kind: 'browser', reel: home, ...browserFull, path: '/' }],
        keys: [{ at: T.home, z: 1, fx: 720, fy: 450 }, { at: when(home, 'hero') + 0.7, z: 1.3, fx: 520, fy: 470, tx: 900, ty: 500 }, { at: when(home, 'results') + 0.5, z: 1, fx: 720, fy: 450 }],
        captions: [{ at: T.home + 0.9, end: T.apply - 0.1, title: 'A job board, built end to end.', body: 'One application, followed until it gets an answer.', x: 70, y: 852, w: 1100, size: 48, card: true }],
    },
    {
        id: 'apply', chapter: 'The candidate: search, match, apply', start: T.apply, end: T.review, bg: 'paper', enter: 'rise',
        devices: [{ kind: 'phone', reel: apply, ...phoneRight }],
        keys: [
            { at: T.apply, z: 1, fx: 195, fy: 422 },
            { at: when(apply, 'match') - 0.2, z: 1.42, fx: 195, fy: 400, tx: 1400, ty: 540 },
            { at: when(apply, 'top') - 0.3, z: 1, fx: 195, fy: 422 },
            { at: when(apply, 'form') + 0.9, z: 1.36, fx: 195, fy: 520, tx: 1400, ty: 540 },
            { at: when(apply, 'sent') + 0.3, z: 1, fx: 195, fy: 422 },
            { at: when(apply, 'timeline') + 0.9, z: 1.36, fx: 195, fy: 330, tx: 1400, ty: 540 },
        ],
        captions: [
            { at: T.apply + 0.3, end: when(apply, 'job') - 0.05, label: 'The candidate', title: 'Rafi looks for Laravel work.', body: 'Each result shows his match.', x: 130, y: 360, w: 880 },
            { at: when(apply, 'job') + 0.1, end: when(apply, 'top') - 0.6, title: 'The match, before he applies.', body: 'Skills, pay, workplace, location and experience, line by line. Only he can see it.', x: 130, y: 360, w: 880 },
            { at: when(apply, 'top') - 0.3, end: when(apply, 'sent') - 0.1, title: 'A CV and a few honest lines.', body: 'Applied here, so it can be followed to the end.', x: 130, y: 360, w: 880 },
            { at: when(apply, 'sent') + 0.2, end: T.review - 0.1, title: 'Sent. And it has a timeline.', body: 'Every answer from the company will land here.', x: 130, y: 380, w: 880 },
        ],
    },
    {
        id: 'review', chapter: 'The employer: applicants, stages, an answer', start: T.review, end: T.company, bg: 'ink', enter: 'rise',
        devices: [{ kind: 'browser', reel: hannah, ...board, path: '/companies/fernhill-software' }, { kind: 'phone', reel: rafi, x: 1436, y: 150, s: 0.98 }],
        keys: [
            { at: T.review, z: 1, fx: 720, fy: 450 },
            { at: when(hannah, 'menu-1') + 0.1, z: 1.3, fx: 1180, fy: 190, tx: 1080, ty: 470 },
            { at: when(hannah, 'reload-1') + 0.1, z: 1, fx: 720, fy: 450 },
            { at: when(hannah, 'do-hire') + 1.3, z: 1.22, fx: 900, fy: 200, tx: 880, ty: 470 },
            { at: tAnswer - 0.1, z: 1, fx: 720, fy: 450 },
            { at: tAnswer + 1.1, z: 1.32, fx: 195, fy: 330, tx: 1330, ty: 590, dev: 1 },
        ],
        captions: [
            { at: T.review + 0.4, end: when(hannah, 'to-detail') + 0.1, label: 'The employer', title: 'Hannah opens her applicants.', ...top },
            { at: when(hannah, 'to-detail') + 0.5, end: when(hannah, 'do-shortlist'), label: 'The employer', title: 'Profile, CV and application in one place.', ...top },
            { at: when(hannah, 'do-shortlist') + 0.4, end: when(hannah, 'do-interview'), label: 'Shortlisted', title: 'Rafi opens his application. It is already there.', ...top },
            { at: when(hannah, 'do-interview') + 0.4, end: when(hannah, 'do-hire'), label: 'Interview', title: 'Each stage, as it happens.', ...top },
            { at: when(hannah, 'do-hire') + 0.4, end: tAnswer - 0.15, label: 'Hired', title: 'Ten minutes to undo it, before he is told.', ...top },
            { at: tAnswer + 0.3, end: T.company - 0.1, label: 'The answer', title: 'He heard back.', ...top },
        ],
    },
    {
        id: 'company', chapter: 'Silence shows: the responsive mark and reviews', start: T.company, end: T.cv, bg: 'paper', enter: 'rise',
        devices: [{ kind: 'browser', reel: company, ...browserFull, path: '/companies/fernhill-software' }],
        keys: [
            { at: T.company, z: 1, fx: 720, fy: 450 },
            { at: when(company, 'top') + 1.2, z: 1.5, fx: mark.x, fy: mark.y + 20, tx: 1250, ty: 470 },
            { at: when(company, 'mark') + 0.9, z: 1, fx: 720, fy: 450 },
        ],
        captions: [
            { at: T.company + 0.5, end: when(company, 'reviews') - 0.3, label: 'Silence shows', title: 'Answering people is public.', body: 'Companies that answer their applicants carry this mark.', x: 70, y: 770, w: 980, size: 48, card: true },
            { at: when(company, 'reviews') + 0.1, end: T.cv - 0.1, title: 'Candidates review the hiring.', body: 'After an interview, a decision, or 30 days of silence. The company can answer in public.', x: 70, y: 790, w: 1040, size: 48, card: true },
        ],
    },
    {
        id: 'cv', chapter: 'The CV builder', start: T.cv, end: T.analytics, bg: 'paper', enter: 'rise',
        devices: [{ kind: 'browser', reel: cv, ...browserFull, path: '/candidate/cv-builder' }],
        keys: [{ at: T.cv, z: 1, fx: 720, fy: 450 }],
        captions: [
            { at: T.cv + 0.5, end: when(cv, 'pick-creative') - 1.0, label: 'The CV builder', title: 'A CV, drawn from the profile.', body: 'Seven templates and a live preview. No design tool needed.', x: 70, y: 800, w: 900, size: 48, card: true },
            { at: when(cv, 'pick-creative') - 0.7, end: T.analytics - 0.1, title: 'Any colour that reads on white.', body: 'Up to ten saved CVs, aimed at a job, as PDF, Word or a web page.', x: 70, y: 800, w: 900, size: 48, card: true },
        ],
    },
    {
        id: 'analytics', chapter: 'Analytics and moderation', start: T.analytics, end: T.staff, bg: 'paper', enter: 'rise',
        devices: [{ kind: 'browser', reel: analytics, ...browserFull, path: '/companies/fernhill-software/analytics' }],
        keys: [{ at: T.analytics, z: 1, fx: 720, fy: 450 }, { at: when(analytics, 'funnel') + 0.7, z: 1.14, fx: 860, fy: 560, tx: 1020, ty: 500 }],
        captions: [{ at: T.analytics + 0.5, end: T.staff - 0.1, label: 'The numbers', title: 'How each posting is doing.', body: 'Views, applications, how far people got, and how quickly the team answers.', x: 70, y: 800, w: 980, size: 48, card: true }],
    },
    {
        id: 'staff', start: T.staff, end: T.look, bg: 'ink', enter: 'rise',
        devices: [{ kind: 'browser', reel: staff, ...browserFull, path: '/admin' }],
        keys: [{ at: T.staff, z: 1, fx: 720, fy: 450 }, { at: when(staff, 'queue') + 0.9, z: 1.16, fx: viewBtn.x - 260, fy: viewBtn.y, tx: 1080, ty: 520 }, { at: when(staff, 'to-view') + 0.55, z: 1, fx: 720, fy: 450 }],
        captions: [{ at: T.staff + 0.5, end: T.look - 0.1, label: 'The staff', title: 'A new employer is read first.', body: 'Their postings wait for a moderator before the public sees them.', x: 70, y: 800, w: 960, size: 48, card: true }],
    },
    {
        id: 'look', chapter: 'The details and the build', start: T.look, end: T.eng, bg: 'ink', enter: 'fade', devices: [], custom: 'themes',
        captions: [{ at: T.look + 0.5, end: T.eng - 0.1, title: 'Light and dark, from the same tokens.', x: 70, y: 930, w: 1000, size: 44, card: true }],
    },
    { id: 'eng', start: T.eng, end: T.close, bg: 'ink', enter: 'fade', devices: [], custom: 'eng' },
    { id: 'close', start: T.close, end: TOTAL, bg: 'paper', enter: 'ring', devices: [], custom: 'close' },
];

export const chapters = shots.filter((s) => s.chapter).map((s) => ({ t: s.start, name: s.chapter! }));
export const reels = { home, apply, hannah, rafi, company, cv, analytics, staff };
// How much room each reel leaves before the next chapter (negative means it is cut off).
export const slack = { home: T.apply - (reelEnd(home) - 9), apply: T.review - reelEnd(apply), review: T.company - reelEnd(rafi), company: T.cv - reelEnd(company), cv: T.analytics - (reelEnd(cv) - 9 + 1.6), analytics: T.staff - reelEnd(analytics), staff: T.look - reelEnd(staff) };
export const answerAt = tAnswer;
