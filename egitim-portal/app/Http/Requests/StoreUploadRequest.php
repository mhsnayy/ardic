<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUploadRequest extends FormRequest
{
    /** @return array{label: string, max_bytes: int, mimes: array<string, string>}|null */
    private function spec(): ?array
    {
        $kind = $this->input('kind');

        return is_string($kind) ? config("media.kinds.$kind") : null;
    }

    public function rules(): array
    {
        $spec = $this->spec();

        return [
            'kind' => ['required', 'string', 'in:'.implode(',', array_keys(config('media.kinds')))],
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'in:'.implode(',', array_keys($spec['mimes'] ?? []))],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:'.($spec['max_bytes'] ?? 0)],
        ];
    }

    public function attributes(): array
    {
        return ['kind' => 'tür', 'filename' => 'dosya adı', 'mime_type' => 'dosya türü', 'size_bytes' => 'dosya boyutu'];
    }

    public function messages(): array
    {
        $spec = $this->spec();
        $mb = $spec ? (int) round($spec['max_bytes'] / 1048576) : 0;
        $formats = $spec ? strtoupper(implode(', ', array_values($spec['mimes']))) : '';

        return [
            'required' => ':attribute alanı zorunludur.',
            'string' => ':attribute metin olmalıdır.',
            'integer' => ':attribute sayı olmalıdır.',
            'filename.max' => 'Dosya adı en fazla 255 karakter olabilir.',
            'kind.in' => 'Geçerli bir içerik türü seçin (video veya document).',
            'mime_type.in' => "Desteklenmeyen dosya biçimi. İzin verilenler: $formats.",
            'size_bytes.min' => 'Dosya boş olamaz.',
            'size_bytes.max' => "Dosya boyutu en fazla $mb MB olabilir.",
        ];
    }
}
