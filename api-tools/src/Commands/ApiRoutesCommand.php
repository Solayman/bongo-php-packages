<?php

declare(strict_types=1);

namespace Bongo\Packages\ApiTools\Commands;

use System\Console\Command;
use System\Routing\Router;

class ApiRoutesCommand extends Command
{
    protected string $name = 'api:routes';
    protected string $description = 'List all registered API routes and their details';

    public function handle(array $args, array $options): int
    {
        $router = Router::current() ?? (class_exists(Router::class) && method_exists(Router::class, 'fromConfig') ? Router::fromConfig() : null);

        if (!$router) {
            $this->error("Unable to load Router instance.");
            return 1;
        }

        $routes = $router->routes();
        $apiPrefix = function_exists('config') ? (string) config('api.prefix', 'api') : 'api';
        $prefixMatch = '/' . ltrim($apiPrefix, '/');

        $apiRoutes = [];
        foreach ($routes as $route) {
            if ($prefixMatch === '/' || str_starts_with($route->path, $prefixMatch)) {
                $apiRoutes[] = $route;
            }
        }

        if (empty($apiRoutes)) {
            $this->warn("No API routes found matching prefix [{$prefixMatch}].");
            return 0;
        }

        $this->info("Found " . count($apiRoutes) . " API route(s):");
        $this->line(str_repeat('-', 95));
        $this->line(sprintf("%-10s %-35s %-32s %-15s", "METHOD", "URI", "ACTION", "NAME"));
        $this->line(str_repeat('-', 95));

        foreach ($apiRoutes as $route) {
            $methods = implode('|', array_filter($route->methods, fn($m) => !in_array($m, ['HEAD', 'OPTIONS'], true)));
            $action = 'Closure';
            if (is_array($route->action)) {
                $ctrl = basename(str_replace('\\', '/', (string) ($route->action[0] ?? '')));
                $method = (string) ($route->action[1] ?? '');
                $action = "{$ctrl}@{$method}";
            }
            $name = (string) ($route->name ?? '-');

            $this->line(sprintf(
                "%-10s %-35s %-32s %-15s",
                $methods,
                $route->path,
                substr($action, 0, 31),
                substr($name, 0, 14)
            ));
        }

        $this->line(str_repeat('-', 95));
        return 0;
    }
}
