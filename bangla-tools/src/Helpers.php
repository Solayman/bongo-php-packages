<?php

declare(strict_types=1);

use Bongo\Packages\BanglaTools\Bangla;

if (!function_exists('bangla_number')) {
    function bangla_number(int|float|string $number): string
    {
        return Bangla::number($number);
    }
}

if (!function_exists('bangla_date')) {
    function bangla_date(DateTimeInterface|string|null $date = null): string
    {
        return Bangla::date($date);
    }
}

if (!function_exists('bangla_words')) {
    function bangla_words(int|float $number, bool $asCurrency = false): string
    {
        return Bangla::words($number, $asCurrency);
    }
}

if (!function_exists('bangla_taka')) {
    function bangla_taka(float|int|string $amount, bool $inBanglaDigits = true, string $symbol = '৳'): string
    {
        return Bangla::taka($amount, $inBanglaDigits, $symbol);
    }
}

if (!function_exists('bangla_slug')) {
    function bangla_slug(string $title, string $separator = '-'): string
    {
        return Bangla::slug($title, $separator);
    }
}
