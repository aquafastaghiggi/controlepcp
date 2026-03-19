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

        $calendar = $this->loadCalendar((int) $line['lin_id']);
        $products = $this->loadProducts((int) $line['lin_id']);
        $matrixData = $this->loadSetupMatrixData();
        $sampleProgram = $this->loadLastProgram((int) $line['lin_id']);

        return [
            'calendar' => array_merge($calendar, ['line' => $line['lin_codigo']]),
            'products' => $products,
            'setup_matrix' => $matrixData['matrix'],
            'setup_matrix_sections' => $matrixData['sections'],
            'sample_program' => $sampleProgram,
        ];
    }

    private function loadLine(): ?array
    {
        $stmt = $this->pdo->query(
            'SELECT l.*,'
            . ' (SELECT COUNT(*) FROM prd_produtos p WHERE p.prd_linha_id = l.lin_id) AS products_count,'
            . ' (SELECT COUNT(*) FROM cal_intervalos i JOIN cal_calendarios c ON c.cal_id = i.cal_calendario_id WHERE c.cal_linha_id = l.lin_id) AS intervals_count,'
            . ' (SELECT COUNT(*) FROM mat_matriz_setup m WHERE m.mat_linha_id = l.lin_id) AS matrix_count'
            . ' FROM lin_linhas l'
            . ' ORDER BY (products_count + intervals_count + matrix_count) DESC, l.lin_id'
            . ' LIMIT 1'
        );
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
                    'start' => $this->formatTimeValue((string) $interval['cal_inicio']),
                    'end' => $this->formatTimeValue((string) $interval['cal_fim']),
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

    private function formatTimeValue(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}/', $trimmed) === 1) {
            return substr($trimmed, 0, 5);
        }

        return $trimmed;
    }

    private function normalizeSkuValue(string $value): string
    {
        $text = trim($value);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        if (preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/', $text) === 1) {
            $num = (float) $text;
            if (is_finite($num)) {
                return (string) (int) $num;
            }
        }

        return $text;
    }

    private function normalizeSkuKey(string $value): string
    {
        $normalized = $this->normalizeSkuValue($value);
        return strtoupper($normalized);
    }

    private function loadDefaultWorkingDays(array $calendar): array
    {
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

    private function loadSetupMatrixData(): array
    {
        $stmt = $this->pdo->query(
            'SELECT m.mat_sku_origem, m.mat_sku_destino, m.mat_duracao_minutos, l.lin_codigo'
            . ' FROM mat_matriz_setup m'
            . ' JOIN lin_linhas l ON m.mat_linha_id = l.lin_id'
            . ' ORDER BY l.lin_codigo, m.mat_id'
        );

        $matrix = [];
        $sectionsByLine = [];

        while ($row = $stmt->fetch()) {
            $duration = DateTimeHelper::durationFromMinutes((int) $row['mat_duracao_minutos']);
            $line = (string) $row['lin_codigo'];

            if (!isset($matrix[$row['mat_sku_origem']])) {
                $matrix[$row['mat_sku_origem']] = [];
            }

            $matrix[$row['mat_sku_origem']][$row['mat_sku_destino']] = $duration;

            if (!isset($sectionsByLine[$line])) {
                $sectionsByLine[$line] = [
                    'line' => $line,
                    'rows' => [],
                ];
            }

            $sectionsByLine[$line]['rows'][] = [
                'line' => $line,
                'from' => $row['mat_sku_origem'],
                'to' => $row['mat_sku_destino'],
                'duration' => $duration,
            ];
        }

        return [
            'matrix' => $matrix,
            'sections' => array_values($sectionsByLine),
        ];
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
        $lineCode = (string) ($data['calendar']['line'] ?? 'L2');
        $meta = isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : [];
        $allowClearProducts = !empty($meta['allow_clear_products']);
        $allowClearMatrix = !empty($meta['allow_clear_matrix']);
        $allowClearCalendar = !empty($meta['allow_clear_calendar']);

        $this->pdo->beginTransaction();

        try {
            $lineId = $this->ensureLine($lineCode);
            $calendarId = $this->ensureCalendar($lineId);

            $intervals = $data['calendar']['intervals'] ?? null;
            if (is_array($intervals) && $intervals !== []) {
                $this->replaceCalendarIntervals($calendarId, $intervals);
            } elseif ($allowClearCalendar) {
                $this->replaceCalendarIntervals($calendarId, []);
            }

            $holidays = $data['calendar']['holidays'] ?? null;
            if (is_array($holidays)) {
                $this->replaceCalendarHolidays($calendarId, $holidays);
            }

            if (array_key_exists('products', $data) && is_array($data['products'])) {
                if ($data['products'] !== [] || $allowClearProducts) {
                    $this->replaceProducts($lineId, $data['products']);
                }
            }

            $sections = $this->extractSetupMatrixSections($data, $lineCode);
            $this->replaceSetupMatrixEntries($sections, $lineCode, $allowClearMatrix);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
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
            $normalizedSku = $this->normalizeSkuValue((string) $sku);
            if ($normalizedSku === '') {
                continue;
            }

            $insert->execute([
                'sku' => $normalizedSku,
                'descricao' => (string) ($product['description'] ?? ''),
                'ref' => (string) ($product['reference_setup'] ?? ''),
                'lineId' => $lineId,
                'rate' => (float) ($product['rate_per_hour'] ?? 0),
                'unit' => (string) ($product['unit'] ?? ''),
            ]);
        }
    }
    private function replaceSetupMatrixEntries(array $sections, string $defaultLineCode, bool $allowClear): void
    {
        $skuStmt = $this->pdo->query('SELECT prd_sku FROM prd_produtos');
        $allowedSkus = [];
        foreach ($skuStmt->fetchAll(PDO::FETCH_COLUMN) as $sku) {
            $key = $this->normalizeSkuKey((string) $sku);
            if ($key === '') {
                continue;
            }
            $allowedSkus[$key] = $this->normalizeSkuValue((string) $sku);
        }

        if ($allowedSkus === []) {
            if ($sections !== [] && !$allowClear) {
                throw new \RuntimeException('Importe os produtos antes de importar a matriz.');
            }
            if ($allowClear) {
                $this->pdo->prepare('DELETE FROM mat_matriz_setup')->execute();
            }
            return;
        }

        $validRows = [];
        $missingSkus = [];
        $lineCache = [];

        foreach ($sections as $section) {
            $lineCode = trim((string) ($section['line'] ?? ''));
            if ($lineCode === '') {
                $lineCode = $defaultLineCode;
            }
            $lineCode = $lineCode ?: 'L2';

            if (!isset($lineCache[$lineCode])) {
                $lineCache[$lineCode] = $this->ensureLine($lineCode);
            }
            $lineId = $lineCache[$lineCode];

            $fromRaw = trim((string) ($section['from'] ?? ''));
            $toRaw = trim((string) ($section['to'] ?? ''));
            $duration = trim((string) ($section['duration'] ?? ''));

            if ($fromRaw === '' || $toRaw === '' || $duration === '') {
                continue;
            }

            $fromKey = $this->normalizeSkuKey($fromRaw);
            $toKey = $this->normalizeSkuKey($toRaw);
            $from = $allowedSkus[$fromKey] ?? null;
            $to = $allowedSkus[$toKey] ?? null;

            if ($from === null || $to === null) {
                if ($from === null) {
                    $missingSkus[$fromKey ?: $fromRaw] = true;
                }
                if ($to === null) {
                    $missingSkus[$toKey ?: $toRaw] = true;
                }
                continue;
            }

            if (str_contains($duration, ':')) {
                $minutes = DateTimeHelper::minutesFromDuration($duration);
            } elseif (is_numeric($duration)) {
                $minutes = (int) $duration;
            } else {
                continue;
            }

            $validRows[] = [
                'lineId' => $lineId,
                'from' => $from,
                'to' => $to,
                'duration' => $minutes,
            ];
        }

        if ($validRows === []) {
            if ($sections !== [] && !$allowClear) {
                $missingList = array_slice(array_keys($missingSkus), 0, 8);
                $detail = $missingList ? ' SKUs ausentes: ' . implode(', ', $missingList) : '';
                throw new \RuntimeException('Nenhum SKU da matriz foi encontrado na base de produtos. Importe os produtos antes da matriz.' . $detail);
            }
            if ($allowClear) {
                $this->pdo->prepare('DELETE FROM mat_matriz_setup')->execute();
            }
            return;
        }

        $uniqueRows = [];
        foreach ($validRows as $row) {
            $key = sprintf('%d|%s|%s', $row['lineId'], $row['from'], $row['to']);
            $uniqueRows[$key] = $row;
        }
        $validRows = array_values($uniqueRows);

        $this->pdo->prepare('DELETE FROM mat_matriz_setup')->execute();

        $insert = $this->pdo->prepare(
            'INSERT INTO mat_matriz_setup (mat_linha_id, mat_sku_origem, mat_sku_destino, mat_duracao_minutos) VALUES (:lineId, :from, :to, :duration)'
        );

        foreach ($validRows as $row) {
            $insert->execute([
                'lineId' => $row['lineId'],
                'from' => $row['from'],
                'to' => $row['to'],
                'duration' => $row['duration'],
            ]);
        }
    }

    private function extractSetupMatrixSections(array $data, string $defaultLineCode): array
    {
        $sections = [];
        if (isset($data['setup_matrix_sections']) && is_array($data['setup_matrix_sections'])) {
            $sections = $data['setup_matrix_sections'];
        }

        if ($sections) {
            return $this->flattenSetupMatrixSections($sections, $defaultLineCode);
        }

        return $this->buildSectionsFromMatrix($data['setup_matrix'] ?? [], $defaultLineCode);
    }

    private function buildSectionsFromMatrix(array $matrix, string $lineCode): array
    {
        $rows = [];
        foreach ($matrix as $from => $targets) {
            if (!is_array($targets)) {
                continue;
            }

            foreach ($targets as $to => $duration) {
                $rows[] = [
                    'line' => $lineCode,
                    'from' => $from,
                    'to' => $to,
                    'duration' => (string) $duration,
                ];
            }
        }

        return $rows;
    }

    private function flattenSetupMatrixSections(array $sections, string $defaultLineCode): array
    {
        $rows = [];

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $sectionLine = trim((string) ($section['line'] ?? ''));
            if ($sectionLine === '') {
                $sectionLine = $defaultLineCode;
            }

            $sectionRows = $section['rows'] ?? [];
            if (!is_array($sectionRows)) {
                continue;
            }

            foreach ($sectionRows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $rowLine = trim((string) ($row['line'] ?? $sectionLine));
                if ($rowLine === '') {
                    $rowLine = $sectionLine;
                }

                $rows[] = [
                    'line' => $rowLine,
                    'from' => $row['from'] ?? '',
                    'to' => $row['to'] ?? '',
                    'duration' => $row['duration'] ?? '',
                ];
            }
        }

        return $rows;
    }

    public function clearMatrix(): void
    {
        $this->pdo->prepare('DELETE FROM mat_matriz_setup')->execute();
    }
}
