<?php

namespace App\Http\Requests;

use App\Enums\EducationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EducationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(EducationType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'ad', 'type' => 'tür', 'description' => 'açıklama'];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute alanı zorunludur.',
            'string' => ':attribute metin olmalıdır.',
            'max' => ':attribute en fazla :max karakter olabilir.',
            'type.enum' => 'Geçerli bir tür seçin.',
        ];
    }
}
