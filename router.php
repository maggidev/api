<?php
/**
 * Router principal da API
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Security.php';
require_once __DIR__ . '/utils/Logger.php';
require_once __DIR__ . '/utils/Cache.php';
require_once __DIR__ . '/scrapers/BaseScraper.php';
require_once __DIR__ . '/scrapers/AnimeFireScraper.php';
require_once __DIR__ . '/scrapers/ThreeHentaiScraper.php';
require_once __DIR__ . '/scrapers/HentaisTubeScraper.php';

use AnimeScraper\Utils\Security;
use AnimeScraper\Utils\Logger;
use AnimeScraper\Utils\Cache;
use AnimeScraper\Scrapers\AnimeFireScraper;
use AnimeScraper\Scrapers\ThreeHentaiScraper;
use AnimeScraper\Scrapers\HentaisTubeScraper;

// Define tempo de início para cálculo de response time
define('REQUEST_START_TIME', microtime(true));

// Configura headers CORS
Security::setCorsHeaders();

// Obtém rota e parâmetros
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/index.php', '', $path);
$path = trim($path, '/');

// Parse query parameters
parse_str(parse_url($requestUri, PHP_URL_QUERY) ?? '', $params);

/**
 * Envia resposta JSON
 */
function sendResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Endpoint de logs (protegido)
 */
if ($path === 'logs' || $path === 'api/logs') {
    if (!Security::validateAdminKey()) {
        exit;
    }
    
    $filter = $params['filter'] ?? null;
    $date = $params['date'] ?? null;
    $limit = (int) ($params['limit'] ?? 100);
    $stats = isset($params['stats']);
    
    if ($stats) {
        $data = Logger::getStats();
        Logger::logRequest('/logs?stats', true);
        sendResponse(['success' => true, 'data' => $data]);
    } else {
        $logs = Logger::getFilteredLogs($filter, $date, $limit);
        Logger::logRequest('/logs', true);
        sendResponse(['success' => true, 'data' => $logs, 'count' => count($logs)]);
    }
}

/**
 * Endpoint de health check (público)
 */
