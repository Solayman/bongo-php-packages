<?php

declare(strict_types=1);

namespace Bongo\Packages\Settings\Commands;

use Bongo\Packages\Settings\Setting;
use System\Console\Command;

class SettingClearCommand extends Command
{
    protected string $name = 'setting:clear';
    protected string $description = 'Clear the in-memory and cached settings';

    public function handle(array $args, array $options): int
    {
        Setting::clearCache();
        $this->info("Settings cache cleared successfully.");

        return 0;
    }
}
