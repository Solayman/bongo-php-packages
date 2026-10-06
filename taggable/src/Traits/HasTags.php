<?php

declare(strict_types=1);

namespace Bongo\Packages\Taggable\Traits;

use Bongo\Packages\Taggable\Models\Tag;
use Bongo\Packages\Taggable\Models\Taggable;
use System\Database\Model;
use System\Database\QueryBuilder;

trait HasTags
{
    /**
     * Get all tags associated with this model.
     *
     * @return Tag[]
     */
    public function tags(?string $type = null): array
    {
        $taggables = Taggable::query()
            ->where('taggable_type', static::class)
            ->where('taggable_id', (string) $this->getKey())
            ->get();

        $tagIds = array_filter(array_map(fn($t) => (int) $t->tag_id, $taggables));
        if (empty($tagIds)) {
            return [];
        }

        $query = Tag::query()->whereIn('id', $tagIds);
        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->orderBy('name', 'ASC')->get();
    }

    /**
     * Get an array of tag names attached to this model.
     *
     * @return string[]
     */
    public function tagNames(?string $type = null): array
    {
        return array_map(fn(Tag $t) => $t->name, $this->tags($type));
    }

    /**
     * Get an array of tag slugs attached to this model.
     *
     * @return string[]
     */
    public function tagSlugs(?string $type = null): array
    {
        return array_map(fn(Tag $t) => $t->slug, $this->tags($type));
    }

    /**
     * Get tag names formatted as a delimited string.
     */
    public function tagsAsString(?string $type = null, string $separator = ', '): string
    {
        return implode($separator, $this->tagNames($type));
    }

