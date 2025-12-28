<?php
namespace AnimeScraper\Scrapers;

use AnimeScraper\Utils\Cache;

/**
 * Scraper para 3Hentai (mangás hentai)
 */
class ThreeHentaiScraper extends BaseScraper
{
    public function __construct()
    {
        parent::__construct('https://3hentai.net');
        $this->headers['Referer'] = $this->baseUrl . '/';
        $this->headers['Origin'] = $this->baseUrl;
    }

    /**
     * Lista mangás populares
     */
    public function getPopular(int $page = 1): array
    {
        $cacheKey = Cache::generateKey('3hentai_popular', ['page' => $page]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $url = "{$this->baseUrl}/search?q=pages%3A>0&page={$page}&sort=popular";
            $html = $this->get($url);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar página');
            }

            $mangas = $this->parseListPage($html);
            Cache::set($cacheKey, $mangas);
            return $this->success($mangas);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar mangás populares', $e);
        }
    }

    /**
     * Lista mangás recentes
     */
    public function getLatest(int $page = 1): array
    {
        $cacheKey = Cache::generateKey('3hentai_latest', ['page' => $page]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $url = "{$this->baseUrl}/search?q=pages%3A>0&page={$page}";
            $html = $this->get($url);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar página');
            }

            $mangas = $this->parseListPage($html);
            Cache::set($cacheKey, $mangas);
            return $this->success($mangas);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar mangás recentes', $e);
        }
    }

    /**
     * Lista mangás em português
     */
    public function getPortuguese(int $page = 1): array
    {
        $cacheKey = Cache::generateKey('3hentai_portuguese', ['page' => $page]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $url = "{$this->baseUrl}/language/portuguese?page={$page}";
            $html = $this->get($url);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar página');
            }

            $mangas = $this->parseListPage($html);
            Cache::set($cacheKey, $mangas);
            return $this->success($mangas);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar mangás em português', $e);
        }
    }

    /**
     * Lista mangás populares em português
     */
    public function getPortuguesePopular(int $page = 1): array
    {
        $cacheKey = Cache::generateKey('3hentai_pt_popular', ['page' => $page]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $url = "{$this->baseUrl}/language/portuguese?page={$page}&sort=popular";
            $html = $this->get($url);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar página');
            }

            $mangas = $this->parseListPage($html);
            Cache::set($cacheKey, $mangas);
            return $this->success($mangas);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar mangás populares em português', $e);
        }
    }

    /**
     * Busca mangás
     */
    public function search(string $query, bool $portugueseOnly = false, int $page = 1): array
    {
        $cacheKey = Cache::generateKey('3hentai_search', [
            'query' => $query, 
            'pt' => $portugueseOnly, 
            'page' => $page
        ]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $q = urlencode(trim($query));
            if ($portugueseOnly) {
                $q = urlencode("language:portuguese {$query}");
            }
            
            $url = "{$this->baseUrl}/search?q={$q}&page={$page}";
            $html = $this->get($url);
            
            if (!$html) {
                return $this->handleError('Falha ao realizar busca');
            }

            $mangas = $this->parseListPage($html);
            Cache::set($cacheKey, $mangas);
            return $this->success($mangas);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar mangás', $e);
        }
    }

    /**
     * Detalhes do mangá
     */
    public function getDetails(string $mangaUrl): array
    {
        $cacheKey = Cache::generateKey('3hentai_details', ['url' => $mangaUrl]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $html = $this->get($mangaUrl);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar detalhes');
            }

            $crawler = $this->crawl($html);
            
            $title = $this->extractText($crawler, 'h1 span');
            $thumbnail = $this->extractAttr($crawler, 'img.thumbnail', 'src');
            
            // Tags
            $tags = [];
            $crawler->filter('div.tag-container a[href*="/tags/"]')->each(function (Crawler $node) use (&$tags) {
                $tags[] = $this->sanitize($node->text());
            });
            
            // Artistas
            $artists = [];
            $crawler->filter('div.tag-container a[href*="/artists/"]')->each(function (Crawler $node) use (&$artists) {
                $artists[] = $this->sanitize($node->text());
            });
            
            // Grupos
            $groups = [];
            $crawler->filter('div.tag-container a[href*="/groups/"]')->each(function (Crawler $node) use (&$groups) {
                $groups[] = $this->sanitize($node->text());
            });
            
            // Número de páginas
            $pagesText = $this->extractText($crawler, 'div.tag-container');
            $pages = null;
            if ($pagesText && preg_match('/(\d+)/', $pagesText, $matches)) {
                $pages = (int) $matches[1];
            }
            
            // Idioma
            $isPortuguese = $crawler->filter('a[href*="/language/portuguese"]')->count() > 0;
            $language = $isPortuguese ? 'Português (PT-BR)' : 'Outro idioma';

            $details = [
                'title' => $this->sanitize($title ?? 'Sem título'),
                'url' => $mangaUrl,
                'thumbnail' => $thumbnail ? $this->absoluteUrl($thumbnail) : null,
                'tags' => $tags,
                'artists' => $artists,
                'groups' => $groups,
                'pages' => $pages,
                'language' => $language,
                'type' => 'manga'
            ];

            Cache::set($cacheKey, $details);
            return $this->success($details);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar detalhes', $e);
        }
    }

    /**
     * Obtém páginas do mangá
     */
    public function getPages(string $mangaUrl): array
    {
        $cacheKey = Cache::generateKey('3hentai_pages', ['url' => $mangaUrl]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $html = $this->get($mangaUrl);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar páginas');
            }

            $crawler = $this->crawl($html);
            $pages = [];

            $crawler->filter('img')->each(function (Crawler $node) use (&$pages) {
                $src = $node->attr('data-src') ?: $node->attr('src');
                
                if ($src && 
                    preg_match('/\.(jpg|png|webp)$/i', $src) && 
                    stripos($src, 'thumb') === false) {
                    $pages[] = $this->absoluteUrl($src);
                }
            });

            // Remove duplicatas
            $pages = array_unique($pages);
            $pages = array_values($pages);

            Cache::set($cacheKey, $pages);
            return $this->success($pages);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar páginas', $e);
        }
    }

    /**
     * Parse página de listagem
     */
    private function parseListPage(string $html): array
    {
        $crawler = $this->crawl($html);
        $mangas = [];

        $crawler->filter('a[href*="/d/"]')->each(function (Crawler $node) use (&$mangas) {
            $href = $node->attr('href');
            $title = $this->extractText($node, 'div');
            $thumbnail = $this->extractAttr($node, 'img', 'src');

            if ($href && $title) {
                $mangas[] = [
                    'title' => $this->sanitize($title),
                    'url' => $this->absoluteUrl($href),
                    'thumbnail' => $thumbnail ? $this->absoluteUrl($thumbnail) : null,
                    'type' => 'manga'
                ];
            }
        });

        return $mangas;
    }
}
