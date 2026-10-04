<?php

declare(strict_types=1);

namespace Bongo\Packages\Image\Commands;

use Bongo\Packages\Image\Image;
use System\Console\Command;

class ImageOptimizeCommand extends Command
{
    protected string $name = 'image:optimize';
    protected string $description = 'Optimize images or convert them to WebP format';

    public function handle(array $args, array $options): int
    {
        $target = $args[0] ?? null;
        if ($target === null) {
            $this->error("Usage: php bongo image:optimize <file-or-dir> [--webp] [--quality=85] [--max-width=1920]");
            return 1;
        }

        if (!file_exists($target)) {
            $this->error("Target path not found: {$target}");
            return 1;
        }

        $toWebp = isset($options['webp']);
        $quality = isset($options['quality']) ? (int) $options['quality'] : 85;
        $maxWidth = isset($options['max-width']) ? (int) $options['max-width'] : null;

        $files = is_dir($target) ? $this->scanDirectory($target) : [$target];

        if (empty($files)) {
            $this->warn("No supported images found in: {$target}");
            return 0;
        }

        $this->info("Optimizing " . count($files) . " image(s)...");

        $totalOriginal = 0;
        $totalOptimized = 0;

        foreach ($files as $file) {
            $origSize = filesize($file);
            $totalOriginal += $origSize;

            try {
                $img = Image::load($file);

                if ($maxWidth !== null && $img->width() > $maxWidth) {
                    $img->resizeToWidth($maxWidth);
                }

                if ($toWebp) {
                    $dest = preg_replace('/\.[^.]+$/', '.webp', $file);
                    $img->save($dest, $quality, 'webp');
                    $newSize = filesize($dest);
                } else {
                    $img->save($file, $quality);
                    $newSize = filesize($file);
                }

                $totalOptimized += $newSize;
                $saved = $origSize > 0 ? round((1 - ($newSize / $origSize)) * 100, 1) : 0;
                $this->line("  ✓ " . basename($file) . " [{$origSize}B -> {$newSize}B, {$saved}% saved]");
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed to optimize " . basename($file) . ": " . $e->getMessage());
                $totalOptimized += $origSize;
            }
        }

        $totalSaved = $totalOriginal > 0 ? round((1 - ($totalOptimized / $totalOriginal)) * 100, 1) : 0;
        $this->info("Done! Total: {$totalOriginal}B -> {$totalOptimized}B ({$totalSaved}% reduction).");

        return 0;
    }

    /**
     * @return string[]
     */
    protected function scanDirectory(string $dir): array
    {
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];
        $files = [];

        $items = scandir($dir);
        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $files = array_merge($files, $this->scanDirectory($full));
            } elseif (is_file($full)) {
                $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed, true)) {
                    $files[] = $full;
                }
            }
        }

        return $files;
    }
}
