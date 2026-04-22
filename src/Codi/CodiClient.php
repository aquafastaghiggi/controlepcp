<?php
/**
 * CODI HTTP REST Client
 * 
 * Responsabilidade: Conectar ao servidor CODI e fazer requisições HTTP
 * 
 * FASE 2 - Integração CODI
 * Criado: 2026-04-06
 */

namespace Codi;

class CodiClient
{
    /**
     * Configuração do servidor CODI
     */
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $companyCode;
    
    /**
     * Configuração de retry
     */
    private int $maxRetries = 3;
    private int $retryDelayMs = 1000;
    
    /**
     * Logs de requisições
     */
    private array $logs = [];
    private bool $enableLogging = true;
    
    /**
     * Timeout em segundos
     */
    private int $timeout = 30;
    
    /**
     * Endpoints CODI conhecidos
     */
    private const ENDPOINTS = [
        'eventos' => '/action/ger/webservice/rest/relatorioEvento',
        'eventos_consolidado' => '/action/ger/webservice/rest/relatorioEventoConsolidado',
        'performance' => '/action/ger/webservice/rest/performance',
        'calendario' => '/action/ger/webservice/rest/calendarioFabril',
        'recursos' => '/action/ger/webservice/rest/recurso',
        'operacoes' => '/action/ger/webservice/rest/operacao',
        'produtos' => '/action/ger/webservice/rest/produto',
    ];
    
    /**
     * Construtor
     * 
     * @param string $baseUrl         Exemplo: 'http://192.168.0.100:8080'
     * @param string $username        Usuário CODI
     * @param string $password        Senha CODI
     * @param string $companyCode     Código da empresa (ex: 'matriz')
     */
    public function __construct(
        string $baseUrl,
        string $username,
        string $password,
        string $companyCode = 'matriz'
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->companyCode = $companyCode;
        
        $this->log("Client initialized for: {$this->baseUrl}");
    }
    
    /**
     * GET Request
     * 
     * @param string $endpoint   Nome ou URL completa
     * @param array  $params     Parâmetros de query
     * 
     * @return array|null       Dados decodificados ou null em erro
     */
    public function get(string $endpoint, array $params = []): ?array
    {
        $url = $this->buildUrl($endpoint, $params);
        return $this->request('GET', $url);
    }
    
    /**
     * POST Request
     * 
     * @param string $endpoint   Nome ou URL completa
     * @param array  $data       Dados a enviar
     * @param array  $params     Parâmetros de query (opcional)
     * 
     * @return array|null       Dados decodificados ou null em erro
     */
    public function post(string $endpoint, array $data = [], array $params = []): ?array
    {
        $url = $this->buildUrl($endpoint, $params);
        return $this->request('POST', $url, $data);
    }
    
    /**
     * Buscar Eventos de Produção
     * 
     * @param array $filters     Filtros adicionais (data_inicio, data_fim, etc)
     * 
     * @return array|null
     */
    public function getEventos(array $filters = []): ?array
    {
        $params = array_merge([
            'empresaCodigo' => $this->companyCode,
        ], $filters);
        
        return $this->get('eventos', $params);
    }
    
    /**
     * Buscar Eventos Consolidados
     * 
     * @param array $filters
     * 
     * @return array|null
     */
    public function getEventosConsolidado(array $filters = []): ?array
    {
        $params = array_merge([
            'empresaCodigo' => $this->companyCode,
        ], $filters);
        
        return $this->get('eventos_consolidado', $params);
    }
    
    /**
     * Buscar Performance Atual
     * 
     * @param array $filters
     * 
     * @return array|null
     */
    public function getPerformance(array $filters = []): ?array
    {
        $params = array_merge([
            'empresaCodigo' => $this->companyCode,
        ], $filters);
        
        return $this->get('performance', $params);
    }
    
    /**
     * Buscar Calendário Fabril
     * 
     * @param array $filters
     * 
     * @return array|null
     */
    public function getCalendario(array $filters = []): ?array
    {
        $params = array_merge([
            'empresaCodigo' => $this->companyCode,
        ], $filters);
        
        return $this->get('calendario', $params);
    }
    
    /**
     * Buscar Recursos (máquinas)
     * 
     * @param array $filters
     * 
     * @return array|null
     */
    public function getRecursos(array $filters = []): ?array
    {
        $params = array_merge([
            'empresaCodigo' => $this->companyCode,
        ], $filters);
        
        return $this->get('recursos', $params);
    }
    
    /**
     * Buscar Operações
     * 
     * @param array $filters
     * 
     * @return array|null
     */
    public function getOperacoes(array $filters = []): ?array
    {
        $params = array_merge([
            'empresaCodigo' => $this->companyCode,
        ], $filters);
        
        return $this->get('operacoes', $params);
    }
    
    /**
     * Buscar Produtos
     * 
     * @param array $filters
     * 
     * @return array|null
     */
    public function getProdutos(array $filters = []): ?array
    {
        $params = array_merge([
            'empresaCodigo' => $this->companyCode,
        ], $filters);
        
        return $this->get('produtos', $params);
    }
    
