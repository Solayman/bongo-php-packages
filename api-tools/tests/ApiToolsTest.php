<?php

declare(strict_types=1);

namespace Bongo\Packages\ApiTools\Tests;

use Bongo\Packages\ApiTools\ApiResponse;
use Bongo\Packages\ApiTools\Controllers\ApiDocsController;
use Bongo\Packages\ApiTools\Http\JsonResponse;
use Bongo\Packages\ApiTools\Middleware\ApiThrottleMiddleware;
use Bongo\Packages\ApiTools\OpenApi\OpenApiGenerator;
use Bongo\Packages\ApiTools\Resources\JsonResource;
use Bongo\Packages\ApiTools\Resources\ResourceCollection;
use System\Http\Request;
use System\Routing\RouteDefinition;
use System\Routing\Router;
use System\Testing\TestCase;

class ApiToolsTest extends TestCase
{
    public function setUp(): void
    {
        JsonResource::wrap('data');
        if (class_exists(\System\View\ViewEngine::class)) {
            \System\View\ViewEngine::addNamespace('api-tools', dirname(__DIR__) . '/views');
        }
        $this->cleanThrottleCache();
    }

    public function tearDown(): void
    {
        JsonResource::wrap('data');
        $this->cleanThrottleCache();
    }

    protected function cleanThrottleCache(): void
    {
        $cacheDir = function_exists('base_path') ? base_path('storage/cache/throttle') : sys_get_temp_dir() . '/bongo_throttle';
        foreach (glob($cacheDir . '/*.json') ?: [] as $f) {
            @unlink($f);
        }
    }

    public function testJsonResourceTransformsAttributesAndWraps(): void
    {
        $user = (object) [
            'id'    => 1,
            'name'  => 'Solayman',
            'email' => 'solayman@example.com',
        ];

        $resource = new class($user) extends JsonResource {
            public function toArray(Request $request): array
            {
                return [
                    'id'    => $this->id,
                    'name'  => $this->name,
                    'email' => $this->email,
                ];
            }
        };

        $resolved = $resource->resolve();
        $this->assertArrayHasKey('data', $resolved);
        $this->assertSame(1, $resolved['data']['id']);
        $this->assertSame('Solayman', $resolved['data']['name']);
        $this->assertSame('solayman@example.com', $resolved['data']['email']);
    }

    public function testJsonResourceConditionalWhenAttribute(): void
    {
        $userAdmin = (object) ['id' => 1, 'name' => 'Alice', 'is_admin' => true, 'secret_token' => 'xyz'];
        $userRegular = (object) ['id' => 2, 'name' => 'Bob', 'is_admin' => false, 'secret_token' => 'abc'];

        $resourceClass = new class($userAdmin) extends JsonResource {
            public function toArray(Request $request): array
            {
                return [
                    'id'     => $this->id,
                    'name'   => $this->name,
                    'secret' => $this->when($this->is_admin, $this->secret_token),
                ];
            }
        };

        // When true: attribute is present
        $resolvedAdmin = (new $resourceClass($userAdmin))->resolve();
        $this->assertSame('xyz', $resolvedAdmin['data']['secret']);

        // When false: attribute key is completely omitted
        $resolvedRegular = (new $resourceClass($userRegular))->resolve();
        $this->assertArrayHasKey('id', $resolvedRegular['data']);
        $this->assertSame(false, array_key_exists('secret', $resolvedRegular['data']));
    }

    public function testJsonResourceConditionalWhenLoadedRelationship(): void
    {
        $postWithAuthor = new class {
            public int $id = 10;
            public string $title = 'Post Title';
            public array $author = ['name' => 'John Author'];

            public function relationLoaded(string $relation): bool
            {
                return $relation === 'author';
            }
        };

        $postWithoutAuthor = new class {
            public int $id = 20;
            public string $title = 'Other Post';

            public function relationLoaded(string $relation): bool
            {
                return false;
            }
        };

        $resourceClass = new class($postWithAuthor) extends JsonResource {
            public function toArray(Request $request): array
            {
                return [
                    'id'     => $this->id,
                    'title'  => $this->title,
                    'author' => $this->whenLoaded('author'),
                ];
            }
        };

        $res1 = (new $resourceClass($postWithAuthor))->resolve();
        $this->assertSame('John Author', $res1['data']['author']['name']);

        $res2 = (new $resourceClass($postWithoutAuthor))->resolve();
        $this->assertSame(false, array_key_exists('author', $res2['data']));
    }

    public function testJsonResourceWithoutWrappingAndAdditionalMeta(): void
    {
        $user = ['id' => 5, 'name' => 'Sara'];

        JsonResource::withoutWrapping();

        $resource = JsonResource::make($user);
        $resource->additional(['version' => '1.0']);

        $resolved = $resource->resolve();
        $this->assertSame(5, $resolved['id']);
        $this->assertSame('Sara', $resolved['name']);
        $this->assertSame('1.0', $resolved['version']);
    }

    public function testResourceCollectionMapping(): void
    {
        $users = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $userResource = new class(['id' => 0]) extends JsonResource {
            public function toArray(Request $request): array
            {
                return [
                    'id'   => $this->resource['id'],
                    'name' => strtoupper($this->resource['name']),
                ];
            }
        };

        $collection = new ResourceCollection($users, get_class($userResource));
        $resolved = $collection->resolve();

        $this->assertArrayHasKey('data', $resolved);
        $this->assertCount(2, $resolved['data']);
        $this->assertSame('ALICE', $resolved['data'][0]['name']);
        $this->assertSame('BOB', $resolved['data'][1]['name']);
    }

