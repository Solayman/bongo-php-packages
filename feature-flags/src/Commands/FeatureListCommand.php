<?php

declare(strict_types=1);

namespace Bongo\Packages\FeatureFlags\Commands;

use Bongo\Packages\FeatureFlags\Feature;
use System\Console\Command;

class FeatureListCommand extends Command
{
    protected string $signature = 'feature:list';
    protected string $description = 'List all defined and database-persisted feature flags';

    public function handle(array $args, array $options): int
    {
        $features = Feature::all();

        if (empty($features)) {
            $this->warn('No feature flags registered or configured.');
            return 0;
        }

        $this->info("Registered Feature Flags (" . count($features) . "):");
        $this->line(str_repeat('-', 40));

        foreach ($features as $name => $active) {
            $status = $active ? "\033[32m[ACTIVE]\033[0m" : "\033[31m[INACTIVE]\033[0m";
            $this->line("  {$name} : {$status}");
        }

        $this->line(str_repeat('-', 40));
        return 0;
    }
}
