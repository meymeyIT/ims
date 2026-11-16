<?php

namespace App\Http\Requests;

use App\Rules\PhoneNumber;
use App\Support\Branding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'business_email' => filled($this->business_email) ? strtolower(trim((string) $this->business_email)) : null,
            'purchase_number_prefix' => strtoupper(trim((string) $this->purchase_number_prefix)),
            'receipt_number_prefix' => strtoupper(trim((string) $this->receipt_number_prefix)),
            'low_stock_notifications' => $this->boolean('low_stock_notifications'),
            'low_stock_email' => $this->boolean('low_stock_email'),
            'remove_logo' => $this->boolean('remove_logo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:150'],
            'business_address' => ['nullable', 'string', 'max:500'],
            'business_phone' => ['nullable', 'string', 'max:30', new PhoneNumber()],
            'business_email' => ['nullable', 'email', 'max:150'],
            // PNG / JPG / WEBP only. SVG is refused on purpose: it can contain JavaScript.
            'business_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
            'remove_logo' => ['boolean'],
            'currency' => ['required', Rule::in(['USD', 'KHR'])],
            'khr_exchange_rate' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:100000'],
            'date_format' => ['required', Rule::in(array_keys(Branding::DATE_FORMATS))],
            'low_stock_notifications' => ['boolean'],
            'low_stock_email' => ['boolean'],
            'purchase_number_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/'],
            'receipt_number_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/', 'different:purchase_number_prefix'],
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_number_prefix.regex' => 'Use only letters and numbers (e.g. PO).',
            'receipt_number_prefix.regex' => 'Use only letters and numbers (e.g. GRN).',
            'receipt_number_prefix.different' => 'Receipt and purchase prefixes must be different.',
        ];
    }
}
