<?php

return [

    // Pangolin Integration API — used by ImportResourceCreator/NormalizeProcessor
    // (App\Services\Pangolin\PangolinApiClient) for the Import/Normalize tabs,
    // the only two Pangolin features this app has (Monitor and Logs were
    // removed entirely — see CLAUDE.md).
    'base_url' => env('PANGOLIN_API_BASE_URL'),
    'org_slug' => env('PANGOLIN_ORG_SLUG'),
    'api_key' => env('PANGOLIN_API_KEY'),

    'http_timeout' => (int) env('PANGOLIN_HTTP_TIMEOUT', 5),

];
