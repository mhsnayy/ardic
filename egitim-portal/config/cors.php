<?php

// CORS is opened ONLY for the browser's direct upload (PUT /uploads/{id}) and ONLY for the frontend origin.
return [
    'paths' => ['uploads/*'],
    'allowed_methods' => ['PUT', 'OPTIONS'],
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
