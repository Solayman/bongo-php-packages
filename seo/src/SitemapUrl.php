<?php

declare(strict_types=1);

namespace Bongo\Packages\Seo;

class SitemapUrl
{
    public function __construct(
        public string $loc,
        public ?string $lastModified = null,
        public ?float $priority = null,
        public ?string $changeFrequency = null
    ) {
    }

    public function toXml(): string
    {
        $xml = "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($this->loc, ENT_XML1, 'UTF-8') . "</loc>\n";

        if ($this->lastModified !== null) {
            $formatted = date('c', strtotime($this->lastModified) ?: time());
            $xml .= "    <lastmod>{$formatted}</lastmod>\n";
        }

        if ($this->changeFrequency !== null) {
            $xml .= "    <changefreq>" . htmlspecialchars($this->changeFrequency, ENT_XML1, 'UTF-8') . "</changefreq>\n";
        }

        if ($this->priority !== null) {
            $p = number_format(max(0.0, min(1.0, $this->priority)), 1, '.', '');
            $xml .= "    <priority>{$p}</priority>\n";
        }

        $xml .= "  </url>\n";
        return $xml;
    }
}
