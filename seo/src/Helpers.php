<?php

declare(strict_types=1);

use Bongo\Packages\Seo\SeoManager;

if (!function_exists('seo')) {
    /**
     * Get the SEO manager instance or set page title quickly.
     */
    function seo(?string $title = null, ?string $siteName = null): SeoManager
    {
        $manager = SeoManager::getInstance();

        if ($title !== null) {
            $manager->title($title, $siteName);
        }

        return $manager;
    }
}
