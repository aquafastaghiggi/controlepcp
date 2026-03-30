<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;

Auth::startSession();
Auth::requireLoginApi();

header('Content-Type: application/json; charset=utf-8');

$features = [
    'performance' => false,
];

$path = __DIR__ . '/../.tmp/feature-flags.json';
$raw = @file_get_contents($path) ?: '';
if ($raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $payload = is_array($decoded['features'] ?? null) ? $decoded['features'] : $decoded;
        if (is_array($payload)) {
            $features['performance'] = (bool) ($payload['performance'] ?? $features['performance']);
        }
    }
}

echo json_encode(['features' => $features], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