if ($path === 'health' || $path === 'api/health' || $path === '') {
    sendResponse([
        'success' => true,
        'message' => 'API is running',
        'version' => '1.0.0',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// A partir daqui, todas as rotas requerem autenticação
if (!Security::validateAppKey()) {
    exit;
}

// Rate limiting
if (!Security::checkRateLimit()) {
    exit;
}

/**
 * Roteamento para scrapers
 */
try {
    $success = true;
    $result = null;
    
    // ANIMES (AnimeFire)
    if (preg_match('#^api/animes/popular#', $path)) {
        $page = (int) ($params['page'] ?? 1);
        $scraper = new AnimeFireScraper();
        $result = $scraper->getPopular($page);
        
    } elseif (preg_match('#^api/animes/latest#', $path)) {
        $page = (int) ($params['page'] ?? 1);
        $scraper = new AnimeFireScraper();
        $result = $scraper->getLatest($page);
        
    } elseif (preg_match('#^api/animes/dubbed#', $path)) {
        $page = (int) ($params['page'] ?? 1);
        $scraper = new AnimeFireScraper();
        $result = $scraper->getDubbed($page);
        
    } elseif (preg_match('#^api/animes/search#', $path)) {
        $query = $params['q'] ?? $params['query'] ?? '';
        $page = (int) ($params['page'] ?? 1);
        if (!$query) {
            $result = ['success' => false, 'error' => 'Query parameter required'];
        } else {
            $scraper = new AnimeFireScraper();
            $result = $scraper->search($query, $page);
        }
        
    } elseif (preg_match('#^api/animes/details#', $path)) {
        $url = $params['url'] ?? '';
        if (!$url) {
            $result = ['success' => false, 'error' => 'URL parameter required'];
        } else {
            $scraper = new AnimeFireScraper();
            $result = $scraper->getDetails($url);
        }
        
    } elseif (preg_match('#^api/animes/episodes#', $path)) {
        $url = $params['url'] ?? '';
        $batch = (int) ($params['batch'] ?? 1);
        if (!$url) {
            $result = ['success' => false, 'error' => 'URL parameter required'];
        } else {
            $scraper = new AnimeFireScraper();
            $result = $scraper->getEpisodes($url, $batch);
        }
        
    } elseif (preg_match('#^api/animes/video#', $path)) {
        $url = $params['url'] ?? '';
        if (!$url) {
            $result = ['success' => false, 'error' => 'URL parameter required'];
        } else {
            $scraper = new AnimeFireScraper();
            $result = $scraper->getVideoUrl($url);
        }
        
    // MANGÁS (3Hentai)
    } elseif (preg_match('#^api/mangas/popular#', $path)) {
        $page = (int) ($params['page'] ?? 1);
        $scraper = new ThreeHentaiScraper();
        $result = $scraper->getPopular($page);
        
    } elseif (preg_match('#^api/mangas/latest#', $path)) {
        $page = (int) ($params['page'] ?? 1);
        $scraper = new ThreeHentaiScraper();
        $result = $scraper->getLatest($page);
        
    } elseif (preg_match('#^api/mangas/portuguese#', $path)) {
        $page = (int) ($params['page'] ?? 1);
        $scraper = new ThreeHentaiScraper();
        $result = $scraper->getPortuguese($page);
        
    } elseif (preg_match('#^api/mangas/portuguese-popular#', $path)) {
        $page = (int) ($params['page'] ?? 1);
        $scraper = new ThreeHentaiScraper();
        $result = $scraper->getPortuguesePopular($page);
        
    } elseif (preg_match('#^api/mangas/search#', $path)) {
        $query = $params['q'] ?? $params['query'] ?? '';
        $portugueseOnly = isset($params['pt']) || isset($params['portuguese']);
        $page = (int) ($params['page'] ?? 1);
        if (!$query) {
            $result = ['success' => false, 'error' => 'Query parameter required'];
        } else {
            $scraper = new ThreeHentaiScraper();
            $result = $scraper->search($query, $portugueseOnly, $page);
        }
        
    } elseif (preg_match('#^api/mangas/details#', $path)) {
        $url = $params['url'] ?? '';
        if (!$url) {
            $result = ['success' => false, 'error' => 'URL parameter required'];
        } else {
            $scraper = new ThreeHentaiScraper();
            $result = $scraper->getDetails($url);
        }
        
    } elseif (preg_match('#^api/mangas/pages#', $path)) {
        $url = $params['url'] ?? '';
        if (!$url) {
            $result = ['success' => false, 'error' => 'URL parameter required'];
        } else {
            $scraper = new ThreeHentaiScraper();
            $result = $scraper->getPages($url);
        }
        
    // HENTAIS (HentaisTube)
    } elseif (preg_match('#^api/hentais/popular#', $path)) {
        $page = (int) ($params['page'] ?? 1);
        $scraper = new HentaisTubeScraper();
        $result = $scraper->getPopular($page);
        
    } elseif (preg_match('#^api/hentais/latest#', $path)) {
        $page = (int) ($params['page'] ?? 1);
        $scraper = new HentaisTubeScraper();
        $result = $scraper->getLatest($page);
        
    } elseif (preg_match('#^api/hentais/search#', $path)) {
        $query = $params['q'] ?? $params['query'] ?? '';
        if (!$query) {
            $result = ['success' => false, 'error' => 'Query parameter required'];
        } else {
            $scraper = new HentaisTubeScraper();
            $result = $scraper->search($query);
        }
        
    } elseif (preg_match('#^api/hentais/details#', $path)) {
        $url = $params['url'] ?? '';
        if (!$url) {
            $result = ['success' => false, 'error' => 'URL parameter required'];
        } else {
            $scraper = new HentaisTubeScraper();
            $result = $scraper->getDetails($url);
        }
        
    } elseif (preg_match('#^api/hentais/video#', $path)) {
        $url = $params['url'] ?? '';
        if (!$url) {
            $result = ['success' => false, 'error' => 'URL parameter required'];
        } else {
            $scraper = new HentaisTubeScraper();
            $result = $scraper->getVideoUrl($url);
        }
        
    } else {
        $result = [
            'success' => false,
            'error' => 'Endpoint not found',
            'available_endpoints' => [
                'animes' => [
                    '/api/animes/popular?page=1',
                    '/api/animes/latest?page=1',
                    '/api/animes/dubbed?page=1',
                    '/api/animes/search?q=naruto&page=1',
                    '/api/animes/details?url=...',
                    '/api/animes/episodes?url=...&batch=1',
                    '/api/animes/video?url=...'
                ],
                'mangas' => [
                    '/api/mangas/popular?page=1',
                    '/api/mangas/latest?page=1',
                    '/api/mangas/portuguese?page=1',
                    '/api/mangas/portuguese-popular?page=1',
                    '/api/mangas/search?q=...&pt=1&page=1',
                    '/api/mangas/details?url=...',
                    '/api/mangas/pages?url=...'
                ],
                'hentais' => [
                    '/api/hentais/popular?page=1',
                    '/api/hentais/latest?page=1',
                    '/api/hentais/search?q=...',
                    '/api/hentais/details?url=...',
                    '/api/hentais/video?url=...'
                ]
            ]
        ];
        $success = false;
    }
    
    // Log da requisição
    if ($result) {
        $success = $result['success'] ?? false;
        $error = $success ? null : ($result['error'] ?? 'Unknown error');
        Logger::logRequest($path, $success, $error);
        sendResponse($result);
    }
    
} catch (\Exception $e) {
    Logger::logRequest($path, false, $e->getMessage());
    sendResponse([
        'success' => false,
        'error' => 'Internal server error',
        'message' => APP_ENV === 'development' ? $e->getMessage() : 'An error occurred'
    ], 500);
}
