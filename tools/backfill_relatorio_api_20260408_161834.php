<?php

declare(strict_types=1);

$xlsxPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'relatorio_api_20260408_161834.xlsx';

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

    return ctype_digit($value) ? ltrim($value, '0') ?: '0' : $value;
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

function isSetupTarget(?string $name): bool
{
    $name = strtoupper(trim((string) $name));
    return in_array($name, ['TROCA DE KIT', 'TROCA DE LIQUIDO'], true);
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

$stmtEvent = $pdo->prepare(
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

$stmtExcel = $pdo->prepare(
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

$agg = [];
$insertedEventRows = 0;
$insertedExcelRows = 0;

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

    $data = trim((string) cellValueFromRowXml($rowXml, 'B'));
    $inicio = trim((string) cellValueFromRowXml($rowXml, 'C'));
    $fim = trim((string) cellValueFromRowXml($rowXml, 'D'));
    $duracao = (float) (cellValueFromRowXml($rowXml, 'E') ?? 0);
    $op = normalizeOp(cellValueFromRowXml($rowXml, 'AH'));
    $quantidade = (float) (cellValueFromRowXml($rowXml, 'AR') ?? 0);
    $estado = trim((string) cellValueFromRowXml($rowXml, 'A'));
    $nomeParada = trim((string) cellValueFromRowXml($rowXml, 'Z'));
    $tipoParada = trim((string) cellValueFromRowXml($rowXml, 'AB'));

    if ($data === '' || $op === '') {
        continue;
    }

    $evtKey = sprintf(
        'xlsx|%d|%s|%s|%s|%s|%s',
        $rowNumber,
        $data,
        $inicio,
        $fim,
        $op,
        $nomeParada !== '' ? $nomeParada : 'SEM_PARADA'
    );

    $setupDuracao = isSetupTarget($nomeParada) ? $duracao : 0.0;
    $setupEventos = isSetupTarget($nomeParada) ? 1 : 0;

    $stmtEvent->execute([
        'evt_chave_externa' => $evtKey,
        'evt_codigo_evento' => null,
        'data_evento' => $data,
        'ordem_op' => $op,
        'quantidade' => $quantidade,
        'inicio_evento' => $inicio !== '' ? $inicio : null,
        'fim_evento' => $fim !== '' ? $fim : null,
        'duracao_evento_minutos' => $duracao,
        'estado_evento' => $estado !== '' ? $estado : null,
        'parada_nomeParada' => $nomeParada !== '' ? $nomeParada : null,
        'parada_tipo_nome' => $tipoParada !== '' ? $tipoParada : null,
        'setup_duracao_minutos' => $setupDuracao,
        'setup_eventos_count' => $setupEventos,
        'payload_json' => json_encode([
            'row_number' => $rowNumber,
            'data' => $data,
            'inicio' => $inicio,
            'fim' => $fim,
            'duracao' => $duracao,
            'ordem_op' => $op,
            'quantidade' => $quantidade,
            'estado' => $estado,
            'parada_nomeParada' => $nomeParada,
            'parada_tipo_nome' => $tipoParada,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $insertedEventRows++;

    $key = $data . '|' . $op;
    if (!isset($agg[$key])) {
        $agg[$key] = [
            'data_evento' => $data,
            'ordem_op' => $op,
            'quantidade' => 0.0,
            'inicio_evento' => $inicio,
            'fim_evento' => $fim,
            'parada_nomeParada' => $nomeParada,
            'setup_duracao_minutos' => 0.0,
            'setup_eventos_count' => 0,
        ];
    }

    $agg[$key]['quantidade'] += $quantidade;
    if ($inicio !== '' && ($agg[$key]['inicio_evento'] === '' || $inicio < $agg[$key]['inicio_evento'])) {
        $agg[$key]['inicio_evento'] = $inicio;
    }
    if ($fim !== '' && ($agg[$key]['fim_evento'] === '' || $fim > $agg[$key]['fim_evento'])) {
        $agg[$key]['fim_evento'] = $fim;
    }

    if (priorityParada($nomeParada) > priorityParada($agg[$key]['parada_nomeParada'])) {
        $agg[$key]['parada_nomeParada'] = $nomeParada;
    } elseif (trim((string) $agg[$key]['parada_nomeParada']) === '' && trim($nomeParada) !== '') {
        $agg[$key]['parada_nomeParada'] = $nomeParada;
    }

    $agg[$key]['setup_duracao_minutos'] += $setupDuracao;
    $agg[$key]['setup_eventos_count'] += $setupEventos;
}

$pdo->beginTransaction();
try {
    foreach ($agg as $row) {
        $stmtExcel->execute([
            'data_evento' => $row['data_evento'],
            'ordem_op' => $row['ordem_op'],
            'quantidade' => $row['quantidade'],
            'inicio_evento' => $row['inicio_evento'] !== '' ? $row['inicio_evento'] : null,
            'fim_evento' => $row['fim_evento'] !== '' ? $row['fim_evento'] : null,
            'parada_nomeParada' => trim((string) $row['parada_nomeParada']) !== '' ? $row['parada_nomeParada'] : null,
            'setup_duracao_minutos' => $row['setup_duracao_minutos'],
            'setup_eventos_count' => $row['setup_eventos_count'],
        ]);
        $insertedExcelRows++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Falha no backfill: {$e->getMessage()}\n");
    exit(1);
}

echo "Backfill concluido.\n";
echo "Linhas brutas importadas: {$insertedEventRows}\n";
echo "Linhas agregadas importadas: {$insertedExcelRows}\n";
