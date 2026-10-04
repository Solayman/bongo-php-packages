<?php

declare(strict_types=1);

namespace Bongo\Packages\Zip\Commands;

use Bongo\Packages\Zip\Zip;
use System\Console\Command;

class ZipExtractCommand extends Command
{
    protected string $name = 'zip:extract';
    protected string $description = 'Extract a ZIP archive to a destination directory';

    public function handle(array $args, array $options): int
    {
        $archive = $args[0] ?? null;
        $dest = $args[1] ?? null;

        if (!$archive || !$dest) {
            $this->error("Usage: php bongo zip:extract <archive.zip> <destination_dir>");
            return 1;
        }

        if (!file_exists($archive)) {
            $this->error("Archive file not found: {$archive}");
            return 1;
        }

        $this->info("Extracting [{$archive}] to [{$dest}]...");
        Zip::extract($archive, $dest);
        $this->info("Archive extracted successfully.");

        return 0;
    }
}
