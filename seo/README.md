# Bongo SEO Package



<p align="center">
  <img src="docs/screenshot.svg" alt="seo screenshot" width="800">
</p>
A comprehensive, zero-dependency Search Engine Optimization (SEO), OpenGraph, Twitter Cards, Schema.org JSON-LD, and dynamic XML Sitemap suite for Bongo MVC.

---

## Features

- **Zero Dependencies:** Pure PHP 8.0+ implementation without external packages.
- **Fluent Builder:** Chainable `Seo::title()`, `Seo::description()`, `Seo::image()`, `Seo::canonical()`.
- **OpenGraph & Twitter Cards:** Full support for social sharing previews (Facebook, LinkedIn, X/Twitter).
- **Schema.org Structured Data:** Clean JSON-LD generator for Products, Articles, Organizations, FAQs, and Breadcrumbs.
- **Dynamic XML Sitemap:** `Sitemap` generator adhering to `sitemaps.org` 0.9 protocol with priority, changefreq, and lastmod.
- **Template Integration:** Use `@seo` directive or `<x-seo::head />` in your layouts.
- **CLI Tool:** `php bongo sitemap:generate` to produce static `public/sitemap.xml`.

---

## Installation

```bash
# Enable in config/packages.php and install
php bongo add seo
```

---

## Usage

### Setting Metadata

```php
use Bongo\Packages\Seo\Seo;

// In your controller or action:
Seo::title('Wireless Noise-Canceling Headphones')
   ->description('Shop premium headphones with 40-hour battery life and spatial audio.')
   ->keywords(['headphones', 'audio', 'wireless', 'tech'])
   ->canonical('https://example.com/products/headphones')
   ->image('https://example.com/images/headphones.jpg', width: 1200, height: 630, alt: 'Headphones preview')
   ->schema('Product', [
       'name' => 'Wireless Headphones',
       'image' => 'https://example.com/images/headphones.jpg',
       'offers' => [
           '@type' => 'Offer',
           'price' => '199.99',
           'priceCurrency' => 'USD',
           'availability' => 'https://schema.org/InStock',
       ],
   ]);
```

### In Your Layout View

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @seo
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    @yield('content')
</body>
</html>
```

### Generating XML Sitemaps

```php
use Bongo\Packages\Seo\Sitemap;

$sitemap = new Sitemap();

$sitemap->add('https://example.com', priority: 1.0, changeFrequency: 'daily')
        ->add('https://example.com/about', priority: 0.8, changeFrequency: 'monthly')
        ->add('https://example.com/contact', priority: 0.5);

// Get XML string
$xml = $sitemap->toXml();

// Save to disk
$sitemap->save('public/sitemap.xml');
```

---

## License

MIT License. Open source for Bongo MVC projects.
