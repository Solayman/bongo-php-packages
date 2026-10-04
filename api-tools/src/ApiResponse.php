<?php

declare(strict_types=1);

namespace Bongo\Packages\ApiTools;

use Bongo\Packages\ApiTools\Http\JsonResponse;
use Bongo\Packages\ApiTools\Resources\JsonResource;
use Bongo\Packages\ApiTools\Resources\ResourceCollection;

class ApiResponse
{
    /**
     * Return a standardized successful JSON API response.
     */
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200, array $meta = [], array $headers = []): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
                $resolved = $data->resolve();
                if (isset($resolved['data'])) {
                    $payload['data'] = $resolved['data'];
                    foreach ($resolved as $k => $v) {
                        if ($k !== 'data') {
                            $payload[$k] = $v;
                        }
                    }
                } else {
                    $payload['data'] = $resolved;
                }
            } else {
                $payload['data'] = $data;
            }
        }

        if (!empty($meta)) {
            $payload['meta'] = array_merge($payload['meta'] ?? [], $meta);
        }

        return self::json($payload, $status, $headers);
    }

    /**
     * Return a standardized error JSON API response.
     */
    public static function error(string $message = 'Error', int $status = 400, array $errors = [], ?string $code = null, array $headers = []): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($code !== null) {
            $payload['code'] = $code;
        }

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        return self::json($payload, $status, $headers);
    }

    /**
     * Return a paginated JSON API response.
     */
    public static function paginated(mixed $paginator, ?string $resourceClass = null, string $message = 'Success', array $headers = []): JsonResponse
    {
        $collection = new ResourceCollection($paginator, $resourceClass);
        $resolved = $collection->resolve();

        $payload = [
            'success' => true,
            'message' => $message,
            'data'    => $resolved['data'] ?? [],
            'meta'    => $resolved['meta'] ?? [],
            'links'   => $resolved['links'] ?? [],
        ];

        return self::json($payload, 200, $headers);
    }

    /**
     * Return a 204 No Content response.
     */
    public static function noContent(int $status = 204, array $headers = []): JsonResponse
    {
        return new JsonResponse('', $status, $headers);
    }

    /**
     * Output raw JSON response with appropriate headers.
     */
    public static function json(array $payload, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($payload, $status, $headers);
    }
}
