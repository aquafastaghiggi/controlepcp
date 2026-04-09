<?php declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;

Auth::startSession();

// Esta API ? exclusiva do sandbox.
if ((getenv('APP_ENV') ?: '') !== 'sandbox') {
    http_response_code(404);
    exit;
}

Auth::requireAdmin();

header('Content-Type: application/json; charset=utf-8');

$storageDir = __DIR__ . '/../.tmp';
$storagePath = $storageDir . '/release-center.json';
$featureFlagsPath = $storageDir . '/feature-flags.json';

// Produ??o fica fora do sandbox (mesma m?quina). Pode sobrescrever via env se mudar estrutura.
$prodRoot = (string) (getenv('PCP_PROD_ROOT') ?: 'C:\\xampp\\htdocs\\controlepcp');
$prodFeatureFlagsPath = rtrim($prodRoot, "\\/") . '\\.tmp\\feature-flags.json';

if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0777, true);
}

$defaultState = [
    'schema' => 1,
    'env' => 'sandbox',
    'publish_checklist' => [
        'login_ok' => false,
        'import_excel_ok' => false,
        'calcular_ok' => false,
        'historico_ok' => false,
        'impressao_ok' => false,
    ],
    'publish_checklist_updated_at' => null,
    'publish_checklist_updated_by' => null,
    'items' => [],
    'releases' => [],
    'updated_at' => date('c'),
];

$readState = static function () use ($storagePath, $defaultState): array {
    $raw = @file_get_contents($storagePath);
    if (!$raw) {
        return $defaultState;
    }
    // Arquivo pode vir com UTF-8 BOM (PowerShell). Remove BOM para json_decode funcionar.
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $defaultState;
    }

    $decoded['schema'] = (int) ($decoded['schema'] ?? 1);
    $decoded['env'] = (string) ($decoded['env'] ?? 'sandbox');
    $decoded['publish_checklist'] = is_array($decoded['publish_checklist'] ?? null)
        ? $decoded['publish_checklist']
        : $defaultState['publish_checklist'];
    $decoded['publish_checklist_updated_at'] = $decoded['publish_checklist_updated_at'] ?? null;
    $decoded['publish_checklist_updated_by'] = $decoded['publish_checklist_updated_by'] ?? null;
    $decoded['items'] = is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
    $decoded['releases'] = is_array($decoded['releases'] ?? null) ? $decoded['releases'] : [];
    $decoded['updated_at'] = (string) ($decoded['updated_at'] ?? date('c'));
    return $decoded;
};

$writeState = static function (array $state) use ($storagePath): void {
    $state['updated_at'] = date('c');
    $json = json_encode(
        $state,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($json === false) {
        throw new RuntimeException('Falha ao serializar estado da Central de Publica??o.');
    }

    // Escrita at?mica simples (LOCK_EX) para evitar corrup??o em acessos r?pidos.
    if (@file_put_contents($storagePath, $json, LOCK_EX) === false) {
        throw new RuntimeException('Falha ao gravar estado da Central de Publica??o.');
    }
};

$normalizeTitle = static function (string $value): string {
    $value = strtolower($value);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }
    }
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: $value;
    return trim($value);
};

$computeFeatureFlags = static function (array $state) use ($normalizeTitle): array {
    $items = is_array($state['items'] ?? null) ? $state['items'] : [];
    $performance = false;

    foreach ($items as $it) {
        $status = strtolower((string) ($it['status'] ?? ''));
        if ($status !== 'approved' && $status !== 'published') {
            continue;
        }
        $title = $normalizeTitle((string) ($it['title'] ?? $it['titulo'] ?? ''));
        if ($title === '') {
            continue;
        }
        if (str_contains($title, 'desempenho') || str_contains($title, 'gantt') || str_contains($title, 'grafico')) {
            $performance = true;
            break;
        }
    }

    return [
        'schema' => 1,
        'updated_at' => date('c'),
        'features' => [
            'performance' => $performance,
        ],
    ];
};

