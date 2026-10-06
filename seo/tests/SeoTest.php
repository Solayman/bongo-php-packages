<?php

declare(strict_types=1);

namespace Bongo\Packages\Seo\Tests;

use Bongo\Packages\Seo\Commands\SitemapGenerateCommand;
use Bongo\Packages\Seo\Seo;
use Bongo\Packages\Seo\SeoManager;
use Bongo\Packages\Seo\Sitemap;
use System\Testing\TestCase;

require_once __DIR__ . '/../src/Helpers.php';

class SeoTest extends TestCase
{
    public function setUp(): void
    {
        Seo::reset();
    }

    public function tearDown(): void
    {
        Seo::reset();
    }

    public function testTitleAndDescriptionRendering(): void
    {
        Seo::title('Home Page', 'My Awesome App')
            ->description('Explore the best features of our app.');

        $html = Seo::render();

        $this->assertContains('<title>Home Page - My Awesome App</title>', $html);
        $this->assertContains('<meta name="description" content="Explore the best features of our app.">', $html);
        $this->assertContains('<meta property="og:title" content="Home Page">', $html);
        $this->assertContains('<meta name="twitter:title" content="Home Page">', $html);
    }

    public function testKeywordsRobotsAndCanonical(): void
    {
        Seo::keywords(['php', 'mvc', 'framework'])
            ->robots('noindex, nofollow')
            ->canonical('https://example.com/canonical-url');

        $html = Seo::render();

        $this->assertContains('<meta name="keywords" content="php, mvc, framework">', $html);
        $this->assertContains('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertContains('<link rel="canonical" href="https://example.com/canonical-url">', $html);
        $this->assertContains('<meta property="og:url" content="https://example.com/canonical-url">', $html);
    }

    public function testImageAndOpenGraphTags(): void
    {
        Seo::image('https://example.com/cover.jpg', 1200, 630, 'Cover Image')
            ->openGraph('type', 'article');

        $html = Seo::render();

        $this->assertContains('<meta property="og:image" content="https://example.com/cover.jpg">', $html);
        $this->assertContains('<meta property="og:image:width" content="1200">', $html);
        $this->assertContains('<meta property="og:image:height" content="630">', $html);
        $this->assertContains('<meta property="og:image:alt" content="Cover Image">', $html);
        $this->assertContains('<meta property="og:type" content="article">', $html);
    }

    public function testTwitterCardTags(): void
    {
        Seo::twitter([
            'card' => 'summary',
            'site' => '@bongophp',
            'creator' => '@solayman',
        ]);

        $html = Seo::render();

        $this->assertContains('<meta name="twitter:card" content="summary">', $html);
        $this->assertContains('<meta name="twitter:site" content="@bongophp">', $html);
        $this->assertContains('<meta name="twitter:creator" content="@solayman">', $html);
    }

    public function testSchemaJsonLdGeneration(): void
    {
        Seo::schema('Product', [
            'name' => 'Premium Coffee Beans',
            'offers' => [
                '@type' => 'Offer',
                'price' => '24.99',
                'priceCurrency' => 'USD',
            ],
        ]);

        $html = Seo::render();

        $this->assertContains('<script type="application/ld+json">', $html);
        $this->assertContains('"@type": "Product"', $html);
        $this->assertContains('"name": "Premium Coffee Beans"', $html);
        $this->assertContains('"price": "24.99"', $html);
    }

    public function testCustomMetaTags(): void
    {
        Seo::manager()->addMeta('theme-color', '#3b82f6');

        $html = Seo::render();

        $this->assertContains('<meta name="theme-color" content="#3b82f6">', $html);
    }

    public function testGlobalSeoHelper(): void
    {
        $manager = seo('About Us', 'Bongo');
        $this->assertTrue($manager instanceof SeoManager);
        $this->assertContains('<title>About Us - Bongo</title>', seo()->render());
    }

    public function testXmlSitemapGenerationAndFileSaving(): void
    {
        $sitemap = new Sitemap();
        $sitemap->add('https://example.com', '2026-10-04', 1.0, 'daily')
            ->add('https://example.com/pricing', '2026-10-01', 0.8, 'weekly');

        $this->assertSame(2, $sitemap->count());

        $xml = $sitemap->toXml();
        $this->assertContains('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertContains('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertContains('<loc>https://example.com</loc>', $xml);
        $this->assertContains('<priority>1.0</priority>', $xml);
        $this->assertContains('<changefreq>daily</changefreq>', $xml);
        $this->assertContains('<loc>https://example.com/pricing</loc>', $xml);

        $tmpFile = sys_get_temp_dir() . '/test_sitemap_' . uniqid() . '.xml';
        $saved = $sitemap->save($tmpFile);
        $this->assertTrue($saved);
        $this->assertTrue(is_file($tmpFile));
        $content = file_get_contents($tmpFile);
        $this->assertContains('<loc>https://example.com</loc>', $content);
        @unlink($tmpFile);
    }

    public function testSitemapGenerateCommand(): void
    {
        $tmpOut = sys_get_temp_dir() . '/cli_sitemap_' . uniqid() . '.xml';
        $cmd = new SitemapGenerateCommand();

        ob_start();
        $status = $cmd->handle([], ['out' => $tmpOut]);
        $output = ob_get_clean();

        $this->assertSame(0, $status);
        $this->assertContains('XML sitemap generated successfully', $output);
        $this->assertTrue(is_file($tmpOut));
        @unlink($tmpOut);
    }

    public function testTemplateDirectivesAreRegistered(): void
    {
        require_once dirname(__DIR__) . '/bootstrap.php';
        $php = (new \System\View\ViewCompiler())->compile("@seo");
        $this->assertContains('Seo::render()', $php);
    }
}
