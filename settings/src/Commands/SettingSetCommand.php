<?php

declare(strict_types=1);

namespace Bongo\Packages\Settings\Commands;

use Bongo\Packages\Settings\Setting;
use System\Console\Command;

class SettingSetCommand extends Command
{
    protected string $name = 'setting:set';
    protected string $description = 'Set or update an application setting';

    public function handle(array $args, array $options): int
    {
        $key = $args[0] ?? null;
        $value = $args[1] ?? null;

        if (!$key || $value === null) {
            $this->error("Usage: php bongo setting:set <key> <value> [--type=string] [--group=general]");
            return 1;
        }

        $type = $options['type'] ?? null;
        $group = $options['group'] ?? null;

        // Auto-convert type for CLI input if type option is set
        if ($type === 'json' || $type === 'array') {
            $decoded = json_decode((string) $value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        } elseif ($type === 'bool' || $type === 'boolean') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } elseif ($type === 'int' || $type === 'integer') {
            $value = (int) $value;
        } elseif ($type === 'float' || $type === 'double') {
            $value = (float) $value;
        }

        Setting::set($key, $value, $type, $group);

        $displayVal = is_array($value) ? json_encode($value) : (string) $value;
        $this->info("Setting [{$key}] has been saved ({$displayVal}).");

        return 0;
    }
}
