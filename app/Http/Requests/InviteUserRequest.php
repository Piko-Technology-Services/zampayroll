<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasPermission('team.manage');
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
            'role'     => ['required', Rule::in(\App\Models\CompanyRole::withoutGlobalScope('company')->pluck('key')->unique()->all())],
            'position' => ['nullable', 'string', 'max:255'],
        ];
    }
}
