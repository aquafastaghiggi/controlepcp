<?php

declare(strict_types=1);

namespace App\Data;

final class MockData
{
    public static function all(): array
    {
        return [
            'calendar' => [
                'line' => 'L2',
                'working_days' => [1, 2, 3, 4, 5],
                'holidays' => [],
                'intervals' => [
                    ['start' => '07:10', 'end' => '11:28'],
                    ['start' => '13:35', 'end' => '17:40'],
                    ['start' => '17:40', 'end' => '22:00'],
                    ['start' => '23:00', 'end' => '03:00'],
                ],
            ],
            'products' => [
                'AGUA SANITARIA 5L' => [
                    'description' => 'Agua Sanitaria 5L',
                    'line' => 'L2',
                    'rate_per_hour' => 200.0,
                    'unit' => 'cx',
                ],
                'ALVEJANTE S/ CLORO 3L' => [
                    'description' => 'Alvejante S/ Cloro 3L',
                    'line' => 'L2',
                    'rate_per_hour' => 180.0,
                    'unit' => 'cx',
                ],
                'DESINFETANTE CAMPOS LAVANDA 5L' => [
                    'description' => 'Desinfetante Campos Lavanda 5L',
                    'line' => 'L2',
                    'rate_per_hour' => 200.0,
                    'unit' => 'cx',
                ],
                'DESINFETANTE ENERGIA 5L' => [
                    'description' => 'Desinfetante Energia 5L',
                    'line' => 'L2',
                    'rate_per_hour' => 200.0,
                    'unit' => 'cx',
                ],
                'DESINFETANTE FL. DE EUCALIPTO 5L' => [
                    'description' => 'Desinfetante Fl. de Eucalipto 5L',
                    'line' => 'L2',
                    'rate_per_hour' => 200.0,
                    'unit' => 'cx',
                ],
                'DESINFETANTE HARMONIA NATURAL 5L' => [
                    'description' => 'Desinfetante Harmonia Natural 5L',
                    'line' => 'L2',
                    'rate_per_hour' => 200.0,
                    'unit' => 'cx',
                ],
                'DESINFETANTE JARDIM FLORIDO 5L' => [
                    'description' => 'Desinfetante Jardim Florido 5L',
                    'line' => 'L2',
                    'rate_per_hour' => 200.0,
                    'unit' => 'cx',
                ],
                'DESINFETANTE MARINE 5L' => [
                    'description' => 'Desinfetante Marine 5L',
                    'line' => 'L2',
                    'rate_per_hour' => 200.0,
                    'unit' => 'cx',
                ],
                'DESINFETANTE PAIXAO 5L' => [
                    'description' => 'Desinfetante Paixao 5L',
                    'line' => 'L2',
                    'rate_per_hour' => 200.0,
                    'unit' => 'cx',
                ],
            ],
            'setup_matrix' => self::setupMatrix(),
            'sample_program' => [
                [
                    'sequence' => 1,
                    'sku' => 'ALVEJANTE S/ CLORO 3L',
                    'quantity' => 1500,
                    'planned_start' => '2026-04-14T13:35',
                ],
                [
                    'sequence' => 2,
                    'sku' => 'DESINFETANTE CAMPOS LAVANDA 5L',
                    'quantity' => 310,
                    'planned_start' => '',
                ],
            ],
        ];
    }

    private static function setupMatrix(): array
    {
        $products = [
            'AGUA SANITARIA 5L',
            'ALVEJANTE S/ CLORO 3L',
            'DESINFETANTE CAMPOS LAVANDA 5L',
            'DESINFETANTE ENERGIA 5L',
            'DESINFETANTE FL. DE EUCALIPTO 5L',
            'DESINFETANTE HARMONIA NATURAL 5L',
            'DESINFETANTE JARDIM FLORIDO 5L',
            'DESINFETANTE MARINE 5L',
            'DESINFETANTE PAIXAO 5L',
        ];

        $matrix = [];
        $specialProducts = [
            'AGUA SANITARIA 5L',
            'ALVEJANTE S/ CLORO 3L',
        ];

        foreach ($products as $from) {
            foreach ($products as $to) {
                $duration = '00:20';

                if ($from !== $to && (in_array($from, $specialProducts, true) || in_array($to, $specialProducts, true))) {
                    $duration = '00:30';
                }

                $matrix[$from][$to] = $duration;
            }
        }

        return $matrix;
    }
}
