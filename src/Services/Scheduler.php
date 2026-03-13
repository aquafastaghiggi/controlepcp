<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\MockData;
use App\Support\DateTimeHelper;
use DateTimeImmutable;

final class Scheduler
{
    private array $data;

    public function __construct()
    {
        $this->data = MockData::all();
    }

    public function calculate(array $program, DateTimeImmutable $baseStart, ?DateTimeImmutable $queryDateTime = null): array
    {
        $calendarData = $this->data['calendar'];
        $calendar = new WorkCalendar(
            $calendarData['intervals'],
            $calendarData['working_days'] ?? [1, 2, 3, 4, 5],
            $calendarData['holidays'] ?? []
        );

        $results = [];
        $errors = [];

        usort(
            $program,
            static fn (array $left, array $right): int => ((int) $left['sequence']) <=> ((int) $right['sequence'])
        );

        $previousSku = null;
        $previousProductionEnd = null;
        $firstItem = true;

        foreach ($program as $item) {
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

            if ($ratePerHour <= 0.0) {
                $results[] = $this->errorRow($sequence, $sku, $quantity, 'Taxa invalida');
                $errors[] = "SKU {$sku} com taxa invalida.";
                continue;
            }

            $productionMinutes = (int) round(($quantity / $ratePerHour) * 60);
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
                $ratePerHour,
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
                'rate_per_hour' => $ratePerHour,
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
