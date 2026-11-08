<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return ['email' => 'e-posta', 'password' => 'şifre'];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute alanı zorunludur.',
            'email' => 'Geçerli bir e-posta adresi girin.',
        ];
    }
}
