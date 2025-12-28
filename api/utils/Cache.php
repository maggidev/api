<?php
namespace AnimeScraper\Utils;

/**
 * Sistema de cache em arquivo
 */
class Cache
{
    /**
     * Obtém item do cache
     */
    public static function get(string $key): mixed
    {
        $file = self::getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        // Verifica se expirou
        if ($data['expires_at'] < time()) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }

    /**
     * Salva item no cache
     */
    public static function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $ttl = $ttl ?? CACHE_TTL;
        $file = self::getCacheFile($key);
        
        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time()
        ];
        
        file_put_contents($file, json_encode($data));
    }

    /**
     * Remove item do cache
     */
    public static function delete(string $key): void
    {
        $file = self::getCacheFile($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Limpa todo o cache
     */
    public static function clear(): void
    {
        $files = glob(CACHE_DIR . '/cache_*.json');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Limpa cache expirado
     */
    public static function clearExpired(): void
    {
        $files = glob(CACHE_DIR . '/cache_*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['expires_at'] < time()) {
                unlink($file);
            }
        }
    }

    /**
     * Gera nome do arquivo de cache
     */
    private static function getCacheFile(string $key): string
    {
        $hash = md5($key);
        return CACHE_DIR . '/cache_' . $hash . '.json';
    }

    /**
     * Gera chave de cache baseada em parâmetros
     */
    public static function generateKey(string $prefix, array $params = []): string
    {
        ksort($params);
        return $prefix . '_' . md5(json_encode($params));
    }
}
