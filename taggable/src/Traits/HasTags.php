<?php

declare(strict_types=1);

namespace Bongo\Packages\Taggable\Traits;

use Bongo\Packages\Taggable\Models\Tag;
use Bongo\Packages\Taggable\Models\Taggable;
use System\Database\Model;
use System\Database\QueryBuilder;

trait HasTags
{
    /** @var array<string, Tag[]>|null Instance cache for tags() */
    protected ?array $_bongoTagsCache = null;

    /**
     * Get all tags associated with this model.
     *
     * @return Tag[]
     */
    public function tags(?string $type = null): array
    {
        $cacheKey = $type ?? '__all__';
        if (isset($this->_bongoTagsCache[$cacheKey])) {
            return $this->_bongoTagsCache[$cacheKey];
        }

        $taggables = Taggable::query()
            ->where('taggable_type', static::class)
            ->where('taggable_id', (string) $this->getKey())
            ->get();

        $tagIds = array_filter(array_map(fn($t) => (int) $t->tag_id, $taggables));
        if (empty($tagIds)) {
            $this->_bongoTagsCache[$cacheKey] = [];
            return [];
        }

        $query = Tag::query()->whereIn('id', $tagIds);
        if ($type !== null) {
            $query->where('type', $type);
        }

        $result = $query->orderBy('name', 'ASC')->get();
        $this->_bongoTagsCache[$cacheKey] = $result;
        return $result;
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
        $this->_bongoTagsCache = null;
        $tagList = $this->normalizeTagsInput($tags);
        if (empty($tagList)) {
            return $this;
        }

        // Batch resolve/create tags
        $slugsToLookup = [];
        $rawTags = [];
        foreach ($tagList as $item) {
            if ($item instanceof Tag) {
                $rawTags[] = $item;
            } else {
                $slug = Tag::slugify((string) $item);
                $slugsToLookup[$slug] = (string) $item;
            }
        }

        if (!empty($slugsToLookup)) {
            $existing = Tag::query()->whereIn('slug', array_keys($slugsToLookup))->get();
            $existingBySlug = [];
            foreach ($existing as $exTag) {
                $existingBySlug[$exTag->slug] = $exTag;
                $rawTags[] = $exTag;
            }

            foreach ($slugsToLookup as $slug => $name) {
                if (!isset($existingBySlug[$slug])) {
                    $created = Tag::create([
                        'name' => trim(strip_tags($name)),
                        'slug' => $slug,
                        'type' => $type,
                        'count' => 0,
                    ]);
                    $rawTags[] = $created;
                }
            }
        }

        // De-duplicate tags by ID
        $uniqueTags = [];
        foreach ($rawTags as $tag) {
            if ($tag && $tag->id) {
                $uniqueTags[(int) $tag->id] = $tag;
            }
        }

        if (empty($uniqueTags)) {
            return $this;
        }

        // Batch fetch existing pivot attachments
        $tagIds = array_keys($uniqueTags);
        $existingPivots = Taggable::query()
            ->whereIn('tag_id', $tagIds)
            ->where('taggable_type', static::class)
            ->where('taggable_id', (string) $this->getKey())
            ->get();

        $existingTagIds = array_flip(array_map(fn($p) => (int) $p->tag_id, $existingPivots));

        // One INSERT for all new pivot rows and one atomic UPDATE for their usage counters
        $newIds = array_values(array_filter($tagIds, fn($id) => !isset($existingTagIds[$id])));
        if ($newIds !== []) {
            $now = date('Y-m-d H:i:s');
            Taggable::query()->insert(array_map(fn($id) => [
                'tag_id' => $id,
                'taggable_type' => static::class,
                'taggable_id' => (string) $this->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ], $newIds));
            Tag::query()->whereIn('id', $newIds)->increment('count');
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
        $this->_bongoTagsCache = null;

        if ($tags === null) {
            // Remove all tags (filtered by type if specified)
            $currentTags = $this->tags($type);
            if (!empty($currentTags)) {
                $tagIds = array_map(fn($t) => (int) $t->id, $currentTags);
                Taggable::query()
                    ->whereIn('tag_id', $tagIds)
                    ->where('taggable_type', static::class)
                    ->where('taggable_id', (string) $this->getKey())
                    ->delete();

                // One atomic UPDATE; never below zero
                Tag::query()->whereIn('id', $tagIds)->where('count', '>', 0)->decrement('count');
            }
            $this->_bongoTagsCache = null;
            return $this;
        }

        $tagList = $this->normalizeTagsInput($tags);
        if (empty($tagList)) {
            $this->_bongoTagsCache = null;
            return $this;
        }

        $slugs = [];
        foreach ($tagList as $item) {
            $slugs[] = $item instanceof Tag ? $item->slug : Tag::slugify($item);
        }
        $slugs = array_unique(array_filter($slugs));

        if (empty($slugs)) {
            $this->_bongoTagsCache = null;
            return $this;
        }

        $tagQuery = Tag::query()->whereIn('slug', $slugs);
        if ($type !== null) {
            $tagQuery->where('type', $type);
        }
        $matchingTags = $tagQuery->get();
        if (empty($matchingTags)) {
            $this->_bongoTagsCache = null;
            return $this;
        }

        $tagIds = array_map(fn($t) => (int) $t->id, $matchingTags);
        $pivots = Taggable::query()
            ->whereIn('tag_id', $tagIds)
            ->where('taggable_type', static::class)
            ->where('taggable_id', (string) $this->getKey())
            ->get();

        if (empty($pivots)) {
            $this->_bongoTagsCache = null;
            return $this;
        }

        $attachedTagIds = array_flip(array_map(fn($p) => (int) $p->tag_id, $pivots));

        Taggable::query()
            ->whereIn('tag_id', array_keys($attachedTagIds))
            ->where('taggable_type', static::class)
            ->where('taggable_id', (string) $this->getKey())
            ->delete();

        // One atomic UPDATE; never below zero
        Tag::query()->whereIn('id', array_keys($attachedTagIds))->where('count', '>', 0)->decrement('count');

        $this->_bongoTagsCache = null;
        return $this;
    }

    /**
     * Sync tags attached to this model (removes old, attaches new).
     *
     * @param string|string[]|Tag|Tag[] $tags
     */
    public function retag(string|array|Tag $tags, ?string $type = null): static
    {
        $this->_bongoTagsCache = null;
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
