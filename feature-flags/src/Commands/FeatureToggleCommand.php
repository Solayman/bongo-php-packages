<?php

declare(strict_types=1);

namespace Bongo\Packages\FeatureFlags\Commands;

use Bongo\Packages\FeatureFlags\Feature;
use System\Console\Command;

class FeatureToggleCommand extends Command
{
    protected string $signature = 'feature:toggle {feature : The feature name} {--scope= : Specific scope to toggle} {--off : Turn the feature off}';
    protected string $description = 'Toggle or set the state of a feature flag';

    public function handle(array $args, array $options): int
    {
        $feature = $args['feature'] ?? null;
        if (!$feature) {
            $this->error('Feature name is required.');
            return 1;
        }

        $scope = $options['scope'] ?? null;
        $off = isset($options['off']) && $options['off'] !== false;

        if ($off) {
            Feature::deactivate($feature, $scope);
            $this->info("Feature [{$feature}] deactivated" . ($scope ? " for scope [{$scope}]." : " globally."));
        } else {
            Feature::activate($feature, $scope);
            $this->info("Feature [{$feature}] activated" . ($scope ? " for scope [{$scope}]." : " globally."));
        }

        return 0;
    }
}
