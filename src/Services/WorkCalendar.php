<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\DateTimeHelper;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;

final class WorkCalendar
{
    public function __construct(
        private readonly array $intervals,
        private readonly array $workingDays = [1, 2, 3, 4, 5],
        private readonly array $holidays = []
    ) {
        if ($intervals === []) {
            throw new RuntimeException('Nenhum intervalo de trabalho cadastrado.');
        }
    }

    public function nextValidDateTime(DateTimeImmutable $dateTime): DateTimeImmutable
    {
        $currentInterval = $this->findCurrentInterval($dateTime);

        if ($currentInterval !== null) {
            return $dateTime;
        }

        for ($offset = 0; $offset <= 30; $offset++) {
            $day = $dateTime->setTime(0, 0)->add(new DateInterval('P' . $offset . 'D'));

            if (!$this->isWorkingDay($day)) {
                continue;
            }

            foreach ($this->intervalInstancesForDay($day) as $interval) {
                if ($dateTime < $interval['start']) {
                    return $interval['start'];
                }
            }
        }

        throw new RuntimeException('Nao foi possivel encontrar o proximo horario valido.');
    }

    public function addWorkingMinutes(DateTimeImmutable $start, int $minutes): DateTimeImmutable
    {
        return $this->buildWorkingPlan($start, $minutes)['end'];
    }

    public function buildWorkingPlan(DateTimeImmutable $start, int $minutes): array
    {
        $current = $this->nextValidDateTime($start);
        $remaining = $minutes;
        $segments = [];

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

            $consumed = min($remaining, $available);
            $segmentEnd = DateTimeHelper::addMinutes($current, $consumed);

            $segments[] = [
                'start' => $current,
                'end' => $segmentEnd,
                'minutes' => $consumed,
                'interval_start' => $interval['start'],
                'interval_end' => $interval['end'],
                'interval_minutes' => (int) floor(($interval['end']->getTimestamp() - $interval['start']->getTimestamp()) / 60),
            ];

            if ($remaining <= $available) {
                return [
                    'end' => $segmentEnd,
                    'segments' => $segments,
                ];
            }

            $remaining -= $consumed;
            $current = $this->nextValidDateTime($interval['end']);
        }

        return [
            'end' => $current,
            'segments' => $segments,
        ];
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

    private function isWorkingDay(DateTimeImmutable $day): bool
    {
        $dayNumber = (int) $day->format('N');
        $dayKey = $day->format('Y-m-d');

        return in_array($dayNumber, $this->workingDays, true)
            && !in_array($dayKey, $this->holidays, true);
    }

    private function findCurrentInterval(DateTimeImmutable $dateTime): ?array
    {
        $previousDay = $dateTime->setTime(0, 0)->sub(new DateInterval('P1D'));
        foreach ($this->intervalInstancesForDay($previousDay) as $interval) {
            if ($dateTime >= $interval['start'] && $dateTime < $interval['end']) {
                return $interval;
            }
        }

        $currentDay = $dateTime->setTime(0, 0);
        foreach ($this->intervalInstancesForDay($currentDay) as $interval) {
            if ($dateTime >= $interval['start'] && $dateTime < $interval['end']) {
                return $interval;
            }
        }

        return null;
    }

    private function intervalInstancesForDay(DateTimeImmutable $day): array
    {
        if (!$this->isWorkingDay($day)) {
            return [];
        }

        $instances = [];

        foreach ($this->intervals as $interval) {
            [$startHour, $startMinute] = array_map('intval', explode(':', $interval['start']));
            [$endHour, $endMinute] = array_map('intval', explode(':', $interval['end']));

            $start = $day->setTime($startHour, $startMinute);
            $end = $day->setTime($endHour, $endMinute);

            if ($end <= $start) {
                $nextDay = $day->add(new DateInterval('P1D'));

                if (!$this->isWorkingDay($nextDay)) {
                    continue;
                }

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
