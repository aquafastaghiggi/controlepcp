<?php

declare(strict_types=1);

namespace App\Data;

use App\Database\Connection;
use App\Support\DateTimeHelper;
use PDO;

final class DatabaseData
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function all(): array
    {
        $line = $this->loadLine();
        if (!$line) {
            return [
                'calendar' => [
                    'line' => '',
                    'working_days' => [],
                    'holidays' => [],
                    'intervals' => [],
                ],
                'products' => [],
                'setup_matrix' => [],
                'sample_program' => [],
            ];
        }

        $calendar = $this->loadCalendar($line['lin_id']);
        $products = $this->loadProducts($line['lin_id']);
        $setupMatrix = $this->loadSetupMatrix($line['lin_id']);
        $sampleProgram = $this->loadLastProgram($line['lin_id']);

        return [
            'calendar' => array_merge($calendar, ['line' => $line['lin_codigo']]),
            'products' => $products,
            'setup_matrix' => $setupMatrix,
            'sample_program' => $sampleProgram,
        ];
    }

    private function loadLine(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM lin_linhas ORDER BY lin_id LIMIT 1');
        $line = $stmt->fetch();

        return $line ?: null;
    }

    private function loadCalendar(int $lineId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cal_calendarios WHERE cal_linha_id = :lineId ORDER BY cal_id LIMIT 1');
        $stmt->execute(['lineId' => $lineId]);
        $calendar = $stmt->fetch() ?: [];

        $intervals = [];

        if ($calendar) {
            $intervalStmt = $this->pdo->prepare('SELECT * FROM cal_intervalos WHERE cal_calendario_id = :calId ORDER BY cal_id');
            $intervalStmt->execute(['calId' => $calendar['cal_id']]);

            while ($interval = $intervalStmt->fetch()) {
                $weekdayStmt = $this->pdo->prepare('SELECT diu_dia_peq FROM cal_dias_uteis WHERE diu_intervalo_id = :intervalId');
                $weekdayStmt->execute(['intervalId' => $interval['cal_id']]);

                $days = array_map('intval', $weekdayStmt->fetchAll(PDO::FETCH_COLUMN));

                $intervals[] = [
                    'start' => $interval['cal_inicio'],
                    'end' => $interval['cal_fim'],
                    'days' => $days,
                ];
            }
        }

        $holidays = [];
        if ($calendar) {
            $holidayStmt = $this->pdo->prepare('SELECT cal_data, cal_nome FROM cal_feriados WHERE cal_calendario_id = :calId ORDER BY cal_data');
            $holidayStmt->execute(['calId' => $calendar['cal_id']]);

            while ($holiday = $holidayStmt->fetch()) {
                $holidays[] = [
                    'date' => $holiday['cal_data'],
                    'name' => $holiday['cal_nome'],
                ];
            }
        }

        return [
            'working_days' => $this->loadDefaultWorkingDays($calendar),
            'holidays' => $holidays,
            'intervals' => $intervals,
        ];
    }

    private function loadDefaultWorkingDays(array $calendar): array
    {
        // O sistema espera que exista a lista de dias uteis; aqui mantemos a mesma lista de 1 a 5 por padrão.
        return [1, 2, 3, 4, 5];
    }

    private function loadProducts(int $lineId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM prd_produtos WHERE prd_linha_id = :lineId ORDER BY prd_sku');
        $stmt->execute(['lineId' => $lineId]);

        $products = [];
        while ($row = $stmt->fetch()) {
            $products[$row['prd_sku']] = [
                'description' => $row['prd_descricao'],
                'reference_setup' => $row['prd_referencia_setup'],
                'line' => $row['prd_linha_id'],
                'rate_per_hour' => (float) $row['prd_taxa_por_hora'],
                'unit' => $row['prd_unidade'],
            ];
        }

        return $products;
    }

    private function loadSetupMatrix(int $lineId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM mat_matriz_setup WHERE mat_linha_id = :lineId ORDER BY mat_id');
        $stmt->execute(['lineId' => $lineId]);

        $matrix = [];
        while ($row = $stmt->fetch()) {
            $duration = DateTimeHelper::durationFromMinutes((int) $row['mat_duracao_minutos']);
            if (!isset($matrix[$row['mat_sku_origem']])) {
                $matrix[$row['mat_sku_origem']] = [];
            }
            $matrix[$row['mat_sku_origem']][$row['mat_sku_destino']] = $duration;
        }

        return $matrix;
    }

    private function loadLastProgram(int $lineId): array
    {
        $stmt = $this->pdo->prepare('SELECT prg_id FROM prg_programas WHERE prg_linha_id = :lineId ORDER BY prg_id DESC LIMIT 1');
        $stmt->execute(['lineId' => $lineId]);
        $program = $stmt->fetch();

        if (!$program) {
            return [];
        }

        $itemsStmt = $this->pdo->prepare('SELECT prg_sequencia, prg_sku, prg_quantidade, prg_inicio_planejado FROM prg_itens WHERE prg_programa_id = :programId ORDER BY prg_sequencia');
        $itemsStmt->execute(['programId' => $program['prg_id']]);

        $rows = [];
        while ($item = $itemsStmt->fetch()) {
            $rows[] = [
                'sequence' => (int) $item['prg_sequencia'],
                'sku' => $item['prg_sku'],
                'quantity' => (float) $item['prg_quantidade'],
                'planned_start' => $item['prg_inicio_planejado'] ? (new \DateTimeImmutable($item['prg_inicio_planejado']))->format('Y-m-d\TH:i') : '',
            ];
        }

        return $rows;
    }

    public function persistDataset(array $data): void
    {
        $this->pdo->beginTransaction();

        $lineCode = (string) ($data['calendar']['line'] ?? 'L2');
        $lineId = $this->ensureLine($lineCode);

        $calendarId = $this->ensureCalendar($lineId);

        $this->replaceCalendarIntervals($calendarId, $data['calendar']['intervals'] ?? []);
        $this->replaceCalendarHolidays($calendarId, $data['calendar']['holidays'] ?? []);

        $this->replaceProducts($lineId, $data['products'] ?? []);
        $this->replaceSetupMatrix($lineId, $data['setup_matrix'] ?? []);

        $this->pdo->commit();
    }

    private function ensureLine(string $code): int
    {
        $stmt = $this->pdo->prepare('SELECT lin_id FROM lin_linhas WHERE lin_codigo = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $line = $stmt->fetch();

        if ($line) {
            return (int) $line['lin_id'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO lin_linhas (lin_codigo, lin_nome) VALUES (:code, :name)');
        $stmt->execute(['code' => $code, 'name' => sprintf('Linha %s', $code)]);

        return (int) $this->pdo->lastInsertId();
    }

    private function ensureCalendar(int $lineId): int
    {
        $stmt = $this->pdo->prepare('SELECT cal_id FROM cal_calendarios WHERE cal_linha_id = :lineId LIMIT 1');
        $stmt->execute(['lineId' => $lineId]);
        $cal = $stmt->fetch();

        if ($cal) {
            return (int) $cal['cal_id'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO cal_calendarios (cal_linha_id) VALUES (:lineId)');
        $stmt->execute(['lineId' => $lineId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function replaceCalendarIntervals(int $calendarId, array $intervals): void
    {
        $this->pdo->prepare('DELETE FROM cal_dias_uteis WHERE diu_intervalo_id IN (SELECT cal_id FROM cal_intervalos WHERE cal_calendario_id = :calId)')->execute(['calId' => $calendarId]);
        $this->pdo->prepare('DELETE FROM cal_intervalos WHERE cal_calendario_id = :calId')->execute(['calId' => $calendarId]);

        $insertInterval = $this->pdo->prepare('INSERT INTO cal_intervalos (cal_calendario_id, cal_inicio, cal_fim) VALUES (:calId, :start, :end)');
        $insertDay = $this->pdo->prepare('INSERT INTO cal_dias_uteis (diu_intervalo_id, diu_dia_peq) VALUES (:intervalId, :weekday)');

        foreach ($intervals as $interval) {
            $start = (string) ($interval['start'] ?? '07:10');
            $end = (string) ($interval['end'] ?? '11:28');
            $days = is_array($interval['days']) ? $interval['days'] : [];

            $insertInterval->execute(['calId' => $calendarId, 'start' => $start, 'end' => $end]);
            $intervalId = (int) $this->pdo->lastInsertId();

            foreach ($days as $day) {
                $weekday = (int) $day;
                if ($weekday < 1 || $weekday > 7) {
                    continue;
                }
                $insertDay->execute(['intervalId' => $intervalId, 'weekday' => $weekday]);
            }
        }
    }

    private function replaceCalendarHolidays(int $calendarId, array $holidays): void
    {
        $this->pdo->prepare('DELETE FROM cal_feriados WHERE cal_calendario_id = :calId')->execute(['calId' => $calendarId]);

        $insertHoliday = $this->pdo->prepare('INSERT INTO cal_feriados (cal_calendario_id, cal_data, cal_nome) VALUES (:calId, :date, :name)');

        foreach ($holidays as $holiday) {
            $date = (string) ($holiday['date'] ?? '');
            $name = (string) ($holiday['name'] ?? '');

            if ($date === '') {
                continue;
            }

            $insertHoliday->execute(['calId' => $calendarId, 'date' => $date, 'name' => $name]);
        }
    }

    private function replaceProducts(int $lineId, array $products): void
    {
        $this->pdo->prepare('DELETE FROM prd_produtos WHERE prd_linha_id = :lineId')->execute(['lineId' => $lineId]);

        $insert = $this->pdo->prepare(
            'INSERT INTO prd_produtos (prd_sku, prd_descricao, prd_referencia_setup, prd_linha_id, prd_taxa_por_hora, prd_unidade) VALUES (:sku, :descricao, :ref, :lineId, :rate, :unit)'
        );

        foreach ($products as $sku => $product) {
            $insert->execute([
                'sku' => $sku,
                'descricao' => (string) ($product['description'] ?? ''),
                'ref' => (string) ($product['reference_setup'] ?? ''),
                'lineId' => $lineId,
                'rate' => (float) ($product['rate_per_hour'] ?? 0),
                'unit' => (string) ($product['unit'] ?? ''),
            ]);
        }
    }

    private function replaceSetupMatrix(int $lineId, array $setup): void
    {
        $this->pdo->prepare('DELETE FROM mat_matriz_setup WHERE mat_linha_id = :lineId')->execute(['lineId' => $lineId]);

        $insert = $this->pdo->prepare(
            'INSERT INTO mat_matriz_setup (mat_linha_id, mat_sku_origem, mat_sku_destino, mat_duracao_minutos) VALUES (:lineId, :from, :to, :duration)'
        );

        foreach ($setup as $from => $targets) {
            if (!is_array($targets)) {
                continue;
            }
            foreach ($targets as $to => $duration) {
                $minutes = DateTimeHelper::minutesFromDuration((string) $duration);
                $insert->execute([
                    'lineId' => $lineId,
                    'from' => $from,
                    'to' => $to,
                    'duration' => $minutes,
                ]);
            }
        }
    }
}
