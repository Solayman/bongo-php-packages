<?php

declare(strict_types=1);

namespace Bongo\Packages\ApiTools\Controllers;

use Bongo\Packages\ApiTools\Http\JsonResponse;
use Bongo\Packages\ApiTools\OpenApi\OpenApiGenerator;

class ApiDocsController
{
    /**
     * Return the generated OpenAPI 3.0 specification as JSON.
     */
    public function json(): JsonResponse
    {
        $file = function_exists('base_path') ? base_path('public/docs/openapi.json') : 'public/docs/openapi.json';
        if (is_file($file)) {
            $content = (string) @file_get_contents($file);
            $spec = json_decode($content, true);
            if (is_array($spec)) {
                return new JsonResponse($spec, 200, [
                    'Access-Control-Allow-Origin'  => '*',
                    'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                ]);
            }
        }

        $generator = new OpenApiGenerator();
        $spec = $generator->generate();

        return new JsonResponse($spec, 200, [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        ]);
    }

    /**
     * Render the interactive Swagger UI.
     */
    public function swagger(): string
    {
        $jsonRoute = function_exists('config') ? config('api.json_route', '/docs/openapi.json') : '/docs/openapi.json';
        $title = function_exists('config') ? config('api.title', 'Bongo API Documentation') : 'Bongo API Documentation';

        return function_exists('view')
            ? view('api-tools::swagger', ['jsonRoute' => $jsonRoute, 'title' => $title])
            : $this->renderSwaggerHtml($jsonRoute, $title);
    }

    /**
     * Render the Redoc documentation UI.
     */
    public function redoc(): string
    {
        $jsonRoute = function_exists('config') ? config('api.json_route', '/docs/openapi.json') : '/docs/openapi.json';
        $title = function_exists('config') ? config('api.title', 'Bongo API Documentation') : 'Bongo API Documentation';

        return function_exists('view')
            ? view('api-tools::redoc', ['jsonRoute' => $jsonRoute, 'title' => $title])
            : $this->renderRedocHtml($jsonRoute, $title);
    }

    protected function renderSwaggerHtml(string $jsonRoute, string $title): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedRoute = htmlspecialchars($jsonRoute, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$escapedTitle} - Swagger UI</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; padding: 0; background: #fafafa; }
        .topbar { display: none; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.onload = function() {
            SwaggerUIBundle({
                url: "{$escapedRoute}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                layout: "BaseLayout"
            });
        };
    </script>
</body>
</html>
HTML;
    }

    protected function renderRedocHtml(string $jsonRoute, string $title): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedRoute = htmlspecialchars($jsonRoute, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$escapedTitle} - Redoc</title>
    <style>body { margin: 0; padding: 0; }</style>
</head>
<body>
    <redoc spec-url="{$escapedRoute}"></redoc>
    <script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
</body>
</html>
HTML;
    }
}
