<x-layouts::app :title="'Job Create'">
    <div class="max-w-4xl mx-auto py-8 px-4">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-zinc-800 dark:text-white">Job Edit</h1>
            <a href="{{ route('job-listings.index') }}" 
            class="px-4 py-2 bg-zinc-800 text-white rounded-lg text-sm hover:bg-zinc-700 dark:bg-white dark:text-zinc-800 dark:hover:bg-zinc-100">Home</a>
        </div>

        {{-- Job create --}}
        <form action="{{ route('job-listings.update', $jobListing) }}" 
        method="POST" 
        class="flex flex-col gap-4">
            @csrf
            @method('put')
            {{-- title --}}
            <label for="title" class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition cursor-pointer">
                <h2 class="test-lg font-semibold text-zinc-800 dark:text-white mb-2">Job Title</h2>
                <input type="text" name="title" id="title" value="{{ old('title', $jobListing->title) }}" placeholder="Give a title" class="w-full outline-none px-3 focus:ring focus:ring-zinc-200 dark:focus:ring-zinc-700 py-3 rounded-sm bg-zinc-100 test-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                @error('title')
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-2">* {{ $message }}
                    </p>
                @enderror
            </label>

            {{-- company --}}
            <label for="company"
                class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition cursor-pointer">
                <h2 class="test-lg font-semibold text-zinc-800 dark:text-white mb-2">Company Name</h2>
                <input type="text" name="company" id="company" value="{{ old('company', $jobListing->company) }}" placeholder="Company name"
                    class="w-full outline-none px-3 focus:ring focus:ring-zinc-200 dark:focus:ring-zinc-700 py-3 rounded-sm bg-zinc-100 test-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                @error('company')
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-2">* {{ $message }}
                    </p>
                @enderror
            </label>

            {{-- description --}}
            <label for="description"
                class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition cursor-pointer">
                <h2 class="test-lg font-semibold text-zinc-800 dark:text-white mb-2">Job Description</h2>
                <textarea type="text" name="description" id="description" placeholder="Write Job description here"
                    class="w-full outline-none px-3 focus:ring focus:ring-zinc-200 dark:focus:ring-zinc-700 py-3 rounded-sm bg-zinc-100 test-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ old('description', $jobListing->description) }}</textarea>
                @error('description')
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-2">* {{ $message }}
                    </p>
                @enderror
            </label>

            {{-- location --}}
            <label for="location" class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition cursor-pointer">
                <h2 class="test-lg font-semibold text-zinc-800 dark:text-white mb-2">Job Location</h2>
                <input type="text" name="location" id="location" value="{{ old('location', $jobListing->location) }}" placeholder="Write location" class="w-full outline-none px-3 focus:ring focus:ring-zinc-200 dark:focus:ring-zinc-700 py-3 rounded-sm bg-zinc-100 test-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                @error('location')
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-2">* {{ $message }}
                    </p>
                @enderror
            </label>

            {{-- salary_min salary_max salary_currency salary_period --}}
            <div class="grid grid-cols-2 gap-4">
                <label for="salary_min"
                    class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition cursor-pointer">
                    <h2 class="test-lg font-semibold text-zinc-800 dark:text-white mb-2">Salary Min</h2>
                    <input type="number" name="salary_min" id="salary_min" value="{{ old('salary_min', $jobListing->salary_min) }}"
                        placeholder="Minimum salary"
                        class="w-full outline-none px-3 focus:ring focus:ring-zinc-200 dark:focus:ring-zinc-700 py-3 rounded-sm bg-zinc-100 test-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    @error('salary_min')
                        <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-2">* {{ $message }}</p>
                    @enderror
                </label>
            
                <label for="salary_max"
                    class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition cursor-pointer">
                    <h2 class="test-lg font-semibold text-zinc-800 dark:text-white mb-2">Salary Max</h2>
                    <input type="number" name="salary_max" id="salary_max" value="{{ old('salary_max', $jobListing->salary_max) }}"
                        placeholder="Maximum salary"
                        class="w-full outline-none px-3 focus:ring focus:ring-zinc-200 dark:focus:ring-zinc-700 py-3 rounded-sm bg-zinc-100 test-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    @error('salary_max')
                        <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-2">* {{ $message }}</p>
                    @enderror
                </label>
            
                <label for="salary_currency"
                    class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition cursor-pointer">
                    <h2 class="test-lg font-semibold text-zinc-800 dark:text-white mb-2">Salary Currency</h2>
                    <input type="text" name="salary_currency" id="salary_currency"
                        value="{{ old('salary_currency', $jobListing->salary_currency) }}" placeholder="Currency (e.g., USD)"
                        class="w-full outline-none px-3 focus:ring focus:ring-zinc-200 dark:focus:ring-zinc-700 py-3 rounded-sm bg-zinc-100 test-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    @error('salary_currency')
                        <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-2">* {{ $message }}</p>
                    @enderror
                </label>
            
                <label for="salary_period"
                    class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition cursor-pointer">
                    <h2 class="test-lg font-semibold text-zinc-800 dark:text-white mb-2">Salary Period</h2>
                    <select name="salary_period" id="salary_period"
                        class="w-full outline-none px-3 focus:ring focus:ring-zinc-200 dark:focus:ring-zinc-700 py-3 rounded-sm bg-zinc-100 test-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        <option value="hourly" {{ old('salary_period', $jobListing->salary_period) == 'hourly' ? 'selected' : "" }}>
                            Hourly</option>
                        <option value="weekly" {{ old('salary_period', $jobListing->salary_period) == 'weekly' ? 'selected' : "" }}>
                            Weekly</option>
                        <option value="monthly" {{ old('salary_period', $jobListing->salary_period) == 'monthly' ? 'selected' : "" }}>
                            Monthly</option>
                        <option value="yearly" {{ old('salary_period', $jobListing->salary_period) == 'yearly' ? 'selected' : "" }}>
                            Yearly</option>
                        <option value="contract" {{ old('salary_period', $jobListing->salary_period) == 'contract' ? 'selected' : "" }}>Contract</option>
                    </select>
                    @error('salary_period')
                        <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-2">* {{ $message }}</p>
                    @enderror
                </label>
            </div>

            {{-- categories --}}
            <div class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition cursor-pointer">
                <h2 class="test-lg font-semibold text-zinc-800 dark:text-white mb-2">Categories</h2>
                @foreach ($categories as $category)
                    <div class="flex items-center gap-2 mb-2">
                        <input type="checkbox" name="categories[]" id="category_{{ $category->id }}" value="{{ $category->id }}" {{ in_array($category->id, old('categories', $jobListing->categories->pluck('id')->toArray())) ? 'checked' : '' }} class="w-4 h-4 text-zinc-800 dark:text-zinc-100 bg-zinc-100 border-zinc-300 rounded focus:ring-zinc-200 dark:focus:ring-zinc-700 dark:bg-zinc-800 dark:border-zinc-700">
                        <label for="category_{{ $category->id }}" class="text-zinc-800 dark:text-zinc-100">{{ $category->name }}</label>
                    </div>
                    
                @endforeach
                @error('categories')
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-2">* {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- skills --}}
            <div class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition cursor-pointer">
                <h2 class="test-lg font-semibold text-zinc-800 dark:text-white mb-2">Skills</h2>
                @foreach ($skills as $skill)
                    <div class="flex items-center gap-2 mb-2">
                        <input type="checkbox" name="skills[{{ $skill->id }}][selected]" id="skill_{{ $skill->id }}" value="1" {{ old('skills.'.$skill->id.'.selected', $jobListing->skills->contains($skill->id)) ? 'checked' : '' }} class="w-4 h-4 text-zinc-800 dark:text-zinc-100 bg-zinc-100 border-zinc-300 rounded focus:ring-zinc-200 dark:focus:ring-zinc-700 dark:bg-zinc-800 dark:border-zinc-700">
                        <label for="skill_{{ $skill->id }}" class="text-zinc-800 dark:text-zinc-100">{{ $skill->name }}</label>
                        <select name="skills[{{ $skill->id }}][importance]" class="ml-2 outline-none px-2 focus:ring focus:ring-zinc-200 dark:focus:ring-zinc-700 py-1 rounded-sm bg-zinc-100 test-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            <option value="required" {{ old('skills.'.$skill->id.'.importance', $jobListing->skills->find($skill->id)?->pivot->importance) == 'required' ? 'selected' : '' }}>
                                Required
                            </option>
                            <option value="nice-to-have" {{ old('skills.'.$skill->id.'.importance', $jobListing->skills->find($skill->id)?->pivot->importance) == 'nice-to-have' ? 'selected' : '' }}>
                                Nice To Have
                            </option>
                        </select>
                    </div>
                @endforeach
                @error('skills')
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-2">* {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- type --}}
            <label for="type" class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition cursor-pointer">
                <h2 class="test-lg font-semibold text-zinc-800 dark:text-white mb-2">Job Type</h2>
                <select name="type" id="type" class="w-full outline-none px-3 focus:ring focus:ring-zinc-200 dark:focus:ring-zinc-700 py-3 rounded-sm bg-zinc-100 test-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    <option value="full-time" {{ old('type', $jobListing->type) == 'full-time' ? 'selected' : "" }}>Full Time</option>
                    <option value="part-time" {{ old('type', $jobListing->type) == 'part-time' ? 'selected' : "" }}>Part Time</option>
                    <option value="remote" {{ old('type', $jobListing->type) == 'remote' ? 'selected' : "" }}>Remote</option>
                    <option value="contract" {{ old('type', $jobListing->type) == 'contract' ? 'selected' : "" }}>Contract</option>
                    <option value="internship" {{ old('type', $jobListing->type) == 'internship' ? 'selected' : "" }}>internship</option>
                </select>
                @error('type')
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-2">* {{ $message }}
                    </p>
                @enderror
            </label>

            {{-- status --}}
            
            {{-- Submit & cancel --}}
            <div class="flex items-center justify-evenly bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-3 gap-2">
                <button type="submit" 
                class="cursor-pointer 
                w-full 
                rounded 
                px-4 py-2 
                bg-zinc-100
                text-zinc-800 
                hover:bg-zinc-200 
                dark:bg-zinc-800 
                dark:text-zinc-100
                dark:hover:bg-zinc-700">Update</button>
                <a href="{{ route('job-listings.show', $jobListing) }}" class="cursor-pointer 
                w-full 
                rounded 
                px-4 py-2 
                bg-zinc-100
                text-zinc-800 
                hover:bg-zinc-200 
                dark:bg-zinc-800 
                dark:text-zinc-100
                dark:hover:bg-zinc-700 text-center">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts::app>