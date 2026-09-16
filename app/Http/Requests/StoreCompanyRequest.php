<?php

namespace App\Http\Requests;

use App\Enums\IdentityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    /**
     * Any signed-in person may start a company -- there is no prior
     * status to check, because creating one is how employer-side access
     * begins. What they may do afterwards is decided by the membership
     * this creates.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'identity_type' => ['required', Rule::enum(IdentityType::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'identity_type' => 'type',
        ];
    }
}
