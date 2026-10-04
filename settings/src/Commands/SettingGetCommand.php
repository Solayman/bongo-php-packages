<?php

declare(strict_types=1);

namespace Bongo\Packages\Settings\Commands;

use Bongo\Packages\Settings\Setting;
use System\Console\Command;

class SettingGetCommand extends Command
{
    protected string $name = 'setting:get';
    protected string $description = 'Retrieve the value of an application setting';

    public function handle(array $args, array $options): int
    {
        $key = $args[0] ?? null;

        if (!$key) {
            $this->error("Usage: php bongo setting:get <key>");
            return 1;
        }

        if (!Setting::has($key)) {
            $this->warn("Setting [{$key}] is not set.");
            return 1;
        }

        $val = Setting::get($key);

        if (is_array($val)) {
            $this->line(json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } elseif (is_bool($val)) {
            $this->line($val ? 'true' : 'false');
        } else {
            $this->line((string) $val);
        }

        return 0;
    }
}
