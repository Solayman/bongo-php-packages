<?php

namespace Bongo\Packages\BdLocations\Commands;

use Bongo\Packages\BdLocations\Location;
use System\Console\Command;

class BdLocationsCommand extends Command
{
    protected string $name = 'bd-locations:list';
    protected string $description = 'List all Bangladesh divisions and their district counts';

    public function handle(array $args, array $options): int
    {
        $this->info("Bangladesh Administrative Divisions & Districts:");
        $this->line("--------------------------------------------------");

        $divisions = Location::divisions();
        foreach ($divisions as $d) {
            $districts = Location::districts($d['id']);
            $count = count($districts);
            $this->line(sprintf(" [%d] %-12s / %-12s (%d Districts)", $d['id'], $d['name'], $d['bn_name'], $count));
        }

        $this->line("--------------------------------------------------");
        $this->info(sprintf("Total: %d Divisions, %d Districts", count($divisions), count(Location::districts())));
        return 0;
    }
}
