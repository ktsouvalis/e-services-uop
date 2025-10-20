<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSheetmailerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled via policy in controller using Gate::authorize
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes','string','max:255'],
            'subject' => ['sometimes','string','max:255'],
            'body' => ['sometimes','string','nullable'],
            'signature' => ['sometimes','string','nullable','max:255'],
            'is_public' => ['nullable','boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure checkbox maps to boolean; unchecked may be missing
        if ($this->has('is_public')) {
            $this->merge(['is_public' => (bool) $this->input('is_public')]);
        }
    }
}
