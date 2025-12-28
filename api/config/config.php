<?php
/**
 * Configuração principal da API
 * Adaptada para ambiente serverless (Vercel)
 */

// Carrega variáveis de ambiente (no Vercel elas vêm do dashboard, .env não existe)
$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Configurações gerais (prioridade: variáveis do Vercel → .env → defaults)
define('APP_SECRET_KEY', $_SERVER['APP_SECRET_KEY'] ?? $_ENV['APP_SECRET_KEY'] ?? 'default-secret-key-change-in-production');
define('ADMIN_LOG_KEY', $_SERVER['ADMIN_LOG_KEY'] ?? $_ENV['ADMIN_LOG_KEY'] ?? 'default-admin-key-change-in-production');
define('RATE_LIMIT_PER_HOUR', (int)($_SERVER['RATE_LIMIT_PER_HOUR'] ?? $_ENV['RATE_LIMIT_PER_HOUR'] ?? 100));
define('CACHE_TTL', (int)($_SERVER['CACHE_TTL'] ?? $_ENV['CACHE_TTL'] ?? 3600));
define('LOG_RETENTION_DAYS', (int)($_SERVER['LOG_RETENTION_DAYS'] ?? $_ENV['LOG_RETENTION_DAYS'] ?? 7));
define('APP_ENV', $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'production');
define('CORS_ORIGINS', $_SERVER['CORS_ORIGINS'] ?? $_ENV['CORS_ORIGINS'] ?? '*');

// Diretórios adaptados para Vercel (única pasta com permissão de escrita = /tmp)
define('BASE_DIR', dirname(__DIR__));
define('LOGS_DIR', '/tmp/logs');
define('CACHE_DIR', '/tmp/cache');

// Cria diretórios temporários no /tmp (funciona no Vercel)
if (!is_dir(LOGS_DIR)) {
    @mkdir(LOGS_DIR, 0755, true);  // @ suprime warning caso falhe (raro)
}
if (!is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0755, true);
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
define('REQUEST_TIMEOUT', 10);  // Aumentei um pouco, Vercel permite até 10s no plano gratuito

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