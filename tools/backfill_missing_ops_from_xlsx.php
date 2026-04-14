<?php

declare(strict_types=1);

$xlsxPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'relatorio_api_20260408_161834.xlsx';
$dryRun = false;

foreach ($argv ?? [] as $arg) {
    if ($arg === '--dry-run' || $arg === '--dry-run=1') {
        $dryRun = true;
    }
}

if (!is_file($xlsxPath)) {
    fwrite(STDERR, "Arquivo XLSX nao encontrado: {$xlsxPath}\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=localhost;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

function normalizeOp(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    return ctype_digit($value) ? (ltrim($value, '0') ?: '0') : $value;
}

function isSetupTarget(?string $name): bool
{
    $name = strtoupper(trim((string) $name));
    return in_array($name, ['TROCA DE KIT', 'TROCA DE LIQUIDO'], true);
}

function priorityParada(?string $name): int
{
    $name = strtoupper(trim((string) $name));
    return match ($name) {
        'TROCA DE KIT' => 2,
        'TROCA DE LIQUIDO' => 1,
        default => 0,
    };
}

function cellValueFromRowXml(string $rowXml, string $col): ?string
{
    $pattern = '/<c r="' . preg_quote($col, '/') . '\d+"(?: t="(?<t>[^"]+)")?[^>]*>(?<inner>.*?)<\/c>/s';
    if (!preg_match($pattern, $rowXml, $match)) {
        return null;
    }

    $type = $match['t'] ?? '';
    $inner = $match['inner'] ?? '';

    if ($type === 'inlineStr') {
        if (preg_match('/<t>(.*?)<\/t>/s', $inner, $valueMatch)) {
            return html_entity_decode($valueMatch[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return '';
    }

    if (preg_match('/<v>(.*?)<\/v>/s', $inner, $valueMatch)) {
        return html_entity_decode($valueMatch[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    return '';
}

$zip = new ZipArchive();
if ($zip->open($xlsxPath) !== true) {
    fwrite(STDERR, "Nao foi possivel abrir o XLSX.\n");
    exit(1);
}

$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
$zip->close();

if ($sheetXml === false) {
    fwrite(STDERR, "sheet1.xml nao encontrado no XLSX.\n");
    exit(1);
}

$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->loadXML($sheetXml);
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
$rows = $xpath->query('//a:sheetData/a:row');

$rowsByOp = [];
$opSources = [];

for ($i = 0; $i < $rows->length; $i++) {
    $rowNode = $rows->item($i);
    if (!$rowNode instanceof DOMElement) {
        continue;
    }

    $rowNumber = (int) $rowNode->getAttribute('r');
    if ($rowNumber === 1) {
        continue;
    }

    $rowXml = $dom->saveXML($rowNode);
    if (!is_string($rowXml) || $rowXml === '') {
        continue;
    }

    $opRawAn = cellValueFromRowXml($rowXml, 'AN');
    $opRawAh = cellValueFromRowXml($rowXml, 'AH');
    $opSource = 'AN';
    $opRaw = $opRawAn;
    if (trim((string) $opRaw) === '') {
        $opSource = 'AH';
        $opRaw = $opRawAh;
    }

    $op = normalizeOp($opRaw);
    if ($op === '') {
        continue;
    }

    $data = trim((string) cellValueFromRowXml($rowXml, 'B'));
    $inicio = trim((string) cellValueFromRowXml($rowXml, 'C'));
    $fim = trim((string) cellValueFromRowXml($rowXml, 'D'));
    $duracao = (float) (cellValueFromRowXml($rowXml, 'E') ?? 0);
    $quantidade = (float) (cellValueFromRowXml($rowXml, 'AR') ?? 0);
    $estado = trim((string) cellValueFromRowXml($rowXml, 'A'));
    $nomeParada = trim((string) cellValueFromRowXml($rowXml, 'Z'));
    $tipoParada = trim((string) cellValueFromRowXml($rowXml, 'AB'));

    if ($data === '') {
        continue;
    }

    $rowsByOp[$op][] = [
        'row_number' => $rowNumber,
        'op_source' => $opSource,
        'op_original' => trim((string) $opRaw),
        'data' => $data,
        'inicio' => $inicio,
        'fim' => $fim,
        'duracao' => $duracao,
        'quantidade' => $quantidade,
        'estado' => $estado,
        'parada_nomeParada' => $nomeParada,
        'parada_tipo_nome' => $tipoParada,
    ];
    $opSources[$op] = $opSource;
}

$stmtRawCount = $pdo->prepare('SELECT COUNT(*) FROM realizado_2026_eventos WHERE CAST(ordem_op AS CHAR) = :op');
$stmtExcelCount = $pdo->prepare('SELECT COUNT(*) FROM realizado_2026_excel WHERE CAST(ordem_op AS CHAR) = :op');

$rawInsert = $pdo->prepare(
    'INSERT INTO realizado_2026_eventos
        (evt_chave_externa, evt_codigo_evento, data_evento, ordem_op, quantidade, inicio_evento, fim_evento, duracao_evento_minutos, estado_evento, parada_nomeParada, parada_tipo_nome, setup_duracao_minutos, setup_eventos_count, payload_json)
     VALUES
        (:evt_chave_externa, :evt_codigo_evento, :data_evento, :ordem_op, :quantidade, :inicio_evento, :fim_evento, :duracao_evento_minutos, :estado_evento, :parada_nomeParada, :parada_tipo_nome, :setup_duracao_minutos, :setup_eventos_count, :payload_json)
     ON DUPLICATE KEY UPDATE
        evt_codigo_evento = VALUES(evt_codigo_evento),
        data_evento = VALUES(data_evento),
        ordem_op = VALUES(ordem_op),
        quantidade = VALUES(quantidade),
        inicio_evento = VALUES(inicio_evento),
        fim_evento = VALUES(fim_evento),
        duracao_evento_minutos = VALUES(duracao_evento_minutos),
        estado_evento = VALUES(estado_evento),
        parada_nomeParada = COALESCE(NULLIF(VALUES(parada_nomeParada), \'\'), parada_nomeParada),
        parada_tipo_nome = COALESCE(NULLIF(VALUES(parada_tipo_nome), \'\'), parada_tipo_nome),
        setup_duracao_minutos = VALUES(setup_duracao_minutos),
        setup_eventos_count = VALUES(setup_eventos_count),
        payload_json = VALUES(payload_json),
        imported_at = NOW()'
);

$excelInsert = $pdo->prepare(
    'INSERT INTO realizado_2026_excel
        (data_evento, ordem_op, quantidade, inicio_evento, fim_evento, parada_nomeParada, setup_duracao_minutos, setup_eventos_count)
     VALUES
        (:data_evento, :ordem_op, :quantidade, :inicio_evento, :fim_evento, :parada_nomeParada, :setup_duracao_minutos, :setup_eventos_count)
     ON DUPLICATE KEY UPDATE
        quantidade = quantidade + VALUES(quantidade),
        inicio_evento = CASE
            WHEN inicio_evento IS NULL OR inicio_evento = "" THEN VALUES(inicio_evento)
            WHEN VALUES(inicio_evento) IS NULL OR VALUES(inicio_evento) = "" THEN inicio_evento
            WHEN VALUES(inicio_evento) < inicio_evento THEN VALUES(inicio_evento)
            ELSE inicio_evento
        END,
        fim_evento = CASE
            WHEN fim_evento IS NULL OR fim_evento = "" THEN VALUES(fim_evento)
            WHEN VALUES(fim_evento) IS NULL OR VALUES(fim_evento) = "" THEN fim_evento
            WHEN VALUES(fim_evento) > fim_evento THEN VALUES(fim_evento)
            ELSE fim_evento
        END,
        parada_nomeParada = CASE
            WHEN COALESCE(NULLIF(VALUES(parada_nomeParada), ""), "") = "" THEN parada_nomeParada
            WHEN parada_nomeParada IS NULL OR TRIM(parada_nomeParada) = "" THEN VALUES(parada_nomeParada)
            WHEN UPPER(VALUES(parada_nomeParada)) = "TROCA DE KIT" THEN VALUES(parada_nomeParada)
            WHEN UPPER(VALUES(parada_nomeParada)) = "TROCA DE LIQUIDO" AND UPPER(parada_nomeParada) <> "TROCA DE KIT" THEN VALUES(parada_nomeParada)
            ELSE parada_nomeParada
        END,
        setup_duracao_minutos = VALUES(setup_duracao_minutos),
        setup_eventos_count = VALUES(setup_eventos_count),
        imported_at = NOW()'
);

$pdo->beginTransaction();

$analyzedOps = count($rowsByOp);
$opsWithoutRaw = [];
$opsCorrected = [];
$opsWithoutExcel = [];
$rawInserted = 0;
$excelInserted = 0;

try {
    foreach ($rowsByOp as $op => $rowsForOp) {
        $stmtRawCount->execute(['op' => $op]);
        $rawCount = (int) $stmtRawCount->fetchColumn();

        $stmtExcelCount->execute(['op' => $op]);
        $excelCount = (int) $stmtExcelCount->fetchColumn();

        if ($rawCount > 0) {
            continue;
        }

        $opsWithoutRaw[] = $op;
        if ($excelCount > 0) {
            $opsCorrected[] = $op;
        } else {
            $opsWithoutExcel[] = $op;
        }

        foreach ($rowsForOp as $row) {
            $evtKey = sprintf(
                'xlsx|%d|%s|%s|%s|%s|%s',
                $row['row_number'],
                $row['data'],
                $row['inicio'],
                $row['fim'],
                $op,
                $row['parada_nomeParada'] !== '' ? $row['parada_nomeParada'] : 'SEM_PARADA'
            );

            $setupDuracao = isSetupTarget($row['parada_nomeParada']) ? $row['duracao'] : 0.0;
            $setupEventos = isSetupTarget($row['parada_nomeParada']) ? 1 : 0;

            $rawInsert->execute([
                'evt_chave_externa' => $evtKey,
                'evt_codigo_evento' => null,
                'data_evento' => $row['data'],
                'ordem_op' => $op,
                'quantidade' => $row['quantidade'],
                'inicio_evento' => $row['inicio'] !== '' ? $row['inicio'] : null,
                'fim_evento' => $row['fim'] !== '' ? $row['fim'] : null,
                'duracao_evento_minutos' => $row['duracao'],
                'estado_evento' => $row['estado'] !== '' ? $row['estado'] : null,
                'parada_nomeParada' => $row['parada_nomeParada'] !== '' ? $row['parada_nomeParada'] : null,
                'parada_tipo_nome' => $row['parada_tipo_nome'] !== '' ? $row['parada_tipo_nome'] : null,
                'setup_duracao_minutos' => $setupDuracao,
                'setup_eventos_count' => $setupEventos,
                'payload_json' => json_encode([
                    'row_number' => $row['row_number'],
                    'op_source' => $row['op_source'],
                    'op_original' => $row['op_original'],
                    'data' => $row['data'],
                    'inicio' => $row['inicio'],
                    'fim' => $row['fim'],
                    'duracao' => $row['duracao'],
                    'ordem_op' => $op,
                    'quantidade' => $row['quantidade'],
                    'estado' => $row['estado'],
                    'parada_nomeParada' => $row['parada_nomeParada'],
                    'parada_tipo_nome' => $row['parada_tipo_nome'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $rawInserted++;
        }

        if ($excelCount === 0) {
            $agg = [];
            foreach ($rowsForOp as $row) {
                $key = $row['data'] . '|' . $op;
                if (!isset($agg[$key])) {
                    $agg[$key] = [
                        'data_evento' => $row['data'],
                        'ordem_op' => $op,
                        'quantidade' => 0.0,
                        'inicio_evento' => $row['inicio'],
                        'fim_evento' => $row['fim'],
                        'parada_nomeParada' => $row['parada_nomeParada'],
                        'setup_duracao_minutos' => 0.0,
                        'setup_eventos_count' => 0,
                    ];
                }

                $agg[$key]['quantidade'] += $row['quantidade'];
                if ($row['inicio'] !== '' && ($agg[$key]['inicio_evento'] === '' || $row['inicio'] < $agg[$key]['inicio_evento'])) {
                    $agg[$key]['inicio_evento'] = $row['inicio'];
                }
                if ($row['fim'] !== '' && ($agg[$key]['fim_evento'] === '' || $row['fim'] > $agg[$key]['fim_evento'])) {
                    $agg[$key]['fim_evento'] = $row['fim'];
                }
                if (priorityParada($row['parada_nomeParada']) > priorityParada($agg[$key]['parada_nomeParada'])) {
                    $agg[$key]['parada_nomeParada'] = $row['parada_nomeParada'];
                } elseif (trim((string) $agg[$key]['parada_nomeParada']) === '' && trim($row['parada_nomeParada']) !== '') {
                    $agg[$key]['parada_nomeParada'] = $row['parada_nomeParada'];
                }
                $agg[$key]['setup_duracao_minutos'] += $setupDuracao;
                $agg[$key]['setup_eventos_count'] += $setupEventos;
            }

            foreach ($agg as $row) {
                $excelInsert->execute([
                    'data_evento' => $row['data_evento'],
                    'ordem_op' => $row['ordem_op'],
                    'quantidade' => $row['quantidade'],
                    'inicio_evento' => $row['inicio_evento'] !== '' ? $row['inicio_evento'] : null,
                    'fim_evento' => $row['fim_evento'] !== '' ? $row['fim_evento'] : null,
                    'parada_nomeParada' => $row['parada_nomeParada'] !== '' ? $row['parada_nomeParada'] : null,
                    'setup_duracao_minutos' => $row['setup_duracao_minutos'],
                    'setup_eventos_count' => $row['setup_eventos_count'],
                ]);
                $excelInserted++;
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Falha no backfill: {$e->getMessage()}\n");
    exit(1);
}

sort($opsWithoutRaw, SORT_NATURAL);
sort($opsCorrected, SORT_NATURAL);
sort($opsWithoutExcel, SORT_NATURAL);

echo "Backfill de complementares concluido." . PHP_EOL;
echo "OPS analisadas no XLSX: {$analyzedOps}" . PHP_EOL;
echo "OPS sem bruto no sandbox: " . count($opsWithoutRaw) . PHP_EOL;
echo "OPS corrigidas (excel tinha linha principal): " . count($opsCorrected) . PHP_EOL;
echo "OPS sem linha principal no excel: " . count($opsWithoutExcel) . PHP_EOL;
echo "Linhas brutas importadas: {$rawInserted}" . PHP_EOL;
echo "Linhas agregadas importadas: {$excelInserted}" . PHP_EOL;

if (!empty($opsCorrected)) {
    echo "OPS corrigidas: " . implode(', ', array_slice($opsCorrected, 0, 80));
    if (count($opsCorrected) > 80) {
        echo ' ...';
    }
    echo PHP_EOL;
}

if (!empty($opsWithoutExcel)) {
    echo "OPS sem linha principal para criar no excel: " . implode(', ', array_slice($opsWithoutExcel, 0, 80));
    if (count($opsWithoutExcel) > 80) {
        echo ' ...';
    }
    echo PHP_EOL;
}

