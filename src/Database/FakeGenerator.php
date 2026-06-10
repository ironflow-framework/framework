<?php

declare(strict_types=1);

namespace Ironflow\Database;

/**
 * Minimal fake data generator. No external dependencies.
 */
class FakeGenerator
{
    private static array $firstNames = ['Alice', 'Bob', 'Charlie', 'Diana', 'Ethan', 'Fiona', 'George', 'Hannah', 'Ivan', 'Julia'];
    private static array $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Wilson', 'Moore'];
    private static array $words = ['lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit', 'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore'];
    private static array $domains = ['example.com', 'test.org', 'demo.net', 'sample.io', 'fake.dev'];

    public function name(): string
    {
        return self::$firstNames[array_rand(self::$firstNames)] . ' ' . self::$lastNames[array_rand(self::$lastNames)];
    }

    public function firstName(): string
    {
        return self::$firstNames[array_rand(self::$firstNames)];
    }

    public function lastName(): string
    {
        return self::$lastNames[array_rand(self::$lastNames)];
    }

    public function email(): string
    {
        $name = strtolower($this->firstName()) . mt_rand(1, 999);
        $domain = self::$domains[array_rand(self::$domains)];
        return "{$name}@{$domain}";
    }

    public function password(string $plain = 'password'): string
    {
        return \Ironflow\Auth\Hash::make($plain);
    }

    public function sentence(int $wordCount = 8): string
    {
        $words = [];
        for ($i = 0; $i < $wordCount; $i++) {
            $words[] = self::$words[array_rand(self::$words)];
        }
        return ucfirst(implode(' ', $words)) . '.';
    }

    public function paragraph(int $sentences = 3): string
    {
        $parts = [];
        for ($i = 0; $i < $sentences; $i++) {
            $parts[] = $this->sentence(mt_rand(6, 12));
        }
        return implode(' ', $parts);
    }

    public function words(int $n = 3): string
    {
        $words = [];
        for ($i = 0; $i < $n; $i++) {
            $words[] = self::$words[array_rand(self::$words)];
        }
        return implode(' ', $words);
    }

    public function slug(): string
    {
        return str_slug($this->words(3));
    }

    public function boolean(): bool
    {
        return (bool) mt_rand(0, 1);
    }

    public function numberBetween(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    public function dateTimeBetween(string $start = '-1 year', string $end = 'now'): string
    {
        $startTs = strtotime($start);
        $endTs = strtotime($end);
        return date('Y-m-d H:i:s', mt_rand($startTs, $endTs));
    }

    public function randomElement(array $elements): mixed
    {
        return $elements[array_rand($elements)];
    }

    public function url(): string
    {
        return 'https://www.' . self::$domains[array_rand(self::$domains)];
    }
}
