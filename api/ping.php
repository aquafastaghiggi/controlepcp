<?php
header('Content-Type: application/json');

// Teste 1: Verificar PHP
echo json_encode([
    'sucesso' => true,
    'php' => PHP_VERSION,
    'timestamp' => date('Y-m-d H:i:s'),
    'includes_ok' => true
], JSON_UNESCAPED_UNICODE);
