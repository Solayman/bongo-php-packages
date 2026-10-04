<?php

declare(strict_types=1);

namespace Bongo\Packages\Settings\Commands;

use Bongo\Packages\Settings\Models\Setting as SettingModel;
use System\Console\Command;

class SettingListCommand extends Command
{
    protected string $name = 'setting:list';
    protected string $description = 'List all configured application settings';

    public function handle(array $args, array $options): int
    {
        $group = $options['group'] ?? null;

        try {
            $query = SettingModel::query();
            if ($group !== null) {
                $query->where('setting_group', $group);
            }
            $settings = $query->orderBy('setting_group')->orderBy('setting_key')->get();
        } catch (\Throwable $e) {
            $this->error("Failed to query settings table: " . $e->getMessage());
            return 1;
        }

        if (empty($settings)) {
            $this->warn("No settings found in database.");
            return 0;
        }

        $this->info("Application Settings (" . count($settings) . "):");
        $this->line(str_repeat('-', 70));
        printf("%-30s | %-12s | %-10s | %s\n", "KEY", "GROUP", "TYPE", "VALUE");
        $this->line(str_repeat('-', 70));

        foreach ($settings as $s) {
            $key = $s->setting_key ?? $s->key;
            $grp = $s->setting_group ?? $s->group ?? 'general';
            $val = $s->value ?? '';
            if (strlen((string) $val) > 40) {
                $val = substr((string) $val, 0, 37) . '...';
            }
            printf("%-30s | %-12s | %-10s | %s\n", $key, $grp, $s->type ?? 'string', $val);
        }
        $this->line(str_repeat('-', 70));

        return 0;
    }
}
