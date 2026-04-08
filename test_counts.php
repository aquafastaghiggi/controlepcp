<?php
$pdo = new PDO('mysql:host=localhost;dbname=controlepcp_sandbox', 'root', 'k7m2y9u4');
$result = $pdo->query('SELECT COUNT(*) as count FROM prg_programas')->fetch();
echo "Programações disponíveis: " . $result['count'] . "\n";

$result = $pdo->query('SELECT COUNT(*) as count FROM sch_linhas')->fetch();
echo "Linhas de produção: " . $result['count'] . "\n";

$result = $pdo->query('SELECT COUNT(*) as count FROM prg_itens')->fetch();
echo "Items de programação: " . $result['count'] . "\n";

$result = $pdo->query('SELECT COUNT(*) as count FROM realizado_2026_excel')->fetch();
echo "Registros de realizado: " . $result['count'] . "\n";
