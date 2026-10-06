<?php

declare(strict_types=1);

namespace Bongo\Packages\Courier\Commands;

use Bongo\Packages\Courier\Courier;
use System\Console\Command;

class CourierTrackCommand extends Command
{
    protected string $name = 'courier:track';
    protected string $description = 'Track a courier consignment by tracking code or ID';

    public function handle(array $args, array $options): int
    {
        $identifier = $args[0] ?? ($options['id'] ?? null);

        if (!$identifier) {
            $this->error('Usage: php bongo courier:track <consignment_id_or_tracking_code>');
            return 1;
        }

        $driver = $options['driver'] ?? 'steadfast';
        $this->info("Tracking shipment '{$identifier}' via {$driver}...");

        $res = Courier::driver($driver)->checkStatus($identifier);

        $status = $res['delivery_status'] ?? ($res['data']['order_status'] ?? 'unknown');
        $this->info("✓ Shipment Status: " . strtoupper((string) $status));
        $this->line(json_encode($res, JSON_PRETTY_PRINT));

        return 0;
    }
}
