<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create / update product.
 * current_stock and average_cost are intentionally NOT accepted here:
 * stock only changes through stock transactions.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('product') ? 'products.update' : 'products.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->name),
            'sku' => strtoupper(trim((string) $this->sku)),
            'barcode' => filled($this->barcode) ? trim((string) $this->barcode) : null,
            'is_active' => $this->boolean('is_active'),
            'remove_image' => $this->boolean('remove_image'),
        ]);
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:150'],
            // Unique across ALL products, archived ones included: an SKU is never reused.
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9._\-\/]+$/', Rule::unique('products', 'sku')->ignore($product)],
            'barcode' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('products', 'barcode')->ignore($product)],
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')],
            'unit_id' => ['required', 'integer', Rule::exists(Unit::class, 'id')],
            'purchase_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'selling_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'minimum_stock' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999999'],
            // Secure upload: real image content checked (not just the extension), max 2 MB.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'remove_image' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['category_id' => 'category', 'unit_id' => 'unit', 'sku' => 'SKU'];
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'This SKU is already used by another product (archived products included).',
            'sku.regex' => 'SKU may only contain letters, numbers and . _ - /',
            'barcode.unique' => 'This barcode is already used by another product.',
        ];
    }
}