    /**
     * Testar Conexão com CODI
     * 
     * @return bool  true se conectado, false caso contrário
     */
    public function testConnection(): bool
    {
        try {
            $result = $this->get('calendario', [
                'empresaCodigo' => $this->companyCode,
                'limit' => 1
            ]);
            
            return $result !== null;
        } catch (\Exception $e) {
            $this->log("Connection test failed: {$e->getMessage()}", 'ERROR');
            return false;
        }
    }
    
    /**
     * Fazer requisição HTTP com retry automático
     * 
     * @param string $method   GET, POST, etc
     * @param string $url      URL completa
     * @param array  $data     Dados para POST
     * 
     * @return array|null
     */
    private function request(string $method, string $url, array $data = []): ?array
    {
        $attempt = 0;
        
        while ($attempt < $this->maxRetries) {
            $attempt++;
            
            try {
                $response = $this->doRequest($method, $url, $data);
                
                if ($response === null) {
                    throw new \Exception('Empty response from server');
                }
                
                $this->log("Request successful: {$method} {$url}", 'SUCCESS');
                return $response;
                
            } catch (\Exception $e) {
                $error = $e->getMessage();
                $this->log("Attempt {$attempt}/{$this->maxRetries} failed: {$error}", 'WARNING');
                
                // Se for última tentativa, retornicar erro
                if ($attempt >= $this->maxRetries) {
                    $this->log("All {$this->maxRetries} attempts failed for: {$url}", 'ERROR');
                    return null;
                }
                
                // Aguardar antes de next retry
                usleep($this->retryDelayMs * 1000);
            }
        }
        
        return null;
    }
    
    /**
     * Executar requisição HTTP
     * 
     * @param string $method
     * @param string $url
     * @param array  $data
     * 
     * @return array|null
     * 
     * @throws \Exception
     */
    private function doRequest(string $method, string $url, array $data = []): ?array
    {
        if (!extension_loaded('curl')) {
            throw new \Exception('cURL extension not loaded');
        }
        
        $ch = curl_init();
        
        try {
            // Configuração básica
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            // Autenticação Basic Auth
            $credentials = base64_encode("{$this->username}:{$this->password}");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Basic {$credentials}",
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
            
            // Método HTTP
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            } elseif ($method !== 'GET') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            }
            
            // Executar
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            
            if ($curlError) {
                throw new \Exception("cURL Error: {$curlError}");
            }
            
            // Validar HTTP Code
            if ($httpCode >= 400) {
                throw new \Exception("HTTP {$httpCode}: " . substr($response, 0, 100));
            }
            
            if (empty($response)) {
                throw new \Exception('Empty response body');
            }
            
            // Converter encoding ISO-8859-1 para UTF-8 se necessário
            // CODI server pode retornar dados em ISO-8859-1
            $response = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
            
            // Decodificar JSON
            $decoded = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("JSON decode error: " . json_last_error_msg());
            }
            
            return $decoded;
            
        } finally {
            curl_close($ch);
        }
    }
    
    /**
     * Construir URL completa
     * 
     * @param string $endpoint
     * @param array  $params
     * 
     * @return string
     */
    private function buildUrl(string $endpoint, array $params = []): string
    {
        // Se é um nome de endpoint conhecido, expandir
        if (isset(self::ENDPOINTS[$endpoint])) {
            $endpoint = self::ENDPOINTS[$endpoint];
        }
        
        // Construir URL
        $url = $this->baseUrl . $endpoint;
        
        // Adicionar parâmetros
        if (!empty($params)) {
            $url .= '?' . http_build_query($params, '', '&');
        }
        
        return $url;
    }
    
    /**
     * Logging interno
     * 
     * @param string $message
     * @param string $level    INFO|WARNING|ERROR|SUCCESS
     */
    private function log(string $message, string $level = 'INFO'): void
    {
        if (!$this->enableLogging) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
        ];
        
        $this->logs[] = $logEntry;
    }
    
    /**
     * Obter logs
     * 
     * @param string|null $level  Filtrar por nível (opcional)
     * 
     * @return array
     */
    public function getLogs(?string $level = null): array
    {
        if ($level === null) {
            return $this->logs;
        }
        
        return array_filter($this->logs, fn($log) => $log['level'] === $level);
    }
    
    /**
     * Limpar logs
     */
    public function clearLogs(): void
    {
        $this->logs = [];
    }
    
    /**
     * Configurar número máximo de retry
     * 
     * @param int $maxRetries
     */
    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = max(1, $maxRetries);
        return $this;
    }
    
    /**
     * Configurar delay entre retries (ms)
     * 
     * @param int $delayMs
     */
    public function setRetryDelayMs(int $delayMs): self
    {
        $this->retryDelayMs = max(100, $delayMs);
        return $this;
    }
    
    /**
     * Habilitar/desabilitar logging
     * 
     * @param bool $enabled
     */
    public function setLogging(bool $enabled): self
    {
        $this->enableLogging = $enabled;
        return $this;
    }
    
    /**
     * Configurar timeout
     * 
     * @param int $seconds
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = max(5, $seconds);
        return $this;
    }
    
    /**
     * Obter configuração atual
     * 
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'baseUrl' => $this->baseUrl,
            'username' => $this->username,
            'companyCode' => $this->companyCode,
            'maxRetries' => $this->maxRetries,
            'retryDelayMs' => $this->retryDelayMs,
            'timeout' => $this->timeout,
            'loggingEnabled' => $this->enableLogging,
        ];
    }
}
?>
