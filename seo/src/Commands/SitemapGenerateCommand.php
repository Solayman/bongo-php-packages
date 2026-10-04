<?php

declare(strict_types=1);

namespace Bongo\Packages\Seo\Commands;

use Bongo\Packages\Seo\Sitemap;
use System\Console\Command;

class SitemapGenerateCommand extends Command
{
    protected string $name = 'sitemap:generate';
    protected string $description = 'Generate an XML sitemap for the application';

    public function handle(array $args, array $options): int
    {
        $out = $options['out'] ?? (function_exists('config') ? config('seo.sitemap.output_path', 'public/sitemap.xml') : 'public/sitemap.xml');
        $baseUrl = function_exists('env') ? env('APP_URL', 'http://localhost') : 'http://localhost';

        $sitemap = new Sitemap();
        $sitemap->add($baseUrl, date('Y-m-d'), 1.0, 'daily');

        // Allow app hook if class exists
        if (class_exists('App\\Services\\SitemapProvider')) {
            /** @var callable $provider */
            $provider = ['App\\Services\\SitemapProvider', 'populate'];
            if (is_callable($provider)) {
                $provider($sitemap);
            }
        }

        $isAbsolute = str_starts_with($out, '/') || str_starts_with($out, '\\') || (strlen($out) > 2 && $out[1] === ':');
        $fullPath = $isAbsolute ? $out : (function_exists('base_path') ? base_path($out) : $out);
        if ($sitemap->save($fullPath)) {
            $count = $sitemap->count();
            $this->info("XML sitemap generated successfully ({$count} URLs): {$fullPath}");
            return 0;
        }

        $this->error("Failed to write sitemap to {$fullPath}");
        return 1;
    }
}
