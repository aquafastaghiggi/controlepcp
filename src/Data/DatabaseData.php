<?php

declare(strict_types=1);

namespace App\Data;

use App\Database\Connection;
use App\Support\DateTimeHelper;
use PDO;

final class DatabaseData
{
    private PDO $pdo;
    private ?string $preferredLineCode = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function all(): array
    {
        $line = $this->loadLine($this->preferredLineCode);
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
        $products = $this->loadProducts();
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

    private function loadLine(?string $preferredLineCode = null): ?array
    {
        if ($preferredLineCode !== null) {
            $line = $this->fetchLineByCode($preferredLineCode);
            if ($line) {
                return $line;
            }
        }

        $stmt = $this->pdo->query(
            'SELECT l.*,'
            . ' (SELECT COUNT(*) FROM prd_produtos p WHERE p.prd_linha_id = l.lin_id) AS products_count,'
            . ' (SELECT COUNT(*) FROM cal_intervalos i JOIN cal_calendarios c ON c.cal_id = i.cal_calendario_id WHERE c.cal_linha_id = l.lin_id) AS intervals_count,'
            . ' (SELECT COUNT(*) FROM mat_matriz_setup m WHERE m.mat_linha_id = l.lin_id) AS matrix_count'
            . ' FROM lin_linhas l'
            . ' ORDER BY products_count DESC, (intervals_count + matrix_count) DESC, l.lin_id'
            . ' LIMIT 1'
        );
        $line = $stmt->fetch();

        return $line ?: null;
    }

    private function fetchLineByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.*,'
            . ' (SELECT COUNT(*) FROM prd_produtos p WHERE p.prd_linha_id = l.lin_id) AS products_count,'
            . ' (SELECT COUNT(*) FROM cal_intervalos i JOIN cal_calendarios c ON c.cal_id = i.cal_calendario_id WHERE c.cal_linha_id = l.lin_id) AS intervals_count,'
            . ' (SELECT COUNT(*) FROM mat_matriz_setup m WHERE m.mat_linha_id = l.lin_id) AS matrix_count'
            . ' FROM lin_linhas l'
            . ' WHERE l.lin_codigo = :code'
            . ' LIMIT 1'
        );
        $stmt->execute(['code' => $code]);

