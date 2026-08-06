<x-layouts::guest>
    <section class="text-center px-6 py-16">
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900">
            Find work that fits your skills
        </h1>
        <p class="mt-3 text-gray-600">
            Browse and search freely — sign up only when you apply
        </p>
        <form action="{{ route('job-listings.index') }}" method="GET"
            class="mt-8 max-w-2xl mx-auto flex flex-col sm:flex-row gap-3">
        
            <input type="text" name="search" placeholder="Job title, keyword, or skill"
                class="flex-1 rounded-md border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        
            <input type="text" name="location" placeholder="Location"
                class="sm:w-48 rounded-md border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        
            <button type="submit" class="rounded-md bg-blue-600 px-6 py-3 text-sm font-medium text-white hover:bg-blue-700">
                Search
            </button>
        </form>
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            <a href="{{ route('job-listings.index', ['search' => 'Laravel']) }}"
                class="rounded-full border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:border-blue-500 hover:text-blue-600">Laravel</a>
            <a href="{{ route('job-listings.index', ['search' => 'React']) }}"
                class="rounded-full border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:border-blue-500 hover:text-blue-600">React</a>
            <a href="{{ route('job-listings.index', ['search' => 'Remote']) }}"
                class="rounded-full border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:border-blue-500 hover:text-blue-600">Remote</a>
            <a href="{{ route('job-listings.index', ['search' => 'Full-time']) }}"
                class="rounded-full border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:border-blue-500 hover:text-blue-600">Full-time</a>
        </div>
    </section>
</x-layouts::guest>