    public function testResourceCollectionPaginationLinksAndMeta(): void
    {
        $paginator = new class {
            public function items(): array
            {
                return [['id' => 1], ['id' => 2]];
            }
            public function total(): int
            {
                return 50;
            }
            public function perPage(): int
            {
                return 10;
            }
            public function currentPage(): int
            {
                return 2;
            }
            public function lastPage(): int
            {
                return 5;
            }
            public function path(): string
            {
                return '/api/items';
            }
        };

        $collection = ResourceCollection::make($paginator);
        $resolved = $collection->resolve();

        $this->assertArrayHasKey('meta', $resolved);
        $this->assertArrayHasKey('links', $resolved);

        $this->assertSame(2, $resolved['meta']['current_page']);
        $this->assertSame(10, $resolved['meta']['per_page']);
        $this->assertSame(50, $resolved['meta']['total']);
        $this->assertSame(5, $resolved['meta']['last_page']);
        $this->assertSame(11, $resolved['meta']['from']);
        $this->assertSame(20, $resolved['meta']['to']);

        $this->assertSame('/api/items?page=1', $resolved['links']['first']);
        $this->assertSame('/api/items?page=5', $resolved['links']['last']);
        $this->assertSame('/api/items?page=1', $resolved['links']['prev']);
        $this->assertSame('/api/items?page=3', $resolved['links']['next']);
    }

    public function testApiResponseSuccessAndError(): void
    {
        // Success response
        $respSuccess = ApiResponse::success(['user_id' => 42], 'User registered', 201);
        $this->assertSame(201, $respSuccess->getStatusCode());
        $bodySuccess = json_decode($respSuccess->getContent(), true);
        $this->assertTrue($bodySuccess['success']);
        $this->assertSame('User registered', $bodySuccess['message']);
        $this->assertSame(42, $bodySuccess['data']['user_id']);

        // Error response
        $respError = ApiResponse::error('Invalid input', 422, ['email' => ['Required']], 'VALIDATION_ERR');
        $this->assertSame(422, $respError->getStatusCode());
        $bodyError = json_decode($respError->getContent(), true);
        $this->assertFalse($bodyError['success']);
        $this->assertSame('VALIDATION_ERR', $bodyError['code']);
        $this->assertSame(['Required'], $bodyError['errors']['email']);
    }

    public function testApiResponsePaginated(): void
    {
        $paginator = new class {
            public function items(): array
            {
                return [['id' => 1, 'name' => 'Item 1']];
            }
            public function total(): int
            {
                return 1;
            }
            public function perPage(): int
            {
                return 15;
            }
            public function currentPage(): int
            {
                return 1;
            }
            public function lastPage(): int
            {
                return 1;
            }
            public function path(): string
            {
                return '/api/items';
            }
        };

        $res = ApiResponse::paginated($paginator);
        $body = json_decode($res->getContent(), true);

        $this->assertTrue($body['success']);
        $this->assertCount(1, $body['data']);
        $this->assertSame(1, $body['meta']['total']);
    }

    public function testApiThrottleMiddleware(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.' . mt_rand(1, 200) . '.' . mt_rand(1, 200);
        $middleware = new ApiThrottleMiddleware();
        $req = new Request();

        $next = function (Request $request): JsonResponse {
            return new JsonResponse('OK', 200);
        };

        // First 3 requests should pass (limit: 3)
        $res1 = $middleware->handle($req, $next, 3, 1);
        $this->assertSame(200, $res1->getStatusCode());

        $res2 = $middleware->handle($req, $next, 3, 1);
        $this->assertSame(200, $res2->getStatusCode());

        $res3 = $middleware->handle($req, $next, 3, 1);
        $this->assertSame(200, $res3->getStatusCode());

        // 4th request should return 429 Too Many Requests
        $res4 = $middleware->handle($req, $next, 3, 1);
        $this->assertSame(429, $res4->getStatusCode());
        $body = json_decode($res4->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('RATE_LIMIT_EXCEEDED', $body['code']);
    }

    public function testOpenApiGeneratorProducesValidSpec(): void
    {
        $router = new Router();
        $router->get('/api/users', fn() => [])->name('users.index');
        $router->post('/api/users', fn() => [])->name('users.store');
        $router->get('/api/users/{id}', fn() => [])->name('users.show')->where('id', '\d+')->middleware('auth');

        $generator = new OpenApiGenerator($router, [
            'title'       => 'Test API',
            'version'     => '2.0.0',
            'description' => 'Test API Spec',
            'prefix'      => 'api',
        ]);

        $spec = $generator->generate();

        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertSame('Test API', $spec['info']['title']);
        $this->assertSame('2.0.0', $spec['info']['version']);

        $this->assertArrayHasKey('/api/users', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/api/users']);
        $this->assertArrayHasKey('post', $spec['paths']['/api/users']);

        $this->assertArrayHasKey('/api/users/{id}', $spec['paths']);
        $showOp = $spec['paths']['/api/users/{id}']['get'];

        // Parameter schema
        $this->assertSame('id', $showOp['parameters'][0]['name']);
        $this->assertSame('integer', $showOp['parameters'][0]['schema']['type']);

        // Security scheme attached because of auth middleware
        $this->assertArrayHasKey('security', $showOp);
        $this->assertSame('BearerAuth', array_keys($showOp['security'][0])[0]);
    }

    public function testApiDocsController(): void
    {
        $controller = new ApiDocsController();

        $jsonResp = $controller->json();
        $this->assertSame(200, $jsonResp->getStatusCode());
        $body = json_decode($jsonResp->getContent(), true);
        $this->assertSame('3.0.3', $body['openapi']);

        $swaggerHtml = $controller->swagger();
        $this->assertContains('swagger-ui', $swaggerHtml);

        $redocHtml = $controller->redoc();
        $this->assertContains('redoc', $redocHtml);
    }
}
