<?php

use App\Actions\RenderCvPdf;
use App\Actions\StoreCandidateDocument;
use App\Enums\CvGap;
use App\Enums\DocumentType;
use App\Models\CandidatePreference;
use App\Models\Document;
use App\Models\EducationRecord;
use App\Models\ExperienceRecord;
use App\Models\Skill;
use App\Models\User;
use App\Support\CvChecks;
use App\Support\CvData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Spatie\LaravelPdf\Enums\Format;

/**
 * Karim, with a full profile: two finished roles and a current one, a
 * degree in progress and a finished one, and skills at every level.
 */
function karimForCv(): User
{
    $karim = candidateUser()->fresh();
    $karim->forceFill(['name' => 'Karim Rahman', 'email' => 'karim@example.com'])->save();

    $profile = $karim->candidateProfile;
    $profile->update([
        'headline' => 'Backend developer',
        'bio' => "I build Laravel applications.\nPayments are my thing.",
        'phone' => '+880 1712-345678',
        'location' => 'Dhaka, Bangladesh',
        'linkedin_url' => 'https://www.linkedin.com/in/karim-rahman/',
        'github_url' => null,
        'portfolio_url' => 'https://karim.dev',
    ]);

    foreach ([
        ['Intern', 'Beta Inc', '2018-06-01', '2018-12-01', null],
        ['Junior Developer', 'Gamma Ltd', '2019-01-01', '2021-02-01', '<p>Kept the <strong>billing</strong> service running.</p>'],
        ['Backend Developer', 'Acme Ltd', '2021-03-01', null, '<ul><li>Built the payments API</li><li>Cut checkout time by 40%</li></ul>'],
    ] as [$title, $company, $start, $end, $description]) {
        ExperienceRecord::factory()->for($profile)->create([
            'job_title' => $title, 'company_name' => $company, 'start_date' => $start, 'end_date' => $end, 'description' => $description,
        ]);
    }

    EducationRecord::factory()->for($profile)->create([
        'institution_name' => 'University of Dhaka', 'degree' => 'BSc', 'field_of_study' => 'CSE', 'start_date' => '2015-01-01', 'end_date' => '2019-01-01',
    ]);
    EducationRecord::factory()->for($profile)->create([
        'institution_name' => 'Open University', 'degree' => null, 'field_of_study' => 'Data Science', 'start_date' => '2024-09-01', 'end_date' => null,
    ]);

    foreach (['Docker' => 'beginner', 'PHP' => 'advanced', 'MySQL' => 'intermediate', 'Laravel' => 'advanced'] as $name => $level) {
        $profile->skills()->attach(Skill::firstOrCreate(['name' => $name]), ['proficiency' => $level]);
    }

    CandidatePreference::factory()->for($profile)->create([
        'desired_salary_min' => 91234,
        'desired_salary_currency' => 'BDT',
        'preferred_workplace_type' => 'remote',
    ]);

    return $karim->fresh();
}

function cvText(string $pdf): string
{
    return preg_replace('/\s+/u', ' ', (new Parser)->parseContent($pdf)->getText());
}

test('roles are listed current first, then newest, each with its dates', function () {
    $cv = CvData::fromProfile(karimForCv()->candidateProfile);

    expect(array_column($cv->experience, 'title'))->toBe(['Backend Developer', 'Junior Developer', 'Intern'])
        ->and(array_column($cv->experience, 'dates'))->toBe(['Mar 2021 – Present', 'Jan 2019 – Feb 2021', 'Jun 2018 – Dec 2018'])
        ->and($cv->experience[2]['description'])->toBeNull();
});

test('courses read as qualification and institution, leaving out blank parts', function () {
    $cv = CvData::fromProfile(karimForCv()->candidateProfile);

    expect($cv->education)->toBe([
        ['title' => 'Data Science', 'institution' => 'Open University', 'dates' => 'Sep 2024 – Present'],
        ['title' => 'BSc in CSE', 'institution' => 'University of Dhaka', 'dates' => 'Jan 2015 – Jan 2019'],
    ]);
});

test('skills are names only, strongest first, then by name', function () {
    $cv = CvData::fromProfile(karimForCv()->candidateProfile);

    expect($cv->skills)->toBe(['Laravel', 'PHP', 'MySQL', 'Docker']);
});

test('contact details and links come from the account and the profile', function () {
    $cv = CvData::fromProfile(karimForCv()->candidateProfile);

    expect($cv->name)->toBe('Karim Rahman')
        ->and($cv->email)->toBe('karim@example.com')
        ->and($cv->phone)->toBe('+880 1712-345678')
        ->and($cv->location)->toBe('Dhaka, Bangladesh')
        ->and($cv->links)->toBe([
            ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/karim-rahman/', 'text' => 'linkedin.com/in/karim-rahman'],
            ['label' => 'Portfolio', 'url' => 'https://karim.dev', 'text' => 'karim.dev'],
        ]);
});

