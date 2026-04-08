<?php
// Simular GET parameter para execução CLI
$_GET = ['action' => $argv[1] ?? 'sync-all'];

require_once __DIR__ . '/codi_cache_service.php';
