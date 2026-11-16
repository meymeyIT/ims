<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->username)),
            'email' => strtolower(trim((string) $this->email)),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            // letters, numbers, dot, dash, underscore; no @ so it can never look like an email
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{6,30}$/'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'password' => ['nullable', 'confirmed', Password::defaults()], // blank = keep current password
            'is_active' => ['boolean'],
        ];
    }

    /** Business rules that need the database / current user. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $target = $this->route('user');
                $isSelf = $this->user()->is($target);
                $newRoleIsAdmin = Role::whereKey($this->input('role_id'))->value('name') === Role::ADMIN;

                if ($isSelf && (int) $this->input('role_id') !== $target->role_id) {
                    $validator->errors()->add('role_id', 'You cannot change your own role.');
                }

                if ($isSelf && ! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'You cannot deactivate your own account.');
                }

                // Never leave the system without an active administrator.
                if ($target->isLastActiveAdmin() && (! $newRoleIsAdmin || ! $this->boolean('is_active'))) {
                    $validator->errors()->add('role_id', 'This is the last active administrator. Create or activate another admin first.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return ['role_id' => 'role', 'is_active' => 'status'];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number (digits, spaces, +, -, brackets).',
            'username.regex' => 'Username may only contain lowercase letters, numbers, dots, dashes and underscores.',
        ];
    }
}