test('a link that is not a web address is printed but never made clickable', function () {
    $karim = karimForCv();
    $karim->candidateProfile->forceFill(['portfolio_url' => 'data://text/html,hello'])->save();

    $cv = CvData::fromProfile($karim->candidateProfile->fresh());
    $html = view('cv.classic', ['cv' => $cv, 'photo' => null])->render();

    expect($cv->links[1])->toBe(['label' => 'Portfolio', 'url' => null, 'text' => 'data://text/html,hello'])
        ->and($html)->toContain('data://text/html,hello')
        ->not->toContain('href="data:')
        ->toContain('href="https://www.linkedin.com/in/karim-rahman/"');
});

test('a CV needs at least one role, course or skill', function () {
    $candidate = candidateUser();

    expect(CvData::fromProfile($candidate->candidateProfile)->hasContent())->toBeFalse();

    $candidate->candidateProfile->skills()->attach(Skill::firstOrCreate(['name' => 'PHP']), ['proficiency' => 'beginner']);

    expect(CvData::fromProfile($candidate->candidateProfile->fresh())->hasContent())->toBeTrue();
});

test('the file is named after the person, or plainly when the name gives no letters for it', function (string $name, string $file) {
    $candidate = candidateUser();
    $candidate->forceFill(['name' => $name])->save();

    expect(CvData::fromProfile($candidate->candidateProfile->fresh())->fileName())->toBe($file);
})->with([
    ['Karim Rahman', 'Karim-Rahman-CV.pdf'],
    ['José Núñez', 'Jose-Nunez-CV.pdf'],
    ['Иван Петров', 'Ivan-Petrov-CV.pdf'],
    ['করিম রহমান', 'CV.pdf'],
    ['محمد علي', 'CV.pdf'],
    ['Karim করিম', 'CV.pdf'],
]);

test('text the PDF cannot draw is reported by the part of the CV it is in', function () {
    $candidate = karimForCv();
    $candidate->forceFill(['name' => 'করিম রহমান'])->save();
    $candidate->candidateProfile->experienceRecords()->first()->update(['description' => '<p>মারহাবা — مرحبا</p>']);
    $candidate->candidateProfile->update(['location' => 'Αθήνα, Ελλάδα']);

    $cv = CvData::fromProfile($candidate->fresh()->candidateProfile);

    expect($cv->unsupportedScriptParts())->toBe(['Name', 'Experience']);
});

test('Latin, Greek and Cyrillic text needs no warning', function () {
    $candidate = karimForCv();
    $candidate->forceFill(['name' => 'Zoë Ivanova'])->save();
    $candidate->candidateProfile->update(['location' => 'Αθήνα, Ελλάδα', 'headline' => 'Разработчик']);

    expect(CvData::fromProfile($candidate->fresh()->candidateProfile)->unsupportedScriptParts())->toBe([]);
});

test('the checks name what a CV is missing, in page order, and where to add it', function () {
    $candidate = candidateUser();
    $candidate->candidateProfile->update(['headline' => null, 'bio' => null, 'phone' => null, 'location' => null]);
    ExperienceRecord::factory()->for($candidate->candidateProfile)->create(['job_title' => 'Intern', 'company_name' => 'Beta Inc', 'description' => '<p></p>']);

    $gaps = CvChecks::for(CvData::fromProfile($candidate->candidateProfile));

    expect(array_map(fn (array $gap) => $gap['gap'], $gaps))->toBe([
        CvGap::Headline, CvGap::Phone, CvGap::Location, CvGap::Summary, CvGap::RoleDescription, CvGap::Education, CvGap::Skills,
    ])
        ->and($gaps[4]['gap']->message($gaps[4]['subject']))->toBe('Intern at Beta Inc has no description of what you did.')
        ->and($gaps[4]['gap']->route())->toBe('candidate.experience.index');

    expect(CvChecks::for(CvData::fromProfile(karimForCv()->candidateProfile)))
        ->toBe([['gap' => CvGap::RoleDescription, 'subject' => 'Intern at Beta Inc']]);
});

test('a profile with nothing in it is missing work experience too', function () {
    $candidate = candidateUser();

    expect(array_column(CvChecks::for(CvData::fromProfile($candidate->candidateProfile)), 'gap'))->toContain(CvGap::Experience);
});

