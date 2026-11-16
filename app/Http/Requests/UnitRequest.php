<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('units.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->name),
            'abbreviation' => trim((string) $this->abbreviation),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $unit = $this->route('unit');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('units', 'name')->ignore($unit)],
            'abbreviation' => ['required', 'string', 'max:20', Rule::unique('units', 'abbreviation')->ignore($unit)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
