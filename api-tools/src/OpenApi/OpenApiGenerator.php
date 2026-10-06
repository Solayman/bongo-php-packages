<?php

declare(strict_types=1);

namespace Bongo\Packages\ApiTools\OpenApi;

use ReflectionClass;
use ReflectionMethod;
use System\Routing\RouteDefinition;
use System\Routing\Router;

class OpenApiGenerator
{
    protected ?Router $router;
    protected array $config;
    protected array $customSpec = [];

    public function __construct(?Router $router = null, array $config = [])
    {
        $this->router = $router ?? Router::current();
        $this->config = array_merge([
            'title'       => function_exists('config') ? config('api.title', 'Bongo API') : 'Bongo API',
            'version'     => function_exists('config') ? config('api.version', '1.0.0') : '1.0.0',
            'description' => function_exists('config') ? config('api.description', 'RESTful API powered by Bongo PHP Framework') : 'RESTful API powered by Bongo PHP Framework',
            'prefix'      => function_exists('config') ? config('api.prefix', 'api') : 'api',
            'base_url'    => function_exists('env') ? env('APP_URL', 'http://localhost:8000') : 'http://localhost:8000',
        ], $config);

        if (function_exists('config') && is_array(config('api.custom_spec'))) {
            $this->customSpec = config('api.custom_spec');
        }
    }

    /**
     * Merge custom OpenAPI specification array or overrides into the generated spec.
     */
    public function merge(array $customSpec): self
    {
        $this->customSpec = array_replace_recursive($this->customSpec, $customSpec);
        return $this;
    }

    /**
     * Add or update an individual path operation in the custom specification.
     */
    public function addPath(string $path, string $method, array $operation): self
    {
        $verb = strtolower($method);
        if (!isset($this->customSpec['paths'][$path])) {
            $this->customSpec['paths'][$path] = [];
        }
        $this->customSpec['paths'][$path][$verb] = $operation;
        return $this;
    }


