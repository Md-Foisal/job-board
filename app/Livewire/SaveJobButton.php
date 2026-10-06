<?php

namespace App\Livewire;

use App\Models\JobPosting;
use Livewire\Component;

/**
 * Isolated save/unsave toggle embedded on the job card and job detail
 * action group -- kept as its own small component per the established
 * Blade-vs-Livewire heuristic (a single isolated reactive action doesn't
 * need to make its whole parent page a Livewire component).
 */
class SaveJobButton extends Component
{
    public JobPosting $jobPosting;

    public bool $saved = false;

    /**
     * Icon only, for a job card, where the title needs the room. The job
     * page keeps the labelled button.
     */
    public bool $compact = false;

    /**
     * A list of cards passes $initiallySaved in, having looked up the
     * whole page at once; on its own the button asks. Not named $saved:
     * Livewire copies a parameter onto the property of the same name
     * before mount runs, and a null would not fit the bool.
     */
    public function mount(JobPosting $jobPosting, ?bool $initiallySaved = null): void
    {
        $this->jobPosting = $jobPosting;

        $user = auth()->user();

        $this->saved = $initiallySaved ?? ($user
            ? $user->savedJobs()->where('job_posting_id', $jobPosting->id)->exists()
            : false);
    }

    public function toggle(): void
    {
        $user = auth()->user();

        if (! $user) {
            // Back to the page they were on after signing in, search and
            // filters intact, rather than to a dashboard. Only a page of
            // this site: the address comes from the Referer header. Not
            // url()->previous(), which falls back to the home page when it
            // knows nothing, and would send them there instead.
            $page = request()->headers->get('referer') ?: session()->previousUrl();

            if ($page && parse_url($page, PHP_URL_HOST) === request()->getHost()) {
                redirect()->setIntendedUrl($page);
            }

            $this->redirectRoute('login');

            return;
        }

        if ($this->saved) {
            $user->savedJobs()->detach($this->jobPosting->id);
        } else {
            $user->savedJobs()->syncWithoutDetaching([$this->jobPosting->id]);
        }

        $this->saved = ! $this->saved;
    }

    public function render()
    {
        return view('livewire.save-job-button');
    }
}
