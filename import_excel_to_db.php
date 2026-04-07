<?php
// Script de importação: Excel → MySQL
// Execute via: php import_excel_to_db.php

echo "[*] Iniciando importação de Excel para MySQL...\n";

// Conexão
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=controlepcp_sandbox',
        'root',
        'k7m2y9u4'
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "[✓] Conectado ao MySQL\n";
} catch (Exception $e) {
    echo "[✗] ERRO ao conectar: {$e->getMessage()}\n";
    exit(1);
}

// Criar tabela
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS realizado_2026_excel (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data_evento DATE,
            ordem_op VARCHAR(20),
            quantidade DECIMAL(10, 2),
            imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_op_data (ordem_op, data_evento),
            INDEX idx_ordem_op (ordem_op),
            INDEX idx_data (data_evento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "[✓] Tabela criada/verificada\n";
} catch (Exception $e) {
    echo "[✗] ERRO ao criar tabela: {$e->getMessage()}\n";
    exit(1);
}

// Limpar
$pdo->exec("TRUNCATE TABLE realizado_2026_excel");
echo "[✓] Tabela limpa\n";

// Carregar Excel
require 'c:\\xampp\\htdocs\\controlepcp\\.venv\\Lib\\site-packages\\openpyxl\\__init__.py';

try {
    $excel_path = 'c:\\dadosCodi\\relatorio_api_2026.xlsx';
    if (!file_exists($excel_path)) {
        echo "[✗] Excel não encontrado: $excel_path\n";
        exit(1);
    }
    
    // Usar Python para isso
    $python_code = <<<'PYTHON'
import openpyxl
from datetime import datetime
import json

try:
    wb = openpyxl.load_workbook('c:\\dadosCodi\\relatorio_api_2026.xlsx', data_only=True)
    ws = wb.active
    
    data = []
    rows_proc = 0
    rows_filt = 0
    
    for row in ws.iter_rows(min_row=2, max_row=ws.max_row, values_only=True):
        rows_proc += 1
        try:
            data_raw = row[1] if len(row) > 1 else None
            op_raw = row[39] if len(row) > 39 else None
            qty_raw = row[43] if len(row) > 43 else None
            
            if not data_raw:
                continue
            
            if not isinstance(data_raw, datetime):
                try:
                    data_raw = datetime.fromisoformat(str(data_raw).split()[0])
                except:
                    continue
            
            if not (data_raw.year == 2026 and data_raw.month in [3, 4]):
                continue
            
            rows_filt += 1
            
            if op_raw and qty_raw:
                op = str(op_raw).strip().lstrip('0') or '0'
                qty = float(qty_raw) if isinstance(qty_raw, (int, float)) else 0
                if op and qty > 0:
                    data.append({
                        'data': data_raw.strftime('%Y-%m-%d'),
                        'op': op,
                        'qty': qty
                    })
        except:
            pass
    
    print(json.dumps({'items': data, 'rows_proc': rows_proc, 'rows_filt': rows_filt, 'count': len(data)}))
except Exception as e:
    print(json.dumps({'items': [], 'error': str(e), 'rows_proc': 0, 'rows_filt': 0, 'count': 0}))
PYTHON;

    $temp = sys_get_temp_dir() . '/import_' . uniqid() . '.py';
    file_put_contents($temp, $python_code);
    
    $cmd = "c:\\xampp\\htdocs\\controlepcp\\.venv\\Scripts\\python.exe " . escapeshellarg($temp) . " 2>&1";
    $result = shell_exec($cmd);
    @unlink($temp);
    
    if (!$result) {
        echo "[✗] Python retornou vazio\n";
        exit(1);
    }
    
    $json = json_decode($result, true);
    if (!$json || isset($json['error'])) {
        echo "[✗] Erro Python: " . ($json['error'] ?? 'JSON inválido') . "\n";
        exit(1);
    }
    
    echo "[✓] Excel carregado: {$json['rows_proc']} linhas\n";
    echo "[✓] Filtradas (Mar-Abr 2026): {$json['rows_filt']} linhas\n";
    echo "[✓] Registros únicos: {$json['count']} itens\n";
    
    // Inserir dados
    $stmt = $pdo->prepare("
        INSERT INTO realizado_2026_excel (data_evento, ordem_op, quantidade)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantidade = quantidade + VALUES(quantidade)
    ");
    
    $inserted = 0;
    foreach ($json['items'] as $item) {
        try {
            $stmt->execute([$item['data'], $item['op'], $item['qty']]);
            $inserted++;
        } catch (Exception $e) {
            // Duplicate ou erro, continuar
        }
    }
    
    echo "[✓] Inseridos/atualizados: $inserted registros\n";
    
    // Estatísticas
    $result = $pdo->query("
        SELECT COUNT(*) as total, COUNT(DISTINCT ordem_op) as ops, SUM(quantidade) as qty
        FROM realizado_2026_excel
    ")->fetch();
    
    echo "\n[✓] IMPORTAÇÃO CONCLUÍDA\n";
    echo "  Total de registros: {$result['total']}\n";
    echo "  OPs distintas: {$result['ops']}\n";
    echo "  Quantidade total: " . number_format($result['qty'], 2, ',', '.') . "\n";
    
} catch (Exception $e) {
    echo "[✗] Erro: {$e->getMessage()}\n";
    exit(1);
}
