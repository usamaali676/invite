<?php

namespace App\Http\Requests;

use App\Enums\AccessMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-gbp') ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email:rfc', 'max:255'],
            'google_maps_url' => ['nullable', 'url:http,https', 'max:2048'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'access_method' => ['sometimes', Rule::enum(AccessMethod::class)],
        ];
    }
}
