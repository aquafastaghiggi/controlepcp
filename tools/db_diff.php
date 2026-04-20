<?php

declare(strict_types=1);

/**
 * Read-only DB diff helper for controlepcp.
 *
 * Goals:
 * - Compare schema (tables/columns/PK/collation) between two databases.
 * - Provide a safe starting point for a surgical sync plan.
 *
 * This script MUST NOT write to production.
 */

function cli_option(array $options, string $key, ?string $fallback = null): ?string
{
    if (array_key_exists($key, $options) && $options[$key] !== false && $options[$key] !== null) {
        return (string) $options[$key];
    }

    return $fallback;
}

function connect_information_schema(string $host, string $port, string $user, string $pass): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=information_schema;charset=utf8mb4', $host, $port);

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ]);

    return $pdo;
}

function fetch_schema_info(PDO $pdo, string $dbName): array
{
    $schemaStmt = $pdo->prepare(
        'SELECT SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME'
        . ' FROM SCHEMATA WHERE SCHEMA_NAME = :db'
    );
    $schemaStmt->execute(['db' => $dbName]);
    $schemaInfo = $schemaStmt->fetch() ?: null;

    $tablesStmt = $pdo->prepare(
        'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION'
        . ' FROM TABLES WHERE TABLE_SCHEMA = :db'
    );
    $tablesStmt->execute(['db' => $dbName]);
    $tables = [];
    foreach ($tablesStmt->fetchAll() as $row) {
        $tables[(string) $row['TABLE_NAME']] = [
            'engine' => (string) ($row['ENGINE'] ?? ''),
            'collation' => (string) ($row['TABLE_COLLATION'] ?? ''),
        ];
    }

    $columnsStmt = $pdo->prepare(
        'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME'
        . ' FROM COLUMNS WHERE TABLE_SCHEMA = :db'
        . ' ORDER BY TABLE_NAME ASC, ORDINAL_POSITION ASC'
    );
    $columnsStmt->execute(['db' => $dbName]);
    $columns = [];
    foreach ($columnsStmt->fetchAll() as $row) {
        $table = (string) $row['TABLE_NAME'];
        $col = (string) $row['COLUMN_NAME'];
        if (!isset($columns[$table])) {
            $columns[$table] = [];
        }
        $columns[$table][$col] = [
            'type' => (string) ($row['COLUMN_TYPE'] ?? ''),
            'nullable' => (string) ($row['IS_NULLABLE'] ?? ''),
            'default' => $row['COLUMN_DEFAULT'],
            'extra' => (string) ($row['EXTRA'] ?? ''),
            'charset' => (string) ($row['CHARACTER_SET_NAME'] ?? ''),
            'collation' => (string) ($row['COLLATION_NAME'] ?? ''),
        ];
    }

    $pkStmt = $pdo->prepare(
        "SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION"
        . " FROM KEY_COLUMN_USAGE"
        . " WHERE TABLE_SCHEMA = :db AND CONSTRAINT_NAME = 'PRIMARY'"
        . " ORDER BY TABLE_NAME ASC, ORDINAL_POSITION ASC"
    );
    $pkStmt->execute(['db' => $dbName]);
    $primaryKeys = [];
    foreach ($pkStmt->fetchAll() as $row) {
        $table = (string) $row['TABLE_NAME'];
        if (!isset($primaryKeys[$table])) {
            $primaryKeys[$table] = [];
        }
        $primaryKeys[$table][] = (string) $row['COLUMN_NAME'];
    }

    return [
        'schema' => $schemaInfo ? [
            'name' => (string) $schemaInfo['SCHEMA_NAME'],
            'default_charset' => (string) $schemaInfo['DEFAULT_CHARACTER_SET_NAME'],
            'default_collation' => (string) $schemaInfo['DEFAULT_COLLATION_NAME'],
        ] : null,
        'tables' => $tables,
        'columns' => $columns,
        'primary_keys' => $primaryKeys,
    ];
}

