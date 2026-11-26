// Chapters 4 to 8: the company page, the CV builder, analytics, staff, and the two themes.
import { Cam, launch, slugOf } from './lib.mjs';

const browsers = await launch();
const only = process.argv[2];
const run = async (name, fn) => { if (!only || only === name) await fn(); };

// The company page, as anyone sees it.
await run('company', async () => {
    const cam = await Cam.open(browsers, 'desk', { scene: 'company' });
    await cam.goto('/companies/fernhill-software');
    await cam.record();
    await cam.hold(1500);
    await cam.snap('top');
    await cam.move(cam.page.getByText('Responsive employer').last(), { before: 1600 });
    await cam.snap('mark');
    await cam.scrollInto(cam.page.getByRole('heading', { name: 'Hiring process reviews' }), { top: 70, dur: 1600 });
    await cam.snap('reviews');
    await cam.hold(2200);
    await cam.scrollBy(330, 1500);
    await cam.snap('reply');
    await cam.hold(2600);
    await cam.stop();
});

// The CV builder.
await run('cv', async () => {
    const cam = await Cam.open(browsers, 'rafi', { scene: 'cv', login: 'candidate@jobboard.test' });
    await cam.goto('/candidate/cv-builder');
    await cam.page.locator('iframe[title="Preview of your CV"]:not(.opacity-0)').waitFor({ timeout: 15000 });
    await cam.record();
    await cam.hold(1600);
    await cam.snap('open');
    await cam.tap(cam.page.getByRole('tab', { name: 'Design' }), { after: 500, mark: 'to-design' });
    await cam.snap('design');
    for (const [template, wait] of [['Modern', 1700], ['Executive', 1700], ['Creative', 1900]]) {
        await cam.tap(cam.page.getByRole('radiogroup', { name: 'Template' }).locator('label', { hasText: template }).first(), { after: 200, mark: 'pick-' + template.toLowerCase() });
        await cam.idle(600);
        await cam.snap('tpl-' + template.toLowerCase());
        await cam.hold(wait);
    }
    const colours = cam.page.getByRole('radiogroup', { name: 'Colour' }).locator('label');
    for (const i of [2, 5]) {
        await cam.tap(colours.nth(i), { after: 200, mark: 'pick-colour-' + i });
        await cam.idle(600);
        await cam.snap('colour-' + i);
        await cam.hold(1300);
    }
    await cam.hold(1200);
    await cam.stop();
});

// Analytics, as the employer.
await run('analytics', async () => {
    const cam = await Cam.open(browsers, 'hannah', { scene: 'analytics', login: 'employer@jobboard.test' });
    await cam.goto('/companies/fernhill-software/analytics');
    await cam.record();
    await cam.hold(2200);
    await cam.snap('top');
    await cam.scrollBy(330, 1700);
    await cam.snap('funnel');
    await cam.hold(2600);
    await cam.stop();
});

// Staff: what waits for a decision, then the posting hidden for review.
await run('staff', async () => {
    const cam = await Cam.open(browsers, 'mod', { scene: 'staff', login: 'moderator@jobboard.test' });
    await cam.goto('/admin');
    await cam.record();
    await cam.hold(2400);
    await cam.snap('dashboard');
    await cam.tap(cam.page.getByRole('link', { name: /Job postings/ }).first(), { after: 200, mark: 'to-queue' });
    await cam.idle(800);
    await cam.snap('queue');
    await cam.hold(1800);
    const row = cam.page.getByRole('row', { name: /Frontend Engineer/ });
    await cam.tap(row.getByRole('link', { name: 'View' }).or(row.getByRole('button', { name: 'View' })).first(), { after: 300, mark: 'to-view' });
    await cam.idle(900);
    await cam.snap('view');
    await cam.hold(2600);
    await cam.stop();
});

// The same page in both themes, and on a phone.
await run('look', async () => {
    for (const theme of ['light', 'dark']) {
        const cam = await Cam.open(browsers, theme, { scene: 'look', theme, login: 'candidate@jobboard.test' });
        await cam.goto('/candidate/dashboard');
        await cam.record();
        await cam.hold(900);
        await cam.snap('still');
        await cam.hold(600);
        await cam.stop();
    }
});

await browsers.close();
