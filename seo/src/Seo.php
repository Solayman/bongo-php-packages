<?php

declare(strict_types=1);

namespace Bongo\Packages\Seo;

class Seo
{
    public static function title(string $title, ?string $siteName = null): SeoManager
    {
        return SeoManager::getInstance()->title($title, $siteName);
    }

    public static function description(string $description): SeoManager
    {
        return SeoManager::getInstance()->description($description);
    }

    public static function keywords(array|string $keywords): SeoManager
    {
        return SeoManager::getInstance()->keywords($keywords);
    }

    public static function canonical(?string $url = null): SeoManager
    {
        return SeoManager::getInstance()->canonical($url);
    }

    public static function robots(string $robots): SeoManager
    {
        return SeoManager::getInstance()->robots($robots);
    }

    public static function image(string $url, ?int $width = null, ?int $height = null, ?string $alt = null): SeoManager
    {
        return SeoManager::getInstance()->image($url, $width, $height, $alt);
    }

    public static function openGraph(string|array $property, ?string $value = null): SeoManager
    {
        return SeoManager::getInstance()->openGraph($property, $value);
    }

    public static function twitter(string|array $property, ?string $value = null): SeoManager
    {
        return SeoManager::getInstance()->twitter($property, $value);
    }

    public static function schema(string $type, array $data): SeoManager
    {
        return SeoManager::getInstance()->schema($type, $data);
    }

    public static function render(): string
    {
        return SeoManager::getInstance()->render();
    }

    public static function reset(): SeoManager
    {
        return SeoManager::getInstance()->reset();
    }

    public static function manager(): SeoManager
    {
        return SeoManager::getInstance();
    }
}