function compare_schema(array $sandbox, array $prod): array
{
    $sbTables = array_keys($sandbox['tables']);
    $pdTables = array_keys($prod['tables']);

    $onlySb = array_values(array_diff($sbTables, $pdTables));
    sort($onlySb);
    $onlyPd = array_values(array_diff($pdTables, $sbTables));
    sort($onlyPd);
    $both = array_values(array_intersect($sbTables, $pdTables));
    sort($both);

    $tableDiffs = [];
    foreach ($both as $table) {
        $sbTable = $sandbox['tables'][$table] ?? [];
        $pdTable = $prod['tables'][$table] ?? [];

        $diff = [
            'table' => $table,
            'table_engine' => [
                'sandbox' => $sbTable['engine'] ?? '',
                'prod' => $pdTable['engine'] ?? '',
            ],
            'table_collation' => [
                'sandbox' => $sbTable['collation'] ?? '',
                'prod' => $pdTable['collation'] ?? '',
            ],
            'primary_key' => [
                'sandbox' => $sandbox['primary_keys'][$table] ?? [],
                'prod' => $prod['primary_keys'][$table] ?? [],
            ],
            'columns_only_in_sandbox' => [],
            'columns_only_in_prod' => [],
            'columns_changed' => [],
        ];

        $sbCols = $sandbox['columns'][$table] ?? [];
        $pdCols = $prod['columns'][$table] ?? [];
        $sbColNames = array_keys($sbCols);
        $pdColNames = array_keys($pdCols);

        $diff['columns_only_in_sandbox'] = array_values(array_diff($sbColNames, $pdColNames));
        sort($diff['columns_only_in_sandbox']);
        $diff['columns_only_in_prod'] = array_values(array_diff($pdColNames, $sbColNames));
        sort($diff['columns_only_in_prod']);

        $commonCols = array_values(array_intersect($sbColNames, $pdColNames));
        sort($commonCols);
        foreach ($commonCols as $col) {
            $a = $sbCols[$col];
            $b = $pdCols[$col];
            if ($a !== $b) {
                $diff['columns_changed'][$col] = [
                    'sandbox' => $a,
                    'prod' => $b,
                ];
            }
        }

        $hasAny = false;
        if ($diff['table_engine']['sandbox'] !== $diff['table_engine']['prod']) $hasAny = true;
        if ($diff['table_collation']['sandbox'] !== $diff['table_collation']['prod']) $hasAny = true;
        if ($diff['primary_key']['sandbox'] !== $diff['primary_key']['prod']) $hasAny = true;
        if (!empty($diff['columns_only_in_sandbox'])) $hasAny = true;
        if (!empty($diff['columns_only_in_prod'])) $hasAny = true;
        if (!empty($diff['columns_changed'])) $hasAny = true;

        if ($hasAny) {
            $tableDiffs[] = $diff;
        }
    }

    return [
        'db_defaults' => [
            'sandbox' => $sandbox['schema'],
            'prod' => $prod['schema'],
        ],
        'tables_only_in_sandbox' => $onlySb,
        'tables_only_in_prod' => $onlyPd,
        'table_diffs' => $tableDiffs,
    ];
}

function connect_db(string $host, string $port, string $db, string $user, string $pass): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ]);

    return $pdo;
}

