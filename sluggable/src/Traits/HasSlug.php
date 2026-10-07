<?php

declare(strict_types=1);

namespace Bongo\Packages\Sluggable\Traits;

use Bongo\Packages\Sluggable\Slugger;
use RuntimeException;

trait HasSlug
{
    /**
     * Default sluggable configuration.
     * Can be overridden by defining sluggable() in the model.
     *
     * @return array<string, array{source: string|array, separator?: string, max_length?: int, on_update?: bool}>
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
                'separator' => function_exists('config') ? config('sluggable.separator', '-') : '-',
                'max_length' => function_exists('config') ? (int) config('sluggable.max_length', 120) : 120,
                'on_update' => function_exists('config') ? (bool) config('sluggable.on_update', false) : false,
            ],
        ];
    }

    /**
     * Automatically generate slugs before saving the model.
     */
    public function generateSlug(bool $force = false): static
    {
        $configs = $this->sluggable();

        foreach ($configs as $slugField => $options) {
            $sourceField = $options['source'] ?? 'title';
            $separator = (string) ($options['separator'] ?? (function_exists('config') ? config('sluggable.separator', '-') : '-'));
            $maxLength = (int) ($options['max_length'] ?? (function_exists('config') ? config('sluggable.max_length', 120) : 120));
            $onUpdate = (bool) ($options['on_update'] ?? (function_exists('config') ? config('sluggable.on_update', false) : false));

            $currentSlug = (string) ($this->{$slugField} ?? '');

            // Only generate if slug is empty, forced, or on_update is explicitly true
            if ($currentSlug !== '' && !$force && !$onUpdate) {
                continue;
            }

            // Resolve source string
            $sourceText = '';
            if (is_array($sourceField)) {
                $parts = [];
                foreach ($sourceField as $field) {
                    $parts[] = (string) ($this->{$field} ?? '');
                }
                $sourceText = implode(' ', array_filter($parts));
            } else {
                $sourceText = (string) ($this->{$sourceField} ?? '');
            }

            if (trim($sourceText) === '') {
                continue;
            }

            $baseSlug = Slugger::slugify($sourceText, $separator, $maxLength);

            $reserved = function_exists('config') ? (array) config('sluggable.reserved', []) : [];

            // One query for every taken "base" / "base-N" slug, then pick the first free one in memory
            // (instead of one EXISTS query per candidate: the 50th "iPhone case" used to cost 50 queries)
            $query = static::query()->where(function ($q) use ($slugField, $baseSlug, $separator) {
                $q->where($slugField, $baseSlug)->orWhere($slugField, 'LIKE', $baseSlug . $separator . '%');
            });
            $id = $this->id ?? null;
            if ($id !== null) {
                $query->where('id', '!=', $id);
            }
            $taken = array_flip(array_map('strval', $query->pluck($slugField)));
            $existsChecker = fn(string $testSlug): bool => isset($taken[$testSlug]);

            $uniqueSlug = Slugger::makeUnique($baseSlug, $existsChecker, $separator, $reserved);
            $this->{$slugField} = $uniqueSlug;
        }

        return $this;
    }

    /**
     * Scope a query to find a record by slug.
     */
    public function scopeWhereSlug($query, string $slug, string $slugField = 'slug')
    {
        return $query->where($slugField, $slug);
    }

    /**
     * Find a model by its slug.
     */
    public static function findBySlug(string $slug, string $slugField = 'slug'): ?static
    {
        return static::where($slugField, $slug)->first();
    }

    /**
     * Find a model by its slug or throw an exception.
     */
    public static function findBySlugOrFail(string $slug, string $slugField = 'slug'): static
    {
        $model = static::findBySlug($slug, $slugField);
        if (!$model) {
            throw new RuntimeException("Model [" . static::class . "] not found for slug [{$slug}].");
        }
        return $model;
    }
}