$findGitExecutable = static function (): string {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $candidates = [
        getenv('GIT_PATH'),
        'git',
        'C:\\Program Files\\Git\\cmd\\git.exe',
        'C:\\Program Files\\Git\\bin\\git.exe',
    ];

    foreach ($candidates as $candidate) {
        $candidate = (string) ($candidate ?? '');
        if ($candidate === '') {
            continue;
        }

        $output = [];
        $code = 0;
        @exec(escapeshellarg($candidate) . ' --version', $output, $code);
        if ($code === 0) {
            return $cached = $candidate;
        }
    }

    throw new RuntimeException('Git n?o est? acess?vel no servidor sandbox.');
};

    $runGit = static function (string $cwd, array $args) use ($findGitExecutable): string {
        $gitExec = $findGitExecutable();
        $cmd = escapeshellarg($gitExec) . ' -C ' . escapeshellarg($cwd) . ' ' . implode(' ', array_map('escapeshellarg', $args));
        if (DIRECTORY_SEPARATOR === '\\') {
            $cmd = str_replace('%', '%%', $cmd);
        }
        $output = [];
    $code = 0;
    @exec($cmd, $output, $code);
    if ($code !== 0) {
        throw new RuntimeException('Falha ao executar git para gerar item automaticamente. ' . trim(implode("\n", $output)));
    }
    return trim(implode("\n", $output));
};

$createItem = static function (array $items, string $title, string $username, string $note, array $extra = []): array {
    $max = 0;
    foreach ($items as $it) {
        $id = (int) ($it['id'] ?? 0);
        if ($id > $max) {
            $max = $id;
        }
    }

    $newId = $max + 1;
    $now = date('c');
    $item = [
        'id' => $newId,
        'title' => $title,
        'status' => 'testing',
        'created_at' => $now,
        'updated_at' => $now,
        'approved_at' => null,
        'approved_by' => null,
        'note' => $note,
        'created_by' => $username ?: 'admin',
    ];

    foreach ($extra as $k => $v) {
        if (!array_key_exists($k, $item)) {
            $item[$k] = $v;
        }
    }

    $items[] = $item;
    return $items;
};

$writeFeatureFlags = static function (array $featureState) use ($featureFlagsPath, $prodFeatureFlagsPath): void {
    $json = json_encode($featureState, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return;
    }

    $dir = dirname($featureFlagsPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    @file_put_contents($featureFlagsPath, $json, LOCK_EX);

    $prodDir = dirname($prodFeatureFlagsPath);
    if (!is_dir($prodDir)) {
        @mkdir($prodDir, 0777, true);
    }
    @file_put_contents($prodFeatureFlagsPath, $json, LOCK_EX);
};

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    echo json_encode($readState(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'M?todo n?o permitido.']);
    exit;
}

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
$payload = [];

if (str_contains($contentType, 'application/json')) {
    $raw = (string) file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
} else {
    $payload = $_POST;
}

try {
    Auth::requireCsrf($payload['csrf_token'] ?? null);
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF inv?lido.']);
    exit;
}

$action = (string) ($payload['action'] ?? '');
$state = $readState();
$items = $state['items'] ?? [];
$user = Auth::user();
$username = (string) ($user['username'] ?? '');

