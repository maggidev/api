<?php
/**
 * Configuração principal da API
 */

// Carrega variáveis de ambiente
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Configurações gerais
define('APP_SECRET_KEY', $_ENV['APP_SECRET_KEY'] ?? 'default-secret-key');
define('ADMIN_LOG_KEY', $_ENV['ADMIN_LOG_KEY'] ?? 'default-admin-key');
define('RATE_LIMIT_PER_HOUR', (int)($_ENV['RATE_LIMIT_PER_HOUR'] ?? 100));
define('CACHE_TTL', (int)($_ENV['CACHE_TTL'] ?? 3600));
define('LOG_RETENTION_DAYS', (int)($_ENV['LOG_RETENTION_DAYS'] ?? 7));
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('CORS_ORIGINS', $_ENV['CORS_ORIGINS'] ?? '*');

// Diretórios
define('BASE_DIR', dirname(__DIR__));
define('LOGS_DIR', BASE_DIR . '/logs');
define('CACHE_DIR', BASE_DIR . '/cache');

// Cria diretórios se não existirem
if (!is_dir(LOGS_DIR)) {
    mkdir(LOGS_DIR, 0755, true);
}
if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

// Headers padrão para scraping
define('DEFAULT_HEADERS', [
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
    'Accept-Encoding' => 'gzip, deflate, br',
    'Connection' => 'keep-alive',
    'Upgrade-Insecure-Requests' => '1'
]);

// Timeout para requisições (segundos)
define('REQUEST_TIMEOUT', 5);

return [
    'app_secret_key' => APP_SECRET_KEY,
    'admin_log_key' => ADMIN_LOG_KEY,
    'rate_limit' => RATE_LIMIT_PER_HOUR,
    'cache_ttl' => CACHE_TTL,
    'log_retention_days' => LOG_RETENTION_DAYS,
    'app_env' => APP_ENV,
    'cors_origins' => CORS_ORIGINS,
    'logs_dir' => LOGS_DIR,
    'cache_dir' => CACHE_DIR,
    'default_headers' => DEFAULT_HEADERS,
    'request_timeout' => REQUEST_TIMEOUT
];