    /**
     * Determine if this model has the specified tag (by name or slug).
     */
    public function hasTag(string|Tag $tag, ?string $type = null): bool
    {
        $targetSlug = $tag instanceof Tag ? $tag->slug : Tag::slugify($tag);
        $targetName = $tag instanceof Tag ? $tag->name : trim(strip_tags($tag));

        foreach ($this->tags($type) as $t) {
            if ($t->slug === $targetSlug || strcasecmp($t->name, $targetName) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if this model has any of the specified tags.
     *
     * @param string[]|Tag[] $tags
     */
    public function hasAnyTag(array $tags, ?string $type = null): bool
    {
        foreach ($tags as $tag) {
            if ($this->hasTag($tag, $type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine if this model has all of the specified tags.
     *
     * @param string[]|Tag[] $tags
     */
    public function hasAllTags(array $tags, ?string $type = null): bool
    {
        if (empty($tags)) {
            return false;
        }

        foreach ($tags as $tag) {
            if (!$this->hasTag($tag, $type)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Attach one or multiple tags to this model.
     *
     * @param string|string[]|Tag|Tag[] $tags
     */
    public function tag(string|array|Tag $tags, ?string $type = null): static
    {
        $tagList = $this->normalizeTagsInput($tags);

        foreach ($tagList as $tagItem) {
            $tag = $tagItem instanceof Tag ? $tagItem : Tag::findOrCreate($tagItem, $type);

            $existing = Taggable::query()
                ->where('tag_id', $tag->id)
                ->where('taggable_type', static::class)
                ->where('taggable_id', (string) $this->getKey())
                ->first();

            if (!$existing) {
                $pivot = new Taggable([
                    'tag_id' => $tag->id,
                    'taggable_type' => static::class,
                    'taggable_id' => (string) $this->getKey(),
                ]);
                $pivot->save();

                // Increment usage counter
                $tag->count = ((int) $tag->count) + 1;
                $tag->save();
            }
        }

        return $this;
    }

    /**
     * Detach tags from this model. If no tags given, removes all tags.
     *
     * @param string|string[]|Tag|Tag[]|null $tags
     */
    public function untag(string|array|Tag|null $tags = null, ?string $type = null): static
    {
        if ($tags === null) {
            // Remove all tags (filtered by type if specified)
            $currentTags = $this->tags($type);
            foreach ($currentTags as $tag) {
                Taggable::query()
                    ->where('tag_id', $tag->id)
                    ->where('taggable_type', static::class)
                    ->where('taggable_id', (string) $this->getKey())
                    ->delete();

                $tag->count = max(0, ((int) $tag->count) - 1);
                $tag->save();
            }
            return $this;
        }

        $tagList = $this->normalizeTagsInput($tags);
        foreach ($tagList as $tagItem) {
            $slug = $tagItem instanceof Tag ? $tagItem->slug : Tag::slugify($tagItem);
            $name = $tagItem instanceof Tag ? $tagItem->name : trim(strip_tags($tagItem));

            $tagQuery = Tag::query()->where('slug', $slug);
            if ($type !== null) {
                $tagQuery->where('type', $type);
            }
            /** @var Tag|null $tag */
            $tag = $tagQuery->first();

            if (!$tag) {
                continue;
            }

            $deleted = Taggable::query()
                ->where('tag_id', $tag->id)
                ->where('taggable_type', static::class)
                ->where('taggable_id', (string) $this->getKey())
                ->delete();

            if ($deleted > 0) {
                $tag->count = max(0, ((int) $tag->count) - 1);
                $tag->save();
            }
        }

        return $this;
    }

    /**
     * Sync tags attached to this model (removes old, attaches new).
     *
     * @param string|string[]|Tag|Tag[] $tags
     */
    public function retag(string|array|Tag $tags, ?string $type = null): static
    {
        $this->untag(null, $type);
        return $this->tag($tags, $type);
    }

    /**
     * Scope query to models having any of the given tags.
     *
     * @param string|string[] $tags
     */
    public static function withAnyTag(string|array $tags, ?string $type = null): QueryBuilder
    {
        $slugs = static::resolveTagSlugs($tags);
        if (empty($slugs)) {
            return static::query()->whereRaw('1 = 0');
        }

        $tagIds = array_map(
            fn(Tag $t) => (int) $t->id,
            Tag::query()->whereIn('slug', $slugs)->get()
        );

        if (empty($tagIds)) {
            return static::query()->whereRaw('1 = 0');
        }

        $taggables = Taggable::query()
            ->where('taggable_type', static::class)
            ->whereIn('tag_id', $tagIds)
            ->get();

        $ids = array_unique(array_map(fn($t) => $t->taggable_id, $taggables));
        if (empty($ids)) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->whereIn('id', $ids);
    }

    /**
     * Scope query to models having all of the given tags.
     *
     * @param string|string[] $tags
     */
    public static function withAllTags(string|array $tags, ?string $type = null): QueryBuilder
    {
        $slugs = static::resolveTagSlugs($tags);
        if (empty($slugs)) {
            return static::query()->whereRaw('1 = 0');
        }

        $tagIds = array_map(
            fn(Tag $t) => (int) $t->id,
            Tag::query()->whereIn('slug', $slugs)->get()
        );

        if (count($tagIds) < count($slugs)) {
            return static::query()->whereRaw('1 = 0');
        }

        $taggables = Taggable::query()
            ->where('taggable_type', static::class)
            ->whereIn('tag_id', $tagIds)
            ->get();

        // Group by taggable_id and verify count
        $counts = [];
        foreach ($taggables as $t) {
            $id = (string) $t->taggable_id;
            $counts[$id] = ($counts[$id] ?? 0) + 1;
        }

        $requiredCount = count($tagIds);
        $matchingIds = [];
        foreach ($counts as $id => $cnt) {
            if ($cnt >= $requiredCount) {
                $matchingIds[] = $id;
            }
        }

        if (empty($matchingIds)) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->whereIn('id', $matchingIds);
    }

    /**
     * Normalize tags input into an array of strings or Tag objects.
     *
     * @param string|string[]|Tag|Tag[] $tags
     * @return array<int, string|Tag>
     */
    protected function normalizeTagsInput(string|array|Tag $tags): array
    {
        if ($tags instanceof Tag) {
            return [$tags];
        }

        if (is_string($tags)) {
            $delim = function_exists('config') ? config('taggable.delimiter', ',') : ',';
            $parts = explode($delim, $tags);
            $cleaned = [];
            foreach ($parts as $part) {
                $item = trim(strip_tags($part));
                if ($item !== '') {
                    $cleaned[] = $item;
                }
            }
            return $cleaned;
        }

        $result = [];
        foreach ($tags as $item) {
            if ($item instanceof Tag) {
                $result[] = $item;
            } elseif (is_string($item)) {
                $trimmed = trim(strip_tags($item));
                if ($trimmed !== '') {
                    $result[] = $trimmed;
                }
            }
        }

        return $result;
    }

    /**
     * Resolve tags to an array of slugs.
     *
     * @param string|string[] $tags
     * @return string[]
     */
    protected static function resolveTagSlugs(string|array $tags): array
    {
        if (is_string($tags)) {
            $delim = function_exists('config') ? config('taggable.delimiter', ',') : ',';
            $tags = explode($delim, $tags);
        }

        $slugs = [];
        foreach ($tags as $t) {
            $str = trim(strip_tags((string) $t));
            if ($str !== '') {
                $slugs[] = Tag::slugify($str);
            }
        }

        return array_unique($slugs);
    }
}
