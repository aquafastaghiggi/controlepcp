<?php
/**
 * Teste de Conexão PostgreSQL - CODI
 * Usando credenciais fornecidas
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$host = '190.15.43.23';
$port = '5001';
$user = 'Postgres';
$password = 'data#3789!';
$database = 'codi';  // Assumindo nome do DB

echo "🔌 Testando Conexão PostgreSQL CODI\n";
echo "=" . str_repeat("=", 80) . "\n\n";

try {
    // Tentar conexão
    echo "📡 Conectando a: $host:$port\n";
    echo "   Usuário: $user\n\n";
    
    $dsn = "pgsql:host=$host;port=$port;user=$user;password=$password";
    
    // Listar databases disponíveis
    $connInfo = "host=$host port=$port user=$user password=$password";
    
    // Conectar sem especificar database primeiro
    $conn = @pg_connect("host=$host port=$port user=$user password=$password dbname=postgres");
    
    if (!$conn) {
        echo "❌ Erro na conexão inicial\n";
        echo "Tentando alternativas...\n\n";
        
        // Tentar com diferentes nomes de database
        $databases = ['codi', 'postgres', 'template1'];
        foreach ($databases as $db) {
            echo "  Tentando database: $db... ";
            $testConn = @pg_connect("host=$host port=$port user=$user password=$password dbname=$db");
            if ($testConn) {
                echo "✅ CONECTADO!\n";
                $conn = $testConn;
                break;
            } else {
                echo "❌\n";
            }
        }
    } else {
        echo "✅ Conectado!\n\n";
    }
    
    if (!$conn) {
        echo "\n❌ Não foi possível conectar ao PostgreSQL\n";
        exit(1);
    }
    
    // Listar databases
    echo "\n📋 Databases disponíveis:\n";
    $result = pg_query($conn, "SELECT datname FROM pg_database WHERE datistemplate = false ORDER BY datname;");
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            echo "  - " . $row['datname'] . "\n";
        }
    }
    
    // Se conectado, explorar tabelas
    $currentDb = pg_query($conn, "SELECT current_database();");
    $dbName = pg_fetch_row($currentDb)[0];
    echo "\n✅ Database Atual: $dbName\n\n";
    
    // Listar schemas
    echo "📊 Schemas e Tabelas:\n";
    $result = pg_query($conn, "
        SELECT 
            n.nspname as schema,
            c.relname as table_name,
            pg_size_pretty(pg_total_relation_size(c.oid)) as size
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname NOT IN ('pg_catalog', 'information_schema')
        AND c.relkind = 'r'
        ORDER BY n.nspname, c.relname;
    ");
    
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            echo "\n  [{$row['schema']}] {$row['table_name']} ({$row['size']})\n";
            
            // Mostrar primeiras colunas
            $colResult = pg_query($conn, "
                SELECT column_name, data_type 
                FROM information_schema.columns 
                WHERE table_schema='{$row['schema']}' AND table_name='{$row['table_name']}'
                LIMIT 10
            ");
            while ($col = pg_fetch_assoc($colResult)) {
                echo "    - {$col['column_name']} ({$col['data_type']})\n";
            }
            
            // Contar registros
            $countResult = pg_query($conn, "SELECT COUNT(*) FROM \"{$row['schema']}\".\"{$row['table_name']}\";");
            $count = pg_fetch_row($countResult)[0];
            echo "    Total: $count registros\n";
        }
    }
    
    pg_close($conn);
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
