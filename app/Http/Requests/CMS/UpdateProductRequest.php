<?php

namespace App\Http\Requests\CMS;

use App\Enums\ProductGender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('products.manage') ?? false;
    }

    /**
     * Partial update: every field is optional. Stock is changed via adjust-stock only.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'sku' => ['sometimes', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'base_price' => ['sometimes', 'numeric', 'min:0.01'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($productId)],
            'code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'sub_category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tag' => ['sometimes', 'nullable', 'string', 'max:100'],
            'badge' => ['sometimes', 'nullable', 'string', 'max:100'],
            'substrate' => ['sometimes', 'nullable', 'string', 'max:255'],
            'spec_weight' => ['sometimes', 'nullable', 'string', 'max:100'],
            'colorway' => ['sometimes', 'nullable', 'string', 'max:100'],
            'gender' => ['sometimes', Rule::enum(ProductGender::class)],
            'is_featured' => ['sometimes', 'boolean'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'sizes' => ['sometimes', 'array'],
            'sizes.*' => ['string', 'max:50'],
            'colors' => ['sometimes', 'array'],
            'colors.*.name' => ['required', 'string', 'max:100'],
            'colors.*.hex' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'specs' => ['sometimes', 'nullable', 'array'],
            'specs.origin' => ['nullable', 'string', 'max:255'],
            'specs.weight' => ['nullable', 'string', 'max:255'],
            'specs.composition' => ['nullable', 'string', 'max:255'],
            'specs.care' => ['nullable', 'string', 'max:255'],
            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'long_description' => ['sometimes', 'nullable', 'string'],
            'care_instructions' => ['sometimes', 'nullable', 'string'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:320'],
        ];
    }
}
