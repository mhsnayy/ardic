<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'personnel_ids' => ['required', 'array', 'min:1'],
            'personnel_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    public function attributes(): array
    {
        return ['personnel_ids' => 'personel', 'personnel_ids.*' => 'personel'];
    }

    public function messages(): array
    {
        return [
            'required' => 'En az bir :attribute seçin.',
            'array' => ':attribute listesi geçersiz.',
            'min' => 'En az bir :attribute seçin.',
            'integer' => 'Geçersiz :attribute kimliği.',
            'distinct' => 'Aynı :attribute birden fazla kez seçilemez.',
        ];
    }
}
