# YouTube upload

Uploaded 26 November 2026, public: https://youtu.be/ZavoyqCT30E. The title and description were changed at upload; the live text is the one below. The film was uploaded from this folder, with `hearback-film.en.srt` as English subtitles and `youtube-thumbnail.jpg` as the thumbnail.

## Title

HearBack: Laravel Job Board with Livewire and Filament | Full Demo

## Description

HearBack is a Laravel job board and hiring platform where every application has a timeline the candidate can follow. Live: https://hearbackjobs.com
Built with Laravel 13, Livewire 4 and Filament 5 by Md. Foisal. Code: https://github.com/Md-Foisal/job-board

On most job boards an application can just disappear. HearBack is a job board (job portal) built so that a company's silence shows.

Every application has its own timeline, with each stage change and the final answer. Employers who answer the people who apply get a "Responsive employer" mark. Candidates can review a company's hiring process, and the company can answer in public. Staff read a new employer's job posts before the public sees them.

There is a CV builder with seven templates and PDF and Word export, and a CV can be aimed at one job. Employers get an applicant board by stage and analytics for each posting.

Everything in this film is the running app:
00:00 Opening
00:14 The candidate: search, match, apply
00:36 The employer: applicants, stages, an answer
01:01 Silence shows: the responsive mark and reviews
01:14 The CV builder
01:30 Analytics and moderation
01:45 The details and the build

How it was built: in nine layers, from the data model up to the live launch. The main design decisions are written down in the repo, one file each, nine so far.

Stack: Laravel 13, Livewire 4, Flux UI, Filament 5, Tailwind CSS 4, MySQL and Pest, with over 1,590 tests.

About the demo: this is a portfolio project and the film uses seeded demo data. Every company and person in it is made up, and the pictures are drawn avatars. The live site runs with its AI features off, on free hosting.

Music: written for this film and made in code. No third-party recordings.

#laravel #livewire #filamentphp

## Tags

hearback, hearbackjobs, laravel job board, laravel job portal project, hiring platform, laravel livewire filament project, livewire 4, filament 5, laravel 13 project, cv builder laravel, applicant tracking board, job application timeline, pest php, laravel developer portfolio, md foisal

## Thumbnail

`youtube-thumbnail.jpg`, built from the film's own frames: the candidate's timeline on the phone beside the employer's applicant page.

## In YouTube Studio

- Subtitles: English, upload `hearback-film.en.srt` with timing.
- Playlists: Product Films; Laravel Project Demos (not created yet, so the film is in no playlist).
- Category: Science & Technology. Language: English. Audience: not made for kids.
- End screen in the last 15 seconds: one other film and the playlist.
- Pinned comment: Try it: https://hearbackjobs.com. The film uses made-up demo data. Design decisions are in the repo as ADRs. Questions are welcome here.

The "How it was built" line says only what the repository shows: the nine layers and the nine ADRs in `docs/adr`.
