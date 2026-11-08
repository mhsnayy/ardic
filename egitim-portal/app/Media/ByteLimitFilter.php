<?php

namespace App\Media;

use php_user_filter;
use stdClass;

/** Read filter that aborts the stream once more than $params->max bytes have passed through. */
final class ByteLimitFilter extends php_user_filter
{
    private int $seen = 0;

    /** @param resource $stream */
    public static function attach($stream, int $max): stdClass
    {
        if (! in_array('media.bytelimit', stream_get_filters(), true)) {
            stream_filter_register('media.bytelimit', self::class);
        }
        $state = new stdClass;
        $state->max = $max;
        $state->exceeded = false;
        stream_filter_append($stream, 'media.bytelimit', STREAM_FILTER_READ, $state);

        return $state;
    }

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $this->seen += $bucket->datalen;
            if ($this->seen > $this->params->max) {
                $this->params->exceeded = true;

                return PSFS_ERR_FATAL;
            }
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
