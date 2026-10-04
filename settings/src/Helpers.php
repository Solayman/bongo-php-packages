<?php

declare(strict_types=1);

use Bongo\Packages\Settings\SettingsManager;

if (!function_exists('setting')) {
    /**
     * Get / set the specified setting value.
     *
     * If called with no parameters, the SettingsManager instance is returned.
     * If called with an array, sets each key-value pair.
     * If called with a string key, returns the setting value or default.
     */
    function setting(string|array|null $key = null, mixed $default = null): mixed
    {
        $manager = SettingsManager::getInstance();

        if ($key === null) {
            return $manager;
        }

        if (is_array($key)) {
            $manager->set($key);
            return null;
        }

        return $manager->get($key, $default);
    }
}
