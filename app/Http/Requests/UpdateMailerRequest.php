<?php

namespace App\Http\Requests;

use App\Models\Mailer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMailerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
       return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            
            'name' => 'required|string|max:255',         // Name is required, must be a string, and has a max length of 255 characters
            'subject' => 'required|string|max:255',      // Subject can be nullable, but if present, it must be a string with a max length of 255
            'body' => 'nullable|string',                 // Body can be nullable, and must be a string if present
            'signature' => 'nullable|string|max:255',    // Signature is nullable, string, and has a max length of 255 characters
            'files' => 'array',                          // Files can be nullable but must be a valid array
            'files.*' => 'file|max:10240',               // Each file inside the files array must be an actual file and not larger than 10MB (10240 KB)
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'subject.max' => 'The subject must not exceed 255 characters.',
            'signature.max' => 'The signature must not exceed 255 characters.',
            'files.*.file' => 'Each file must be a valid file.',
            'files.*.max' => 'Each file must not exceed 10MB.',
        ];
    }
}
