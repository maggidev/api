<?php
namespace AnimeScraper\Utils;

/**
 * Classe de segurança para autenticação e rate limiting
 */
class Security
{
    private static $rateLimitFile = null;

    public static function init()
    {
        self::$rateLimitFile = CACHE_DIR . '/rate_limits.json';
    }

    /**
     * Verifica se a requisição tem a chave de API válida
     */
    public static function validateAppKey(): bool
    {
        $headers = getallheaders();
        $appKey = $headers['X-App-Key'] ?? $headers['x-app-key'] ?? null;
        
        if (!$appKey || $appKey !== APP_SECRET_KEY) {
            self::sendError(403, 'Forbidden: Invalid or missing API key');
            return false;
        }
        
        return true;
    }

    /**
     * Verifica se a requisição tem a chave de admin válida
     */
    public static function validateAdminKey(): bool
    {
        $headers = getallheaders();
        $adminKey = $headers['X-Admin-Key'] ?? $headers['x-admin-key'] ?? null;
        
        if (!$adminKey || $adminKey !== ADMIN_LOG_KEY) {
            self::sendError(403, 'Forbidden: Invalid or missing admin key');
            return false;
        }
        
        return true;
    }

    /**
     * Implementa rate limiting por IP
     */
    public static function checkRateLimit(): bool
    {
        $ip = self::getClientIP();
        $currentHour = date('Y-m-d-H');
        $rateLimits = self::loadRateLimits();
        
        // Limpa dados antigos
        $rateLimits = array_filter($rateLimits, function($data) {
            return isset($data['hour']) && 
                   strtotime($data['hour']) > strtotime('-2 hours');
        });
        
        // Verifica limite do IP
        $key = $ip . '_' . $currentHour;
        if (!isset($rateLimits[$key])) {
            $rateLimits[$key] = ['count' => 0, 'hour' => $currentHour];
        }
        
        $rateLimits[$key]['count']++;
        
        if ($rateLimits[$key]['count'] > RATE_LIMIT_PER_HOUR) {
            self::saveRateLimits($rateLimits);
            self::sendError(429, 'Too Many Requests: Rate limit exceeded');
            return false;
        }
        
        self::saveRateLimits($rateLimits);
        return true;
    }

    /**
     * Obtém o IP real do cliente
     */
    public static function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Carrega dados de rate limiting
     */
    private static function loadRateLimits(): array
    {
        if (!file_exists(self::$rateLimitFile)) {
            return [];
        }
        
        $content = file_get_contents(self::$rateLimitFile);
        return json_decode($content, true) ?: [];
    }

    /**
     * Salva dados de rate limiting
     */
    private static function saveRateLimits(array $data): void
    {
        file_put_contents(self::$rateLimitFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Envia resposta de erro e encerra execução
     */
    public static function sendError(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code
        ]);
        exit;
    }

    /**
     * Configura headers CORS
     */
    public static function setCorsHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        
        if (CORS_ORIGINS === '*') {
            header('Access-Control-Allow-Origin: *');
        } else {
            $allowedOrigins = explode(',', CORS_ORIGINS);
            if (in_array($origin, $allowedOrigins)) {
                header("Access-Control-Allow-Origin: $origin");
            }
        }
        
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-App-Key, X-Admin-Key, X-Device-Model, User-Agent');
        header('Access-Control-Max-Age: 3600');
        
        // Responde a preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}

Security::init();
