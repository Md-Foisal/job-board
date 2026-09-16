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

    public function mount(JobPosting $jobPosting): void
    {
        $this->jobPosting = $jobPosting;

        $user = auth()->user();

        $this->saved = $user
            ? $user->savedJobs()->where('job_posting_id', $jobPosting->id)->exists()
            : false;
    }

    public function toggle(): void
    {
        $user = auth()->user();

        if (! $user) {
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
