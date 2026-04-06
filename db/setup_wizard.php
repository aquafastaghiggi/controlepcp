<?php
/**
 * Setup Wizard - Integração CODI
 * Guia passo a passo para configurar a integração
 */

// Suprimir output
header('Content-Type: application/json');

$step = $_GET['step'] ?? 1;
$action = $_GET['action'] ?? '';

try {
    // ========== INCLUDE BOOTSTRAP ==========
    require_once __DIR__ . '/../src/bootstrap.php';

    // ========== PASSO 1: STATUS ==========
    if ($step == 1 && $action == 'status') {
        $response = [
            'passo' => 1,
            'titulo' => 'Verificar Pré-requisitos',
            'descricao' => 'Validando ambiente para integração CODI',
            'checklist' => [
                [
                    'item' => 'PHP PDO MySQL',
                    'status' => extension_loaded('pdo_mysql') ? 'ok' : 'erro',
                    'detalhes' => extension_loaded('pdo_mysql') ? 'Instalado' : 'Não encontrado'
                ],
                [
                    'item' => 'PHP cURL',
                    'status' => extension_loaded('curl') ? 'ok' : 'erro',
                    'detalhes' => extension_loaded('curl') ? 'Instalado' : 'Necessário para CODI'
                ],
                [
                    'item' => 'Banco de dados',
                    'status' => isset($pdo) ? 'ok' : 'erro',
                    'detalhes' => isset($pdo) ? 'Conectado (bootstrap.php)' : 'Verificar bootstrap.php'
                ],
                [
                    'item' => 'Migrations SQL',
                    'status' => file_exists(__DIR__ . '/codi_migrations.sql') ? 'ok' : 'aviso',
                    'detalhes' => 'Arquivo disponível'
                ]
            ],
            'proxima_acao' => 'Executar migrations'
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    // ========== PASSO 2: EXECUTAR MIGRATIONS ==========
    elseif ($step == 2 && $action == 'executar') {
        $migration_file = __DIR__ . '/codi_migrations.sql';
        
        if (!file_exists($migration_file)) {
            throw new Exception('Arquivo de migrações não encontrado');
        }

        $sql_content = file_get_contents($migration_file);
        $statements = array_filter(
            array_map('trim', explode(';', $sql_content)),
            fn($s) => !empty($s) && strpos($s, '--') !== 0
        );

        $resultados = [];
        $erros = [];

        foreach ($statements as $stmt) {
            $lines = explode("\n", $stmt);
            $clean_stmt = implode("\n", array_filter($lines, fn($l) => strpos(trim($l), '--') !== 0));

            if (empty(trim($clean_stmt))) continue;

            try {
                $pdo->exec($clean_stmt);
                $resultados[] = substr($clean_stmt, 0, 50) . '...';
            } catch (PDOException $e) {
                $erros[] = $e->getMessage();
            }
        }

        $response = [
            'passo' => 2,
            'titulo' => 'Migrações Executadas',
            'status' => count($erros) === 0 ? 'sucesso' : 'parcial',
            'statements_executados' => count($resultados),
            'erros' => $erros,
            'proxima_acao' => 'Verificar tabelas'
        ];

        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    // ========== PASSO 3: VERIFICAR TABELAS ==========
    elseif ($step == 3 && $action == 'verificar') {
        $tabelas_esperadas = [
            'cdi_configuracao',
            'cdi_eventos',
            'cdi_performance',
            'cdi_sincronizacao_log',
            'cdi_sku_mapping',
            'cdi_eficiencia_medicao',
            'cdi_eficiencia_historico',
            'cdi_resumo_diario'
        ];

        $tabelas_verificadas = [];

        foreach ($tabelas_esperadas as $tabela) {
            try {
                $result = $pdo->query("SELECT 1 FROM {$tabela} LIMIT 1");
                $tabelas_verificadas[] = [
                    'tabela' => $tabela,
                    'status' => 'existe',
                    'colunas' => $pdo->query("DESCRIBE {$tabela}")->rowCount()
                ];
            } catch (PDOException $e) {
                $tabelas_verificadas[] = [
                    'tabela' => $tabela,
                    'status' => 'não encontrada',
                    'erro' => $e->getMessage()
                ];
            }
        }

        $response = [
            'passo' => 3,
            'titulo' => 'Verificação de Tabelas',
            'total_esperadas' => count($tabelas_esperadas),
            'total_existentes' => count(array_filter($tabelas_verificadas, fn($t) => $t['status'] === 'existe')),
            'tabelas' => $tabelas_verificadas,
            'status' => count(array_filter($tabelas_verificadas, fn($t) => $t['status'] === 'existe')) === count($tabelas_esperadas)
                ? 'sucesso'
                : 'alerta'
        ];

        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    // ========== PASSO 4: CONFIGURAÇÃO CODI ==========
    elseif ($step == 4 && $action == 'config') {
        $response = [
            'passo' => 4,
            'titulo' => 'Configuração CODI',
            'campos_necessarios' => [
                [
                    'campo' => 'cdi_servidor_url',
                    'tipo' => 'text',
                    'placeholder' => 'http://192.168.0.1:8080',
                    'obrigatorio' => true
                ],
                [
                    'campo' => 'cdi_usuario',
                    'tipo' => 'text',
                    'placeholder' => 'admin',
                    'obrigatorio' => true
                ],
                [
                    'campo' => 'cdi_senha',
                    'tipo' => 'password',
                    'placeholder' => '••••••••',
                    'obrigatorio' => true
                ],
                [
                    'campo' => 'cdi_codename_empresa',
                    'tipo' => 'text',
                    'placeholder' => 'matriz',
                    'obrigatorio' => true
                ]
            ],
            'instrucoes' => 'Preencha as credenciais do seu servidor CODI',
            'proxima_acao' => 'Testar conexão'
        ];

        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    // ========== PASSO 5: TESTAR CONEXÃO ==========
    elseif ($step == 5 && $action == 'testar') {
        $url = $_POST['url'] ?? '';
        $user = $_POST['user'] ?? '';
        $pass = $_POST['pass'] ?? '';

        if (!$url || !$user || !$pass) {
            throw new Exception('credenciais incompletas');
        }

        $ch = curl_init($url . '/action/ger/webservice/rest/calendarioFabril');
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = [
            'passo' => 5,
            'titulo' => 'Teste de Conexão',
            'url' => $url,
            'status_http' => $http_code,
            'conectado' => ($http_code === 200 || $http_code === 401) ? true : false,
            'mensagem' => match($http_code) {
                200 => '✅ Conectado com sucesso!',
                401 => '⚠️ Autenticação falhou (verifique credenciais)',
                404 => '❌ URL não encontrada',
                default => "❌ Erro HTTP {$http_code}"
            }
        ];

        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    // ========== PASSO FINAL: RESUMO ==========
    elseif ($step == 6 && $action == 'resumo') {
        $response = [
            'passo' => 6,
            'titulo' => 'Setup Concluído',
            'status' => 'sucesso',
            'proximos_passos' => [
                'Criar CodiClient.php (src/Codi/)',
                'Implementar CodiSyncService.php',
                'Configurar scheduler de sincronização',
                'Criar EficienciaCalculator.php',
                'Desenvolver API endpoints'
            ],
            'documentacao' => [
                'Schema SQL' => 'db/CODI_SCHEMA_DOCUMENTATION.md',
                'Resumo Fase 1' => 'db/RESUMO_FASE_1.md',
                'Índice' => 'db/ÍNDICE.md'
            ]
        ];

        echo json_encode($response, JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>
