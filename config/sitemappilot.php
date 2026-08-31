<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SitemapPilot API Key
    |--------------------------------------------------------------------------
    |
    | Your Workspace API Key obtained from your SitemapPilot dashboard.
    |
    */

    'api_key' => env('SITEMAPPILOT_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Property ID
    |--------------------------------------------------------------------------
    |
    | The ID of the target property in SitemapPilot that corresponds to this app.
    |
    */

    'property_id' => env('SITEMAPPILOT_PROPERTY_ID'),

    /*
    |--------------------------------------------------------------------------
    | Base API URL
    |--------------------------------------------------------------------------
    |
    | The base URL of the SitemapPilot API server.
    |
    */

    'base_url' => env('SITEMAPPILOT_BASE_URL', 'https://sitemappilot.com/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The HTTP request timeout in seconds.
    |
    */

    'timeout' => (int) env('SITEMAPPILOT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Auto-Ping Queue
    |--------------------------------------------------------------------------
    |
    | The queue connection and queue name to use for background model ping jobs.
    | Leave null to use the default queue.
    |
    */

    'queue' => [
        'connection' => env('SITEMAPPILOT_QUEUE_CONNECTION', null),
        'queue' => env('SITEMAPPILOT_QUEUE_NAME', null),
    ],

];
