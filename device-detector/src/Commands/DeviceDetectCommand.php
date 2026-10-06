<?php

declare(strict_types=1);

namespace Bongo\Packages\DeviceDetector\Commands;

use Bongo\Packages\DeviceDetector\Device;
use System\Console\Command;

class DeviceDetectCommand extends Command
{
    protected string $signature = 'device:detect {ua? : Optional User-Agent string to parse}';
    protected string $description = 'Inspect and parse a User-Agent string into device, OS, and browser details';

    public function handle(array $args, array $options): int
    {
        $ua = $args['ua'] ?? null;
        $device = Device::detect($ua);

        $this->info("Device Detection Results:");
        $this->line("  Device Type:     " . $device->deviceType());
        $this->line("  Is Mobile:       " . ($device->isMobile() ? 'Yes' : 'No'));
        $this->line("  Is Tablet:       " . ($device->isTablet() ? 'Yes' : 'No'));
        $this->line("  Is Desktop:      " . ($device->isDesktop() ? 'Yes' : 'No'));
        $this->line("  Is Bot/Crawler:  " . ($device->isBot() ? 'Yes (' . $device->botName() . ')' : 'No'));
        $this->line("  Operating System:" . " {$device->os()} (v{$device->osVersion()})");
        $this->line("  Browser:         " . " {$device->browser()} (v{$device->browserVersion()})");

        return 0;
    }
}