        return $stmt->fetch() ?: null;
    }

    private function loadCalendar(int $lineId): array
    {
        $calendar = $this->fetchCalendarForLine($lineId);
        $calendarId = $calendar ? (int) $calendar['cal_id'] : null;

        $intervals = $this->loadCalendarIntervalsForId($calendarId);

        if ($intervals === []) {
            $fallbackCalendar = $this->fetchAnyCalendarWithIntervals($calendarId);
            if ($fallbackCalendar) {
                $calendar = $fallbackCalendar;
                $calendarId = (int) $calendar['cal_id'];
                $intervals = $this->loadCalendarIntervalsForId($calendarId);
            }
        }

        $holidays = $this->loadCalendarHolidaysForId($calendarId);

        return [
            'working_days' => $this->loadDefaultWorkingDays($calendar ?? []),
            'holidays' => $holidays,
            'intervals' => $intervals,
        ];
    }

    private function fetchCalendarForLine(int $lineId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cal_calendarios WHERE cal_linha_id = :lineId ORDER BY cal_id LIMIT 1');
        $stmt->execute(['lineId' => $lineId]);

        return $stmt->fetch() ?: null;
    }

    private function fetchAnyCalendarWithIntervals(?int $excludeCalendarId = null): ?array
    {
        $sql = 'SELECT c.* FROM cal_calendarios c WHERE EXISTS (SELECT 1 FROM cal_intervalos i WHERE i.cal_calendario_id = c.cal_id)';
        $params = [];

        if ($excludeCalendarId !== null) {
            $sql .= ' AND c.cal_id != :exclude';
            $params['exclude'] = $excludeCalendarId;
        }

        $sql .= ' ORDER BY c.cal_id LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() ?: null;
    }

    private function loadCalendarIntervalsForId(?int $calendarId): array
    {
        if ($calendarId === null) {
            return [];
        }

        $intervalStmt = $this->pdo->prepare('SELECT * FROM cal_intervalos WHERE cal_calendario_id = :calId ORDER BY cal_id');
        $intervalStmt->execute(['calId' => $calendarId]);

        $weekdayStmt = $this->pdo->prepare('SELECT diu_dia_peq FROM cal_dias_uteis WHERE diu_intervalo_id = :intervalId');

        $intervals = [];
        while ($interval = $intervalStmt->fetch()) {
            $weekdayStmt->execute(['intervalId' => $interval['cal_id']]);

            $days = array_map('intval', $weekdayStmt->fetchAll(PDO::FETCH_COLUMN));

            $intervals[] = [
                'start' => $this->formatTimeValue((string) $interval['cal_inicio']),
                'end' => $this->formatTimeValue((string) $interval['cal_fim']),
                'days' => $days,
            ];
        }

        return $intervals;
    }

    private function loadCalendarHolidaysForId(?int $calendarId): array
    {
        if ($calendarId === null) {
            return [];
        }

        $holidayStmt = $this->pdo->prepare('SELECT cal_data, cal_nome FROM cal_feriados WHERE cal_calendario_id = :calId ORDER BY cal_data');
        $holidayStmt->execute(['calId' => $calendarId]);

        $holidays = [];
        while ($holiday = $holidayStmt->fetch()) {
            $holidays[] = [
                'date' => $holiday['cal_data'],
                'name' => $holiday['cal_nome'],
            ];
        }

        return $holidays;
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

    private function normalizeLineCode(string $value): string
    {
        $code = trim($value);
        if ($code === '') {
            return '';
        }

        $code = preg_replace('/\s+/', '', $code) ?? $code;
        return strtoupper($code);
    }

    private function loadDefaultWorkingDays(array $calendar): array
    {
        return [1, 2, 3, 4, 5];
    }

    private function loadProducts(): array
    {
        $stmt = $this->pdo->query(
            'SELECT p.*, l.lin_codigo AS line_code'
            . ' FROM prd_produtos p'
            . ' JOIN lin_linhas l ON p.prd_linha_id = l.lin_id'
            . ' ORDER BY p.prd_sku'
        );

        $products = [];
        while ($row = $stmt->fetch()) {
            $products[$row['prd_sku']] = [
                'description' => $row['prd_descricao'],
                'reference_setup' => $row['prd_referencia_setup'],
                'line' => $row['line_code'] ?? '',
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
        $skipProductUpdates = !empty($meta['skip_products']);

        $preferredLineCode = isset($meta['preferred_line_code']) && is_string($meta['preferred_line_code']) ? trim($meta['preferred_line_code']) : null;
        $this->preferredLineCode = $preferredLineCode !== '' ? $preferredLineCode : null;

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

            $productsData = array_key_exists('products', $data) && is_array($data['products'])
                ? $data['products']
                : [];

            if (!$skipProductUpdates && ($productsData !== [] || $allowClearProducts)) {
                $this->replaceProducts($productsData, $lineCode, $allowClearProducts);
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

    private function replaceProducts(array $products, string $defaultLineCode, bool $allowClear): void
    {
        if ($products === []) {
            if ($allowClear) {
                $this->clearMatrix();
                $this->clearProgramItems();
                $this->pdo->prepare('DELETE FROM prd_produtos')->execute();
            }

            return;
        }

        $prepared = [];
        $lineCache = [];
        $lineIds = [];

        foreach ($products as $sku => $product) {
            $normalizedSku = $this->normalizeSkuValue((string) $sku);
            if ($normalizedSku === '') {
                continue;
            }

            $rawLine = (string) ($product['line'] ?? '');
            $lineCode = $this->normalizeLineCode($rawLine);
            if ($lineCode === '') {
                $lineCode = $defaultLineCode;
            }

            if (!isset($lineCache[$lineCode])) {
                $lineCache[$lineCode] = $this->ensureLine($lineCode);
            }

            $lineId = $lineCache[$lineCode];
            $lineIds[$lineId] = true;

            $prepared[] = [
                'sku' => $normalizedSku,
                'description' => (string) ($product['description'] ?? ''),
                'reference_setup' => (string) ($product['reference_setup'] ?? ''),
                'lineId' => $lineId,
                'rate' => (float) ($product['rate_per_hour'] ?? 0),
                'unit' => (string) ($product['unit'] ?? ''),
            ];
        }

        if ($prepared === []) {
            return;
        }

        $lineIdList = array_keys($lineIds);
        foreach ($lineIdList as $lineId) {
            $this->clearMatrixForLine($lineId);
        }

        $this->deleteProductsByLineIds($lineIdList);

        $insert = $this->pdo->prepare(
            'INSERT INTO prd_produtos (prd_sku, prd_descricao, prd_referencia_setup, prd_linha_id, prd_taxa_por_hora, prd_unidade) VALUES (:sku, :descricao, :ref, :lineId, :rate, :unit)'
        );

        foreach ($prepared as $row) {
            $insert->execute([
                'sku' => $row['sku'],
                'descricao' => $row['description'],
                'ref' => $row['reference_setup'],
                'lineId' => $row['lineId'],
                'rate' => $row['rate'],
                'unit' => $row['unit'],
            ]);
        }
    }

    private function clearMatrixForLine(int $lineId): void
    {
        $skuStmt = $this->pdo->prepare('SELECT prd_sku FROM prd_produtos WHERE prd_linha_id = :lineId');
        $skuStmt->execute(['lineId' => $lineId]);
        $skus = array_filter($skuStmt->fetchAll(PDO::FETCH_COLUMN));

        if ($skus === []) {
            return;
        }

        $originPlaceholders = [];
        $destinationPlaceholders = [];
        $params = [];

        foreach ($skus as $index => $sku) {
            $originKey = "origin{$index}";
            $destinationKey = "destination{$index}";
            $originPlaceholders[] = ":{$originKey}";
            $destinationPlaceholders[] = ":{$destinationKey}";
            $params[$originKey] = $sku;
            $params[$destinationKey] = $sku;
        }

        $originList = implode(', ', $originPlaceholders);
        $destinationList = implode(', ', $destinationPlaceholders);
        $sql = sprintf(
            'DELETE FROM mat_matriz_setup WHERE mat_sku_origem IN (%s) OR mat_sku_destino IN (%s)',
            $originList,
            $destinationList
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function deleteProductsByLineIds(array $lineIds): void
    {
        $filtered = array_values(array_filter(array_unique(array_map('intval', $lineIds))));
        if ($filtered === []) {
            return;
        }

        $placeholders = [];
        $params = [];
        foreach ($filtered as $index => $lineId) {
            $key = "line{$index}";
            $placeholders[] = ":{$key}";
            $params[$key] = $lineId;
        }

        $sql = 'DELETE FROM prd_produtos WHERE prd_linha_id IN (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function clearProgramItems(): void
    {
        $this->pdo->prepare('DELETE FROM prg_itens')->execute();
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



