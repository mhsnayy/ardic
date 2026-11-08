<?php

namespace App\Http\Requests;

use App\Services\Content\ContentCreator;
use App\Services\Content\ContentCreatorRegistry;
use Illuminate\Foundation\Http\FormRequest;

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
        $rules = [
            'type' => ['required', 'string', 'in:video,document,link'],
            'title' => ['required', 'string', 'max:150'],
            'is_required' => ['sometimes', 'boolean'],
        ];

        $creator = $this->creator();
        if ($creator) {
            return array_merge($rules, $creator->rules());
        }

        // Valid kind without a registered creator yet (file uploads arrive in step 2).
        if (in_array($this->input('type'), ['video', 'document'], true)) {
            $rules['type'][] = fn ($attribute, $value, $fail) => $fail('Bu içerik türü henüz desteklenmiyor.');
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['type' => 'tür', 'title' => 'başlık', 'is_required' => 'zorunluluk'];
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
