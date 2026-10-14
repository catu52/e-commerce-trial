<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ClientCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.productId' => ['required', 'string', 'max:255'],
            'items.*.size' => ['nullable', 'string', 'max:50'],
            'items.*.color' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'country' => ['required', 'string', 'max:100'],
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'apartment' => ['nullable', 'string', 'max:255'],
            'postalCode' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'deliveryMethod' => ['required', 'in:standard,express,sameday'],
            'deliveryCostEUR' => ['nullable', 'numeric', 'min:0'], // ignored; server computes cost
            'paymentMethod' => ['required', 'string', 'max:50'],
            'promoCode' => ['nullable', 'string', 'max:50'],
            'discountEUR' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
