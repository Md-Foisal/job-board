<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\JobPostingDailyStat;
use App\Support\ClosingDate;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\Catalogue;
use Database\Seeders\Demo\Postings;
use Illuminate\Database\Seeder;

class JobPostingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::query()->with('memberships')->get()->keyBy('slug');

        foreach (Catalogue::companies() as $slug => $details) {
            $company = $companies->get($slug);

            if (! $company) {
                continue;
            }

            foreach ($details['roles'] as $roleKey => $overrides) {
                $posting = self::createPosting($company, $roleKey, $details, $overrides, CarbonImmutable::now()->subDays(random_int(1, 27)), [
                    'posted_by_id' => $company->memberships->first()?->user_id,
                ]);

                self::recordViews($posting);
            }
        }
    }

    /**
     * A posting of a catalogue role, published at the given moment and
     * open for a month from then, as the job form would leave it; the
     * attributes can turn it into a draft or a posting that has ended.
     * Unless told otherwise, a few give no pay range, so search results
     * and posting pages show the negotiable case too.
     *
     * @param  array<string, mixed>  $company
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $attributes
     */
    public static function createPosting(Company $owner, string $roleKey, array $company, array $overrides, CarbonImmutable $publishedAt, array $attributes = [], ?bool $negotiable = null): JobPosting
    {
        $publishedAt = $publishedAt->setTime(random_int(8, 17), random_int(0, 59));

        $posting = JobPosting::factory()->for($owner)->create([
            ...Postings::attributes($roleKey, $company, $overrides, $negotiable ?? random_int(1, 7) === 1),
            'published_at' => $publishedAt,
            'expires_at' => ClosingDate::endOf($publishedAt->addMonth()->setTimezone($owner->timezone)->toDateString(), $owner),
            ...$attributes,
        ]);

        $created = $publishedAt->subHours(random_int(1, 48));
        $posting->forceFill(['created_at' => $created, 'updated_at' => $publishedAt])->saveQuietly();

        Postings::attachTaxonomy($posting, $roleKey);

        return $posting;
    }

    /**
     * Daily views since publishing: a busy first week that tails off, and
     * quieter weekends, so the staff activity chart has a believable
     * shape. Days are the company's, as RecordJobView counts them.
     */
    public static function recordViews(JobPosting $posting): void
    {
        $zone = $posting->company->timezone;
        $published = CarbonImmutable::instance($posting->published_at)->setTimezone($zone)->startOfDay();
        $today = CarbonImmutable::now($zone)->startOfDay();
        $interest = random_int(8, 30);
        $rows = [];

        for ($day = $published; $day->lte($today); $day = $day->addDay()) {
            $age = (int) $published->diffInDays($day);
            $base = $interest * ($age < 7 ? 1.0 : 0.5) * ($day->isWeekend() ? 0.4 : 1.0);

            $rows[] = [
                'job_posting_id' => $posting->id,
                'date' => $day->toDateString(),
                'views' => max(1, (int) round($base * random_int(70, 130) / 100)),
            ];
        }

        JobPostingDailyStat::query()->insert($rows);
    }
}
