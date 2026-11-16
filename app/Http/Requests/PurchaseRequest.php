<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create / edit a draft purchase order. */
class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchases.create');
    }

    protected function prepareForValidation(): void
    {
        // Ignore empty lines the user added but never filled in.
        $items = collect($this->input('items', []))
            ->filter(fn ($row) => is_array($row) && filled($row['product_id'] ?? null))
            ->values()
            ->all();

        $this->merge([
            'items' => $items,
            'discount' => filled($this->discount) ? $this->discount : 0,
            'tax' => filled($this->tax) ? $this->tax : 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'purchase_date' => ['required', 'date'],
            'discount' => ['numeric', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'tax' => ['numeric', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999'],
            'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999'],
            'action' => ['nullable', 'in:draft,submit'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
            'items.*.unit_cost' => 'unit cost',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one product to the purchase.',
            'items.min' => 'Add at least one product to the purchase.',
            'items.*.product_id.distinct' => 'This product is already in the list. Change the quantity instead.',
            'items.*.product_id.exists' => 'This product is inactive or archived.',
            'supplier_id.exists' => 'The selected supplier is inactive or deleted.',
            'items.*.quantity.gt' => 'Quantity must be greater than zero.',
        ];
    }

    public function submitForApproval(): bool
    {
        return $this->input('action') === 'submit';
    }
}
