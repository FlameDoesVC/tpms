<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Published to replace the framework default, which allowed any origin
    | ('*') on every api/* response. Credentials were never permitted, so
    | authenticated data did not leak - but the policy was far broader than
    | anything this application needs. The SPA is served from the same origin
    | as the API, so no cross-origin access is required at all.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE'],

    'allowed_origins' => [env('APP_URL', 'http://localhost')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Session cookies are same-origin only. Enabling this would require pinning
    // allowed_origins to an exact host, never a wildcard.
    'supports_credentials' => false,

];
