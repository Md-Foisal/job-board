<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApplicationRequest extends FormRequest
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
            'job_listing_id' => ['required', 'exists:job_listings,id'],
            'cover_letter' => ['nullable', 'string', 'min:10'],
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:2048'],
        ];
    }
}
