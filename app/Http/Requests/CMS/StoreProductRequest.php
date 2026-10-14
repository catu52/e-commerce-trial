<?php

namespace App\Http\Requests\CMS;

use App\Enums\ProductGender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('products.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0.01'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:products,slug'],
            'code' => ['nullable', 'string', 'max:50'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sub_category' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:100'],
            'badge' => ['nullable', 'string', 'max:100'],
            'substrate' => ['nullable', 'string', 'max:255'],
            'spec_weight' => ['nullable', 'string', 'max:100'],
            'colorway' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::enum(ProductGender::class)],
            'is_featured' => ['nullable', 'boolean'],
            'currency' => ['nullable', 'string', 'size:3'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['string', 'max:50'],
            'colors' => ['nullable', 'array'],
            'colors.*.name' => ['required', 'string', 'max:100'],
            'colors.*.hex' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'specs' => ['nullable', 'array'],
            'specs.origin' => ['nullable', 'string', 'max:255'],
            'specs.weight' => ['nullable', 'string', 'max:255'],
            'specs.composition' => ['nullable', 'string', 'max:255'],
            'specs.care' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'long_description' => ['nullable', 'string'],
            'care_instructions' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ];
    }
}
