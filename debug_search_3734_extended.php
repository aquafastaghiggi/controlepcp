<?php
$pdo = new PDO('mysql:host=localhost;dbname=controlepcp_sandbox;charset=utf8mb4', 'root', 'k7m2y9u4');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== PROCURANDO '3734' EM TODOS OS REGISTROS ===\n\n";

// Buscar em codi_calendario
echo "1. codi_calendario:\n";
$sql = "SELECT cal_id, cal_codigo_codi, cal_recurso_codi_id, cal_grandeza_codi, 
               cal_data, cal_hora_inicio, cal_hora_fim, 
               SUBSTRING(cal_dados_json, 1, 200) as json_sample
        FROM codi_calendario 
        WHERE cal_codigo_codi = 3734 
           OR cal_recurso_codi_id = 3734 
           OR cal_grandeza_codi = 3734 
           OR cal_turno_codi = 3734
           OR cal_dados_json LIKE '%3734%'
        LIMIT 10";

$result = $pdo->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) > 0) {
    echo "   ✓ Encontrado " . count($rows) . " registro(s):\n";
    foreach ($rows as $row) {
        echo "   - cal_id: " . $row['cal_id'] . ", cal_codigo_codi: " . $row['cal_codigo_codi'] . ", data: " . $row['cal_data'] . "\n";
        echo "     JSON: " . $row['json_sample'] . "\n";
    }
} else {
    echo "   ✗ Nenhum registro encontrado\n";
}

// Buscar em codi_performance
echo "\n2. codi_performance:\n";
$sql = "SELECT perf_id, perf_codigo_codi, perf_recurso_codi_id, perf_item_codi, perf_ordem_producao,
               SUBSTRING(perf_dados_json, 1, 200) as json_sample
        FROM codi_performance 
        WHERE perf_codigo_codi = 3734 
           OR perf_recurso_codi_id = 3734 
           OR perf_item_codi = 3734
           OR perf_ordem_producao LIKE '%3734%'
           OR perf_dados_json LIKE '%3734%'
        LIMIT 10";

$result = $pdo->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) > 0) {
    echo "   ✓ Encontrado " . count($rows) . " registro(s):\n";
    foreach ($rows as $row) {
        echo "   - perf_id: " . $row['perf_id'] . ", perf_codigo_codi: " . $row['perf_codigo_codi'] . "\n";
        echo "     JSON: " . $row['json_sample'] . "\n";
    }
} else {
    echo "   ✗ Nenhum registro encontrado\n";
}

// Buscar em prg_programas (pode ter OP 3734)
echo "\n3. prg_programas (busca por OP):\n";
$sql = "SELECT prg_id, prg_numero_op, prg_status, prg_criado_em
        FROM prg_programas 
        WHERE prg_numero_op LIKE '%3734%'
        LIMIT 10";

$result = $pdo->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) > 0) {
    echo "   ✓ Encontrado " . count($rows) . " registro(s):\n";
    foreach ($rows as $row) {
        echo "   - prg_id: " . $row['prg_id'] . ", OP: " . $row['prg_numero_op'] . ", status: " . $row['prg_status'] . "\n";
    }
} else {
    echo "   ✗ Nenhum registro encontrado\n";
}

// Buscar em prg_itens
echo "\n4. prg_itens (busca por OP ou quantidade):\n";
$sql = "SELECT pi.prg_id_item, pi.prg_sku, pi.prg_quantidade, pi.prg_itens_op, pp.prg_numero_op
        FROM prg_itens pi
        LEFT JOIN prg_programas pp ON pi.prg_programa_id = pp.prg_id
        WHERE pi.prg_quantidade = 3734 
           OR pi.prg_quantidade LIKE '%3734%'
           OR pi.prg_itens_op LIKE '%3734%'
        LIMIT 10";

$result = $pdo->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) > 0) {
    echo "   ✓ Encontrado " . count($rows) . " registro(s):\n";
    foreach ($rows as $row) {
        echo "   - Quantidade: " . number_format($row['prg_quantidade'], 4) . ", SKU: " . $row['prg_sku'] . ", OP: " . ($row['prg_numero_op'] ?? 'n/a') . "\n";
    }
} else {
    echo "   ✗ Nenhum registro encontrado\n";
}

// Total de registros em cada tabela
echo "\n=== TOTAIS POR TABELA ===\n";
echo "codi_calendario: " . (int)$pdo->query('SELECT COUNT(*) FROM codi_calendario')->fetchColumn() . " registros\n";
echo "codi_performance: " . (int)$pdo->query('SELECT COUNT(*) FROM codi_performance')->fetchColumn() . " registros\n";
echo "prg_programas: " . (int)$pdo->query('SELECT COUNT(*) FROM prg_programas')->fetchColumn() . " registros\n";
echo "prg_itens: " . (int)$pdo->query('SELECT COUNT(*) FROM prg_itens')->fetchColumn() . " registros\n";
