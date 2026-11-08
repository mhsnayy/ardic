<?php

namespace App\Http\Requests;

use App\Services\Content\ContentCreator;
use App\Services\Content\ContentCreatorRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentRequest extends FormRequest
{
    private function creator(): ?ContentCreator
    {
        $type = $this->input('type');
        $registry = app(ContentCreatorRegistry::class);

        return is_string($type) && $registry->has($type) ? $registry->for($type) : null;
    }

    public function rules(): array
    {
        $registry = app(ContentCreatorRegistry::class);
        $rules = [
            'type' => ['required', 'string', Rule::in($registry->kinds())],
            'title' => ['required', 'string', 'max:150'],
            'is_required' => ['sometimes', 'boolean'],
        ];

        $creator = $this->creator();
        if ($creator) {
            return array_merge($rules, $creator->rules($this->route('education')));
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['duration_seconds' => 'süre', 'type' => 'tür', 'title' => 'başlık', 'is_required' => 'zorunluluk'];
    }

    public function messages(): array
    {
        return ($this->creator()?->messages() ?? []) + [
            'required' => ':attribute alanı zorunludur.',
            'string' => ':attribute metin olmalıdır.',
            'max' => ':attribute en fazla :max karakter olabilir.',
            'boolean' => ':attribute true ya da false olmalıdır.',
            'type.in' => 'Geçerli bir içerik türü seçin.',
        ];
    }
}
