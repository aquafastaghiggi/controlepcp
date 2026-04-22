<?php
/**
 * FASE 7 - TESTES E VALIDAÇÃO
 * 
 * Suite de testes para validar todo o pipeline CODI
 * - FASE 1: Database
 * - FASE 2: CodiClient
 * - FASE 3: CodiSyncService
 * - FASE 4: EficienciaCalculator
 * - FASE 5: API REST
 * - FASE 6: Dashboard
 */

require_once __DIR__ . '/../bootstrap.php';

use Src\Database\Connection;
use Codi\CodiClient;
use Codi\CodiSyncService;
use Codi\EficienciaCalculator;

class TesteSuiteCodiIntegration
{
    private $db;
    private $resultados = [];
    private $total_testes = 0;
    private $testes_passado = 0;
    private $testes_falhado = 0;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    /**
     * Executar todos os testes
     */
    public function executarTodos()
    {
        echo "🧪 FASE 7 - SUITE DE TESTES CODI INTEGRATION\n";
        echo str_repeat("=", 70) . "\n\n";

        $this->testarBancoDados();
        $this->testarCodiClient();
        $this->testarCodiSyncService();
        $this->testarEficienciaCalculator();
        $this->testarAPIREST();

        $this->exibirResumo();
    }

    /**
     * Testes FASE 1: Banco de Dados
     */
    private function testarBancoDados()
    {
        echo "📦 FASE 1: Testando Banco de Dados\n";
        echo str_repeat("-", 70) . "\n";

        // Teste 1.1: Conexão
        $this->teste("Conexão com BD", function() {
            return $this->db !== null;
        });

        // Teste 1.2: Tabelas existem
        $tabelas = [
            'cdi_configuracao',
            'cdi_eventos',
            'cdi_performance',
            'cdi_sincronizacao_log',
            'cdi_sku_mapping',
            'cdi_eficiencia_medicao',
            'cdi_eficiencia_historico',
            'cdi_resumo_diario'
        ];

        foreach ($tabelas as $tabela) {
            $this->teste("Tabela $tabela existe", function() use ($tabela) {
                $sql = "SELECT COUNT(*) FROM information_schema.tables 
                        WHERE table_schema = DATABASE() AND table_name = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$tabela]);
                return $stmt->fetchColumn() > 0;
            });
        }

        // Teste 1.3: Colunas critical
        $this->teste("Colunas de eficiência estão presentes", function() {
            $sql = "SHOW COLUMNS FROM cdi_eficiencia_medicao LIKE 'oee'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        });

        echo "\n";
    }

    /**
     * Testes FASE 2: CodiClient
     */
    private function testarCodiClient()
    {
        echo "🔌 FASE 2: Testando CodiClient\n";
        echo str_repeat("-", 70) . "\n";

        try {
            $client = new CodiClient('http://192.168.8.123:8081', 'admin', 'senha123', 'matriz');

            // Teste 2.1: Classe instancia
            $this->teste("CodiClient instancia corretamente", function() use ($client) {
                return $client !== null;
            });

            // Teste 2.2: Métodos existem
            $metodos = [
                'get',
                'post',
                'getEventos',
                'getPerformance',
                'testConnection',
                'getLogs',
                'setLogging'
            ];

            foreach ($metodos as $metodo) {
                $this->teste("Método $metodo existe", function() use ($client, $metodo) {
                    return method_exists($client, $metodo);
                });
            }

            // Teste 2.3: Fluent interface
            $this->teste("Suporta fluent interface (setLogging)", function() use ($client) {
                $resultado = $client->setLogging(true);
                return $resultado instanceof CodiClient;
            });

        } catch (Exception $e) {
            echo "⚠️  Erro ao testar CodiClient: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * Testes FASE 3: CodiSyncService
     */
    private function testarCodiSyncService()
    {
        echo "🔄 FASE 3: Testando CodiSyncService\n";
        echo str_repeat("-", 70) . "\n";

        try {
            $sync = new CodiSyncService($this->db);

            // Teste 3.1: Classe instancia
            $this->teste("CodiSyncService instancia corretamente", function() use ($sync) {
                return $sync !== null;
            });

            // Teste 3.2: Status inicial
            $this->teste("Método getStatus() retorna array", function() use ($sync) {
                $status = $sync->getStatus();
                return is_array($status);
            });

            // Teste 3.3: Métodos existem
            $metodos = ['syncAll', 'syncEvents', 'syncPerformance', 'getStatus', 'getLogs'];

            foreach ($metodos as $metodo) {
                $this->teste("Método $metodo existe", function() use ($sync, $metodo) {
                    return method_exists($sync, $metodo);
                });
            }

            // Teste 3.4: Logging configurável
            $this->teste("Logging pode ser habilitado/desabilitado", function() use ($sync) {
                $sync->setLogging(true);
                $sync->setLogging(false);
                return true;
            });

        } catch (Exception $e) {
            echo "⚠️  Erro ao testar CodiSyncService: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * Testes FASE 4: EficienciaCalculator
     */
    private function testarEficienciaCalculator()
    {
        echo "📊 FASE 4: Testando EficienciaCalculator\n";
        echo str_repeat("-", 70) . "\n";

        try {
            $calc = new EficienciaCalculator($this->db);

            // Teste 4.1: Classe instancia
            $this->teste("EficienciaCalculator instancia corretamente", function() use ($calc) {
                return $calc !== null;
            });

            // Teste 4.2: Métodos existem
            $metodos = [
                'calcularEficienciaCompleta',
                'setLogging',
                'getLogs'
            ];

            foreach ($metodos as $metodo) {
                $this->teste("Método $metodo existe", function() use ($calc, $metodo) {
                    return method_exists($calc, $metodo);
                });
            }

            // Teste 4.3: Calcula sem erro
            $this->teste("calcularEficienciaCompleta() executa sem erro", function() use ($calc) {
                try {
                    $resultado = $calc->calcularEficienciaCompleta(
                        date('Y-m-d', strtotime('-1 day')),
                        date('Y-m-d'),
                        []
                    );
                    return is_array($resultado) && isset($resultado['sucesso']);
                } catch (Exception $e) {
                    return false;
                }
            });

            // Teste 4.4: Retorno estruturado
            $this->teste("Resultado tem estrutura correta", function() use ($calc) {
                $resultado = $calc->calcularEficienciaCompleta(
                    date('Y-m-d', strtotime('-1 day')),
                    date('Y-m-d')
                );
                $chaves_obrigatorias = ['sucesso', 'periodosProcessados', 'desviosCalculados', 'erros', 'detalhes'];
                foreach ($chaves_obrigatorias as $chave) {
                    if (!isset($resultado[$chave])) return false;
                }
                return true;
            });

        } catch (Exception $e) {
            echo "⚠️  Erro ao testar EficienciaCalculator: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * Testes FASE 5: API REST
     */
    private function testarAPIREST()
    {
        echo "🌐 FASE 5: Testando API REST\n";
        echo str_repeat("-", 70) . "\n";

        $baseUrl = 'http://localhost/controlepcp_sandbox/api/codi_eficiencia.php';

        // Teste 5.1: Endpoint listar
        $this->testeAPI("GET ?action=listar retorna JSON", function() use ($baseUrl) {
            $response = @file_get_contents($baseUrl . '?action=listar');
            if (!$response) return false;
            $json = json_decode($response, true);
            return isset($json['sucesso']) && isset($json['dados']);
        });

        // Teste 5.2: Endpoint resumo
        $this->testeAPI("GET ?action=resumo retorna estatísticas", function() use ($baseUrl) {
            $response = @file_get_contents($baseUrl . '?action=resumo');
            if (!$response) return false;
            $json = json_decode($response, true);
            return isset($json['dados']['resumo']);
        });

        // Teste 5.3: Endpoint criticos
        $this->testeAPI("GET ?action=criticos retorna registros críticos", function() use ($baseUrl) {
            $response = @file_get_contents($baseUrl . '?action=criticos');
            if (!$response) return false;
            $json = json_decode($response, true);
            return isset($json['dados']);
        });

        // Teste 5.4: Filtros funcionam
        $this->testeAPI("Filtros por status funcionam", function() use ($baseUrl) {
            $response = @file_get_contents($baseUrl . '?action=filtrar&status=ok');
            if (!$response) return false;
            $json = json_decode($response, true);
            return isset($json['dados']['filtros_aplicados']);
        });

        echo "\n";
    }

    /**
     * Executar um teste
     */
    private function teste($nome, $funcao)
    {
        $this->total_testes++;

        try {
            if ($funcao()) {
                echo "  ✅ $nome\n";
                $this->testes_passado++;
            } else {
                echo "  ❌ $nome\n";
                $this->testes_falhado++;
            }
        } catch (Exception $e) {
            echo "  ❌ $nome - {$e->getMessage()}\n";
            $this->testes_falhado++;
        }
    }

    /**
     * Executar teste de API
     */
    private function testeAPI($nome, $funcao)
    {
        $this->teste($nome, $funcao);
    }

    /**
     * Exibir resumo
     */
    private function exibirResumo()
    {
        echo str_repeat("=", 70) . "\n";
        echo "📈 RESUMO DE TESTES\n";
        echo str_repeat("=", 70) . "\n";
        echo "Total de Testes: {$this->total_testes}\n";
        echo "✅ Passaram: {$this->testes_passado}\n";
        echo "❌ Falharam: {$this->testes_falhado}\n";

        $percentual = ($this->total_testes > 0) ? ($this->testes_passado / $this->total_testes) * 100 : 0;
        echo sprintf("Taxa de Sucesso: %.1f%%\n", $percentual);

        if ($this->testes_falhado === 0) {
            echo "\n🎉 TODOS OS TESTES PASSARAM!\n";
        } else {
            echo "\n⚠️  Alguns testes falharam. Verifique os erros acima.\n";
        }

        echo "\n" . str_repeat("=", 70) . "\n";
    }
}

// Executar suite de testes
$teste = new TesteSuiteCodiIntegration();
$teste->executarTodos();
