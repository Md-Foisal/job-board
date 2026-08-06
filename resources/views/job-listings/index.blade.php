<x-layouts::guest>
  <div class="max-w-4xl mx-auto py-8 px-4">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-zinc-800 dark:text-white">Job Listings</h1>
      @if(auth()->check() && auth()->user()->hasRole('employer'))
        <a href="{{ route('job-listings.create') }}"
          class="px-4 py-2 bg-zinc-800 text-white rounded-lg text-sm hover:bg-zinc-700 dark:bg-white dark:text-zinc-800 dark:hover:bg-zinc-100">
          Post a Job
        </a>
      @endif
    </div>


    {{-- Livewire Search Component --}}
    <livewire:job-search />

  </div>
</x-layouts::guest>