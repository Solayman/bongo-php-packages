<?php

declare(strict_types=1);

namespace Bongo\Packages\ApiTools\Commands;

use Bongo\Packages\ApiTools\OpenApi\OpenApiGenerator;
use System\Console\Command;
use System\Routing\Router;

class ApiDocsCommand extends Command
{
    protected string $name = 'api:docs';
    protected string $description = 'Generate OpenAPI 3.0 documentation JSON specification';

    public function handle(array $args, array $options): int
    {
        $this->info("Generating OpenAPI 3.0 specification...");

        $router = Router::current() ?? (class_exists(Router::class) && method_exists(Router::class, 'fromConfig') ? Router::fromConfig() : null);
        $generator = new OpenApiGenerator($router);
        $spec = $generator->generate();

        $pathCount = count($spec['paths'] ?? []);
        $totalEndpoints = 0;
        foreach ($spec['paths'] ?? [] as $methods) {
            $totalEndpoints += count($methods);
        }

        $defaultRoute = function_exists('config') ? config('api.json_route', '/docs/openapi.json') : '/docs/openapi.json';
        $defaultPath = function_exists('base_path') ? base_path('public' . $defaultRoute) : 'public' . $defaultRoute;
        $out = $options['out'] ?? $options['o'] ?? $defaultPath;

        if ($generator->saveToFile((string) $out)) {
            $this->info("OpenAPI documentation successfully written to: {$out}");
            $this->line("  Paths:     {$pathCount}");
            $this->line("  Endpoints: {$totalEndpoints}");
            $this->line("  Version:   " . ($spec['info']['version'] ?? '1.0.0'));
            return 0;
        }

        $this->error("Failed to write OpenAPI specification to: {$out}");
        return 1;
    }
}
