<?php

namespace App\Http\Requests;

use App\Enums\IdentityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Delegated rather than re-decided here: repeating an authorization
     * rule in a second place is how the two quietly stop agreeing.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('company'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'identity_type' => ['required', Rule::enum(IdentityType::class)],
            'description' => ['nullable', 'string', 'max:20000'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', Rule::in(['1-10', '11-50', '51-200', '200+'])],
            'logo' => ['nullable', 'image', 'max:2048'],
            'cover_photo' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function attributes(): array
    {
        return [
            'identity_type' => 'type',
            'website_url' => 'website',
        ];
    }
}
