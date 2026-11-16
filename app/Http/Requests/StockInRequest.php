<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Manual stock in (opening stock, restock without a purchase order, ...). */
class StockInRequest extends FormRequest
{
    public const REASONS = [
        'opening_stock' => 'Opening stock',
        'restock' => 'Restock (no purchase order)',
        'transfer_in' => 'Transfer from another location',
        'customer_return' => 'Customer return (RETURN_IN)',
        'other' => 'Other',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('stock.in');
    }

    public function rules(): array
    {
        return [
            'form_token' => ['required', 'uuid'],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999'],
            'unit_cost' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'reason' => ['required', Rule::in(array_keys(self::REASONS))],
            'movement_date' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['product_id' => 'product', 'supplier_id' => 'supplier', 'movement_date' => 'date'];
    }

    public function messages(): array
    {
        return [
            'quantity.gt' => 'Quantity must be greater than zero.',
            'product_id.exists' => 'This product is inactive or archived.',
        ];
    }
}
