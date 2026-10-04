<?php

declare(strict_types=1);

namespace Bongo\Packages\Zip\Commands;

use Bongo\Packages\Zip\Zip;
use System\Console\Command;

class ZipMakeCommand extends Command
{
    protected string $name = 'zip:make';
    protected string $description = 'Compress a file or directory into a ZIP archive';

    public function handle(array $args, array $options): int
    {
        $dest = $args[0] ?? null;
        $source = $args[1] ?? null;

        if (!$dest || !$source) {
            $this->error("Usage: php bongo zip:make <destination.zip> <source_path> [--exclude=.git,vendor]");
            return 1;
        }

        if (!file_exists($source)) {
            $this->error("Source path not found: {$source}");
            return 1;
        }

        $this->info("Creating ZIP archive [{$dest}] from [{$source}]...");

        $builder = Zip::create($dest);

        if (isset($options['exclude'])) {
            $excludes = explode(',', (string) $options['exclude']);
            $builder->exclude($excludes);
        }

        $builder->add($source);
        $builder->save();

        $size = filesize($dest) ?: 0;
        $formatted = round($size / 1024, 2) . ' KB';
        $this->info("Archive created successfully ({$builder->count()} files, {$formatted}).");

        return 0;
    }
}
