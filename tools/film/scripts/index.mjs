// Gathers every camera's index into one file the edit reads.
import { mkdirSync, readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { RAW, ROOT } from './lib.mjs';

const out = {};
for (const scene of readdirSync(RAW)) for (const cam of readdirSync(join(RAW, scene))) {
    const file = join(RAW, scene, cam, 'index.json');
    if (!existsSync(file)) continue;
    const { size, frames, events, marks } = JSON.parse(readFileSync(file, 'utf8'));
    out[`${scene}/${cam}`] = { size, times: frames.map((f) => +f[1].toFixed(4)), marks, events };
}
mkdirSync(join(ROOT, 'src/data'), { recursive: true });
writeFileSync(join(ROOT, 'src/data/captures.ts'), 'export const captures = ' + JSON.stringify(out) + ';\n');
for (const [k, v] of Object.entries(out)) console.log(k.padEnd(20), String(v.times.length).padStart(5), 'frames', Object.entries(v.marks).map(([m, t]) => `${m}@${t.toFixed(1)}`).join(' '));
