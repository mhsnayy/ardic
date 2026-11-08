<?php

namespace App\Services\Content;

use App\Contracts\ContentType;
use App\Media\MediaManager;
use App\Models\Education;
use App\Models\MediaUpload;
use Illuminate\Validation\ValidationException;

/** Shared logic for file-backed kinds: consumes a finished upload (upload_id). */
abstract class MediaContentCreator implements ContentCreator
{
    public function __construct(private MediaManager $media) {}

    /** @return 'video'|'document' */
    abstract protected function kind(): string;

    /** @param array<string, mixed> $attributes
     * @param  array<string, mixed>  $data  validated request data */
    abstract protected function build(array $attributes, array $data): ContentType;

    public function rules(Education $education): array
    {
        return [
            'upload_id' => ['bail', 'required', 'uuid', function ($attribute, $value, $fail) use ($education) {
                $upload = MediaUpload::where('id', $value)
                    ->where('education_id', $education->id)
                    ->where('kind', $this->kind())
                    ->first();

                if (! $upload) {
                    $fail('Yükleme bulunamadı, bu eğitime/türe ait değil ya da daha önce kullanılmış.');
                } elseif ($upload->status !== 'uploaded') {
                    $fail('Dosya henüz yüklenmedi.');
                } elseif (! $this->media->driver($upload->media_driver)->exists($upload->path)) {
                    $fail('Yüklenen dosya bulunamadı.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'upload_id.required' => 'Yükleme kimliği zorunludur.',
            'upload_id.uuid' => 'Geçersiz yükleme kimliği.',
        ];
    }

    public function create(array $data, Education $education): ContentType
    {
        $upload = MediaUpload::whereKey($data['upload_id'])
            ->where('education_id', $education->id)
            ->where('status', 'uploaded')
            ->lockForUpdate()
            ->first();

        if (! $upload) { // used concurrently by another request
            throw ValidationException::withMessages(['upload_id' => 'Yükleme daha önce kullanılmış.']);
        }

        $contentable = $this->build([
            'media_driver' => $upload->media_driver,
            'media_path' => $upload->path,
            'mime_type' => $upload->mime_type,
            'size_bytes' => $upload->size_bytes,
        ], $data);

        // Attached: drop the upload row so media:prune never touches this file.
        $upload->delete();

        return $contentable;
    }
}
