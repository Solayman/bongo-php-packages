# Bongo Bangla Tools (`bangla-tools`)

[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)](#testing)
[![PHP](https://img.shields.io/badge/php-8.0%2B-blue.svg)](https://php.net)
[![Dependencies](https://img.shields.io/badge/dependencies-0%20required-orange.svg)](#zero-dependencies)

Comprehensive Bengali linguistic and localization toolkit for the **Bongo PHP Framework**. Zero third-party dependencies, pure PHP 8.0+.

---

## 🚀 Features

- **Number & Digit Converter:**
  - English digits to Bengali numerals (`12345` -> `১২৩৪৫`).
  - Bengali numerals to English digits (`১২৩৪৫` -> `12345`).
- **Number to Bengali Words:**
  - Converts numbers into formal Bengali words (e.g. `150` -> `একশত পঞ্চাশ`).
  - Currency words format: `একশত পঞ্চাশ টাকা মাত্র`.
  - Supports কোটি (crore), লাখ (lakh), হাজার (thousand), শত (hundred), and paisa decimals.
- **South Asian Bangladeshi Currency Notation:**
  - Standard Bangladeshi comma groupings (`1,50,000.00` ৳ / `৳ ১,৫০,০০০.০০`).
- **Bengali Calendar & Bongabdo Converter:**
  - Converts Gregorian dates to Bongabdo calendar dates according to revised Bangla Academy standards.
  - Returns day, month (বৈশাখ to চৈত্র), season (গ্রীষ্ম to বসন্ত), Bongabdo year, and weekday.
- **Bengali Text & SEO Slug:**
  - Unicode text cleaner (removes invisible zero-width spaces `\u200B` and abnormal whitespace).
  - Bengali SEO slug generator retaining Bangla characters without mangling (`আমার-সোনার-বাংলা`).
- **Template Directives & Global Helpers:**
  - `@bangla_number($val)`, `@bangla_date($date)`, `@taka($amount)`.
  - `bangla_number()`, `bangla_date()`, `bangla_words()`, `bangla_taka()`, `bangla_slug()`.

---

## 📦 Installation

```bash
php bongo add bangla-tools
```

---

## ⚙️ Configuration (`config/bangla_tools.php`)

```php
return [
    'currency_symbol'   => '৳',
    'use_bangla_digits' => true,
];
```

---

## 💡 Usage

### In Views & Templates

```html
<p>Total Price: @taka(15500.50)</p> <!-- Outputs: ৳ ১৫,৫০০.৫০ -->
<p>Published on: @bangla_date('2024-04-14')</p> <!-- Outputs: ১ বৈশাখ ১৪৩১, রবিবার -->
<p>Items in cart: @bangla_number(5)</p> <!-- Outputs: ৫ -->
```

### In PHP & Controllers

```php
use Bongo\Packages\BanglaTools\Bangla;

// Currency & Words
$taka = Bangla::taka(250000); // ৳ ২,৫০,০০০
$inWords = Bangla::words(250000, asCurrency: true); // দুই লাখ পঞ্চাশ হাজার টাকা মাত্র

// Date & Calendar
$date = Bangla::date('now'); // Localized Bangla date string
$calendar = Bangla::calendar('now'); // Array with [day, month_name, season, year, weekday]

// Slug & Text
$slug = Bangla::slug('নতুন আপডেট ও ফিচার সমূহ'); // নতুন-আপডেট-ও-ফিচার-সমূহ
```
