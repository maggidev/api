<?php
namespace AnimeScraper\Utils;

/**
 * Sistema de logging integrado
 */
class Logger
{
    private static $logFile = null;

    public static function init()
    {
        self::$logFile = LOGS_DIR . '/requests.json';
    }

    /**
     * Registra uma requisição
     */
    public static function logRequest(string $endpoint, bool $success = true, ?string $error = null): void
    {
        $headers = getallheaders();
        
        // Extrai modelo do dispositivo
        $deviceModel = $headers['X-Device-Model'] ?? 'Unknown';
        $userAgent = $headers['User-Agent'] ?? 'Unknown';
        
        // Se não tem X-Device-Model, tenta extrair do User-Agent
        if ($deviceModel === 'Unknown' && preg_match('/\(([^)]+)\)/', $userAgent, $matches)) {
            $deviceModel = $matches[1];
        }
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => Security::getClientIP(),
            'device_model' => $deviceModel,
            'user_agent' => $userAgent,
            'endpoint' => $endpoint,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'success' => $success,
            'error' => $error,
            'response_time' => self::getResponseTime()
        ];
        
        self::writeLog($logEntry);
        
        // Limpa logs antigos
        self::cleanOldLogs();
    }

    /**
     * Escreve log no arquivo
     */
    private static function writeLog(array $entry): void
    {
        $logs = self::loadLogs();
        $logs[] = $entry;
        
        // Mantém apenas os últimos 10000 logs em memória
        if (count($logs) > 10000) {
            $logs = array_slice($logs, -10000);
        }
        
        file_put_contents(self::$logFile, json_encode($logs, JSON_PRETTY_PRINT));
    }

    /**
     * Carrega logs do arquivo
     */
    public static function loadLogs(): array
    {
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $content = file_get_contents(self::$logFile);
        return json_decode($content, true) ?: [];
    }

    /**
     * Obtém logs filtrados
     */
    public static function getFilteredLogs(?string $filter = null, ?string $date = null, int $limit = 100): array
    {
        $logs = self::loadLogs();
        
        // Filtra por data
        if ($date) {
            $logs = array_filter($logs, function($log) use ($date) {
                return strpos($log['timestamp'], $date) === 0;
            });
        }
        
        // Filtra por tipo (errors, success)
        if ($filter === 'errors') {
            $logs = array_filter($logs, function($log) {
                return !$log['success'];
            });
        } elseif ($filter === 'success') {
            $logs = array_filter($logs, function($log) {
                return $log['success'];
            });
        }
        
        // Ordena por timestamp decrescente (mais recentes primeiro)
        usort($logs, function($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });
        
        // Limita quantidade
        return array_slice($logs, 0, $limit);
    }

    /**
     * Obtém estatísticas dos logs
     */
    public static function getStats(): array
    {
        $logs = self::loadLogs();
        $total = count($logs);
        $errors = count(array_filter($logs, fn($l) => !$l['success']));
        $success = $total - $errors;
        
        // Endpoints mais acessados
        $endpoints = [];
        foreach ($logs as $log) {
            $ep = $log['endpoint'];
            if (!isset($endpoints[$ep])) {
                $endpoints[$ep] = 0;
            }
            $endpoints[$ep]++;
        }
        arsort($endpoints);
        
        // IPs mais ativos
        $ips = [];
        foreach ($logs as $log) {
            $ip = $log['ip'];
            if (!isset($ips[$ip])) {
                $ips[$ip] = 0;
            }
            $ips[$ip]++;
        }
        arsort($ips);
        
        // Tempo médio de resposta
        $responseTimes = array_filter(array_column($logs, 'response_time'));
        $avgResponseTime = !empty($responseTimes) ? array_sum($responseTimes) / count($responseTimes) : 0;
        
        return [
            'total_requests' => $total,
            'successful_requests' => $success,
            'failed_requests' => $errors,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
            'avg_response_time_ms' => round($avgResponseTime, 2),
            'top_endpoints' => array_slice($endpoints, 0, 10),
            'top_ips' => array_slice($ips, 0, 10)
        ];
    }

    /**
     * Limpa logs antigos
     */
    private static function cleanOldLogs(): void
    {
        // Executa limpeza apenas 1% das vezes para não impactar performance
        if (rand(1, 100) !== 1) {
            return;
        }
        
        $logs = self::loadLogs();
        $cutoffDate = date('Y-m-d', strtotime('-' . LOG_RETENTION_DAYS . ' days'));
        
        $logs = array_filter($logs, function($log) use ($cutoffDate) {
            return $log['timestamp'] >= $cutoffDate;
        });
        
        file_put_contents(self::$logFile, json_encode(array_values($logs), JSON_PRETTY_PRINT));
    }

    /**
     * Calcula tempo de resposta
     */
    private static function getResponseTime(): float
    {
        if (defined('REQUEST_START_TIME')) {
            return round((microtime(true) - REQUEST_START_TIME) * 1000, 2);
        }
        return 0;
    }
}

Logger::init();
