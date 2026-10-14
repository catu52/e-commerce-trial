<?php

namespace App\Http\Requests\CMS;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('clients.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $client = $this->route('client');
        $required = $client ? 'sometimes' : 'required';

        return [
            'first_name' => [$required, 'string', 'max:255'],
            'last_name' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255', Rule::unique('clients', 'email')->ignore($client?->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => [$client ? 'prohibited' : 'required', 'string', 'min:8'],
            'email_verified' => ['sometimes', 'boolean'],
        ];
    }
}
