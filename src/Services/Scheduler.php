<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\DateTimeHelper;
use DateTimeImmutable;

final class Scheduler
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function calculate(
        array $program,
        DateTimeImmutable $baseStart,
        ?DateTimeImmutable $queryDateTime = null,
        float $productionEfficiency = 100.0,
        array $calendarDayOrders = []
    ): array
    {
        if ($productionEfficiency <= 0.0) {
            $productionEfficiency = 100.0;
        }

        $productionFactor = $productionEfficiency / 100.0;

        $calendarData = $this->data['calendar'];
        $allIntervals = array_values(is_array($calendarData['intervals'] ?? null) ? $calendarData['intervals'] : []);
        $workingDays = $calendarData['working_days'] ?? [1, 2, 3, 4, 5];
        $holidays = $calendarData['holidays'] ?? [];

        $results = [];
        $errors = [];

        usort(
            $program,
            static fn (array $left, array $right): int => ((int) $left['sequence']) <=> ((int) $right['sequence'])
        );

        $globalUseOrder3 = !empty($program[0]['use_order_3']);
        $globalUseOrder4 = !empty($program[0]['use_order_4']);

        if ($calendarDayOrders !== []) {
            foreach ($calendarDayOrders as $dayOrders) {
                if (!is_array($dayOrders)) {
                    continue;
                }

                if (!empty($dayOrders[3])) {
                    $globalUseOrder3 = true;
                }

                if (!empty($dayOrders[4])) {
                    $globalUseOrder4 = true;
                }

                if ($globalUseOrder3 && $globalUseOrder4) {
                    break;
                }
            }
        }

        $previousSku = null;
        $previousProductionEnd = null;
        $firstItem = true;

        foreach ($program as $item) {
            $intervals = [];

            if (isset($allIntervals[0])) {
                $intervals[] = $allIntervals[0] + ['order' => 1];
            }

            if (isset($allIntervals[1])) {
                $intervals[] = $allIntervals[1] + ['order' => 2];
            }

            if ($globalUseOrder3 && isset($allIntervals[2])) {
                $intervals[] = $allIntervals[2] + ['order' => 3];
            }

            if ($globalUseOrder4 && isset($allIntervals[3])) {
                $intervals[] = $allIntervals[3] + ['order' => 4];
            }

            $calendar = new WorkCalendar($intervals, $workingDays, $holidays, $calendarDayOrders);

            $sku = trim((string) ($item['sku'] ?? ''));
            $sequence = (int) ($item['sequence'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);
            $plannedStart = DateTimeHelper::fromLocalInput((string) ($item['planned_start'] ?? ''));

            $product = $this->data['products'][$sku] ?? null;

            if ($product === null) {
                $results[] = $this->errorRow($sequence, $sku, $quantity, 'SKU sem cadastro');
                $errors[] = "SKU {$sku} nao encontrado.";
                continue;
            }

            $ratePerHour = (float) $product['rate_per_hour'];
            $effectiveRatePerHour = $ratePerHour * $productionFactor;

            if ($ratePerHour <= 0.0 || $effectiveRatePerHour <= 0.0) {
                $results[] = $this->errorRow($sequence, $sku, $quantity, 'Taxa invalida');
                $errors[] = "SKU {$sku} com taxa invalida.";
                continue;
            }

            $productionMinutes = (int) round(($quantity / $effectiveRatePerHour) * 60);
            $setupMinutes = 0;
            $setupStart = null;
            $setupEnd = null;
            $setupPlan = ['segments' => []];

            if ($firstItem) {
                $startReference = $plannedStart ?? $baseStart;
                $productionStart = $calendar->nextValidDateTime($startReference);
                $firstItem = false;
            } else {
                $startReference = null;
                $setupMinutes = $this->lookupSetupMinutes((string) $previousSku, $sku);
                $setupStart = $previousProductionEnd;
                $setupPlan = $calendar->buildWorkingPlan($setupStart, $setupMinutes);
                $setupEnd = $setupPlan['end'];
                $productionStart = $calendar->nextValidDateTime($setupEnd);
            }

            $productionPlan = $calendar->buildWorkingPlan($productionStart, $productionMinutes);
            $productionEnd = $productionPlan['end'];

            $estimatedProduced = $this->estimateProduced(
                $calendar,
                $productionStart,
                $productionEnd,
                $effectiveRatePerHour,
                $quantity,
                $queryDateTime
            );

            if ($setupMinutes > 0) {
                $results[] = [
                    'sequence' => $sequence,
                    'type' => 'setup',
                    'sku' => 'SETUP',
                    'description' => 'Setup',
                    'quantity' => null,
                    'rate_per_hour' => null,
                    'duration_label' => DateTimeHelper::durationFromMinutes($setupMinutes),
                    'previous_sku' => $previousSku,
                    'planned_start' => '',
                    'date_start' => DateTimeHelper::formatDate($setupStart),
                    'time_start' => DateTimeHelper::formatTime($setupStart),
                    'time_end' => DateTimeHelper::formatTime($setupEnd),
                    'calculation_memory' => $this->formatSegments($setupPlan['segments']),
                    'production_start' => DateTimeHelper::formatDateTime($setupStart),
                    'production_end' => DateTimeHelper::formatDateTime($setupEnd),
                    'estimated_produced' => null,
                    'status' => 'Setup calculado',
                ];
            }

            $results[] = [
                'sequence' => $sequence,
                'type' => 'production',
                'sku' => $sku,
                'description' => $product['description'],
                'quantity' => $quantity,
                'rate_per_hour' => round($effectiveRatePerHour, 2),
                'duration_label' => DateTimeHelper::durationFromMinutes($productionMinutes),
                'previous_sku' => $previousSku,
                'planned_start' => DateTimeHelper::formatDateTime($startReference),
                'date_start' => DateTimeHelper::formatDate($productionStart),
                'time_start' => DateTimeHelper::formatTime($productionStart),
                'time_end' => DateTimeHelper::formatTime($productionEnd),
                'calculation_memory' => $this->formatSegments($productionPlan['segments']),
                'production_start' => DateTimeHelper::formatDateTime($productionStart),
                'production_end' => DateTimeHelper::formatDateTime($productionEnd),
                'estimated_produced' => round($estimatedProduced, 2),
                'status' => 'Calculado',
            ];

            $previousSku = $sku;
            $previousProductionEnd = $productionEnd;
        }

        return [
            'meta' => [
                'base_start' => DateTimeHelper::formatDateTime($baseStart),
                'query_datetime' => DateTimeHelper::formatDateTime($queryDateTime),
                'production_efficiency' => $productionEfficiency,
                'total_orders' => count(array_filter($results, static fn (array $row): bool => $row['type'] === 'production')),
                'errors' => $errors,
            ],
            'rows' => $results,
        ];
    }

    private function estimateProduced(
        WorkCalendar $calendar,
        DateTimeImmutable $productionStart,
        DateTimeImmutable $productionEnd,
        float $ratePerHour,
        float $quantity,
        ?DateTimeImmutable $queryDateTime
    ): float {
        if ($queryDateTime === null || $queryDateTime <= $productionStart) {
            return 0.0;
        }

        if ($queryDateTime >= $productionEnd) {
            return $quantity;
        }

        $workedMinutes = $calendar->workingMinutesBetween($productionStart, $queryDateTime);
        $estimated = ($workedMinutes / 60) * $ratePerHour;

        return min($quantity, $estimated);
    }

    private function lookupSetupMinutes(string $previousSku, string $currentSku): int
    {
        $duration = $this->data['setup_matrix'][$previousSku][$currentSku] ?? null;

        if ($duration === null) {
            return 0;
        }

        return DateTimeHelper::minutesFromDuration($duration);
    }

    private function formatSegments(array $segments): string
    {
        if ($segments === []) {
            return '';
        }

        $parts = [];
        $totalUsedMinutes = 0;
        $totalIntervalMinutes = 0;

        foreach ($segments as $segment) {
            $usedMinutes = (int) $segment['minutes'];
            $intervalMinutes = (int) $segment['interval_minutes'];
            $totalUsedMinutes += $usedMinutes;
            $totalIntervalMinutes += $intervalMinutes;

            $parts[] = sprintf(
                '%s turno %s-%s | usado %s-%s = %s',
                $segment['start']->format('d/m'),
                $segment['interval_start']->format('H:i'),
                $segment['interval_end']->format('H:i'),
                $segment['start']->format('H:i'),
                $segment['end']->format('H:i'),
                DateTimeHelper::durationFromMinutes($usedMinutes)
            );
        }

        $parts[] = sprintf(
            'total usado = %s de %s',
            DateTimeHelper::durationFromMinutes($totalUsedMinutes),
            DateTimeHelper::durationFromMinutes($totalIntervalMinutes)
        );

        return implode(' | ', $parts);
    }

    private function errorRow(int $sequence, string $sku, float $quantity, string $status): array
    {
        return [
            'sequence' => $sequence,
            'type' => 'production',
            'sku' => $sku,
            'description' => $sku,
            'quantity' => $quantity,
            'rate_per_hour' => null,
            'duration_label' => '',
            'previous_sku' => null,
            'planned_start' => '',
            'date_start' => '',
            'time_start' => '',
            'time_end' => '',
            'calculation_memory' => '',
            'production_start' => '',
            'production_end' => '',
            'estimated_produced' => 0,
            'status' => $status,
        ];
    }
}
