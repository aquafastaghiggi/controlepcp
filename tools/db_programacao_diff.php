<?php

declare(strict_types=1);

/**
 * Read-only, programacao-focused diff between sandbox and production DBs.
 *
 * - Targets tables used by gantt.php: prg_programas, prg_itens, sch_linhas.
 * - Restricts analysis to programacoes that exist in sandbox (safe scope).
 * - Does NOT write to either database.
 */

function opt(array $options, string $key, ?string $fallback = null): ?string
{
    if (array_key_exists($key, $options) && $options[$key] !== false && $options[$key] !== null) {
        return (string) $options[$key];
    }
    return $fallback;
}

function pdo_connect(string $host, string $port, string $db, string $user, string $pass): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ]);
}

function fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function array_diff_assoc_keys(array $a, array $b): array
{
    $diff = [];
    $keys = array_unique(array_merge(array_keys($a), array_keys($b)));
    sort($keys);
    foreach ($keys as $k) {
        $va = $a[$k] ?? null;
        $vb = $b[$k] ?? null;
        if ($va !== $vb) {
            $diff[] = $k;
        }
    }
    return $diff;
}

function list_ids(PDO $pdo, string $table, string $idCol, string $whereSql, array $params): array
{
    $sql = "SELECT `{$idCol}` AS id FROM `{$table}` WHERE {$whereSql} ORDER BY `{$idCol}` ASC";
    $rows = fetch_all($pdo, $sql, $params);
    return array_map(static fn(array $r) => $r['id'], $rows);
}

$options = getopt('', [
    'host::',
    'port::',
    'user::',
    'pass::',
    'sandbox::',
    'prod::',
    'limit::',
    'out::',
]);

$host = opt($options, 'host', getenv('DB_HOST') ?: '127.0.0.1') ?? '127.0.0.1';
$port = opt($options, 'port', getenv('DB_PORT') ?: '3306') ?? '3306';
$user = opt($options, 'user', getenv('DB_USER') ?: 'root') ?? 'root';
$pass = opt($options, 'pass', getenv('DB_PASS') ?: '') ?? '';
$dbSandbox = opt($options, 'sandbox', 'controlepcp_sandbox') ?? 'controlepcp_sandbox';
$dbProd = opt($options, 'prod', 'controlepcp') ?? 'controlepcp';
$limit = (int) (opt($options, 'limit', '50') ?? '50');
$out = opt($options, 'out', null);

try {
    if ($pass === '') {
        throw new RuntimeException('DB password ausente. Defina DB_PASS ou use --pass=...');
    }
    $sb = pdo_connect($host, $port, $dbSandbox, $user, $pass);
    $pd = pdo_connect($host, $port, $dbProd, $user, $pass);

    $sandboxPrograms = fetch_all(
        $sb,
        'SELECT * FROM prg_programas ORDER BY prg_id ASC LIMIT ' . $limit
    );

    $programIds = array_values(array_map(static fn(array $r): int => (int) ($r['prg_id'] ?? 0), $sandboxPrograms));
    $programIds = array_values(array_filter($programIds, static fn(int $id): bool => $id > 0));

    $perProgram = [];
    foreach ($sandboxPrograms as $sbRow) {
        $id = (int) ($sbRow['prg_id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $pdRow = fetch_one($pd, 'SELECT * FROM prg_programas WHERE prg_id = :id LIMIT 1', ['id' => $id]);
        $diffCols = $pdRow ? array_diff_assoc_keys($sbRow, $pdRow) : array_keys($sbRow);

        $itemIdsSb = list_ids($sb, 'prg_itens', 'prg_id_item', 'prg_programa_id = :id', ['id' => $id]);
        $itemIdsPd = list_ids($pd, 'prg_itens', 'prg_id_item', 'prg_programa_id = :id', ['id' => $id]);

        $schIdsSb = list_ids($sb, 'sch_linhas', 'sch_id', 'sch_programa_id = :id', ['id' => $id]);
        $schIdsPd = list_ids($pd, 'sch_linhas', 'sch_id', 'sch_programa_id = :id', ['id' => $id]);

        $missingItemsInProd = array_values(array_diff($itemIdsSb, $itemIdsPd));
        $missingItemsInSandbox = array_values(array_diff($itemIdsPd, $itemIdsSb));
        $missingSchInProd = array_values(array_diff($schIdsSb, $schIdsPd));
        $missingSchInSandbox = array_values(array_diff($schIdsPd, $schIdsSb));

        $perProgram[(string) $id] = [
            'prg_programas' => [
                'exists_in_prod' => $pdRow !== null,
                'diff_columns' => $diffCols,
            ],
            'prg_itens' => [
                'count' => ['sandbox' => count($itemIdsSb), 'prod' => count($itemIdsPd)],
                'missing_ids' => [
                    'in_prod' => array_slice($missingItemsInProd, 0, 100),
                    'in_sandbox' => array_slice($missingItemsInSandbox, 0, 100),
                ],
            ],
            'sch_linhas' => [
                'count' => ['sandbox' => count($schIdsSb), 'prod' => count($schIdsPd)],
                'missing_ids' => [
                    'in_prod' => array_slice($missingSchInProd, 0, 100),
                    'in_sandbox' => array_slice($missingSchInSandbox, 0, 100),
                ],
            ],
        ];
    }

    $result = [
        'meta' => [
            'generated_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
            'sandbox_db' => $dbSandbox,
            'prod_db' => $dbProd,
            'limit_programas' => $limit,
            'note' => 'Leitura apenas; escopo limitado a programacoes presentes no sandbox.',
        ],
        'sandbox_programas_count' => count($sandboxPrograms),
        'sandbox_programas_ids' => $programIds,
        'per_program' => $perProgram,
    ];

    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Falha ao gerar JSON.');
    }

    if ($out) {
        file_put_contents($out, $json);
        fwrite(STDOUT, "OK wrote {$out}\n");
    } else {
        fwrite(STDOUT, $json . "\n");
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
