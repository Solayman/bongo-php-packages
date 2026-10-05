<?php

declare(strict_types=1);

namespace Bongo\Packages\Seo;

class SeoManager
{
    protected static ?self $instance = null;

    protected ?string $title = null;
    protected ?string $siteName = null;
    protected ?string $description = null;
    protected array $keywords = [];
    protected ?string $canonical = null;
    protected string $robots = 'index, follow';
    protected ?string $image = null;

    /** @var array<string, string> */
    protected array $openGraph = [];

    /** @var array<string, string> */
    protected array $twitter = [];

    /** @var array<array{name: string, content: string, type: string}> */
    protected array $customMeta = [];

    /** @var array<array> */
    protected array $schemas = [];

    public function __construct()
    {
        $this->loadDefaults();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    protected function loadDefaults(): void
    {
        $cfg = function_exists('config') ? (array) config('seo.defaults', []) : [];

        $this->siteName = $cfg['title'] ?? (function_exists('env') ? env('APP_NAME', 'Bongo Application') : 'Bongo Application');
        $this->description = $cfg['description'] ?? 'A high-performance modern web application built on Bongo.';
        $this->keywords = (array) ($cfg['keywords'] ?? []);
        $this->canonical = $cfg['canonical'] ?? null;
        $this->robots = $cfg['robots'] ?? 'index, follow';

        $og = (array) ($cfg['og'] ?? []);
        $this->openGraph['type'] = $og['type'] ?? 'website';
        $this->openGraph['site_name'] = $og['site_name'] ?? $this->siteName;
        if (!empty($og['image'])) {
            $this->openGraph['image'] = $og['image'];
        }

        $tw = (array) ($cfg['twitter'] ?? []);
        $this->twitter['card'] = $tw['card'] ?? 'summary_large_image';
        if (!empty($tw['site'])) {
            $this->twitter['site'] = $tw['site'];
        }
        if (!empty($tw['creator'])) {
            $this->twitter['creator'] = $tw['creator'];
        }
    }

    public function reset(): self
    {
        $this->title = null;
        $this->description = null;
        $this->keywords = [];
        $this->canonical = null;
        $this->image = null;
        $this->openGraph = [];
        $this->twitter = [];
        $this->customMeta = [];
        $this->schemas = [];
        $this->loadDefaults();

        return $this;
    }

    public function title(string $title, ?string $siteName = null): self
    {
        $this->title = $title;
        if ($siteName !== null) {
            $this->siteName = $siteName;
        }
        $this->openGraph['title'] = $title;
        $this->twitter['title'] = $title;

        return $this;
    }

    public function setTitle(string $title, ?string $siteName = null): self
    {
        return $this->title($title, $siteName);
    }

    public function getTitle(): string
    {
        if ($this->title === null) {
            return $this->siteName ?? 'Bongo Application';
        }

        if ($this->siteName !== null && $this->title !== $this->siteName) {
            $sep = function_exists('config') ? (string) config('seo.defaults.title_separator', ' - ') : ' - ';
            return "{$this->title}{$sep}{$this->siteName}";
        }

        return $this->title;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        $this->openGraph['description'] = $description;
        $this->twitter['description'] = $description;

        return $this;
    }

    public function setDescription(string $description): self
    {
        return $this->description($description);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function keywords(array|string $keywords): self
    {
        $this->keywords = is_array($keywords) ? $keywords : array_map('trim', explode(',', $keywords));
        return $this;
    }

    public function canonical(?string $url = null): self
    {
        $this->canonical = $url;
        if ($url !== null) {
            $this->openGraph['url'] = $url;
        }
        return $this;
    }

    public function robots(string $robots): self
    {
        $this->robots = $robots;
        return $this;
    }

    public function image(string $url, ?int $width = null, ?int $height = null, ?string $alt = null): self
    {
        $this->image = $url;
        $this->openGraph['image'] = $url;
        $this->twitter['image'] = $url;

        if ($width !== null) {
            $this->openGraph['image:width'] = (string) $width;
        }
        if ($height !== null) {
            $this->openGraph['image:height'] = (string) $height;
        }
        if ($alt !== null) {
            $this->openGraph['image:alt'] = $alt;
            $this->twitter['image:alt'] = $alt;
        }

        return $this;
    }

    public function openGraph(string|array $property, ?string $value = null): self
    {
        if (is_array($property)) {
            foreach ($property as $k => $v) {
                $this->openGraph[(string) $k] = (string) $v;
            }
        } elseif ($value !== null) {
            $this->openGraph[$property] = $value;
        }

        return $this;
    }

    public function twitter(string|array $property, ?string $value = null): self
    {
        if (is_array($property)) {
            foreach ($property as $k => $v) {
                $this->twitter[(string) $k] = (string) $v;
            }
        } elseif ($value !== null) {
            $this->twitter[$property] = $value;
        }

        return $this;
    }

    public function addMeta(string $name, string $content, string $type = 'name'): self
    {
        $this->customMeta[] = [
            'name' => $name,
            'content' => $content,
            'type' => $type,
        ];
        return $this;
    }

    /**
     * Add a Schema.org structured data block (JSON-LD).
     */
    public function schema(string $type, array $data): self
    {
        $schema = array_merge([
            '@context' => 'https://schema.org',
            '@type' => $type,
        ], $data);

        $this->schemas[] = $schema;
        return $this;
    }

    /**
     * Generate complete HTML tags string for document head.
     */
    public function render(): string
    {
        $lines = [];

        // Title
        $title = htmlspecialchars($this->getTitle(), ENT_QUOTES, 'UTF-8');
        $lines[] = "<title>{$title}</title>";

        // Description
        if ($this->description !== null) {
            $desc = htmlspecialchars($this->description, ENT_QUOTES, 'UTF-8');
            $lines[] = "<meta name=\"description\" content=\"{$desc}\">";
        }

        // Keywords
        if (!empty($this->keywords)) {
            $kw = htmlspecialchars(implode(', ', $this->keywords), ENT_QUOTES, 'UTF-8');
            $lines[] = "<meta name=\"keywords\" content=\"{$kw}\">";
        }

        // Robots
        if (!empty($this->robots)) {
            $rob = htmlspecialchars($this->robots, ENT_QUOTES, 'UTF-8');
            $lines[] = "<meta name=\"robots\" content=\"{$rob}\">";
        }

        // Canonical
        if ($this->canonical !== null) {
            $can = htmlspecialchars($this->canonical, ENT_QUOTES, 'UTF-8');
            $lines[] = "<link rel=\"canonical\" href=\"{$can}\">";
        }

        // Custom Meta
        foreach ($this->customMeta as $meta) {
            $typeAttr = htmlspecialchars($meta['type'], ENT_QUOTES, 'UTF-8');
            $nameVal = htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8');
            $contentVal = htmlspecialchars($meta['content'], ENT_QUOTES, 'UTF-8');
            $lines[] = "<meta {$typeAttr}=\"{$nameVal}\" content=\"{$contentVal}\">";
        }

        // OpenGraph
        foreach ($this->openGraph as $prop => $val) {
            if ($val !== null && $val !== '') {
                $p = htmlspecialchars($prop, ENT_QUOTES, 'UTF-8');
                $v = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                $lines[] = "<meta property=\"og:{$p}\" content=\"{$v}\">";
            }
        }

        // Twitter
        foreach ($this->twitter as $prop => $val) {
            if ($val !== null && $val !== '') {
                $p = htmlspecialchars($prop, ENT_QUOTES, 'UTF-8');
                $v = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                $lines[] = "<meta name=\"twitter:{$p}\" content=\"{$v}\">";
            }
        }

        // Schema.org JSON-LD
        foreach ($this->schemas as $s) {
            $json = json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP);
            $lines[] = "<script type=\"application/ld+json\">\n{$json}\n</script>";
        }

        return implode("\n", $lines);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
