<?php

namespace Bongo\Packages\Discord;

class DiscordEmbed
{
    protected array $data = [];

    public function __construct(string $title = '', string $description = '', int $color = 0x3b82f6)
    {
        if ($title !== '') {
            $this->data['title'] = $title;
        }
        if ($description !== '') {
            $this->data['description'] = $description;
        }
        $this->data['color'] = $color;
    }

    public static function make(string $title = '', string $description = '', int $color = 0x3b82f6): self
    {
        return new self($title, $description, $color);
    }

    public function title(string $title): self
    {
        $this->data['title'] = $title;
        return $this;
    }

    public function description(string $description): self
    {
        $this->data['description'] = $description;
        return $this;
    }

    public function color(int $color): self
    {
        $this->data['color'] = $color;
        return $this;
    }

    public function url(string $url): self
    {
        $this->data['url'] = $url;
        return $this;
    }

    public function addField(string $name, string $value, bool $inline = true): self
    {
        if (!isset($this->data['fields'])) {
            $this->data['fields'] = [];
        }
        $this->data['fields'][] = [
            'name'   => $name,
            'value'  => $value,
            'inline' => $inline,
        ];
        return $this;
    }

    public function footer(string $text, ?string $iconUrl = null): self
    {
        $this->data['footer'] = ['text' => $text];
        if ($iconUrl) {
            $this->data['footer']['icon_url'] = $iconUrl;
        }
        return $this;
    }

    public function timestamp(?string $iso = null): self
    {
        $this->data['timestamp'] = $iso ?? gmdate('Y-m-d\TH:i:s\Z');
        return $this;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
