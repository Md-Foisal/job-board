<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', 'min:5'],
            'company' => ['required', 'string', 'max:255', 'min:2'],
            'description' => ['required', 'string', 'min:10'],
            'location' => ['required', 'string', 'max:255', 'min:2'],
            'salary_min' => ['nullable', 'integer', 'min:0'],
            'salary_max' => ['nullable', 'integer', 'min:0', 'gte:salary_min'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'salary_period' => ['nullable', 'string', 'in:hourly,weekly,monthly,yearly,contract'],
            'type' => ['required', 'in:full-time,part-time,remote,contract,internship'],
            'categories' => ['required', 'array'],
            'categories.*' => ['exists:categories,id'],
            'skills' => ['required', 'array'],
            'skills.*.selected' => ['boolean'],
            'skills.*.importance' => ['required_if:skills.*.selected,true', 'string', 'in:required,nice-to-have'],
            'expires_at' => ['required', 'date', 'after:today', 'before_or_equal:+30 days'],
        ];
    }
}
