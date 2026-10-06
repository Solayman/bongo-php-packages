<?php

declare(strict_types=1);

namespace Bongo\Packages\BanglaTools\Tests;

use Bongo\Packages\BanglaTools\Bangla;
use Bongo\Packages\BanglaTools\BanglaDate;
use Bongo\Packages\BanglaTools\BanglaNumber;
use Bongo\Packages\BanglaTools\BanglaText;
use System\Testing\TestCase;

class BanglaToolsTest extends TestCase
{
    public function testDigitConversions(): void
    {
        $en = '0123456789';
        $bn = '০১২৩৪৫৬৭৮৯';

        $this->assertSame($bn, BanglaNumber::toBangla($en));
        $this->assertSame($en, BanglaNumber::toEnglish($bn));
        $this->assertSame('১২,৩৪৫', BanglaNumber::toBangla('12,345'));
    }

    public function testNumberToWords(): void
    {
        $this->assertSame('শূন্য', BanglaNumber::toWords(0));
        $this->assertSame('এক', BanglaNumber::toWords(1));
        $this->assertSame('দশ', BanglaNumber::toWords(10));
        $this->assertSame('একশত পঞ্চাশ', BanglaNumber::toWords(150));
        $this->assertSame('এক হাজার পাঁচশত', BanglaNumber::toWords(1500));
        $this->assertSame('পঁচিশ হাজার পাঁচশত', BanglaNumber::toWords(25500));
        $this->assertSame('এক কোটি', BanglaNumber::toWords(10000000));

        // As currency
        $this->assertSame('একশত পঞ্চাশ টাকা মাত্র', BanglaNumber::toWords(150, true));
        $this->assertSame('শূন্য টাকা মাত্র', BanglaNumber::toWords(0, true));
    }

    public function testCurrencyFormatting(): void
    {
        // 1,500 -> ৳ ১,৫০০
        $this->assertSame('৳ ১,৫০০', BanglaNumber::formatCurrency(1500));

        // 1,50,000 -> ৳ ১,৫০,০০০
        $this->assertSame('৳ ১,৫০,০০০', BanglaNumber::formatCurrency(150000));

        // In English digits
        $this->assertSame('৳ 1,50,000', BanglaNumber::formatCurrency(150000, false));

        // With decimals
        $this->assertSame('৳ ১,৫০,০০০.৫০', BanglaNumber::formatCurrency(150000.50));
    }

    public function testBanglaCalendarAndDate(): void
    {
        // April 14, 2024 is 1 Boishakh 1431
        $conv = BanglaDate::convert('2024-04-14');
        $this->assertSame(1, $conv['day']);
        $this->assertSame(1, $conv['month']);
        $this->assertSame('বৈশাখ', $conv['month_name']);
        $this->assertSame('গ্রীষ্ম', $conv['season']);
        $this->assertSame(1431, $conv['year']);
        $this->assertSame('রবিবার', $conv['weekday']);

        $formatted = BanglaDate::format('2024-04-14');
        $this->assertSame('১ বৈশাখ ১৪৩১, রবিবার', $formatted);
    }

    public function testBanglaTextNormalizationAndSlug(): void
    {
        $dirty = "আমার   সোনার \u{200B}বাংলা";
        $cleaned = BanglaText::normalize($dirty);
        $this->assertSame('আমার সোনার বাংলা', $cleaned);

        $slug = BanglaText::slug('আমার সোনার বাংলা - ১ম পর্ব!');
        $this->assertSame('আমার-সোনার-বাংলা-১ম-পর্ব', $slug);
    }

    public function testFacadeAndGlobalHelpers(): void
    {
        require_once dirname(__DIR__) . '/src/Helpers.php';

        $this->assertSame('১২৩', Bangla::number(123));
        $this->assertSame('123', Bangla::enNumber('১২৩'));
        $this->assertSame('একশত টাকা মাত্র', Bangla::words(100, true));
        $this->assertSame('৳ ১,০০০', Bangla::taka(1000));

        // Helpers
        $this->assertSame('৫০০', bangla_number(500));
        $this->assertSame('পাঁচশত', bangla_words(500));
        $this->assertSame('৳ ৫০০', bangla_taka(500));
        $this->assertSame('নতুন-পোস্ট', bangla_slug('নতুন পোস্ট'));
    }

    public function testTemplateDirectivesAreRegistered(): void
    {
        require_once dirname(__DIR__) . '/bootstrap.php';
        $php = (new \System\View\ViewCompiler())->compile("@taka(1500) @bangla_date");
        $this->assertContains('Bangla::taka(1500)', $php);
        $this->assertContains('Bangla::date(null)', $php);
    }
}
