// Lists when each camera actually drew frames, next to its marks: the raw material for placing cuts.
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { RAW } from './lib.mjs';
const want = process.argv[2];
for (const scene of readdirSync(RAW)) for (const cam of readdirSync(join(RAW, scene))) {
    const f = join(RAW, scene, cam, 'index.json');
    if (!existsSync(f) || (want && want !== `${scene}/${cam}`)) continue;
    const v = JSON.parse(readFileSync(f, 'utf8'));
    const s = v.marks.start;
    const out = []; let prev = -9, st = null, n = 0, first = 0, i = 0;
    for (const [, t] of v.frames) { const r = t - s; if (r - prev > 0.25) { if (st !== null) out.push(`${st.toFixed(1)}-${prev.toFixed(1)}(#${first},${n})`); st = r; n = 0; first = i; } prev = r; n++; i++; }
    out.push(`${st.toFixed(1)}-${prev.toFixed(1)}(#${first},${n})`);
    console.log(`${scene}/${cam}\n  marks: ${Object.entries(v.marks).map(([m, t]) => `${m}@${(t - s).toFixed(1)}`).join(' ')}\n  drawn: ${out.join(' ')}`);
}
