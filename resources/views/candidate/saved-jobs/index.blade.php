<x-layouts::app :title="__('Saved jobs')">
    <x-page>
        <x-page-header :title="__('Saved jobs')" />

        @if ($unavailableCount > 0)
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <flux:text>
                    {{ trans_choice('{1} One job you saved is no longer available, so it is not shown.|[2,*] :count jobs you saved are no longer available, so they are not shown.', $unavailableCount) }}
                </flux:text>
                <form method="POST" action="{{ route('candidate.saved-jobs.prune') }}">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit" size="sm" variant="ghost" icon="trash">
                        {{ trans_choice('{1} Remove it|[2,*] Remove them', $unavailableCount) }}
                    </flux:button>
                </form>
            </div>
        @endif

        @if ($jobPostings->isEmpty() && $unavailableCount > 0)
            {{-- Saying "you haven't saved any" here would contradict the
                 line just above it. --}}
            <x-empty-state icon="bookmark" :heading="__('None of the jobs you saved is open right now.')" :action-href="route('jobs.index')" :action-label="__('Browse open roles')" />
        @elseif ($jobPostings->isEmpty())
            <x-empty-state icon="bookmark" :heading="__('You haven\'t saved any jobs yet.')" :action-href="route('jobs.index')" :action-label="__('Browse open roles')">
                {{ __('Save a job from its card or its page and it waits for you here.') }}
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($jobPostings as $jobPosting)
                    <x-job-card :job-posting="$jobPosting" :show-save-button="true" :saved="true" :applied="in_array($jobPosting->id, $appliedIds, true)" />
                @endforeach
            </div>
        @endif
    </x-page>
</x-layouts::app>
