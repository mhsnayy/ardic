<?php

namespace App\Http\Controllers;

use App\Media\ByteLimitFilter;
use App\Models\MediaUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Receives the raw body PUT sent directly by the browser to the signed URL (local driver). */
class UploadReceiveController extends Controller
{
    public function __invoke(Request $request, string $upload): JsonResponse|Response
    {
        $record = MediaUpload::find($upload);
        if (! $record) {
            return $this->fail('Yükleme kaydı bulunamadı.', 404);
        }
        if ($record->status !== 'pending') {
            return $this->fail('Bu dosya zaten yüklenmiş.', 409);
        }
        if ($record->expires_at->isPast()) {
            return $this->fail('Yükleme süresi dolmuş.', 410);
        }

        $contentType = strtolower(trim(explode(';', (string) $request->header('Content-Type'))[0]));
        if ($contentType !== $record->mime_type) {
            return $this->fail('Dosya türü yükleme isteğiyle uyuşmuyor.', 415);
        }

        $length = $request->header('Content-Length');
        if ($length !== null && (int) $length > $record->max_bytes) {
            return $this->fail('Dosya izin verilen boyutu aşıyor.', 413);
        }

        $lock = Cache::lock("media-upload:{$record->id}", 3600);
        if (! $lock->get()) {
            return $this->fail('Bu dosya zaten yükleniyor.', 409);
        }

        try {
            return $this->store($request, $record);
        } finally {
            $lock->release();
        }
    }

    private function store(Request $request, MediaUpload $record): JsonResponse|Response
    {
        $disk = Storage::disk('media');
        $stream = $request->getContent(true); // php://input, never loaded into memory
        $limit = ByteLimitFilter::attach($stream, $record->max_bytes);

        try {
            $written = $disk->writeStream($record->path, $stream);
        } catch (Throwable) {
            $written = false;
        }

        if ($limit->exceeded) { // regardless of what the driver reported
            $disk->delete($record->path);

            return $this->fail('Dosya izin verilen boyutu aşıyor.', 413);
        }

        if ($written === false) {
            $disk->delete($record->path); // partial file

            return $this->fail('Dosya kaydedilemedi.', 500);
        }

        $size = (int) $disk->size($record->path);
        if ($size === 0 || $size > $record->max_bytes) {
            $disk->delete($record->path);

            return $size === 0
                ? $this->fail('Dosya boş.', 422)
                : $this->fail('Dosya izin verilen boyutu aşıyor.', 413);
        }

        $record->update(['status' => 'uploaded', 'size_bytes' => $size]);

        return response()->noContent();
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
