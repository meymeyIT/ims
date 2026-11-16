<?php

namespace App\Http\Requests;

use App\Enums\StockOutReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('stock.out');
    }

    public function rules(): array
    {
        return [
            'form_token' => ['required', 'uuid'],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at')],
            // "Cannot exceed available stock" is checked by StockService under a row lock,
            // which is the only place where the true current stock is known for certain.
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999'],
            'reason' => ['required', Rule::enum(StockOutReason::class)],
            'supplier_id' => [
                'nullable',
                Rule::requiredIf($this->input('reason') === StockOutReason::ReturnedToSupplier->value),
                'integer',
                Rule::exists('suppliers', 'id')->whereNull('deleted_at'),
            ],
            'movement_date' => ['required', 'date', 'before_or_equal:now'],
            'notes' => [
                Rule::requiredIf($this->input('reason') === StockOutReason::Other->value),
                'nullable', 'string', 'max:1000',
            ],
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
            'supplier_id.required' => 'Choose the supplier the goods are returned to.',
            'notes.required' => 'Please describe the reason in the notes.',
        ];
    }
}
