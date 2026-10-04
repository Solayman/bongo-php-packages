<?php

declare(strict_types=1);

namespace Bongo\Packages\Settings\Models;

use System\Database\Model;

class Setting extends Model
{
    protected static $table = 'settings';

    protected array $fillable = [
        'setting_key',
        'value',
        'type',
        'setting_group',
    ];

    public function getAttribute(string $key)
    {
        if ($key === 'key') {
            return $this->attributes['setting_key'] ?? null;
        }
        if ($key === 'group') {
            return $this->attributes['setting_group'] ?? 'general';
        }
        return parent::getAttribute($key);
    }

    public function setAttribute(string $key, $value): void
    {
        if ($key === 'key') {
            $this->attributes['setting_key'] = $value;
            return;
        }
        if ($key === 'group') {
            $this->attributes['setting_group'] = $value;
            return;
        }
        parent::setAttribute($key, $value);
    }

    /**
     * Cast the stored raw string value to its native PHP type.
     */
    public function getCastedValue(): mixed
    {
        return self::cast($this->value, $this->type ?? 'string');
    }

    /**
     * Cast raw value by type.
     */
    public static function cast(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($type)) {
            'int', 'integer' => (int) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'float', 'double' => (float) $value,
            'json', 'array' => is_string($value) ? (json_decode($value, true) ?? []) : (array) $value,
            default => (string) $value,
        };
    }

    /**
     * Serialize a native PHP value to raw string for database persistence.
     */
    public static function serialize(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($type)) {
            'bool', 'boolean' => $value ? '1' : '0',
            'json', 'array' => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    /**
     * Automatically detect type from PHP value.
     */
    public static function detectType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_array($value)) {
            return 'json';
        }
        return 'string';
    }
}
