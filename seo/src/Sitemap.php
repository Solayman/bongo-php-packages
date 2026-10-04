<?php

declare(strict_types=1);

namespace Bongo\Packages\Seo;

class Sitemap
{
    /**
     * @var array<SitemapUrl>
     */
    protected array $urls = [];

    /**
     * Add a URL to the sitemap.
     */
    public function add(
        string $url,
        ?string $lastModified = null,
        ?float $priority = null,
        ?string $changeFrequency = null
    ): self {
        $this->urls[] = new SitemapUrl($url, $lastModified, $priority, $changeFrequency);
        return $this;
    }

    /**
     * Generate the complete XML document string.
     */
    public function toXml(): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($this->urls as $url) {
            $xml .= $url->toXml();
        }

        $xml .= "</urlset>\n";
        return $xml;
    }

    /**
     * Write XML to file.
     */
    public function save(string $filePath): bool
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return file_put_contents($filePath, $this->toXml()) !== false;
    }

    /**
     * Get count of URLs in sitemap.
     */
    public function count(): int
    {
        return count($this->urls);
    }
}