try {
    if ($action === 'create_item') {
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('Informe um t?tulo.');
        }

        $items = $createItem($items, $title, $username, '');

        $state['items'] = $items;
        $writeState($state);
        $writeFeatureFlags($computeFeatureFlags($state));

        echo json_encode(['ok' => true, 'state' => $state], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'create_item_from_git') {
        $repoRoot = realpath(__DIR__ . '/..');
        if (!is_string($repoRoot) || $repoRoot === '') {
            throw new RuntimeException('Pasta do sandbox n?o encontrada para gerar item.');
        }

        $inside = $runGit($repoRoot, ['rev-parse', '--is-inside-work-tree']);
        if (strtolower($inside) !== 'true') {
            throw new RuntimeException('Reposit?rio git n?o encontrado no sandbox.');
        }

        $headFull = $runGit($repoRoot, ['rev-parse', 'HEAD']);
        $headShort = $runGit($repoRoot, ['rev-parse', '--short=8', 'HEAD']);
        $subject = $runGit($repoRoot, ['log', '-1', '--pretty=%s']);

        $base = '';
        $releases = is_array($state['releases'] ?? null) ? $state['releases'] : [];
        for ($i = count($releases) - 1; $i >= 0; $i--) {
            $rel = is_array($releases[$i] ?? null) ? $releases[$i] : null;
            if (!$rel) {
                continue;
            }
            $actionRel = strtolower((string) ($rel['action'] ?? ''));
            if ($actionRel !== 'publish') {
                continue;
            }

            $base = (string) ($rel['git_head'] ?? $rel['git_head_after'] ?? '');
            if ($base !== '') {
                break;
            }

            $publishedAt = (string) ($rel['published_at'] ?? '');
            if ($publishedAt !== '') {
                try {
                    $base = $runGit($repoRoot, ['log', '-1', '--before=' . $publishedAt, '--pretty=%H']);
                } catch (Throwable) {
                    $base = '';
                }
            }
            break;
        }

        $range = '';
        if ($base !== '' && $base !== $headFull) {
            $range = $base . '..' . $headFull;
        }

        if ($range !== '') {
            $commitLines = $runGit($repoRoot, ['log', '--oneline', $range]);
            $fileLines = $runGit($repoRoot, ['diff', '--name-status', $range]);
        } else {
            $commitLines = $runGit($repoRoot, ['log', '-1', '--oneline']);
            $fileLines = $runGit($repoRoot, ['show', '--name-status', '--pretty=format:', '-1']);
        }

        $title = trim($subject) !== '' ? trim($subject) : ('Atualiza??o ' . date('d/m/Y H:i'));
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($title) > 90) {
                $title = mb_substr($title, 0, 90) . '...';
            }
        } elseif (strlen($title) > 90) {
            $title = substr($title, 0, 90) . '...';
        }

        $noteParts = [];
        $noteParts[] = "HEAD: $headShort";
        if ($base !== '') {
            $noteParts[] = 'Base: ' . substr($base, 0, 8);
        }
        $noteParts[] = '';
        $noteParts[] = 'Commits:';
        foreach (preg_split("/\r\n|\n|\r/", trim((string) $commitLines)) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $noteParts[] = '- ' . $line;
        }
        $noteParts[] = '';
        $noteParts[] = 'Arquivos:';
        foreach (preg_split("/\r\n|\n|\r/", trim((string) $fileLines)) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $noteParts[] = '- ' . $line;
        }

        $note = implode("\n", $noteParts);

        $items = $createItem($items, $title, $username, $note, [
            'git_head' => $headFull,
            'git_base' => $base !== '' ? $base : null,
        ]);

        $state['items'] = $items;
        $writeState($state);
        $writeFeatureFlags($computeFeatureFlags($state));

        echo json_encode(['ok' => true, 'state' => $state], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'set_status') {
        $id = (int) ($payload['id'] ?? 0);
        $status = strtolower(trim((string) ($payload['status'] ?? '')));
        $allowed = ['testing', 'approved'];

        if ($id <= 0) {
            throw new RuntimeException('ID inv?lido.');
        }
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('Status inv?lido.');
        }

        $now = date('c');
        $found = false;
        foreach ($items as &$it) {
            if ((int) ($it['id'] ?? 0) !== $id) {
                continue;
            }
            $found = true;
            $it['status'] = $status;
            $it['updated_at'] = $now;
            if ($status === 'approved') {
                $it['approved_at'] = $now;
                $it['approved_by'] = $username ?: 'admin';
            } else {
                $it['approved_at'] = null;
                $it['approved_by'] = null;
            }
            break;
        }
        unset($it);

        if (!$found) {
            throw new RuntimeException('Item n?o encontrado.');
        }

        $state['items'] = $items;
        $writeState($state);
        $writeFeatureFlags($computeFeatureFlags($state));

        echo json_encode(['ok' => true, 'state' => $state], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'set_note') {
        $id = (int) ($payload['id'] ?? 0);
        $note = (string) ($payload['note'] ?? '');
        if ($id <= 0) {
            throw new RuntimeException('ID inv?lido.');
        }

        $now = date('c');
        $found = false;
        foreach ($items as &$it) {
            if ((int) ($it['id'] ?? 0) !== $id) {
                continue;
            }
            $found = true;
            $it['note'] = $note;
            $it['updated_at'] = $now;
            break;
        }
        unset($it);

        if (!$found) {
            throw new RuntimeException('Item n?o encontrado.');
        }

        $state['items'] = $items;
        $writeState($state);
        $writeFeatureFlags($computeFeatureFlags($state));

        echo json_encode(['ok' => true, 'state' => $state], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'set_publish_check') {
        $key = (string) ($payload['key'] ?? '');
        $valueRaw = $payload['value'] ?? null;

        $allowedKeys = array_keys($defaultState['publish_checklist']);
        if (!in_array($key, $allowedKeys, true)) {
            throw new RuntimeException('Checklist inv?lido.');
        }

        $value = false;
        if (is_bool($valueRaw)) {
            $value = $valueRaw;
        } else {
            $value = (string) $valueRaw === '1' || strtolower((string) $valueRaw) === 'true';
        }

        $state['publish_checklist'] = is_array($state['publish_checklist'] ?? null)
            ? $state['publish_checklist']
            : $defaultState['publish_checklist'];

        $state['publish_checklist'][$key] = $value;
        $state['publish_checklist_updated_at'] = date('c');
        $state['publish_checklist_updated_by'] = $username ?: 'admin';

        $writeState($state);
        $writeFeatureFlags($computeFeatureFlags($state));
        echo json_encode(['ok' => true, 'state' => $state], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    throw new RuntimeException('A??o inv?lida.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
