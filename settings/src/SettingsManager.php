<?php

declare(strict_types=1);

namespace Bongo\Packages\Settings;

use Bongo\Packages\Settings\Models\Setting;

class SettingsManager
{
    protected static ?self $instance = null;

    /**
     * In-memory cache of loaded settings.
     *
     * @var array<string, mixed>
     */
    protected array $cache = [];

    /**
     * Whether all settings from the database have been eager-loaded.
     */
    protected bool $allLoaded = false;

    public function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Set or reset the singleton instance (useful for testing).
     */
    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Get a setting value by key, returning default if not found.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        if ($this->allLoaded) {
            return $default;
        }

        try {
            /** @var Setting|null $setting */
            $setting = Setting::where('setting_key', $key)->first();
            if ($setting !== null) {
                $val = $setting->getCastedValue();
                $this->cache[$key] = $val;
                return $val;
            }
        } catch (\Throwable $e) {
            return $default;
        }

        return $default;
    }

    /**
     * Batch load multiple settings into the in-memory cache in a single query.
     * Prevents N+1 queries when accessing multiple setting keys.
     *
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    public function load(array $keys): array
    {
        $keysToFetch = [];
        $result = [];

        foreach ($keys as $k) {
            $k = (string) $k;
            if (array_key_exists($k, $this->cache)) {
                $result[$k] = $this->cache[$k];
            } else {
                $keysToFetch[] = $k;
            }
        }

        if (!empty($keysToFetch) && !$this->allLoaded) {
            try {
                $rows = Setting::whereIn('setting_key', $keysToFetch)->get();
                if (is_array($rows)) {
                    foreach ($rows as $setting) {
                        $val = $setting->getCastedValue();
                        $this->cache[$setting->setting_key] = $val;
                        $result[$setting->setting_key] = $val;
                    }
                }
            } catch (\Throwable) {
                // Ignore DB error
            }
        }

        foreach ($keys as $k) {
            $k = (string) $k;
            if (!array_key_exists($k, $result)) {
                $result[$k] = $this->cache[$k] ?? null;
            }
        }

        return $result;
    }

    /**
     * Set a setting value, or multiple values if an array is passed.
     */
    public function set(
        string|array $key,
        mixed $value = null,
        ?string $type = null,
        ?string $group = null
    ): void {
        if (is_array($key)) {
            $keys = array_map('strval', array_keys($key));
            $existingMap = [];
            try {
                $existingRows = Setting::whereIn('setting_key', $keys)->get();
                if (is_array($existingRows)) {
                    foreach ($existingRows as $row) {
                        $existingMap[$row->setting_key] = $row;
                    }
                }
            } catch (\Throwable) {
                // Ignore DB error during batch fetch
            }

            foreach ($key as $k => $v) {
                $strKey = (string) $k;
                $detectedType = $type ?? Setting::detectType($v);
                $groupName = $group ?? (function_exists('config') ? config('settings.default_group', 'general') : 'general');
                $serialized = Setting::serialize($v, $detectedType);

                try {
                    $existing = $existingMap[$strKey] ?? null;
                    if ($existing !== null) {
                        $existing->value = $serialized;
                        $existing->type = $detectedType;
                        if ($group !== null) {
                            $existing->setting_group = $groupName;
                        }
                        $existing->save();
                    } else {
                        $newSetting = new Setting([
                            'setting_key' => $strKey,
                            'value' => $serialized,
                            'type' => $detectedType,
                            'setting_group' => $groupName,
                        ]);
                        $newSetting->save();
                        $existingMap[$strKey] = $newSetting;
                    }
                } catch (\Throwable) {
                    // Keep memory cache updated even if DB error
                }

                $this->cache[$strKey] = Setting::cast($serialized, $detectedType);
            }
            return;
        }

        $detectedType = $type ?? Setting::detectType($value);
        $groupName = $group ?? (function_exists('config') ? config('settings.default_group', 'general') : 'general');
        $serialized = Setting::serialize($value, $detectedType);

        try {
            /** @var Setting|null $existing */
            $existing = Setting::where('setting_key', $key)->first();

            if ($existing !== null) {
                $existing->value = $serialized;
                $existing->type = $detectedType;
                if ($group !== null) {
                    $existing->setting_group = $groupName;
                }
                $existing->save();
            } else {
                $newSetting = new Setting([
                    'setting_key' => $key,
                    'value' => $serialized,
                    'type' => $detectedType,
                    'setting_group' => $groupName,
                ]);
                $newSetting->save();
            }
        } catch (\Throwable $e) {
            // Keep memory cache updated even if DB error
        }

        $this->cache[$key] = Setting::cast($serialized, $detectedType);
    }

    /**
     * Check if a setting exists.
     */
    public function has(string $key): bool
    {
        if (array_key_exists($key, $this->cache)) {
            return true;
        }

        try {
            return Setting::where('setting_key', $key)->first() !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Delete a setting by key.
     */
    public function forget(string $key): bool
    {
        unset($this->cache[$key]);

        try {
            /** @var Setting|null $setting */
            $setting = Setting::where('setting_key', $key)->first();
            if ($setting !== null) {
                $setting->delete();
                return true;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    /**
     * Get all settings, optionally filtered by group.
     *
     * @return array<string, mixed>
     */
    public function all(?string $group = null): array
    {
        try {
            $query = Setting::query();
            if ($group !== null) {
                $query->where('setting_group', $group);
            }
            $records = $query->get();

            $results = [];
            foreach ($records as $record) {
                $k = $record->setting_key ?? $record->key;
                $val = $record->getCastedValue();
                $results[$k] = $val;
                $this->cache[$k] = $val;
            }

            if ($group === null) {
                $this->allLoaded = true;
            }

            return $results;
        } catch (\Throwable) {
            return $this->cache;
        }
    }

    /**
     * Clear all in-memory and loaded cached values.
     */
    public function clearCache(): void
    {
        $this->cache = [];
        $this->allLoaded = false;
    }
}
