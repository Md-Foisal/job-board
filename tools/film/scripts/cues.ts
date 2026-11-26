// Writes everything outside the picture that follows the edit: sound cues, subtitles and YouTube chapters.
// Run with: node --experimental-strip-types scripts/cues.ts
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { answerAt, BEAT, BEATS, chapters, eventsInFilm, reels, shots, slack, TOTAL, when } from '../src/timeline.ts';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const all = Object.values(reels);
const clicks = all.flatMap((r) => eventsInFilm(r, 'click')).sort((a, b) => a - b);
const keys = all.flatMap((r) => eventsInFilm(r, 'key')).sort((a, b) => a - b);
const turns = shots.filter((s) => s.enter !== 'cut').map((s) => s.start);
// The moments an answer reaches Rafi.
const answers = ['do-shortlist', 'do-interview', 'do-hire'].map((m) => when(reels.hannah, m) + 0.15).concat(answerAt + 0.2);

mkdirSync(join(root, 'out'), { recursive: true });
writeFileSync(join(root, 'out/cues.json'), JSON.stringify({ total: TOTAL, beat: BEAT, beats: BEATS, chapters, clicks, keys, turns, answers }, null, 1));

const stamp = (t: number, sep: string) => {
    const ms = Math.round(t * 1000);
    const p = (n: number, l = 2) => String(n).padStart(l, '0');
    return `${p(Math.floor(ms / 3600000))}:${p(Math.floor(ms / 60000) % 60)}:${p(Math.floor(ms / 1000) % 60)}${sep}${p(ms % 1000, 3)}`;
};
// Subtitles: the on-screen captions, so the film has text YouTube and screen readers can read.
const lines = [
    { at: 0.4, end: 4.3, text: 'You applied. Then nothing.' },
    { at: 4.9, end: shots[1].start, text: 'HearBack. A job board where silence shows.' },
    ...shots.flatMap((s) => (s.captions ?? []).map((c) => ({ at: c.at, end: c.end, text: [c.title, c.body].filter(Boolean).join('\n') }))),
    { at: shots.find((s) => s.id === 'eng')!.start + 0.3, end: shots.find((s) => s.id === 'close')!.start, text: 'Nothing just disappears.\n1,590+ Pest tests. 19 policies. Ten minutes to undo a decision. 9 architecture decisions written down.' },
    { at: shots.find((s) => s.id === 'close')!.start + 0.4, end: TOTAL - 0.3, text: 'HearBack. github.com/Md-Foisal/job-board\nDesigned and built by Md. Foisal. A portfolio project, shown with demo data.' },
].sort((a, b) => a.at - b.at);
writeFileSync(join(root, 'out/hearback-film.en.srt'), lines.map((l, i) => `${i + 1}\n${stamp(l.at, ',')} --> ${stamp(Math.min(l.end, lines[i + 1]?.at ?? TOTAL), ',')}\n${l.text}\n`).join('\n'));

const mmss = (t: number) => `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(Math.floor(t % 60)).padStart(2, '0')}`;
writeFileSync(join(root, 'out/chapters.txt'), chapters.map((c) => `${mmss(c.t)} ${c.name}`).join('\n') + '\n');

console.log(`total ${TOTAL.toFixed(2)} s, ${clicks.length} clicks, ${keys.length} keys`);
console.log('room left per chapter (s):', Object.entries(slack).map(([k, v]) => `${k} ${v.toFixed(2)}`).join('  '));
if (Object.values(slack).some((v) => v < -0.05)) throw new Error('a reel runs past its chapter');
console.log(chapters.map((c) => `${mmss(c.t)} ${c.name}`).join('\n'));
