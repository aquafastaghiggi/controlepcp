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
    private ?bool $productsHasExcelLineColumn = null;

    public function __construct(?PDO $pdo = null, ?string $preferredLineCode = null)
    {
        $this->pdo = $pdo ?? Connection::get();
        if ($preferredLineCode !== null) {
            $normalized = $this->normalizeLineCode($preferredLineCode);
            $this->preferredLineCode = $normalized !== '' ? $normalized : null;
        }
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
        $normalized = trim($code);
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;
        $normalized = strtoupper($normalized);

        $stmt = $this->pdo->prepare(
            'SELECT l.*,'
            . ' (SELECT COUNT(*) FROM prd_produtos p WHERE p.prd_linha_id = l.lin_id) AS products_count,'
            . ' (SELECT COUNT(*) FROM cal_intervalos i JOIN cal_calendarios c ON c.cal_id = i.cal_calendario_id WHERE c.cal_linha_id = l.lin_id) AS intervals_count,'
            . ' (SELECT COUNT(*) FROM mat_matriz_setup m WHERE m.mat_linha_id = l.lin_id) AS matrix_count'
            . ' FROM lin_linhas l'
            . " WHERE REPLACE(UPPER(l.lin_codigo), ' ', '') = :code"
            . ' LIMIT 1'
        );
        $stmt->execute(['code' => $normalized]);

        return $stmt->fetch() ?: null;
    }

    private function loadCalendar(int $lineId): array
    {
        $calendar = $this->fetchCalendarForLine($lineId);
        $calendarId = $calendar ? (int) $calendar['cal_id'] : null;

        if ($calendarId === null) {
            $calendarId = $this->ensureCalendar($lineId);
            $calendar = ['cal_id' => $calendarId];
        }

        $intervals = $this->loadCalendarIntervalsForId($calendarId);

        if ($intervals === []) {
            $fallbackCalendar = $this->fetchAnyCalendarWithIntervals($calendarId);
            if ($fallbackCalendar) {
                $templateIntervals = $this->loadCalendarIntervalsForId((int) $fallbackCalendar['cal_id']);
                if ($templateIntervals !== []) {
                    // Em vez de "emprestar" o calendario de outra linha, replicamos os intervalos/dias uteis
                    // para o calendario desta linha, garantindo amarracao consistente por linha.
                    $this->replaceCalendarIntervals($calendarId, $templateIntervals);
                    $intervals = $this->loadCalendarIntervalsForId($calendarId);
                }
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

    private function normalizeExcelLineAbbrev(string $value): string
    {
        $text = trim($value);
        if ($text === '') {
            return '';
        }

        $text = strtolower($text);
        $text = preg_replace('/\s+/', '', $text) ?? $text;
        $text = preg_replace('/[^a-z0-9]/', '', $text) ?? $text;
        if ($text === '') {
            return '';
        }

        $hasPrefix = str_starts_with($text, 'linha') || str_starts_with($text, 'ln');
        if (!$hasPrefix) {
            return '';
        }

        if (str_starts_with($text, 'linha')) {
            $text = substr($text, 5);
        }

        if (str_starts_with($text, 'ln')) {
            $text = substr($text, 2);
        }

        if (preg_match('/(\d+)/', $text, $match) !== 1) {
            return '';
        }

        $number = (int) $match[1];
        if ($number <= 0) {
            return '';
        }

        return 'ln' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
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

        $itemsStmt = $this->pdo->prepare('SELECT prg_sequencia, prg_sku, prg_quantidade, prg_inicio_planejado, prg_itens_op FROM prg_itens WHERE prg_programa_id = :programId ORDER BY prg_sequencia');
        $itemsStmt->execute(['programId' => $program['prg_id']]);

        $rows = [];
        while ($item = $itemsStmt->fetch()) {
            $rows[] = [
                'sequence' => (int) $item['prg_sequencia'],
                'sku' => $item['prg_sku'],
                'quantity' => (float) $item['prg_quantidade'],
                'planned_start' => $item['prg_inicio_planejado'] ? (new \DateTimeImmutable($item['prg_inicio_planejado']))->format('Y-m-d\TH:i') : '',
                'op' => (string) ($item['prg_itens_op'] ?? ''),
            ];
        }

        return $rows;
    }

    public function persistDataset(array $data): void
    {
        $lineCode = trim((string) ($data['calendar']['line'] ?? 'L2'));
        $normalizedLineCode = $this->normalizeExcelLineAbbrev($lineCode);
        if ($normalizedLineCode !== '') {
            $lineCode = $normalizedLineCode;
        }
        $meta = isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : [];
        $allowClearProducts = !empty($meta['allow_clear_products']);
        $allowClearMatrix = !empty($meta['allow_clear_matrix']);
        $allowClearCalendar = !empty($meta['allow_clear_calendar']);
        $skipProductUpdates = !empty($meta['skip_products']);
        $syncProductsOnly = !empty($meta['sync_products_only']);

        $preferredLineCode = isset($meta['preferred_line_code']) && is_string($meta['preferred_line_code']) ? trim($meta['preferred_line_code']) : null;
        $this->preferredLineCode = $preferredLineCode !== '' ? $preferredLineCode : null;

        $productsData = array_key_exists('products', $data) && is_array($data['products'])
            ? $data['products']
            : [];

        if (!$skipProductUpdates && ($productsData !== [] || $allowClearProducts)) {
            $this->ensureProductsExcelLineColumn();
        }

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

            if (!$skipProductUpdates && ($productsData !== [] || $allowClearProducts)) {
                $this->replaceProducts($productsData, $lineCode, $allowClearProducts, $syncProductsOnly);
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

    public function updateProductRatePerHour(string $sku, float $ratePerHour): void
    {
        $normalizedSku = $this->normalizeSkuValue($sku);
        if ($normalizedSku === '') {
            throw new \InvalidArgumentException('SKU invalido.');
        }

        $stmt = $this->pdo->prepare('SELECT 1 FROM prd_produtos WHERE prd_sku = :sku LIMIT 1');
        $stmt->execute(['sku' => $normalizedSku]);
        if (!$stmt->fetchColumn()) {
            throw new \RuntimeException('SKU nao encontrado.');
        }

        $rate = $ratePerHour;
        if (!is_finite($rate)) {
            $rate = 0.0;
        }

        $update = $this->pdo->prepare('UPDATE prd_produtos SET prd_taxa_por_hora = :rate WHERE prd_sku = :sku');
        $update->execute([
            'rate' => $rate,
            'sku' => $normalizedSku,
        ]);
    }

    public function updateMatrixDuration(string $lineCode, string $fromSku, string $toSku, string $duration): void
    {
        $rawLineCode = trim($lineCode);
        if ($rawLineCode === '') {
            throw new \InvalidArgumentException('Linha invalida.');
        }

        $normalizedLine = $this->normalizeLineCode($rawLineCode);
        if ($normalizedLine === '') {
            throw new \InvalidArgumentException('Linha invalida.');
        }

        $from = $this->normalizeSkuValue($fromSku);
        $to = $this->normalizeSkuValue($toSku);
        if ($from === '' || $to === '') {
            throw new \InvalidArgumentException('SKU invalido.');
        }

        $durationTrimmed = trim((string) $duration);
        if ($durationTrimmed === '') {
            throw new \InvalidArgumentException('Tempo invalido.');
        }

        if (str_contains($durationTrimmed, ':')) {
            $minutes = DateTimeHelper::minutesFromDuration($durationTrimmed);
        } elseif (is_numeric($durationTrimmed)) {
            $minutes = (int) $durationTrimmed;
        } else {
            throw new \InvalidArgumentException('Tempo invalido.');
        }

        if ($minutes < 0) {
            $minutes = 0;
        }

        $lineStmt = $this->pdo->prepare('SELECT lin_id FROM lin_linhas WHERE lin_codigo = :code LIMIT 1');
        $lineStmt->execute(['code' => $rawLineCode]);
        $lineId = (int) ($lineStmt->fetchColumn() ?: 0);

        if ($lineId <= 0) {
            $lineStmt = $this->pdo->prepare("SELECT lin_id FROM lin_linhas WHERE REPLACE(UPPER(lin_codigo), ' ', '') = :code LIMIT 1");
            $lineStmt->execute(['code' => $normalizedLine]);
            $lineId = (int) ($lineStmt->fetchColumn() ?: 0);
        }
        if ($lineId <= 0) {
            throw new \RuntimeException('Linha nao encontrada.');
        }

        $exists = $this->pdo->prepare(
            'SELECT mat_id FROM mat_matriz_setup'
            . ' WHERE mat_linha_id = :lineId AND mat_sku_origem = :fromSku AND mat_sku_destino = :toSku'
            . ' LIMIT 1'
        );
        $exists->execute([
            'lineId' => $lineId,
            'fromSku' => $from,
            'toSku' => $to,
        ]);
        $matrixId = (int) ($exists->fetchColumn() ?: 0);
        if ($matrixId <= 0) {
            throw new \RuntimeException('Registro de matriz nao encontrado.');
        }

        $update = $this->pdo->prepare(
            'UPDATE mat_matriz_setup SET mat_duracao_minutos = :minutes WHERE mat_id = :id'
        );
        $update->execute([
            'minutes' => $minutes,
            'id' => $matrixId,
        ]);
    }

    private function ensureLine(string $code): int
    {
        $trimmed = trim($code);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Linha invalida.');
        }

        $stmt = $this->pdo->prepare('SELECT lin_id FROM lin_linhas WHERE lin_codigo = :code LIMIT 1');
        $stmt->execute(['code' => $trimmed]);
        $line = $stmt->fetch();

        if ($line) {
            return (int) $line['lin_id'];
        }

        $normalizedAbbrev = $this->normalizeExcelLineAbbrev($trimmed);
        $candidates = [];

        $lowerCompact = strtolower($trimmed);
        $lowerCompact = preg_replace('/\s+/', '', $lowerCompact) ?? $lowerCompact;
        $candidates[] = $lowerCompact;

        if ($normalizedAbbrev !== '') {
            $candidates[] = $normalizedAbbrev;
            $digits = substr($normalizedAbbrev, 2);
            if ($digits !== '') {
                $candidates[] = 'linha' . $digits;
            }
        }

        $candidates = array_values(array_filter(array_unique($candidates), static fn (string $value): bool => $value !== ''));
        if ($candidates !== []) {
            $placeholders = [];
            $params = [];
            foreach ($candidates as $index => $value) {
                $key = "c{$index}";
                $placeholders[] = ":{$key}";
                $params[$key] = $value;
            }

            $sql = 'SELECT lin_id FROM lin_linhas WHERE REPLACE(LOWER(lin_codigo), \' \', \'\') IN ('
                . implode(', ', $placeholders)
                . ') LIMIT 1';
            $matchStmt = $this->pdo->prepare($sql);
            $matchStmt->execute($params);
            $matchId = (int) ($matchStmt->fetchColumn() ?: 0);
            if ($matchId > 0) {
                return $matchId;
            }
        }

        $insertCode = $normalizedAbbrev !== '' ? $normalizedAbbrev : $trimmed;
        $stmt = $this->pdo->prepare('INSERT INTO lin_linhas (lin_codigo, lin_nome) VALUES (:code, :name)');
        $stmt->execute(['code' => $insertCode, 'name' => sprintf('Linha %s', $insertCode)]);

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

    private function replaceProducts(array $products, string $defaultLineCode, bool $allowClear, bool $syncOnly = false): void
    {
        if ($products === []) {
            if ($allowClear) {
                $this->clearMatrix();
                $this->clearProgramItems();
                $this->pdo->prepare('DELETE FROM prd_produtos')->execute();
            }

            return;
        }

        $defaultLineCode = trim($defaultLineCode);
        $normalizedDefaultLineCode = $this->normalizeExcelLineAbbrev($defaultLineCode);
        if ($normalizedDefaultLineCode !== '') {
            $defaultLineCode = $normalizedDefaultLineCode;
        }

        $hasExcelLineColumn = $this->ensureProductsExcelLineColumn();
        $prepared = [];
        $lineCache = [];
        $lineIds = [];

        foreach ($products as $sku => $product) {
            $normalizedSku = $this->normalizeSkuValue((string) $sku);
            if ($normalizedSku === '') {
                continue;
            }

            $rawLine = (string) ($product['line'] ?? '');
            $lineCode = $this->normalizeExcelLineAbbrev($rawLine);
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
                'lineExcel' => $lineCode,
                'rate' => (float) ($product['rate_per_hour'] ?? 0),
                'unit' => (string) ($product['unit'] ?? ''),
            ];
        }

        if ($prepared === []) {
            return;
        }

        if (!$syncOnly) {
            $lineIdList = array_keys($lineIds);
            foreach ($lineIdList as $lineId) {
                $this->clearMatrixForLine($lineId);
            }

            $this->deleteProductsByLineIds($lineIdList);
        }

        if ($hasExcelLineColumn) {
            $insert = $this->pdo->prepare(
                'INSERT INTO prd_produtos (prd_sku, prd_descricao, prd_referencia_setup, prd_linha_id, prd_linha_excel, prd_taxa_por_hora, prd_unidade)'
                . ' VALUES (:sku, :descricao, :ref, :lineId, :lineExcel, :rate, :unit)'
                . ' ON DUPLICATE KEY UPDATE'
                . ' prd_descricao = VALUES(prd_descricao),'
                . ' prd_referencia_setup = VALUES(prd_referencia_setup),'
                . ' prd_linha_id = VALUES(prd_linha_id),'
                . ' prd_linha_excel = VALUES(prd_linha_excel),'
                . ' prd_taxa_por_hora = VALUES(prd_taxa_por_hora),'
                . ' prd_unidade = VALUES(prd_unidade)'
            );
        } else {
            $insert = $this->pdo->prepare(
                'INSERT INTO prd_produtos (prd_sku, prd_descricao, prd_referencia_setup, prd_linha_id, prd_taxa_por_hora, prd_unidade)'
                . ' VALUES (:sku, :descricao, :ref, :lineId, :rate, :unit)'
                . ' ON DUPLICATE KEY UPDATE'
                . ' prd_descricao = VALUES(prd_descricao),'
                . ' prd_referencia_setup = VALUES(prd_referencia_setup),'
                . ' prd_linha_id = VALUES(prd_linha_id),'
                . ' prd_taxa_por_hora = VALUES(prd_taxa_por_hora),'
                . ' prd_unidade = VALUES(prd_unidade)'
            );
        }

        foreach ($prepared as $row) {
            $params = [
                'sku' => $row['sku'],
                'descricao' => $row['description'],
                'ref' => $row['reference_setup'],
                'lineId' => $row['lineId'],
                'rate' => $row['rate'],
                'unit' => $row['unit'],
            ];
            if ($hasExcelLineColumn) {
                $params['lineExcel'] = $row['lineExcel'];
            }
            $insert->execute($params);
        }
    }

    private function ensureProductsExcelLineColumn(): bool
    {
        if ($this->productsHasExcelLineColumn !== null) {
            return $this->productsHasExcelLineColumn;
        }

        try {
            $exists = $this->pdo->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prd_produtos' AND COLUMN_NAME = 'prd_linha_excel'"
            )->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }

        if ((int) $exists > 0) {
            $this->productsHasExcelLineColumn = true;
            return true;
        }

        if ($this->pdo->inTransaction()) {
            return false;
        }

        try {
            $this->pdo->exec("ALTER TABLE prd_produtos ADD COLUMN prd_linha_excel VARCHAR(60) NOT NULL DEFAULT '' AFTER prd_linha_id");
        } catch (\Throwable $e) {
            // Se nao for possivel alterar o schema, seguimos sem gravar o campo textual para nao quebrar fluxos existentes.
        }

        $exists = $this->pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prd_produtos' AND COLUMN_NAME = 'prd_linha_excel'"
        )->fetchColumn();

        $this->productsHasExcelLineColumn = (int) $exists > 0;
        return $this->productsHasExcelLineColumn;
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

            $normalizedLineCode = $this->normalizeExcelLineAbbrev($lineCode);
            if ($normalizedLineCode !== '') {
                $lineCode = $normalizedLineCode;
            }

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



