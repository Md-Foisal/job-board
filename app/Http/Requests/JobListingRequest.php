<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\Intl\Currencies;

class JobListingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->salary_currency) {
            $this->merge([
                'salary_currency' => strtoupper($this->salary_currency),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', 'min:5'],
            'description' => ['required', 'string', 'min:10'],
            'location' => ['required', 'string', 'max:255', 'min:2'],
            'salary_min' => ['nullable', 'integer', 'min:0'],
            'salary_max' => ['nullable', 'integer', 'min:0', 'gte:salary_min'],
            'salary_currency' => ['nullable', 'string', Rule::in(Currencies::getCurrencyCodes())],
            'salary_period' => ['nullable', 'string', 'in:hourly,weekly,monthly,yearly,contract'],
            'employment_type' => ['required', 'in:full-time,part-time,contract,internship,freelance'],
            'work_location' => ['required', 'in:remote,onsite,hybrid'],
            'categories' => ['required', 'array'],
            'categories.*' => ['exists:categories,id'],
            'skills' => ['required', 'array'],
            'skills.*.selected' => ['boolean'],
            'skills.*.importance' => ['required_if:skills.*.selected,true', 'string', 'in:required,nice-to-have'],
            'expires_at' => ['required', 'date', 'after:today', 'before_or_equal:+30 days'],
        ];
    }
}
