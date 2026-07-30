<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isCompanyAdmin();
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'max:255', Rule::unique('companies', 'email')->ignore($companyId)],
            'phone'   => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'tpin'    => ['nullable', 'string', 'max:50'],
        ];
    }
}
