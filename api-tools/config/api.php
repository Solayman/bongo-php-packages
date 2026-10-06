<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Metadata & OpenAPI Specification
    |--------------------------------------------------------------------------
    |
    | Information presented in generated OpenAPI 3.0 specs and documentation.
    |
    */
    'title'       => env('API_TITLE', 'Bongo API'),
    'version'     => env('API_VERSION', '1.0.0'),
    'description' => env('API_DESCRIPTION', 'RESTful API powered by Bongo PHP Framework'),

    /*
    |--------------------------------------------------------------------------
    | Documentation UI Routes
    |--------------------------------------------------------------------------
    |
    | Interactive documentation interfaces (Swagger UI and Redoc).
    |
    */
    'docs_enabled' => env('API_DOCS_ENABLED', true),
    'docs_route'   => env('API_DOCS_ROUTE', '/api/docs'),
    'redoc_route'  => env('API_REDOC_ROUTE', '/api/redoc'),
    'json_route'   => env('API_JSON_ROUTE', '/docs/openapi.json'),

    /*
    |--------------------------------------------------------------------------
    | Global API Route Prefix & Middleware
    |--------------------------------------------------------------------------
    */
    'prefix' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Resource Wrapping
    |--------------------------------------------------------------------------
    |
    | When enabled, JsonResource responses are wrapped under a top-level 'data' key.
    |
    */
    'wrap_resources' => true,

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Defaults
    |--------------------------------------------------------------------------
    */
    'throttle' => [
        'max_attempts'  => (int) env('API_THROTTLE_MAX', 60),
        'decay_minutes' => (int) env('API_THROTTLE_DECAY', 1),
    ],
];
