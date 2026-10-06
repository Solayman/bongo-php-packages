<?php

namespace Bongo\Packages\BdLocations;

class Location
{
    protected static ?array $divisions = null;
    protected static ?array $districts = null;
    protected static ?array $upazilas = null;

    public static function divisions(): array
    {
        if (static::$divisions === null) {
            static::$divisions = require __DIR__ . '/Data/divisions.php';
        }
        return static::$divisions;
    }

    public static function division(int|string $idOrName): ?array
    {
        $divisions = static::divisions();
        if (is_numeric($idOrName)) {
            return $divisions[(int) $idOrName] ?? null;
        }

        $needle = strtolower(trim((string) $idOrName));
        foreach ($divisions as $d) {
            if (strtolower($d['name']) === $needle || strtolower($d['bn_name']) === $needle || strtolower($d['code'] ?? '') === $needle) {
                return $d;
            }
        }
        return null;
    }

    public static function districts(?int $divisionId = null): array
    {
        if (static::$districts === null) {
            static::$districts = require __DIR__ . '/Data/districts.php';
        }

        if ($divisionId === null) {
            return static::$districts;
        }

        return array_values(array_filter(static::$districts, fn($d) => (int) $d['division_id'] === (int) $divisionId));
    }

    public static function district(int|string $idOrName): ?array
    {
        $districts = static::districts();
        if (is_numeric($idOrName)) {
            return $districts[(int) $idOrName] ?? null;
        }

        $needle = strtolower(trim((string) $idOrName));
        foreach ($districts as $d) {
            if (strtolower($d['name']) === $needle || strtolower($d['bn_name']) === $needle) {
                return $d;
            }
        }
        return null;
    }

    public static function upazilas(?int $districtId = null): array
    {
        if (static::$upazilas === null) {
            static::$upazilas = require __DIR__ . '/Data/upazilas.php';
        }

        if ($districtId === null) {
            return static::$upazilas;
        }

        return array_values(array_filter(static::$upazilas, fn($u) => (int) $u['district_id'] === (int) $districtId));
    }

    public static function upazila(int|string $idOrName): ?array
    {
        $upazilas = static::upazilas();
        if (is_numeric($idOrName)) {
            foreach ($upazilas as $u) {
                if ((int) $u['id'] === (int) $idOrName) {
                    return $u;
                }
            }
            return null;
        }

        $needle = strtolower(trim((string) $idOrName));
        foreach ($upazilas as $u) {
            if (strtolower($u['name']) === $needle || strtolower($u['bn_name']) === $needle) {
                return $u;
            }
        }
        return null;
    }

    public static function isInsideDhaka(int|string $districtIdOrName): bool
    {
        $district = static::district($districtIdOrName);
        if (!$district) {
            return false;
        }
        return $district['zone'] === 'inside_dhaka';
    }

    public static function isDhakaSuburb(int|string $districtIdOrName): bool
    {
        $district = static::district($districtIdOrName);
        if (!$district) {
            return false;
        }
        return $district['zone'] === 'dhaka_suburb';
    }

    public static function shippingZone(int|string $districtIdOrName): string
    {
        $district = static::district($districtIdOrName);
        if (!$district) {
            return 'outside_dhaka';
        }
        return $district['zone'] ?? 'outside_dhaka';
    }

    public static function search(string $query): array
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return [];
        }

        $results = ['divisions' => [], 'districts' => [], 'upazilas' => []];

        foreach (static::divisions() as $div) {
            if (str_contains(strtolower($div['name']), $q) || str_contains($div['bn_name'], $q)) {
                $results['divisions'][] = $div;
            }
        }

        foreach (static::districts() as $dist) {
            if (str_contains(strtolower($dist['name']), $q) || str_contains($dist['bn_name'], $q)) {
                $results['districts'][] = $dist;
            }
        }

        foreach (static::upazilas() as $upz) {
            if (str_contains(strtolower($upz['name']), $q) || str_contains($upz['bn_name'], $q) || str_contains($upz['post_code'] ?? '', $q)) {
                $results['upazilas'][] = $upz;
            }
        }

        return $results;
    }

    public static function divisionOptions(?int $selected = null, string $locale = 'en'): string
    {
        $html = '<option value="">' . ($locale === 'bn' ? '-- বিভাগ নির্বাচন করুন --' : '-- Select Division --') . '</option>' . "\n";
        foreach (static::divisions() as $d) {
            $isSelected = ($selected !== null && (int) $selected === (int) $d['id']) ? ' selected' : '';
            $label = $locale === 'bn' ? $d['bn_name'] : $d['name'];
            $html .= sprintf('<option value="%d"%s>%s</option>' . "\n", (int) $d['id'], $isSelected, htmlspecialchars($label, ENT_QUOTES, 'UTF-8'));
        }
        return $html;
    }

    public static function districtOptions(?int $divisionId = null, ?int $selected = null, string $locale = 'en'): string
    {
        $html = '<option value="">' . ($locale === 'bn' ? '-- জেলা নির্বাচন করুন --' : '-- Select District --') . '</option>' . "\n";
        foreach (static::districts($divisionId) as $d) {
            $isSelected = ($selected !== null && (int) $selected === (int) $d['id']) ? ' selected' : '';
            $label = $locale === 'bn' ? $d['bn_name'] : $d['name'];
            $html .= sprintf('<option value="%d"%s data-zone="%s">%s</option>' . "\n", (int) $d['id'], $isSelected, htmlspecialchars($d['zone'], ENT_QUOTES, 'UTF-8'), htmlspecialchars($label, ENT_QUOTES, 'UTF-8'));
        }
        return $html;
    }

    public static function upazilaOptions(?int $districtId = null, ?int $selected = null, string $locale = 'en'): string
    {
        $html = '<option value="">' . ($locale === 'bn' ? '-- উপজেলা নির্বাচন করুন --' : '-- Select Upazila --') . '</option>' . "\n";
        foreach (static::upazilas($districtId) as $u) {
            $isSelected = ($selected !== null && (int) $selected === (int) $u['id']) ? ' selected' : '';
            $label = $locale === 'bn' ? $u['bn_name'] : $u['name'];
            $post = !empty($u['post_code']) ? " ({$u['post_code']})" : '';
            $html .= sprintf('<option value="%d"%s>%s%s</option>' . "\n", (int) $u['id'], $isSelected, htmlspecialchars($label, ENT_QUOTES, 'UTF-8'), htmlspecialchars($post, ENT_QUOTES, 'UTF-8'));
        }
        return $html;
    }
}
