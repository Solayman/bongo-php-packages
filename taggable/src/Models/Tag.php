<?php

declare(strict_types=1);

namespace Bongo\Packages\Taggable\Models;

use System\Database\Model;
use System\Database\HasMany;

class Tag extends Model
{
    protected static $table = 'tags';

    protected array $fillable = [
        'id',
        'name',
        'slug',
        'type',
        'description',
        'count',
    ];

    protected array $casts = [
        'count' => 'int',
    ];

    /** @var array<string, string> */
    protected static array $slugCache = [];

    public static function tableName(): string
    {
        return function_exists('config') ? config('taggable.tags_table', 'tags') : (static::$table ?? 'tags');
    }

    /**
     * Generate an SEO-friendly Unicode/Bengali compatible slug with memoization.
     */
    public static function slugify(string $text): string
    {
        if (isset(self::$slugCache[$text])) {
            return self::$slugCache[$text];
        }

        // Strip script tags with their inner content first
        $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $text) ?? $text;

        // If sluggable package or global slug() helper is available, leverage it
        if (class_exists(\Bongo\Packages\Sluggable\Slugger::class)) {
            $slug = \Bongo\Packages\Sluggable\Slugger::slugify($clean);
            if (count(self::$slugCache) < 500) {
                self::$slugCache[$text] = $slug;
            }
            return $slug;
        }

        if (function_exists('slug')) {
            $slug = slug($clean);
            if (count(self::$slugCache) < 500) {
                self::$slugCache[$text] = $slug;
            }
            return $slug;
        }

        // Fallback pure-PHP unicode slugger
        $clean = trim(strip_tags($clean));
        $clean = mb_strtolower($clean, 'UTF-8');
        $slug = preg_replace('/[^\p{L}\p{Nd}\p{M}]+/u', '-', $clean);
        $slug = trim($slug ?? '', '-');
        $result = $slug !== '' ? $slug : 'tag-' . substr(md5($text . microtime()), 0, 8);

        if (count(self::$slugCache) < 500) {
            self::$slugCache[$text] = $result;
        }

        return $result;
    }

    /**
     * Find an existing tag by name/slug or create a new one.
     */
    public static function findOrCreate(string $name, ?string $type = null): static
    {
        $cleanName = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $name) ?? $name;
        $cleanName = trim(strip_tags($cleanName));
        $cleanName = mb_substr($cleanName, 0, 100);

        if ($cleanName === '') {
            throw new \InvalidArgumentException('Tag name cannot be empty.');
        }

        $slug = static::slugify($cleanName);

        $query = static::query()->where('slug', $slug);
        if ($type !== null) {
            $query->where('type', $type);
        } else {
            $query->whereNull('type');
        }

        /** @var static|null $existing */
        $existing = $query->first();
        if ($existing) {
            return $existing;
        }

        // Create new tag
        $tag = new static([
            'name' => $cleanName,
            'slug' => $slug,
            'type' => $type,
            'count' => 0,
        ]);
        $tag->save();

        return $tag;
    }

    /**
     * Retrieve the most popular tags ordered by usage count.
     *
     * @return static[]
     */
    public static function popular(int $limit = 10, ?string $type = null): array
    {
        $query = static::query()->where('count', '>', 0)->orderBy('count', 'DESC');
        if ($type !== null) {
            $query->where('type', $type);
        }
        return $query->limit($limit)->get();
    }

    /**
     * Build tag cloud metadata with font sizes and relative weights.
     *
     * @return array<int, array{tag: static, size: int, weight: float}>
     */
    public static function tagCloud(?string $type = null, int $minSize = 12, int $maxSize = 28): array
    {
        $query = static::query()->where('count', '>', 0);
        if ($type !== null) {
            $query->where('type', $type);
        }
        $tags = $query->orderBy('name', 'ASC')->get();

        if (empty($tags)) {
            return [];
        }

        $counts = array_map(fn($t) => (int) $t->count, $tags);
        $minCount = min($counts);
        $maxCount = max($counts);
        $spread = max(1, $maxCount - $minCount);

        $cloud = [];
        foreach ($tags as $tag) {
            $count = (int) $tag->count;
            $weight = ($count - $minCount) / $spread;
            $size = (int) round($minSize + ($weight * ($maxSize - $minSize)));

            $cloud[] = [
                'tag' => $tag,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'count' => $count,
                'size' => $size,
                'weight' => round($weight, 2),
            ];
        }

        return $cloud;
    }

    public function taggables(): HasMany
    {
        return $this->hasMany(Taggable::class, 'tag_id');
    }
}
