<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\DateTimeHelper;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;

final class WorkCalendar
{
    public function __construct(private readonly array $intervals)
    {
        if ($intervals === []) {
            throw new RuntimeException('Nenhum intervalo de trabalho cadastrado.');
        }
    }

    public function nextValidDateTime(DateTimeImmutable $dateTime): DateTimeImmutable
    {
        for ($offset = -1; $offset <= 10; $offset++) {
            $day = $dateTime->setTime(0, 0);

            if ($offset < 0) {
                $day = $day->sub(new DateInterval('P' . abs($offset) . 'D'));
            } elseif ($offset > 0) {
                $day = $day->add(new DateInterval('P' . $offset . 'D'));
            }

            foreach ($this->intervalInstancesForDay($day) as $interval) {
                if ($dateTime >= $interval['start'] && $dateTime < $interval['end']) {
                    return $dateTime;
                }

                if ($dateTime < $interval['start']) {
                    return $interval['start'];
                }
            }
        }

        throw new RuntimeException('Nao foi possivel encontrar o proximo horario valido.');
    }

    public function addWorkingMinutes(DateTimeImmutable $start, int $minutes): DateTimeImmutable
    {
        $current = $this->nextValidDateTime($start);
        $remaining = $minutes;

        while ($remaining > 0) {
            $interval = $this->findCurrentInterval($current);

            if ($interval === null) {
                $current = $this->nextValidDateTime($current);
                continue;
            }

            $available = (int) floor(($interval['end']->getTimestamp() - $current->getTimestamp()) / 60);

            if ($available <= 0) {
                $current = $this->nextValidDateTime($interval['end']);
                continue;
            }

            if ($remaining <= $available) {
                return DateTimeHelper::addMinutes($current, $remaining);
            }

            $remaining -= $available;
            $current = $this->nextValidDateTime($interval['end']);
        }

        return $current;
    }

    public function workingMinutesBetween(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        if ($end <= $start) {
            return 0;
        }

        $minutes = 0;
        $cursor = $start;

        while ($cursor < $end) {
            $validCursor = $this->nextValidDateTime($cursor);

            if ($validCursor >= $end) {
                break;
            }

            $interval = $this->findCurrentInterval($validCursor);

            if ($interval === null) {
                break;
            }

            $segmentEnd = $interval['end'] < $end ? $interval['end'] : $end;
            $minutes += (int) floor(($segmentEnd->getTimestamp() - $validCursor->getTimestamp()) / 60);
            $cursor = $segmentEnd;
        }

        return $minutes;
    }

    private function findCurrentInterval(DateTimeImmutable $dateTime): ?array
    {
        foreach ($this->intervalInstancesForDay($dateTime->setTime(0, 0)->sub(new DateInterval('P1D'))) as $interval) {
            if ($dateTime >= $interval['start'] && $dateTime < $interval['end']) {
                return $interval;
            }
        }

        foreach ($this->intervalInstancesForDay($dateTime->setTime(0, 0)) as $interval) {
            if ($dateTime >= $interval['start'] && $dateTime < $interval['end']) {
                return $interval;
            }
        }

        return null;
    }

    private function intervalInstancesForDay(DateTimeImmutable $day): array
    {
        $instances = [];

        foreach ($this->intervals as $interval) {
            [$startHour, $startMinute] = array_map('intval', explode(':', $interval['start']));
            [$endHour, $endMinute] = array_map('intval', explode(':', $interval['end']));

            $start = $day->setTime($startHour, $startMinute);
            $end = $day->setTime($endHour, $endMinute);

            if ($end <= $start) {
                $end = $end->add(new DateInterval('P1D'));
            }

            $instances[] = [
                'start' => $start,
                'end' => $end,
            ];
        }

        usort(
            $instances,
            static fn (array $left, array $right): int => $left['start'] <=> $right['start']
        );

        return $instances;
    }
}
