<?php

return [
    // Default storage for NEW uploads. Existing records keep their own media_driver.
    'driver' => env('MEDIA_DRIVER', 'local'),

    'upload_ttl' => 3600,
    'playback_ttl' => 7200,

    // media:prune removes unattached uploads older than this many hours.
    'prune_after_hours' => 24,

    'kinds' => [
        'video' => [
            'label' => 'Video',
            'max_bytes' => 209715200, // 200 MB
            'mimes' => ['video/mp4' => 'mp4', 'video/webm' => 'webm'],
        ],
        'document' => [
            'label' => 'Doküman',
            'max_bytes' => 52428800, // 50 MB
            'mimes' => ['application/pdf' => 'pdf'],
        ],
    ],
];
