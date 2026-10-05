<?php

declare(strict_types=1);

namespace Bongo\Packages\BanglaTools;

use DateTime;
use DateTimeInterface;

class BanglaDate
{
    protected static array $months = [
        1  => 'বৈশাখ',
        2  => 'জ্যৈষ্ঠ',
        3  => 'আষাঢ়',
        4  => 'শ্রাবণ',
        5  => 'ভাদ্র',
        6  => 'আশ্বিন',
        7  => 'কার্তিক',
        8  => 'অগ্রহায়ণ',
        9  => 'পৌষ',
        10 => 'মাঘ',
        11 => 'ফাল্গুন',
        12 => 'চৈত্র',
    ];

    protected static array $seasons = [
        1  => 'গ্রীষ্ম',
        2  => 'গ্রীষ্ম',
        3  => 'বর্ষা',
        4  => 'বর্ষা',
        5  => 'শরৎ',
        6  => 'শরৎ',
        7  => 'হেমন্ত',
        8  => 'হেমন্ত',
        9  => 'শীত',
        10 => 'শীত',
        11 => 'বসন্ত',
        12 => 'বসন্ত',
    ];

    protected static array $weekdays = [
        'Sunday'    => 'রবিবার',
        'Monday'    => 'সোমবার',
        'Tuesday'   => 'মঙ্গলবার',
        'Wednesday' => 'বুধবার',
        'Thursday'  => 'বৃহস্পতিবার',
        'Friday'    => 'শুক্রবার',
        'Saturday'  => 'শনিবার',
    ];

    /**
     * Convert Gregorian date into Bengali calendar date array.
     *
     * @return array [day => int, month => int, year => int, month_name => string, season => string, weekday => string]
     */
    public static function convert(DateTimeInterface|string|null $date = null): array
    {
        if ($date === null) {
            $dt = new DateTime();
        } elseif (is_string($date)) {
            $dt = new DateTime($date);
        } else {
            $dt = $date;
        }

        $gy = (int) $dt->format('Y');
        $gm = (int) $dt->format('n');
        $gd = (int) $dt->format('j');
        $weekday = self::$weekdays[$dt->format('l')] ?? '';

        $isLeap = ($gy % 4 === 0 && ($gy % 100 !== 0 || $gy % 400 === 0));

        // Bangla month lengths (revised Bangla Academy rules)
        $monthDays = [
            1  => 31, // Boishakh
            2  => 31, // Joishtho
            3  => 31, // Asharh
            4  => 31, // Shrabon
            5  => 31, // Bhadro
            6  => 31, // Ashwin
            7  => 30, // Kartik
            8  => 30, // Agrahayan
            9  => 30, // Poush
            10 => 30, // Magh
            11 => $isLeap ? 30 : 29, // Falgun
            12 => 30, // Choitro
        ];

        // Approximate starting Gregorian day and month for each Bangla month
        // April 14 is Boishakh 1
        if (($gm === 4 && $gd >= 14) || $gm > 4 || ($gm < 4) || ($gm === 4 && $gd < 14)) {
            // Calculate Bongabdo Year
            $by = ($gm < 4 || ($gm === 4 && $gd < 14)) ? $gy - 594 : $gy - 593;
        } else {
            $by = $gy - 593;
        }

        // Map month and day
        $bm = 1;
        $bd = 1;

        if ($gm === 4 && $gd >= 14) {
            $bm = 1;
            $bd = $gd - 13;
        } elseif ($gm === 5) {
            if ($gd <= 14) {
                $bm = 1;
                $bd = $gd + 17;
            } else {
                $bm = 2;
                $bd = $gd - 14;
            }
        } elseif ($gm === 6) {
            if ($gd <= 14) {
                $bm = 2;
                $bd = $gd + 17;
            } else {
                $bm = 3;
                $bd = $gd - 14;
            }
        } elseif ($gm === 7) {
            if ($gd <= 15) {
                $bm = 3;
                $bd = $gd + 16;
            } else {
                $bm = 4;
                $bd = $gd - 15;
            }
        } elseif ($gm === 8) {
            if ($gd <= 15) {
                $bm = 4;
                $bd = $gd + 16;
            } else {
                $bm = 5;
                $bd = $gd - 15;
            }
        } elseif ($gm === 9) {
            if ($gd <= 15) {
                $bm = 5;
                $bd = $gd + 16;
            } else {
                $bm = 6;
                $bd = $gd - 15;
            }
        } elseif ($gm === 10) {
            if ($gd <= 16) {
                $bm = 6;
                $bd = $gd + 15;
            } else {
                $bm = 7;
                $bd = $gd - 16;
            }
        } elseif ($gm === 11) {
            if ($gd <= 15) {
                $bm = 7;
                $bd = $gd + 15;
            } else {
                $bm = 8;
                $bd = $gd - 15;
            }
        } elseif ($gm === 12) {
            if ($gd <= 15) {
                $bm = 8;
                $bd = $gd + 15;
            } else {
                $bm = 9;
                $bd = $gd - 15;
            }
        } elseif ($gm === 1) {
            if ($gd <= 14) {
                $bm = 9;
                $bd = $gd + 16;
            } else {
                $bm = 10;
                $bd = $gd - 14;
            }
        } elseif ($gm === 2) {
            if ($gd <= 13) {
                $bm = 10;
                $bd = $gd + 17;
            } else {
                $bm = 11;
                $bd = $gd - 13;
            }
        } elseif ($gm === 3) {
            $falgunDays = $isLeap ? 30 : 29;
            if ($gd <= 14) {
                $bm = 11;
                $bd = $gd + ($falgunDays - 14);
            } else {
                $bm = 12;
                $bd = $gd - 14;
            }
        } elseif ($gm === 4 && $gd < 14) {
            $bm = 12;
            $bd = $gd + 17;
        }

        return [
            'day'        => $bd,
            'month'      => $bm,
            'year'       => $by,
            'month_name' => self::$months[$bm],
            'season'     => self::$seasons[$bm],
            'weekday'    => $weekday,
        ];
    }

    /**
     * Format a date into localized Bengali string (e.g. "১ বৈশাখ ১৪৩১, রবিবার").
     */
    public static function format(DateTimeInterface|string|null $date = null, string $format = 'd F Y'): string
    {
        $conv = self::convert($date);
        $dayBn = BanglaNumber::toBangla($conv['day']);
        $yearBn = BanglaNumber::toBangla($conv['year']);
        $monthName = $conv['month_name'];
        $weekday = $conv['weekday'];

        return "{$dayBn} {$monthName} {$yearBn}, {$weekday}";
    }
}
