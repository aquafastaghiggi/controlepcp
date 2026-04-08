<?php
$file = __DIR__ . '/codi_dados_exportados.json';

if (!file_exists($file)) {
    die("Arquivo não encontrado\n");
}

$data = json_decode(file_get_contents($file), true);

echo "📊 DADOS EXPORTADOS DO CODI\n";
echo str_repeat("=", 60) . "\n\n";

echo "Timestamp: " . $data['timestamp'] . "\n";
echo "Status: " . $data['status'] . "\n";
echo "Servidor: " . $data['servidor'] . "\n\n";

// Recursos
$recursos = $data['dados']['recursos']['data'] ?? [];
echo "RECURSOS: " . count($recursos) . " registros\n";
foreach (array_slice($recursos, 0, 3) as $r) {
    echo "  • " . $r['nomeRecurso'] . "\n";
}
echo "\n";

// Calendário
$calendario = $data['dados']['calendario_fabril']['data'] ?? [];
echo "CALENDÁRIO FABRIL: " . count($calendario) . " registros\n";
foreach (array_slice($calendario, 0, 3) as $c) {
    echo "  • " . $c['data'] . " " . $c['horaInicio'] . "-" . $c['horaFim'] . " \n";
}
echo "\n";

// Performance
$perfomance = $data['dados']['performance']['data'] ?? [];
echo "PERFORMANCE: " . count($perfomance) . " registros\n";
foreach (array_slice($perfomance, 0, 3) as $p) {
    echo "  • Código " . ($p['codigoPerformance'] ?? 'N/A') . "\n";
}
echo "\n";

echo str_repeat("=", 60) . "\n";
echo "✅ Dados extraídos e validados com sucesso!\n";
