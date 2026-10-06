<?php

declare(strict_types=1);

namespace Bongo\Packages\GeoIP\Commands;

use Bongo\Packages\GeoIP\GeoIP;
use System\Console\Command;

class GeoIpLookupCommand extends Command
{
    protected string $name = 'geoip:lookup';
    protected string $description = 'Resolve country, city, and timezone for an IP address';

    public function handle(array $args, array $options): int
    {
        $ip = $args[0] ?? ($options['ip'] ?? null);

        $this->info("Resolving location for " . ($ip ? "IP '{$ip}'" : "current client") . "...");
        $loc = GeoIP::lookup($ip);

        $this->info("✓ Location resolved:");
        $this->line("  IP           : " . $loc->ip);
        $this->line("  Country      : " . $loc->country . " (" . $loc->countryCode . ")");
        $this->line("  City         : " . ($loc->city ?: 'N/A'));
        $this->line("  Region       : " . ($loc->region ?: 'N/A'));
        $this->line("  Timezone     : " . $loc->timezone);
        $this->line("  Currency     : " . $loc->currency);
        $this->line("  Coordinates  : " . $loc->latitude . ", " . $loc->longitude);
        $this->line("  Is Local     : " . ($loc->isLocal ? 'Yes' : 'No'));

        return 0;
    }
}
