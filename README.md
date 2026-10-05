# Job Board

[![tests](https://github.com/Md-Foisal/job-board/actions/workflows/tests.yml/badge.svg)](https://github.com/Md-Foisal/job-board/actions/workflows/tests.yml)
[![linter](https://github.com/Md-Foisal/job-board/actions/workflows/lint.yml/badge.svg)](https://github.com/Md-Foisal/job-board/actions/workflows/lint.yml)

A job board where silence shows. Built with Laravel, Livewire, Filament and Pest.

This project is in active development. Everything described here is merged on `main`.
The UI/UX layer is being built now, and screenshots will be added with it.
See [Status and roadmap](#status-and-roadmap).

## What it is

On most job boards an application can just disappear. The candidate applies and never hears back.
This board is built so that a company's silence is visible:

- Every application has its own timeline. The candidate sees each stage change and the final answer.
- People apply only on this site, so every application can be followed to the end.
- A company that answers the people who apply gets a "Responsive employer" mark.
- Candidates can review a company's hiring process after an interview, a decision, or 30 days with no reply.
  The company can answer in public.
- Staff read new employers' job posts before the public sees them.

It is for three groups of people: job seekers, employers (a company, an agency, or one person hiring),
and the staff who keep the board clean.

## What each role can do

### Guests

- Search jobs by keyword, skill, category, location, workplace type, employment type, experience and pay.
- Sort by newest, or by pay once a currency is chosen. Pay is never compared across currencies.
- Browse categories and company pages, with each company's reviews.
- Job pages carry `JobPosting` structured data for search engines, and there is a sitemap.

### Candidates

- Build a profile: experience, education, skills, links, and a library for CVs and other documents.
- Fill the profile from a PDF CV. The page shows what it found and adds only what the candidate ticks.
- Make a CV from the profile, then download it as a PDF or save it to the library.
- See how well each job matches their skills, with the working behind the score.
  Sort search results by best match.
- Apply with a CV and answers to the employer's screening questions.
- Follow each application on its timeline, and withdraw it.
- Save jobs, see recently viewed jobs, and get job alerts by email at 8 in the morning in their own
  time zone, with one-click unsubscribe.
- Review a company's hiring process.

### Employers

- Set up a company and invite a team. Roles are owner, manager and member, and no one can act above
  their role. One person can work for more than one company.
- Post jobs as drafts or publish them, with skills, categories, pay and screening questions.
  Duplicate an old post to start a new one.
- Review applicants, move them through stages (new, shortlisted, interview, offer), keep private notes,
  and hire or reject. A hire or reject can be undone for 10 minutes before the candidate is emailed.
- See how each post is doing: views, applications, how far applicants got, and how fast the team answered,
  with suggestions for doing better. Days are counted in the company's own time zone.
- Read reviews and answer them.
- Keep a recruiter profile that belongs to the person, not to one company.

### Staff

Staff work in an admin panel at `/admin`, built with Filament.

- Two-factor sign-in is required. Actions that take something away ask for the password again.
- Queues for job posts waiting for review, reports, company verification and company reviews.
  A dashboard shows what is waiting and for how long.
- Verify a company, ask it for documents, revoke verification or ban it. Suspend and reinstate people.
- Merge duplicate skills and categories instead of deleting them.
- Erase a person's data on request.
- Every staff decision goes into a moderation log with who, what and why.
  Staff do not moderate their own company or their own review.

### Everyone

- Sign in with email and password, with email verification, password reset and optional two-factor.
- Delete the account, and restore it by signing in within 30 days. After that it is anonymised.
- Times show in the person's own time zone, taken from the browser or chosen in settings.
- Light and dark theme.

## AI features

These features can use AI: reading a CV into the profile, explaining a match to the candidate,
polishing a CV, suggesting how a live job post could do better, and flagging possible problems in
company reviews for staff. They use Anthropic's Claude Haiku 4.5 through `laravel/ai`, and run on the queue.

AI is off by default (`AI_ENABLED=false`) and the free plan runs no AI. With AI off the product uses its
own rules: CV import still picks up skills and links, the match score is always the product's own
calculation, and employers still get rule-based suggestions for each job post. AI never scores or ranks candidates for
employers. See [ADR 0005](docs/adr/0005-ai-is-optional-and-never-scores-candidates.md).

## How it is built

- **Stack:** Laravel 13 on PHP 8.4, Livewire 4 with Flux UI, Tailwind CSS 4, Filament 5 for the staff
  panel, Laravel Fortify for sign-in and two-factor, Pest for tests. PDFs with `spatie/laravel-pdf`
  (dompdf), charts with Chart.js.
- **Actions:** each business operation is one class in [`app/Actions`](app/Actions), for example
  `SaveJobPosting`, `ChangeApplicationStage` or `VerifyCompany`. Controllers, Livewire pages and the
  Filament panel only take input, check permission, call an action and return a response.
- **Policies:** who can do what is decided in [`app/Policies`](app/Policies), not in views or controllers.
  A company's pages also check for a live membership on every request.
- **Query builder:** search filters live in
  [`JobPostingQueryBuilder`](app/Builders/JobPostingQueryBuilder.php) instead of a pile of model scopes.
- **Queue and schedule:** emails and AI calls run on the queue. Scheduled commands in
  [`routes/console.php`](routes/console.php) close expired jobs and invitations, send job alerts,
  anonymise deleted accounts and clear expired cache.
- **Cache:** pages every guest sees the same way (home, categories, lookups) are cached. Any change to
  that data moves a generation key, so old entries are never read again.
- **Uploads:** images are re-encoded before they are stored. CVs and documents are on a private disk and
  are only served after a policy check.
- **Layers:** the product was built in layers. Each layer is one branch and one pull request, so the
  history reads in the order the product was built.

## Decisions

The main design decisions are written down as Architecture Decision Records in [`docs/adr`](docs/adr):

1. [Companies and memberships, not an employer role on the user](docs/adr/0001-companies-and-memberships.md)
2. [Business rules in single-purpose action classes](docs/adr/0002-business-rules-in-action-classes.md)
3. [Applications happen on this site, and are the only way a company sees a candidate](docs/adr/0003-apply-on-this-site-only.md)
4. [Staff review a new employer's job posts first](docs/adr/0004-staff-review-new-employers-posts.md)
5. [AI is optional and never scores candidates](docs/adr/0005-ai-is-optional-and-never-scores-candidates.md)
6. [Moments in UTC, calendar dates as they are](docs/adr/0006-utc-moments-and-plain-calendar-dates.md)
7. [Pay is compared only within one currency](docs/adr/0007-pay-compared-within-one-currency.md)

## Run it locally

You need PHP 8.4 or newer (with the DOM, MBString and GD extensions), Composer, Node.js 22 and SQLite.
[Laravel Herd](https://herd.laravel.com) on macOS has all of them.

```bash
git clone https://github.com/Md-Foisal/job-board.git
cd job-board
composer run setup
php artisan migrate:fresh --seed
php artisan storage:link
composer run dev
```

`composer run setup` installs the PHP and Node packages, creates `.env`, generates the app key,
creates the SQLite database and builds the assets. `composer run dev` starts the web server, the queue
worker and Vite together. Open http://localhost:8000, and set `APP_URL` in `.env` to the same address so
links in emails point to it.

Emails are written to `storage/logs/laravel.log`, so verification and alert links show up there.
To run the scheduled commands as well, keep `php artisan schedule:work` running in another terminal.

### Demo accounts

The seeder creates these accounts in the local and testing environments only. Every password is `password`.

| Account | Email | Notes |
| --- | --- | --- |
| Super admin | `superadmin@jobboard.test` | Lands in the admin panel. |
| Moderator | `moderator@jobboard.test` | Lands in the admin panel. |
| Employer | `employer@jobboard.test` | Owner of Fernhill Software, with posts and applicants. |
| Candidate | `candidate@jobboard.test` | Full profile, a real PDF CV, applications, saved jobs and alerts. |
| Deleted account | `deleted@jobboard.test` | Inside the 30-day grace period. Signing in offers to restore it. |

Staff two-factor: use the secret `JBSWY3DPEHPK3PXP` in an authenticator app, or a recovery code from
`demo-recovery-code-1` to `demo-recovery-code-8`.

### Trying the AI features

Set `AI_ENABLED=true` and `ANTHROPIC_API_KEY` in `.env`. The free plan's monthly limits in
[`config/plans.php`](config/plans.php) are 0 on purpose, so raise the ones you want to try.

## Tests and CI

```bash
php artisan test --parallel
vendor/bin/pint --test
```

Feature and unit tests are written with Pest. GitHub Actions runs the test suite on PHP 8.4 and 8.5,
and Laravel Pint checks the code style, on every push and pull request to `main`.

## Status and roadmap

| Layer | What it added | Merged |
| --- | --- | --- |
| 1 Foundation | Companies, memberships, invitations and job postings replace the old employer profile ([946a514](https://github.com/Md-Foisal/job-board/commit/946a514)) | Sep 2026 |
| 2 Candidate and guest | Search, job pages, apply, candidate profile, applications and saved jobs ([#2](https://github.com/Md-Foisal/job-board/pull/2)) | Sep 2026 |
| 3 Employer core | Company workspace, team, job posts, applicant review and stage emails ([#3](https://github.com/Md-Foisal/job-board/pull/3)) | Sep 2026 |
| 4 Trust and moderation | Admin panel, moderation queues, verification, reports and the moderation log ([#4](https://github.com/Md-Foisal/job-board/pull/4)) | Sep 2026 |
| 5 Infrastructure polish | Caching, scheduled expiry, job alerts, account deletion and erasure, upload and rate limits ([#5](https://github.com/Md-Foisal/job-board/pull/5)) | Sep 2026 |
| 6 AI features | CV import, match explanation, CV builder, employer analytics, company reviews ([#6](https://github.com/Md-Foisal/job-board/pull/6)) | Oct 2026 |
| 7 Polish | Time zones, currencies, best-match sort and realistic demo data ([#7](https://github.com/Md-Foisal/job-board/pull/7)) | Oct 2026 |

Next:

- **Layer 8, UI/UX (in progress):** a design system first, then every page, starting with the busiest.
- **Launch,** with AI off.
- **After launch:** paid plans, which is when the AI features can be turned on.

## License

The code is public so it can be read and reviewed, but it is not open source. You may run it on your
own computer to evaluate it. You may not deploy it, reuse it in another project or offer it as a
service. See [LICENSE](LICENSE).

## Author

Built by Md. Foisal, a self-taught web developer from Feni, Bangladesh.
[GitHub](https://github.com/Md-Foisal) · [LinkedIn](https://www.linkedin.com/in/md-foisal-395848142) · mffoisal8@gmail.com
