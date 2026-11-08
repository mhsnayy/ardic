<?php

namespace App\Console\Commands;

use App\Media\MediaManager;
use App\Models\MediaUpload;
use Illuminate\Console\Command;

class PruneMedia extends Command
{
    protected $signature = 'media:prune';

    protected $description = 'Delete uploads never attached to a content and older than 24 hours (files included)';

    public function handle(MediaManager $media): int
    {
        // Attaching an upload to a content deletes its row, so every remaining row is unattached.
        $cutoff = now()->subHours((int) config('media.prune_after_hours'));
        $count = 0;

        MediaUpload::where('created_at', '<', $cutoff)->lazyById()->each(function (MediaUpload $upload) use ($media, &$count) {
            $media->driver($upload->media_driver)->delete($upload->path);
            $upload->delete();
            $count++;
        });

        $this->info("$count yükleme silindi.");

        return self::SUCCESS;
    }
}
