<?php

namespace App\Http\Requests;

use App\Support\ImageUploads;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRecruiterProfileRequest extends FormRequest
{
    /**
     * The route already establishes that this person works somewhere;
     * the profile they are editing is their own by construction, since
     * it is looked up from the signed-in user rather than from the URL.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'avatar' => ['nullable', ...ImageUploads::rules(2048)],
        ];
    }
}
