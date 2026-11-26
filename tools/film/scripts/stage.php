<?php

// Arranges the seeded demo data for the camera. Run after migrate:fresh --seed:
//   php artisan tinker tools/film/scripts/stage.php
// The app code is untouched; this only adds rows the way the seeders do.

use App\Actions\ChangeApplicationStage;
use App\Enums\ApplicationStage;
use App\Models\Application;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\People;

$company = Company::where('slug', 'fernhill-software')->firstOrFail();
$posting = $company->jobPostings()->where('title', 'Senior Laravel Developer')->firstOrFail();
$hannah = User::where('email', 'employer@jobboard.test')->firstOrFail();

// A posting six hours old with no applicants makes an empty board. Seven
// people in the same line of work applied in the hours before the film's,
// and Hannah has already moved a few of them along.
$stages = [
    [ApplicationStage::Shortlisted], [ApplicationStage::Shortlisted, ApplicationStage::Interview], [], [ApplicationStage::Shortlisted],
    [], [ApplicationStage::Shortlisted, ApplicationStage::Interview], [],
];
foreach ($stages as $i => $steps) {
    $profile = People::candidate($i % 3 === 0 ? 'fullstack' : 'backend', ['uk', 'bd', 'ie', 'uk', 'de', 'bd', 'uk'][$i]);
    $at = CarbonImmutable::now()->subMinutes(330 - $i * 40);
    $cv = People::unrenderedCv($profile, $at->subDays(3));
    $application = Application::factory()->create([
        'job_posting_id' => $posting->id,
        'candidate_profile_id' => $profile->id,
        'resume_document_id' => $cv->id,
        'cover_letter' => People::coverLetter($profile, $posting),
        'created_at' => $at,
        'updated_at' => $at,
    ]);
    foreach ($steps as $step) {
        app(ChangeApplicationStage::class)($application->refresh(), $hannah, $step);
    }
}

echo "staged {$posting->slug}\n";
