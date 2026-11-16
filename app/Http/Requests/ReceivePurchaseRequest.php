<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchases.receive');
    }

    public function rules(): array
    {
        return [
            'receipt_token' => ['required', 'uuid'],
            'received_at' => ['required', 'date', 'before_or_equal:now'],
            'items' => ['required', 'array'],
            // Over-receiving is checked against the remaining quantity in PurchaseService.
            'items.*' => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['items.*' => 'received quantity', 'received_at' => 'received date'];
    }
}
