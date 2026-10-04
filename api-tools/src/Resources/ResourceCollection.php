<?php

declare(strict_types=1);

namespace Bongo\Packages\ApiTools\Resources;

use ArrayIterator;
use Bongo\Packages\ApiTools\Http\JsonResponse;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use System\Http\Request;
use Traversable;

class ResourceCollection implements JsonSerializable, Countable, IteratorAggregate
{
    public mixed $resource;
    public ?string $collects = null;
    public array $additional = [];
    public static ?string $wrap = 'data';

    public function __construct(mixed $resource, ?string $collects = null)
    {
        $this->resource = $resource;
        $this->collects = $collects;
    }

    public static function make(mixed $resource, ?string $collects = null): static
    {
        return new static($resource, $collects);
    }

    public function additional(array $data): static
    {
        $this->additional = array_merge($this->additional, $data);
        return $this;
    }

    /**
     * Resolve the items in the collection into an array of transformed resources.
     */
    public function toArray(Request $request): array
    {
        $items = $this->extractItems();
        $collects = $this->collects;

        $result = [];
        foreach ($items as $item) {
            if ($collects !== null && class_exists($collects)) {
                $resourceInstance = new $collects($item);
                $result[] = $resourceInstance->toArray($request);
            } elseif ($item instanceof JsonResource) {
                $result[] = $item->toArray($request);
            } elseif (is_object($item) && method_exists($item, 'toArray')) {
                $result[] = $item->toArray();
            } else {
                $result[] = (array) $item;
            }
        }

        return $result;
    }

    public function resolve(?Request $request = null): array
    {
        $req = $request ?? (class_exists(Request::class) ? new Request() : null);
        $data = $this->toArray($req ?? new class extends Request {});

        $response = [];

        if (static::$wrap !== null) {
            $response[static::$wrap] = $data;
        } else {
            $response = $data;
        }

        // Attach pagination metadata if resource is paginated
        if ($this->isPaginated()) {
            $response['meta'] = $this->getPaginationMeta();
            $response['links'] = $this->getPaginationLinks();
        }

        if (!empty($this->additional)) {
            $response = array_merge($response, $this->additional);
        }

        return $response;
    }

    public function jsonSerialize(): mixed
    {
        return $this->resolve();
    }

    public function toJson(int $options = 0): string
    {
        return (string) json_encode($this->jsonSerialize(), $options | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function toResponse(int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($this->jsonSerialize(), $status, $headers);
    }

    public function count(): int
    {
        return count($this->extractItems());
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->extractItems());
    }

    protected function isPaginated(): bool
    {
        if (!is_object($this->resource)) {
            return false;
        }
        return method_exists($this->resource, 'total') && method_exists($this->resource, 'currentPage');
    }

    protected function extractItems(): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        if (is_object($this->resource)) {
            if (method_exists($this->resource, 'items')) {
                return (array) $this->resource->items();
            }
            if ($this->resource instanceof Traversable) {
                return iterator_to_array($this->resource);
            }
            if (method_exists($this->resource, 'toArray')) {
                return (array) $this->resource->toArray();
            }
        }

        return (array) $this->resource;
    }

    protected function getPaginationMeta(): array
    {
        $p = $this->resource;
        $currentPage = (int) (method_exists($p, 'currentPage') ? $p->currentPage() : 1);
        $perPage = (int) (method_exists($p, 'perPage') ? $p->perPage() : count($this->extractItems()));
        $total = (int) (method_exists($p, 'total') ? $p->total() : count($this->extractItems()));
        $lastPage = (int) (method_exists($p, 'lastPage') ? $p->lastPage() : ($perPage > 0 ? (int) ceil($total / $perPage) : 1));

        $from = $total > 0 ? (($currentPage - 1) * $perPage) + 1 : null;
        $to = $total > 0 ? min($currentPage * $perPage, $total) : null;

        return [
            'current_page' => $currentPage,
            'from'         => $from,
            'last_page'    => $lastPage,
            'per_page'     => $perPage,
            'to'           => $to,
            'total'        => $total,
        ];
    }

    protected function getPaginationLinks(): array
    {
        $p = $this->resource;
        $currentPage = (int) (method_exists($p, 'currentPage') ? $p->currentPage() : 1);
        $perPage = (int) (method_exists($p, 'perPage') ? $p->perPage() : 15);
        $total = (int) (method_exists($p, 'total') ? $p->total() : 0);
        $lastPage = (int) (method_exists($p, 'lastPage') ? $p->lastPage() : ($perPage > 0 ? (int) ceil($total / $perPage) : 1));

        $path = '';
        if (method_exists($p, 'path')) {
            $path = (string) $p->path();
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $path = strtok($_SERVER['REQUEST_URI'], '?') ?: '';
        }

        $buildUrl = function (int $page) use ($path): string {
            return $path !== '' ? "{$path}?page={$page}" : "?page={$page}";
        };

        return [
            'first' => $buildUrl(1),
            'last'  => $buildUrl($lastPage),
            'prev'  => $currentPage > 1 ? $buildUrl($currentPage - 1) : null,
            'next'  => $currentPage < $lastPage ? $buildUrl($currentPage + 1) : null,
        ];
    }
}