function safe_row_count(PDO $pdo, string $table): ?int
{
    try {
        $stmt = $pdo->query('SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $table) . '`');
        $row = $stmt->fetch();
        return $row ? (int) ($row['c'] ?? 0) : null;
    } catch (Throwable $e) {
        return null;
    }
}

function diff_missing_pk_values(PDO $sb, PDO $pd, string $table, array $pkCols, int $limit): array
{
    if (count($pkCols) !== 1) {
        return ['supported' => false, 'reason' => 'PK composta ou ausente'];
    }

    $pk = $pkCols[0];
    $qTable = '`' . str_replace('`', '``', $table) . '`';
    $qPk = '`' . str_replace('`', '``', $pk) . '`';

    // Missing in prod
    $sqlMissingInProd = "SELECT s.{$qPk} AS id"
        . " FROM {$qTable} s"
        . " LEFT JOIN {$qTable} p ON p.{$qPk} = s.{$qPk}"
        . " WHERE p.{$qPk} IS NULL"
        . " ORDER BY s.{$qPk} ASC"
        . " LIMIT {$limit}";

    // Missing in sandbox
    $sqlMissingInSandbox = "SELECT p.{$qPk} AS id"
        . " FROM {$qTable} p"
        . " LEFT JOIN {$qTable} s ON s.{$qPk} = p.{$qPk}"
        . " WHERE s.{$qPk} IS NULL"
        . " ORDER BY p.{$qPk} ASC"
        . " LIMIT {$limit}";

    $missingInProd = [];
    $missingInSandbox = [];

    try {
        $sbRes = $sb->query($sqlMissingInProd)->fetchAll();
        foreach ($sbRes as $r) {
            $missingInProd[] = $r['id'];
        }
    } catch (Throwable $e) {
        // ignore
    }

    try {
        $pdRes = $pd->query($sqlMissingInSandbox)->fetchAll();
        foreach ($pdRes as $r) {
            $missingInSandbox[] = $r['id'];
        }
    } catch (Throwable $e) {
        // ignore
    }

    return [
        'supported' => true,
        'pk' => $pk,
        'missing_in_prod_sample' => $missingInProd,
        'missing_in_sandbox_sample' => $missingInSandbox,
        'note' => 'Amostra limitada; nao implica que deve ser sincronizado.',
    ];
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

$host = cli_option($options, 'host', getenv('DB_HOST') ?: '127.0.0.1') ?? '127.0.0.1';
$port = cli_option($options, 'port', getenv('DB_PORT') ?: '3306') ?? '3306';
$user = cli_option($options, 'user', getenv('DB_USER') ?: 'root') ?? 'root';
$pass = cli_option($options, 'pass', getenv('DB_PASS') ?: '') ?? '';
$dbSandbox = cli_option($options, 'sandbox', 'controlepcp_sandbox') ?? 'controlepcp_sandbox';
$dbProd = cli_option($options, 'prod', 'controlepcp') ?? 'controlepcp';
$limit = (int) (cli_option($options, 'limit', '50') ?? '50');
$out = cli_option($options, 'out', null);

try {
    if ($pass === '') {
        throw new RuntimeException('DB password ausente. Defina DB_PASS ou use --pass=...');
    }
    $info = connect_information_schema($host, $port, $user, $pass);
    $sbSchema = fetch_schema_info($info, $dbSandbox);
    $pdSchema = fetch_schema_info($info, $dbProd);

    $schemaDiff = compare_schema($sbSchema, $pdSchema);

    // Limited data diagnostics for tables directly used by gantt.php / ProgramacaoRepository.
    $candidateTables = [
        'prg_programas',
        'prg_itens',
        'sch_linhas',
        'lin_linhas',
        'prd_produtos',
        'realizado_2026_excel',
    ];

    $sbDb = connect_db($host, $port, $dbSandbox, $user, $pass);
    $pdDb = connect_db($host, $port, $dbProd, $user, $pass);

    $dataDiag = [];
    foreach ($candidateTables as $t) {
        if (!isset($sbSchema['tables'][$t]) || !isset($pdSchema['tables'][$t])) {
            $dataDiag[$t] = ['present' => ['sandbox' => isset($sbSchema['tables'][$t]), 'prod' => isset($pdSchema['tables'][$t])]];
            continue;
        }

        $pkSb = $sbSchema['primary_keys'][$t] ?? [];
        $pkPd = $pdSchema['primary_keys'][$t] ?? [];

        $dataDiag[$t] = [
            'row_count' => [
                'sandbox' => safe_row_count($sbDb, $t),
                'prod' => safe_row_count($pdDb, $t),
            ],
            'primary_key' => [
                'sandbox' => $pkSb,
                'prod' => $pkPd,
            ],
            'missing_pk_samples' => diff_missing_pk_values($sbDb, $pdDb, $t, $pkSb ?: $pkPd, $limit),
        ];
    }

    $result = [
        'meta' => [
            'generated_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
            'host' => $host,
            'port' => $port,
            'sandbox_db' => $dbSandbox,
            'prod_db' => $dbProd,
            'limit' => $limit,
            'note' => 'Leitura apenas; nao aplica nenhum sync.',
        ],
        'schema_diff' => $schemaDiff,
        'data_diagnostics_candidates' => $dataDiag,
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
