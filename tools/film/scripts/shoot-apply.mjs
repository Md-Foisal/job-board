// Chapters 1 to 3: a guest searches, Rafi applies on his phone, Hannah answers, Rafi's timeline shows it.
import { artisan, Cam, launch, slugOf } from './lib.mjs';

const browsers = await launch();
const slug = slugOf('Senior Laravel Developer');
const only = process.argv[2];
const run = async (name, fn) => { if (!only || only === name) await fn(); };

// Home, as a guest.
await run('home', async () => {
const home = await Cam.open(browsers, 'desk', { scene: 'home' });
await home.goto('/');
await home.record();
await home.hold(1600);
await home.snap('hero');
await home.type(home.page.getByPlaceholder('Job title, skill or company').first(), 'laravel', { cps: 9 });
await home.snap('typed');
await home.tap(home.page.getByRole('button', { name: 'Search jobs' }).first(), { after: 200, mark: 'search' });
await home.idle(900);
await home.snap('results');
await home.hold(1500);
await home.stop();
});

// Rafi, on his phone.
await run('apply', async () => {
const rafi = await Cam.open(browsers, 'rafi', { scene: 'apply', phone: true, width: 390, height: 844, login: 'candidate@jobboard.test' });
await rafi.goto('/jobs?q=laravel');
await rafi.record();
await rafi.hold(1200);
await rafi.snap('results');
await rafi.scrollBy(300, 1100);
await rafi.hold(900);
await rafi.tap(rafi.page.getByRole('link', { name: 'Senior Laravel Developer' }).first(), { after: 200, mark: 'open' });
await rafi.idle(700);
await rafi.snap('job');
await rafi.hold(1300);
await rafi.scrollInto(rafi.page.getByRole('heading', { name: 'How you match' }), { top: 96, dur: 1500 });
await rafi.snap('match');
await rafi.hold(2600);
await rafi.scrollBy(380, 1400);
await rafi.snap('match2');
await rafi.hold(2200);
await rafi.scrollBy(-2000, 1300);
await rafi.hold(500);
await rafi.snap('top');
await rafi.tap(rafi.page.getByRole('link', { name: 'Apply now' }).first(), { after: 200, mark: 'apply' });
await rafi.idle(700);
await rafi.snap('form');
await rafi.hold(1200);
const letter = rafi.page.locator('[contenteditable="true"]').first();
await rafi.type(letter, 'Five years of Laravel on a scheduling product. I would like to lead this backend.', { cps: 26 });
await rafi.snap('typed');
await rafi.hold(500);
await rafi.tap(rafi.page.getByRole('button', { name: 'Submit application' }), { after: 200, mark: 'submit' });
await rafi.page.waitForURL(/\/jobs\/[^/]+$/);
await rafi.idle(700);
await rafi.snap('sent');
await rafi.hold(1500);
await rafi.tap(rafi.page.getByRole('link', { name: 'Follow your application' }), { after: 200, mark: 'follow' });
await rafi.idle(700);
await rafi.snap('timeline');
await rafi.hold(2400);
await rafi.stop();
});

// Hannah answers on her desktop while Rafi's phone stays on his application.
await run('review', async () => {
const hannah = await Cam.open(browsers, 'hannah', { scene: 'review', login: 'employer@jobboard.test' });
const phone = await Cam.open(browsers, 'rafi', { scene: 'review', phone: true, width: 390, height: 844, login: 'candidate@jobboard.test' });
hannah.page.on('dialog', (d) => d.accept());
await phone.goto('/candidate/applications');
await phone.page.getByRole('link', { name: /Senior Laravel Developer/ }).first().click();
await phone.idle(500);
await hannah.goto(`/companies/fernhill-software/jobs/${slug}/applications`);
await Promise.all([hannah.record(), phone.record()]);
await hannah.hold(1500);
await hannah.snap('list'); await phone.snap('list');
await hannah.tap(hannah.page.getByRole('button', { name: 'Board' }).or(hannah.page.getByRole('radio', { name: 'Board' })).first(), { after: 200, mark: 'to-board' });
await hannah.idle(800);
await hannah.snap('board');
await hannah.hold(1800);
await hannah.tap(hannah.page.getByRole('link', { name: /Rafi Ahmed/ }).first(), { after: 200, mark: 'to-detail' });
await hannah.idle(800);
await hannah.snap('detail');
await hannah.hold(2200);
await hannah.tap(hannah.page.getByRole('button', { name: 'Move to' }), { after: 500, mark: 'menu-1' });
await hannah.snap('menu');
await hannah.tap(hannah.page.getByRole('menuitem', { name: 'Shortlisted' }), { after: 200, mark: 'do-shortlist' });
await hannah.idle(900);
await hannah.snap('shortlisted'); phone.mark('shortlisted');
await hannah.hold(1200);
phone.mark('reload-1'); hannah.mark('reload-1');
await phone.page.reload({ waitUntil: 'networkidle' });
await phone.snap('saw-shortlisted'); hannah.mark('saw-shortlisted');
await hannah.hold(2600);
await hannah.tap(hannah.page.getByRole('button', { name: 'Move to' }), { after: 500, mark: 'menu-2' });
await hannah.tap(hannah.page.getByRole('menuitem', { name: 'Interview' }), { after: 200, mark: 'do-interview' });
await hannah.idle(900);
await hannah.snap('interview'); phone.mark('interview');
await hannah.hold(900);
phone.mark('reload-2'); hannah.mark('reload-2');
await phone.page.reload({ waitUntil: 'networkidle' });
await phone.snap('saw-interview'); hannah.mark('saw-interview');
await hannah.hold(2200);
await hannah.tap(hannah.page.getByRole('button', { name: 'Mark as hired' }), { after: 200, mark: 'do-hire' });
await hannah.idle(900);
await hannah.snap('hired'); phone.mark('hired');
await hannah.hold(3400);
await hannah.snap('undo'); phone.mark('undo');
await Promise.all([hannah.stop()]);

// Arranged for the camera: the ten-minute undo window is moved into the past, so the film
// does not wait ten minutes for the moment Rafi is told.
artisan(`App\\Models\\Application::whereNotNull('decided_at')->latest('decided_at')->first()->forceFill(['decided_at' => now()->subMinutes(11)])->saveQuietly();`);
phone.mark('reload-3');
await phone.page.reload({ waitUntil: 'networkidle' });
await phone.snap('answer');
await phone.hold(3200);
await phone.stop();
});

await browsers.close();
