<?php
$json = file_get_contents('http://localhost/controlepcp_sandbox/api_comparacao_detalhada.php?action=items');
$data = json_decode($json, true);
echo "Status: " . $data['status'] . "\n";
echo "Total: " . $data['total'] . "\n";
if ($data['total'] > 0) {
    $first = $data['items'][0];
    echo "\nPrimeiro item:\n";
    foreach ($first as $k => $v) {
        $val = is_null($v) ? 'NULL' : substr((string)$v, 0, 60);
        echo "  $k: $val\n";
    }
    
    echo "\n\nÚltimos 3 itens:\n";
    $count = count($data['items']);
    for ($i = max(0, $count - 3); $i < $count; $i++) {
        $item = $data['items'][$i];
        echo "  [{$item['sku']}] {$item['produto']} - {$item['quantidade_planejada']} un. - {$item['status_execucao']}\n";
    }
}
