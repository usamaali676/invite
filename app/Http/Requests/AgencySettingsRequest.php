<?php

namespace App\Http\Requests;

use App\Enums\ApiApprovalStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgencySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-gbp') ?? false;
    }

    /**
     * Accept "accounts/123" as well as "123" and store only the id.
     */
    protected function prepareForValidation(): void
    {
        foreach (['organization_id', 'location_group_account_id'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim(preg_replace('#^(accounts|organizations)/#', '', trim($this->input($field))))]);
            }
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'location_group_account_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'manager_email' => ['nullable', 'email:rfc', 'max:255'],
            'api_project_number' => ['nullable', 'string', 'max:32', 'regex:/^[0-9]+$/'],
            'api_approval_status' => ['required', Rule::enum(ApiApprovalStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location_group_account_id.regex' => 'Use only the numeric or alphanumeric account ID, for example 1234567890.',
            'api_project_number.regex' => 'The project number contains digits only.',
        ];
    }
}