    /**
     * Generate the OpenAPI 3.0.3 specification array.
     */
    public function generate(): array
    {
        $spec = [
            'openapi' => '3.0.3',
            'info'    => [
                'title'       => (string) $this->config['title'],
                'version'     => (string) $this->config['version'],
                'description' => (string) $this->config['description'],
            ],
            'servers' => [
                [
                    'url'         => (string) $this->config['base_url'],
                    'description' => 'API Server',
                ],
            ],
            'paths'      => [],
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type'         => 'http',
                        'scheme'       => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description'  => 'Enter JWT Bearer token',
                    ],
                    'ApiKeyAuth' => [
                        'type'        => 'apiKey',
                        'in'          => 'header',
                        'name'        => 'X-API-KEY',
                        'description' => 'API Key authentication',
                    ],
                ],
            ],
        ];

        $routes = $this->router ? $this->router->routes() : [];
        $apiPrefix = '/' . ltrim((string) $this->config['prefix'], '/');

        foreach ($routes as $route) {
            $path = $route->path;

            // Only document routes starting with API prefix, or all if prefix is empty
            if ($apiPrefix !== '/' && !str_starts_with($path, $apiPrefix)) {
                continue;
            }

            // Exclude internal API doc endpoints from the spec
            if (str_ends_with($path, '/docs') || str_ends_with($path, '/redoc') || str_ends_with($path, '/openapi.json')) {
                continue;
            }

            $parameters = $this->extractPathParameters($path, $route);

            foreach ($route->methods as $method) {
                $m = strtoupper($method);
                if (in_array($m, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $verb = strtolower($m);
                $operation = $this->buildOperation($route, $m, $parameters);

                if (!isset($spec['paths'][$path])) {
                    $spec['paths'][$path] = [];
                }

                $spec['paths'][$path][$verb] = $operation;
        }
        }

        if (!empty($this->customSpec)) {
            $spec = array_replace_recursive($spec, $this->customSpec);
        }

        return $spec;
    }

    /**
     * Build OpenAPI operation item for a given route and HTTP verb.
     */
    protected function buildOperation(RouteDefinition $route, string $verb, array $parameters): array
    {
        $summary = $route->name ?? "{$verb} {$route->path}";
        $description = '';
        $tags = [];

        // Derive tags and docblock details from controller action
        if (is_array($route->action) && isset($route->action[0], $route->action[1])) {
            $controllerClass = $route->action[0];
            $methodName = $route->action[1];

            $className = is_object($controllerClass) ? get_class($controllerClass) : (string) $controllerClass;
            $tag = basename(str_replace('\\', '/', $className));
            $tag = preg_replace('/Controller$/', '', $tag) ?: 'Default';
            if (!str_contains($tag, '@anonymous') && !str_starts_with($tag, 'class@')) {
                $tags[] = $tag;
            }

            if (is_object($controllerClass) || class_exists($className)) {
                try {
                    $refMethod = new ReflectionMethod($controllerClass, $methodName);
                    $docComment = $refMethod->getDocComment();
                    if ($docComment) {
                        $parsedDoc = $this->parseDocblock($docComment);
                        if (!empty($parsedDoc['summary'])) {
                            $summary = $parsedDoc['summary'];
                        }
                        if (!empty($parsedDoc['description'])) {
                            $description = $parsedDoc['description'];
                        }
                        if (!empty($parsedDoc['tags'])) {
                            $tags = array_merge($tags, $parsedDoc['tags']);
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore reflection failures
                }
            }
        } else {
            $tags[] = 'General';
        }

        $operation = [
            'summary'     => $summary,
            'description' => $description,
            'tags'        => array_values(array_unique($tags)),
            'responses'   => $this->buildDefaultResponses($verb, $parameters),
        ];

        if (!empty($parameters)) {
            $operation['parameters'] = $parameters;
        }

        // Add request body for POST / PUT / PATCH
        if (in_array($verb, ['POST', 'PUT', 'PATCH'], true)) {
            $operation['requestBody'] = [
                'required' => true,
                'content'  => [
                    'application/json' => [
                        'schema' => [
                            'type'       => 'object',
                            'properties' => (object) [],
                        ],
                    ],
                ],
            ];
        }

        // Check if route requires security
        if ($this->routeRequiresAuth($route)) {
            $operation['security'] = [
                ['BearerAuth' => []],
            ];
        }

        return $operation;
    }

    protected function extractPathParameters(string $path, RouteDefinition $route): array
    {
        $parameters = [];
        if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $path, $matches)) {
            foreach ($matches[1] as $paramName) {
                $schema = ['type' => 'string'];
                if (isset($route->wheres[$paramName])) {
                    $pattern = $route->wheres[$paramName];
                    if ($pattern === '\d+' || $pattern === '[0-9]+') {
                        $schema = ['type' => 'integer'];
                    } else {
                        $schema['pattern'] = $pattern;
                    }
                } elseif ($paramName === 'id' || str_ends_with($paramName, '_id')) {
                    $schema = ['type' => 'integer'];
                }

                $parameters[] = [
                    'name'        => $paramName,
                    'in'          => 'path',
                    'required'    => true,
                    'description' => "Path parameter {$paramName}",
                    'schema'      => $schema,
                ];
            }
        }
        return $parameters;
    }

    protected function buildDefaultResponses(string $verb, array $parameters): array
    {
        $statusCode = $verb === 'POST' ? '201' : '200';
        $responses = [
            $statusCode => [
                'description' => 'Successful operation',
                'content'     => [
                    'application/json' => [
                        'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'success' => ['type' => 'boolean', 'example' => true],
                                'message' => ['type' => 'string', 'example' => 'Success'],
                                'data'    => ['type' => 'object'],
                            ],
                        ],
                    ],
                ],
            ],
            '400' => [
                'description' => 'Validation error or invalid request',
            ],
            '401' => [
                'description' => 'Unauthenticated / Invalid Bearer token',
            ],
        ];

        if (!empty($parameters)) {
            $responses['404'] = [
                'description' => 'Resource not found',
            ];
        }

        $responses['429'] = [
            'description' => 'Rate limit exceeded',
        ];

        return $responses;
    }

    protected function routeRequiresAuth(RouteDefinition $route): bool
    {
        foreach ($route->middleware as $m) {
            if ($m === 'auth' || str_starts_with($m, 'auth:') || $m === 'jwt' || str_starts_with($m, 'role:') || str_starts_with($m, 'permission:')) {
                return true;
            }
        }
        return false;
    }

    protected function parseDocblock(string $docblock): array
    {
        $lines = explode("\n", $docblock);
        $cleanLines = [];
        $summary = '';
        $descriptionLines = [];
        $tags = [];

        foreach ($lines as $line) {
            $line = trim($line, "/* \t\r");
            if ($line === '') {
                continue;
            }

            if (preg_match('/^@summary\s+(.+)$/i', $line, $matches)) {
                $summary = trim($matches[1]);
            } elseif (preg_match('/^@description\s+(.+)$/i', $line, $matches)) {
                $descriptionLines[] = trim($matches[1]);
            } elseif (preg_match('/^@tags?\s+(.+)$/i', $line, $matches)) {
                $parsedTags = array_map('trim', explode(',', $matches[1]));
                $tags = array_merge($tags, array_filter($parsedTags));
            } elseif (!str_starts_with($line, '@')) {
                $cleanLines[] = $line;
            }
        }

        if ($summary === '' && !empty($cleanLines[0])) {
            $summary = $cleanLines[0];
            $cleanLines = array_slice($cleanLines, 1);
        }

        if (empty($descriptionLines) && !empty($cleanLines)) {
            $descriptionLines = $cleanLines;
        }

        return [
            'summary'     => $summary,
            'description' => implode(' ', $descriptionLines),
            'tags'        => array_values(array_unique($tags)),
        ];
    }

    /**
     * Load an existing OpenAPI specification from a JSON file.
     */
    public static function fromFile(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): string
    {
        return (string) json_encode($this->generate(), $flags);
    }

    public function saveToFile(string $path): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return file_put_contents($path, $this->toJson()) !== false;
    }
}
