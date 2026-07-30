<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is enforced by the validity of the invitation token itself.
    }

    public function rules(): array
    {
        $rules = [
            'role'     => ['required', Rule::in(array_keys(User::INVITABLE_ROLES))],
            'position' => ['nullable', 'string', 'max:255'],
        ];

        // Only required when we are creating a brand new account (no existing user with this email).
        if ($this->boolean('is_new_user')) {
            $rules['name']     = ['required', 'string', 'max:255'];
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        return $rules;
    }
}
