<?php
namespace AnimeScraper\Scrapers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Classe base para scrapers
 */
abstract class BaseScraper
{
    protected Client $client;
    protected string $baseUrl;
    protected array $headers;

    public function __construct(string $baseUrl)
{
    $this->baseUrl = $baseUrl;
    $this->headers = DEFAULT_HEADERS;

    // Remove Accept-Encoding pra evitar brotli
    unset($this->headers['Accept-Encoding']);

    $this->client = new Client([
        'timeout' => REQUEST_TIMEOUT,
        'verify' => false,
        'http_errors' => false,
        'headers' => $this->headers,
        'curl' => [
            CURLOPT_ENCODING => '',  // String vazia desativa todas as compressões automáticas
        ]
    ]);
}

    /**
     * Faz requisição GET
     */
    protected function get(string $url, array $headers = []): ?string
    {
        try {
            $response = $this->client->get($url, [
                'headers' => array_merge($this->headers, $headers)
            ]);
            
            if ($response->getStatusCode() === 200) {
                return (string) $response->getBody();
            }
            
            return null;
        } catch (RequestException $e) {
            error_log("Request error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cria um Crawler do Symfony
     */
    protected function crawl(string $html): Crawler
    {
        return new Crawler($html);
    }

    /**
     * Extrai texto de um elemento
     */
    protected function extractText(Crawler $crawler, string $selector): ?string
    {
        try {
            $node = $crawler->filter($selector);
            return $node->count() > 0 ? trim($node->text()) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extrai atributo de um elemento
     */
    protected function extractAttr(Crawler $crawler, string $selector, string $attr): ?string
    {
        try {
            $node = $crawler->filter($selector);
            return $node->count() > 0 ? $node->attr($attr) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Converte URL relativa para absoluta
     */
    protected function absoluteUrl(string $url): string
    {
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }
        
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        
        return rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/');
    }

    /**
     * Sanitiza texto
     */
    protected function sanitize(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Trata erros de forma padronizada
     */
    protected function handleError(string $message, \Exception $e = null): array
    {
        $error = [
            'success' => false,
            'error' => $message
        ];
        
        if ($e && APP_ENV === 'development') {
            $error['debug'] = $e->getMessage();
        }
        
        return $error;
    }

    /**
     * Retorna resposta de sucesso
     */
    protected function success(array $data): array
    {
        return [
            'success' => true,
            'data' => $data
        ];
    }
}
