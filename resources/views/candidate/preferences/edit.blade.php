<x-layouts::app :title="__('Job preferences')">
    <div class="mx-auto max-w-2xl">
        <flux:heading size="xl" level="1">{{ __('Job preferences') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Helps us and employers match you to the right roles.') }}</flux:subheading>
        <flux:separator variant="subtle" class="mb-6" />

        <form method="POST" action="{{ route('candidate.preferences.update') }}" class="space-y-6">
            @csrf
            @method('PATCH')

            <flux:checkbox
                name="is_actively_searching"
                value="1"
                :checked="old('is_actively_searching', $preference?->is_actively_searching ?? true)"
                :label="__('Actively searching')"
                :description="__('Shows employers you are currently open to offers')"
            />

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <flux:input
                    name="desired_salary_min"
                    type="number"
                    min="0"
                    :label="__('Desired salary, min')"
                    :value="old('desired_salary_min', $preference?->desired_salary_min)"
                />

                <flux:input
                    name="desired_salary_max"
                    type="number"
                    min="0"
                    :label="__('Desired salary, max')"
                    :value="old('desired_salary_max', $preference?->desired_salary_max)"
                />
            </div>

            <flux:input
                name="desired_salary_currency"
                :label="__('Currency')"
                :description="__('3-letter code, e.g. USD, BDT')"
                maxlength="3"
                class="uppercase"
                :value="old('desired_salary_currency', $preference?->desired_salary_currency)"
            />

            <flux:select
                name="preferred_workplace_type"
                :label="__('Preferred workplace type')"
            >
                <flux:select.option value="">{{ __('No preference') }}</flux:select.option>
                @foreach (\App\Enums\WorkplaceType::cases() as $type)
                    <flux:select.option
                        :value="$type->value"
                        :selected="old('preferred_workplace_type', $preference?->preferred_workplace_type?->value) === $type->value"
                    >
                        {{ $type->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select
                name="preferred_employment_type"
                :label="__('Preferred employment type')"
            >
                <flux:select.option value="">{{ __('No preference') }}</flux:select.option>
                @foreach (\App\Enums\EmploymentType::cases() as $type)
                    <flux:select.option
                        :value="$type->value"
                        :selected="old('preferred_employment_type', $preference?->preferred_employment_type?->value) === $type->value"
                    >
                        {{ $type->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                name="available_from"
                type="date"
                :label="__('Available from')"
                :value="old('available_from', $preference?->available_from?->format('Y-m-d'))"
            />

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