test('the PDF is real text, in the order of the page, and leaves preferences out', function () {
    $pdf = app(RenderCvPdf::class)(CvData::fromProfile(karimForCv()->candidateProfile));
    $text = cvText($pdf);

    expect($pdf)->toStartWith('%PDF-')
        ->and($text)->toContain('Karim Rahman')
        ->toContain('karim@example.com')
        ->toContain('+880 1712-345678')
        ->toContain('linkedin.com/in/karim-rahman')
        ->toContain('Built the payments API')
        ->toContain('Laravel, PHP, MySQL, Docker')
        ->not->toContain('91234')
        ->not->toContain('91,234')
        ->not->toContain('Remote');

    $order = array_map(fn (string $part) => mb_stripos($text, $part), ['Karim Rahman', 'Summary', 'Work Experience', 'Education', 'Skills']);

    expect($order)->each->toBeInt()
        ->and($order)->toBe(collect($order)->sort()->values()->all());
});

test('the paper is A4 unless Letter is asked for', function () {
    $cv = CvData::fromProfile(karimForCv()->candidateProfile);
    $mediaBox = fn (string $pdf) => (new Parser)->parseContent($pdf)->getPages()[0]->getDetails()['MediaBox'];

    expect(array_map('round', $mediaBox(app(RenderCvPdf::class)($cv))))->toBe([0.0, 0.0, 595.0, 842.0])
        ->and(array_map('round', $mediaBox(app(RenderCvPdf::class)($cv, paper: Format::Letter))))->toBe([0.0, 0.0, 612.0, 792.0]);
});

test('no other paper size is accepted', function () {
    app(RenderCvPdf::class)(CvData::fromProfile(karimForCv()->candidateProfile), paper: Format::A3);
})->throws(InvalidArgumentException::class);

test('the photo is left off unless asked for, and is embedded rather than linked', function () {
    Storage::fake('public');
    $karim = karimForCv();
    $image = imagecreatetruecolor(300, 300);
    ob_start();
    imagepng($image);
    Storage::disk('public')->put('avatars/karim.png', ob_get_clean());
    $karim->forceFill(['avatar' => 'avatars/karim.png'])->save();

    $cv = CvData::fromProfile($karim->fresh()->candidateProfile);

    expect($cv->photo())->toStartWith('data:image/png;base64,')
        ->and(view('cv.classic', ['cv' => $cv, 'photo' => null])->render())->not->toContain('<img')
        ->and(view('cv.classic', ['cv' => $cv, 'photo' => $cv->photo()])->render())->toContain('src="data:image/png;base64,')
        ->and(substr_count(app(RenderCvPdf::class)($cv, withPhoto: true), '/Subtype /Image'))->toBe(1)
        ->and(substr_count(app(RenderCvPdf::class)($cv), '/Subtype /Image'))->toBe(0);
});

test('a photo whose file is gone is simply left off', function () {
    Storage::fake('public');
    $karim = karimForCv();
    $karim->forceFill(['avatar' => 'avatars/missing.png'])->save();

    expect(CvData::fromProfile($karim->fresh()->candidateProfile)->photo())->toBeNull();
});

test('a built CV is saved as a new CV in the library, and the oldest beyond four leaves it', function () {
    Storage::fake('local');
    $profile = karimForCv()->candidateProfile;
    $oldest = null;

    foreach (range(1, 4) as $i) {
        $this->travel(1)->minutes();
        $document = Document::factory()->for($profile)->create(['document_type' => DocumentType::Cv]);
        $oldest ??= $document;
    }

    $this->travel(1)->minutes();
    $saved = app(StoreCandidateDocument::class)->generated($profile, DocumentType::Cv, '%PDF-1.7 built', 'Karim-Rahman-CV.pdf');

    expect($saved->original_filename)->toBe('Karim-Rahman-CV.pdf')
        ->and($saved->file_path)->toStartWith('documents/')->toEndWith('.pdf')
        ->and(Storage::disk('local')->get($saved->file_path))->toBe('%PDF-1.7 built')
        ->and($oldest->fresh()->trashed())->toBeTrue()
        ->and($profile->documents()->where('document_type', DocumentType::Cv)->count())->toBe(4);
});

test('saving twice makes two documents, never overwriting the first', function () {
    Storage::fake('local');
    $profile = karimForCv()->candidateProfile;
    $store = app(StoreCandidateDocument::class);

    $first = $store->generated($profile, DocumentType::Cv, 'first', 'Karim-Rahman-CV.pdf');
    $second = $store->generated($profile, DocumentType::Cv, 'second', 'Karim-Rahman-CV.pdf');

    expect($first->file_path)->not->toBe($second->file_path)
        ->and(Storage::disk('local')->get($first->file_path))->toBe('first');
});

test('when the document cannot be recorded, the file just stored is removed', function () {
    Storage::fake('local');
    $profile = karimForCv()->candidateProfile;
    DB::statement('DROP TABLE documents');

    expect(fn () => app(StoreCandidateDocument::class)->generated($profile, DocumentType::Cv, 'pdf', 'CV.pdf'))
        ->toThrow(Exception::class);

    expect(Storage::disk('local')->allFiles())->toBe([]);
});
