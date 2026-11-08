<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderContentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $expected = $this->route('education')->contents()->pluck('id')->sort()->values()->all();
            $given = collect($this->input('ids'))->map(fn ($i) => (int) $i)->sort()->values()->all();
            if ($expected !== $given) {
                $v->errors()->add('ids', 'Liste, eğitimin tüm içeriklerini tam olarak bir kez içermelidir.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Sıralama listesi zorunludur.',
            'ids.array' => 'Sıralama listesi dizi olmalıdır.',
            'ids.*.integer' => 'Geçersiz içerik kimliği.',
        ];
    }
}
