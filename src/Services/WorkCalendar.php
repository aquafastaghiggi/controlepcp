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
        private readonly array $holidays = [],
        private readonly array $dayOrdersByDate = []
    ) {
        if ($intervals === []) {
            throw new RuntimeException('Nenhum intervalo de trabalho cadastrado.');
        }
    }

    private function isOrderAllowedForDay(int $orderNumber, DateTimeImmutable $day): bool
    {
        $dateKey = $day->format('Y-m-d');

        // Regra A: se a data existir em orders_by_day, ela é soberana (sem fallback).
        if (array_key_exists($dateKey, $this->dayOrdersByDate)) {
            $dayOrders = $this->dayOrdersByDate[$dateKey] ?? null;
            if (!is_array($dayOrders)) {
                return false;
            }

            $hasAnyAllowedOrder = false;
            for ($order = 1; $order <= 4; $order++) {
                if (!empty($dayOrders[$order])) {
                    $hasAnyAllowedOrder = true;
                    break;
                }
            }

            if (!$hasAnyAllowedOrder) {
                return false;
            }

            return !empty($dayOrders[$orderNumber]);
        }

        // Data ausente: fallback antigo (ordem não é filtrada por data).
        return true;
    }

    public function nextValidDateTime(DateTimeImmutable $dateTime): DateTimeImmutable
    {
        $currentInterval = $this->findCurrentInterval($dateTime);

        if ($currentInterval !== null) {
            return $dateTime;
        }

        for ($offset = 0; $offset <= 30; $offset++) {
            $day = $dateTime->setTime(0, 0)->add(new DateInterval('P' . $offset . 'D'));

            if (!$this->hasIntervalsForDay($day)) {
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

    private function hasIntervalsForDay(DateTimeImmutable $day): bool
    {
        return $this->intervalInstancesForDay($day) !== [];
    }

    private function holidayDateKeys(): array
    {
        $keys = [];

        foreach ($this->holidays as $holiday) {
            if (is_array($holiday)) {
                $date = (string) ($holiday['date'] ?? '');
            } else {
                $date = (string) $holiday;
            }

            $date = trim($date);
            if ($date !== '') {
                $keys[] = $date;
            }
        }

        return array_values(array_unique($keys));
    }

    private function isCalendarOpenForDay(DateTimeImmutable $day): bool
    {
        return !in_array($day->format('Y-m-d'), $this->holidayDateKeys(), true);
    }

    private function isIntervalAllowedForDay(array $interval, DateTimeImmutable $day): bool
    {
        if (!$this->isCalendarOpenForDay($day)) {
            return false;
        }

        $dayNumber = (int) $day->format('N');
        $intervalDays = array_values(array_filter(
            array_map('intval', $interval['days'] ?? $this->workingDays),
            static fn (int $value): bool => $value >= 1 && $value <= 7
        ));

        return in_array($dayNumber, $intervalDays, true);
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
        if (!$this->isCalendarOpenForDay($day)) {
            return [];
        }

        $dateKey = $day->format('Y-m-d');
        $hasDayOrders = array_key_exists($dateKey, $this->dayOrdersByDate);
        $allowedOrdersForDate = $hasDayOrders && is_array($this->dayOrdersByDate[$dateKey] ?? null)
            ? $this->dayOrdersByDate[$dateKey]
            : [];

        if ($hasDayOrders) {
            $hasAnyAllowedOrder = false;
            for ($order = 1; $order <= 4; $order++) {
                if (!empty($allowedOrdersForDate[$order])) {
                    $hasAnyAllowedOrder = true;
                    break;
                }
            }

            // Data presente na agenda + todas as ordens desmarcadas = dia bloqueado (sem fallback).
            if (!$hasAnyAllowedOrder) {
                return [];
            }
        }

        $instances = [];

        foreach ($this->intervals as $index => $interval) {
            $orderNumber = isset($interval['order']) ? (int) $interval['order'] : ($index + 1);

            if ($hasDayOrders) {
                if (empty($allowedOrdersForDate[$orderNumber])) {
                    continue;
                }
            }

            if (!$this->isIntervalAllowedForDay($interval, $day)) {
                continue;
            }

            [$startHour, $startMinute] = array_map('intval', explode(':', $interval['start']));
            [$endHour, $endMinute] = array_map('intval', explode(':', $interval['end']));

            $start = $day->setTime($startHour, $startMinute);
            $end = $day->setTime($endHour, $endMinute);

            if ($end <= $start) {
                $nextDay = $day->add(new DateInterval('P1D'));
                $nextDayStart = $nextDay->setTime(0, 0);
                $end = $nextDay->setTime($endHour, $endMinute);

                // Regra B: trecho após 00:00 só existe se o dia seguinte for válido para a mesma ordem.
                // Regra B (ajustada): turno overnight pertence ao dia em que iniciou (bloqueia domingo/feriado).
                $nextDayNumber = (int) $nextDay->format('N');
                if ($nextDayNumber === 7 || !$this->isCalendarOpenForDay($nextDay)) {
                    $end = $nextDayStart;
                }
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
