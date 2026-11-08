<?php

namespace App\Http\Requests;

use App\Enums\AssignmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssignmentRequest extends FormRequest
{
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(AssignmentStatus::class)]];
    }

    public function attributes(): array
    {
        return ['status' => 'durum'];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute alanı zorunludur.',
            'enum' => 'Geçerli bir durum seçin.',
        ];
    }
}
