<?php

declare(strict_types=1);

namespace Bongo\Packages\ApiTools\Resources;

use Bongo\Packages\ApiTools\Http\JsonResponse;
use JsonSerializable;
use System\Http\Request;

class JsonResource implements JsonSerializable
{
    public mixed $resource;

    /**
     * Top-level wrapper key (default: 'data', null to disable).
     */
    public static ?string $wrap = 'data';

    /**
     * Additional top-level metadata.
     */
    public array $additional = [];

    public function __construct(mixed $resource)
    {
        $this->resource = $resource;
    }

    public static function make(mixed $resource): static
    {
        return new static($resource);
    }

    public static function collection(iterable $resource): ResourceCollection
    {
        return new ResourceCollection($resource, static::class);
    }

    public static function wrap(?string $value): void
    {
        static::$wrap = $value;
    }

    public static function withoutWrapping(): void
    {
        static::$wrap = null;
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return [];
        }

        if (is_array($this->resource)) {
            return $this->resource;
        }

        if (is_object($this->resource) && method_exists($this->resource, 'toArray')) {
            return $this->resource->toArray();
        }

        return (array) $this->resource;
    }

    /**
     * Include an attribute conditionally.
     */
    public function when(bool|callable $condition, mixed $value, mixed $default = null): mixed
    {
        $isTrue = is_callable($condition) ? (bool) $condition() : (bool) $condition;

        if ($isTrue) {
            return is_callable($value) ? $value() : $value;
        }

        if ($default !== null) {
            return is_callable($default) ? $default() : $default;
        }

        return new MissingValue();
    }

    /**
     * Include a relation conditionally only if it has already been loaded.
     */
    public function whenLoaded(string $relationship, mixed $value = null, mixed $default = null): mixed
    {
        $loaded = false;

        if (is_object($this->resource)) {
            if (method_exists($this->resource, 'relationLoaded')) {
                $loaded = $this->resource->relationLoaded($relationship);
            } elseif (isset($this->resource->$relationship)) {
                $loaded = true;
            }
        }

        if ($loaded) {
            if ($value !== null) {
                return is_callable($value) ? $value() : $value;
            }
            return $this->resource->$relationship ?? null;
        }

        if ($default !== null) {
            return is_callable($default) ? $default() : $default;
        }

        return new MissingValue();
    }

    /**
     * Add additional top-level metadata to the response.
     */
    public function additional(array $data): static
    {
        $this->additional = array_merge($this->additional, $data);
        return $this;
    }

    /**
     * Get any additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [];
    }

    /**
     * Resolve the resource to an array, filtering out MissingValue instances.
     */
    public function resolve(?Request $request = null): array
    {
        $req = $request ?? (class_exists(Request::class) ? new Request() : null);
        $data = $this->toArray($req ?? new class extends Request {});
        $data = $this->filter($data);

        $with = $req ? $this->with($req) : [];
        $extra = array_merge($with, $this->additional);

        if (static::$wrap !== null) {
            return array_merge([static::$wrap => $data], $extra);
        }

        return array_merge($data, $extra);
    }

    /**
     * Recursively remove MissingValue markers from the array.
     */
    protected function filter(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $result = [];
        $isSequential = array_is_list($data);

        foreach ($data as $key => $value) {
            if ($value instanceof MissingValue) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->filter($value);
            } elseif ($value instanceof self) {
                $value = $value->resolve();
            }

            if ($isSequential) {
                $result[] = $value;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function jsonSerialize(): mixed
    {
        return $this->resolve();
    }

    public function toJson(int $options = 0): string
    {
        return (string) json_encode($this->jsonSerialize(), $options | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Transform the resource into an HTTP Response.
     */
    public function toResponse(int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($this->jsonSerialize(), $status, $headers);
    }

    public function __get(string $key): mixed
    {
        if (is_object($this->resource)) {
            return $this->resource->$key ?? null;
        }
        if (is_array($this->resource)) {
            return $this->resource[$key] ?? null;
        }
        return null;
    }

    public function __isset(string $key): bool
    {
        if (is_object($this->resource)) {
            return isset($this->resource->$key);
        }
        if (is_array($this->resource)) {
            return isset($this->resource[$key]);
        }
        return false;
    }

    public function __call(string $method, array $parameters): mixed
    {
        if (is_object($this->resource) && method_exists($this->resource, $method)) {
            return $this->resource->$method(...$parameters);
        }
        return null;
    }
}
