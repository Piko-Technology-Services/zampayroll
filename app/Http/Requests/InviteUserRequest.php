<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isCompanyAdmin();
    }

    public function rules(): array
    {
        
        return [
            'email'    => [
                'required',
                'email',
                'max:255',
                // Prevent inviting someone who is already an active member of THIS company.
                Rule::unique('users', 'email')->where(fn ($q) => $q->where('company_id', $this->user()->company_id)),
            ],
            'role'     => ['required', Rule::in(array_keys(User::INVITABLE_ROLES))],
            'position' => ['nullable', 'string', 'max:255'],
        ];
    }
}
