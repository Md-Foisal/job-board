<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\JobPosting;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\People;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ApplicationSeeder extends Seeder
{
    /**
     * Work close enough that someone in the first line might apply for
     * a job in the second.
     */
    private const NEARBY = [
        'backend' => ['fullstack', 'devops'],
        'frontend' => ['fullstack'],
        'fullstack' => ['backend', 'frontend'],
        'devops' => ['backend', 'it-support'],
        'qa' => ['it-support'],
        'mobile' => ['frontend'],
        'it-support' => ['qa', 'support'],
        'data' => [],
        'design' => ['frontend'],
        'product' => ['operations'],
        'marketing' => ['writing'],
        'writing' => ['marketing'],
        'sales' => ['support'],
        'support' => ['sales'],
        'finance' => [],
        'operations' => ['product'],
        'hr' => [],
    ];

    /**
     * A hundred people applying for jobs that fit their line of work, at
     * times weighted towards the start of each job's run. Lines of work
     * with more openings draw more applicants. The applications wait at
     * New: no demo account belongs to these companies, so nobody has
     * reviewed them.
     */
    public function run(): void
    {
        $postings = JobPosting::query()->active()->with('company')->get();
        $byFamily = $postings->groupBy(fn (JobPosting $posting) => People::familyOf($posting) ?? '');

        if ($postings->isEmpty()) {
            return;
        }

        foreach (range(1, 100) as $ignored) {
            $family = People::familyOf($postings->random()) ?? 'support';
            $profile = People::candidate($family);

            $choices = collect([$family, ...self::NEARBY[$family]])
                ->flatMap(fn (string $line) => $byFamily->get($line, new Collection))
                ->shuffle()
                ->take(random_int(1, 4));

            $moments = $choices->mapWithKeys(fn (JobPosting $posting) => [$posting->id => self::appliedAt($posting)]);
            $cv = People::unrenderedCv($profile, $moments->min()->subDays(random_int(1, 10)));

            foreach ($choices as $posting) {
                Application::factory()->create([
                    'job_posting_id' => $posting->id,
                    'candidate_profile_id' => $profile->id,
                    'resume_document_id' => $cv->id,
                    'cover_letter' => People::coverLetter($profile, $posting),
                    'created_at' => $moments[$posting->id],
                    'updated_at' => $moments[$posting->id],
                ]);
            }
        }
    }

    /**
     * Some time after the posting went up, weighted towards the start.
     */
    private static function appliedAt(JobPosting $posting): CarbonImmutable
    {
        $published = CarbonImmutable::instance($posting->published_at)->addHour();
        $window = max(0, (int) $published->diffInMinutes(now()));
        $share = (random_int(0, 1000) / 1000) ** 2;

        return $published->addMinutes((int) ($window * $share));
    }
}
