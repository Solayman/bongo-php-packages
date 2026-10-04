<?php

declare(strict_types=1);

namespace Bongo\Packages\Zip\Commands;

use Bongo\Packages\Zip\Zip;
use System\Console\Command;

class ZipListCommand extends Command
{
    protected string $name = 'zip:list';
    protected string $description = 'List all contents and metadata of a ZIP archive';

    public function handle(array $args, array $options): int
    {
        $archive = $args[0] ?? null;
        if (!$archive) {
            $this->error("Usage: php bongo zip:list <archive.zip>");
            return 1;
        }

        if (!file_exists($archive)) {
            $this->error("Archive file not found: {$archive}");
            return 1;
        }

        $list = Zip::list($archive);
        if (empty($list)) {
            $this->warn("The archive is empty.");
            return 0;
        }

        $this->info("Archive contents for [{$archive}] (" . count($list) . " entries):");
        $this->line(sprintf("  %-45s %-12s %-12s %-20s", "ENTRY NAME", "SIZE", "COMPRESSED", "MODIFIED"));
        $this->line("  " . str_repeat("-", 92));

        foreach ($list as $entry) {
            $this->line(sprintf(
                "  %-45s %-12s %-12s %-20s",
                $entry['name'],
                $entry['size'] . ' B',
                $entry['compressed_size'] . ' B',
                $entry['date']
            ));
        }

        return 0;
    }
}
