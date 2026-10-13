<x-layouts::app :title="__('Job preferences')">
    <x-page width="narrow">
        <x-page-header :title="__('Job preferences')">
            <x-slot:status>
                <x-visibility-badge :tip="__('Only you see these. Each job page compares its pay, workplace, location and type of work with them.')" />
            </x-slot:status>
        </x-page-header>

        @php
            $currency = old('desired_salary_currency', $preference?->desired_salary_currency);
        @endphp

        <x-card as="form" method="POST" action="{{ route('candidate.preferences.update') }}" class="space-y-6">
            @csrf
            @method('PATCH')

            {{-- A plain element, not the card: Blade directives such as @js are
                 not compiled inside a component tag. --}}
            <div class="space-y-6" x-data="{
                    currency: @js($currency ?? ''),
                    min: @js((string) old('desired_salary_min', $preference?->desired_salary_min)),
                    max: @js((string) old('desired_salary_max', $preference?->desired_salary_max)),
                    money(amount) {
                        return this.currency
                            ? new Intl.NumberFormat('en-001', { style: 'currency', currency: this.currency, maximumFractionDigits: 0, minimumFractionDigits: 0 }).format(amount)
                            : new Intl.NumberFormat('en-001', { maximumFractionDigits: 0 }).format(amount);
                    },
                    get yearly() {
                        const min = parseInt(this.min, 10);
                        const max = parseInt(this.max, 10);

                        if (Number.isNaN(min) && Number.isNaN(max)) return '';
                        if (Number.isNaN(max) || min === max) return this.money(min * 12) + (Number.isNaN(max) ? ' ' + @js(__('or more')) : '');
                        if (Number.isNaN(min)) return @js(__('up to')) + ' ' + this.money(max * 12);

                        return this.money(min * 12) + '–' + this.money(max * 12);
                    },
                }">
                {{-- Currency first: the amounts mean nothing without it, and a job
                     paying in another currency is not compared. --}}
                <flux:select
                    name="desired_salary_currency"
                    x-model="currency"
                    :label="__('Currency')"
                    :description="__('A job that pays in another currency is not compared with your pay.')"
                >
                    <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                    @foreach (\App\Support\SalaryCurrencies::options($currency) as $code => $label)
                        <flux:select.option :value="$code" :selected="$currency === $code">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                {{-- Monthly, because every posting's pay is turned into a month
                     before the comparison, whatever period it was given in. --}}
                <flux:fieldset>
                    <flux:legend>{{ __('Pay you want, per month') }}</flux:legend>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <flux:input
                            name="desired_salary_min"
                            type="number"
                            min="0"
                            inputmode="numeric"
                            x-model="min"
                            :label="__('At least, per month')"
                            :value="old('desired_salary_min', $preference?->desired_salary_min)"
                        />

                        <flux:input
                            name="desired_salary_max"
                            type="number"
                            min="0"
                            inputmode="numeric"
                            x-model="max"
                            :label="__('Up to, per month')"
                            :value="old('desired_salary_max', $preference?->desired_salary_max)"
                        />
                    </div>

                    <flux:text size="sm" class="mt-3" x-show="yearly" x-cloak aria-live="polite">
                        {{ __('That is') }} <span class="font-medium text-ink" x-text="yearly"></span> {{ __('a year.') }}
                    </flux:text>
                </flux:fieldset>
            </div>

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

            {{-- Where the candidate lives, not where they would like to work:
                 a remote job is open to one country, and an on-site one is in
                 a city, so these two answer both. Moving is the third
                 answer, the way the large boards ask it. --}}
            <flux:fieldset>
                <flux:legend>{{ __('Where you live') }}</flux:legend>
                <flux:text size="sm">{{ __('A remote job is compared by country, an on-site or hybrid one by country and city.') }}</flux:text>

                @php
                    $country = old('location_country', $preference?->location_country);
                    // After a failed save the box shows what was sent, and an
                    // unticked box sends nothing.
                    $relocation = $errors->any() ? (bool) old('open_to_relocation') : (bool) $preference?->open_to_relocation;
                @endphp

                <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:select name="location_country" :label="__('Country')">
                        <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                        @foreach (\App\Support\Countries::options($country) as $code => $name)
                            <flux:select.option :value="$code" :selected="$country === $code">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input
                        name="location_city"
                        maxlength="100"
                        autocomplete="address-level2"
                        :label="__('City')"
                        :value="old('location_city', $preference?->location_city)"
                    />
                </div>

                <div class="mt-4">
                    <flux:checkbox
                        name="open_to_relocation"
                        value="1"
                        :label="__('I would move to another city for the right job')"
                        :checked="$relocation"
                    />
                </div>
            </flux:fieldset>

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
                :description="__('For your own planning; jobs are not compared with it.')"
                :value="old('available_from', $preference?->available_from?->format('Y-m-d'))"
            />

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </x-card>
    </x-page>
</x-layouts::app>
