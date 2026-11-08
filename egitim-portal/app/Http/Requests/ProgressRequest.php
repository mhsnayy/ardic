<?php

namespace App\Http\Requests;

use App\Models\EducationContent;
use Illuminate\Foundation\Http\FormRequest;

class ProgressRequest extends FormRequest
{
    private ?EducationContent $resolved = null;

    /** The content, only if it belongs to the token's assignment's education (otherwise 404). */
    public function learnContent(): EducationContent
    {
        return $this->resolved ??= EducationContent::with('contentable', 'education')
            ->where('education_id', $this->attributes->get('assignment')->education_id)
            ->findOrFail($this->route('content'));
    }

    protected function prepareForValidation(): void
    {
        $this->learnContent(); // 404 before 422
    }

    public function rules(): array
    {
        return $this->learnContent()->contentable->progressRules();
    }

    public function attributes(): array
    {
        return ['position' => 'konum', 'duration' => 'süre'];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute alanı zorunludur.',
            'numeric' => ':attribute sayı olmalıdır.',
            'min' => ':attribute negatif olamaz.',
            'gt' => ':attribute sıfırdan büyük olmalıdır.',
        ];
    }
}
