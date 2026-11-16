<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Physical count adjustment: "the shelf really has X". */
class StockAdjustmentRequest extends FormRequest
{
    public const REASONS = [
        'stock_count' => 'Physical stock count',
        'correction' => 'Data entry correction',
        'found' => 'Found stock',
        'lost' => 'Lost / missing stock',
        'other' => 'Other',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('stock.adjust');
    }

    public function rules(): array
    {
        return [
            'form_token' => ['required', 'uuid'],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'counted_quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999'],
            'reason' => ['required', Rule::in(array_keys(self::REASONS))],
            'notes' => ['required', 'string', 'min:3', 'max:1000'],   // adjustments must always be explained
        ];
    }

    public function attributes(): array
    {
        return ['product_id' => 'product', 'counted_quantity' => 'counted quantity'];
    }
}
