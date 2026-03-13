<?php

declare(strict_types=1);

namespace App\Support;

use DateInterval;
use DateTimeImmutable;

final class DateTimeHelper
{
    public static function fromLocalInput(string $value): ?DateTimeImmutable
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $normalized = str_replace('T', ' ', $trimmed);
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i'];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $normalized);
            if ($date instanceof DateTimeImmutable) {
                return $date;
            }
        }

        return null;
    }

    public static function minutesFromDuration(string $duration): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $duration));

        return ($hours * 60) + $minutes;
    }

    public static function durationFromMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $remainingMinutes);
    }

    public static function formatDate(?DateTimeImmutable $dateTime): string
    {
        return $dateTime?->format('d/m/Y') ?? '';
    }

    public static function formatTime(?DateTimeImmutable $dateTime): string
    {
        return $dateTime?->format('H:i') ?? '';
    }

    public static function formatDateTime(?DateTimeImmutable $dateTime): string
    {
        return $dateTime?->format('d/m/Y H:i') ?? '';
    }

    public static function addMinutes(DateTimeImmutable $dateTime, int $minutes): DateTimeImmutable
    {
        if ($minutes === 0) {
            return $dateTime;
        }

        return $dateTime->add(new DateInterval('PT' . $minutes . 'M'));
    }
}
