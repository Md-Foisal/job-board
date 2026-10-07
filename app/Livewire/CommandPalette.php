<?php

namespace App\Livewire;

use App\Models\Application;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\JobView;
use App\Models\User;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The command palette both workspaces open with Cmd+K or Ctrl+K, or with
 * the search button in the top bar. It lists the workspace's pages (the
 * same entries as the sidebar, filtered in the browser as you type) and
 * searches what that workspace is about: open jobs on the person's own
 * side, the company's applicants and job postings in a company.
 *
 * Nothing is queried until it is opened. Opening shows a few recent
 * things to jump back to: the jobs a candidate last looked at, or the
 * company's newest postings with their applicant counts.
 */
class CommandPalette extends Component
{
    /** Letters typed before the server is asked; one letter matches nearly everything. */
    public const MIN_QUERY_LENGTH = 2;

    /**
     * Null on the person's own side. Locked, because it decides whose
     * applicants are searched; membership is still checked on every
     * search, as the page itself checks it on every request.
     */
    #[Locked]
    public ?Company $company = null;

    public string $q = '';

    /** Set the first time the palette opens, so a closed one costs no queries. */
    public bool $ready = false;

    public function mount(?Company $company = null): void
    {
        $this->company = $company;
        $this->ensureMember();
    }

    /**
     * Every page of this workspace, flat, with its section name for the
     * screen reader and for matching ("Profile" finds Experience too).
     *
     * @return list<array{label: string, section: ?string, item: NavItem}>
     */
    #[Computed]
    public function pages(): array
    {
        $user = $this->user();
        $sections = $this->company
            ? Navigation::company($user, $this->company, withCounts: false)
            : Navigation::personal($user);

        $pages = [];

        if ($this->company && $postJob = Navigation::postJob($user, $this->company)) {
            $pages[] = ['label' => $postJob->label, 'section' => null, 'item' => $postJob];
        }

        foreach ($sections as $section) {
            foreach ($section->items as $item) {
                $pages[] = ['label' => $item->label, 'section' => $section->heading, 'item' => $item];
            }
        }

        return $pages;
    }

    /**
     * What opening an empty palette offers besides the pages.
     *
     * @return Collection<int, array{label: string, meta: string, url: string, icon: string}>
     */
    #[Computed]
    public function recent(): Collection
    {
        if (! $this->ready || trim($this->q) !== '') {
            return collect();
        }

        if ($this->company) {
            $this->ensureMember();

            return $this->postingEntries(
                $this->company->jobPostings()->latest()->latest('id')->limit(5)
            );
        }

        // Only what is still public, as on the dashboard: a posting taken
        // down since it was viewed would open on a 404.
        return JobView::query()
            ->with('jobPosting.company:id,name')
            ->where('user_id', $this->user()->id)
            ->whereHas('jobPosting', fn ($query) => $query->active())
            ->latest('viewed_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->pluck('jobPosting')
            ->map(fn (JobPosting $posting) => [
                'label' => $posting->title,
                'meta' => $posting->company->name,
                'url' => route('jobs.show', $posting),
                'icon' => 'clock',
            ]);
    }

    /**
     * Server results for what was typed, grouped under a heading each.
     *
     * @return array<string, Collection<int, array{label: string, meta: string, url: string, icon: string}>>
     */
    #[Computed]
    public function results(): array
    {
        $term = trim($this->q);

        if (mb_strlen($term) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        if (! $this->company) {
            return [__('Jobs') => $this->jobs($term)];
        }

        $this->ensureMember();

        return array_filter([
            __('Applicants') => $this->applicants($term),
            __('Job postings') => $this->companyPostings($term),
        ], fn (Collection $group) => $group->isNotEmpty());
    }

    public function render()
    {
        return view('livewire.command-palette', [
            'scope' => $this->company?->name ?? config('app.name'),
        ]);
    }

    private function jobs(string $term): Collection
    {
        return JobPosting::query()
            ->active()
            ->keyword($term)
            ->with('company:id,name')
            ->latest()
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (JobPosting $posting) => [
                'label' => $posting->title,
                'meta' => $posting->company->name,
                'url' => route('jobs.show', $posting),
                'icon' => 'briefcase',
            ]);
    }

    /**
     * Everyone on the team may open every application of the company, so
     * the search covers all of them; an account that has been deleted is
     * left out, as it is from the applicant lists.
     */
    private function applicants(string $term): Collection
    {
        return Application::query()
            ->whereHas('jobPosting', fn ($query) => $query->where('company_id', $this->company->id))
            ->whereHas('candidateProfile.user', fn ($query) => $query->where('name', 'like', "%{$term}%"))
            ->with(['candidateProfile.user:id,name', 'jobPosting:id,title'])
            ->latest()
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (Application $application) => [
                'label' => $application->candidateProfile->user->name,
                'meta' => $application->jobPosting->title.' · '.$application->stage->label(),
                'url' => route('employer.applications.show', ['company' => $this->company, 'application' => $application]),
                'icon' => 'user',
            ]);
    }

    private function companyPostings(string $term): Collection
    {
        return $this->postingEntries(
            $this->company->jobPostings()->where('title', 'like', "%{$term}%")->latest()->latest('id')->limit(5)
        );
    }

    /**
     * A company's postings lead to their applicants, so the line beside
     * each says how many there are. Where a posting stands (live, in
     * review, hidden) takes more than one word to say truthfully, and is
     * on the postings list.
     */
    private function postingEntries(HasMany $query): Collection
    {
        return $query
            ->withCount('applications')
            ->get(['id', 'slug', 'title', 'company_id'])
            ->map(fn (JobPosting $posting) => [
                'label' => $posting->title,
                'meta' => trans_choice('{0} No applicants|{1} :count applicant|[2,*] :count applicants', $posting->applications_count, ['count' => $posting->applications_count]),
                'url' => route('employer.jobs.applications', ['company' => $this->company, 'jobPosting' => $posting]),
                'icon' => 'briefcase',
            ]);
    }

    /**
     * A membership can end while the page is open; from then on the
     * palette answers no more than the page itself would.
     */
    private function ensureMember(): void
    {
        abort_if($this->company && ! $this->user()->worksAt($this->company), 403);
    }

    private function user(): User
    {
        return auth()->user();
    }
}
