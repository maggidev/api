<?php
namespace AnimeScraper\Scrapers;

use AnimeScraper\Utils\Cache;

/**
 * Scraper para HentaisTube (vídeos hentai)
 */
class HentaisTubeScraper extends BaseScraper
{
    public function __construct()
    {
        parent::__construct('https://www.hentaistube.com');
        $this->headers['Referer'] = $this->baseUrl;
        $this->headers['Origin'] = $this->baseUrl;
        $this->headers['X-Requested-With'] = 'XMLHttpRequest';
    }

    /**
     * Lista hentais populares
     */
    public function getPopular(int $page = 1): array
    {
        $cacheKey = Cache::generateKey('hentaistube_popular', ['page' => $page]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $url = "{$this->baseUrl}/ranking-hentais?paginacao={$page}";
            $html = $this->get($url);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar página');
            }

            $crawler = $this->crawl($html);
            $hentais = [];

            $crawler->filter('ul.ul_sidebar > li')->each(function ( $node) use (&$hentais) {
                $link = $this->extractAttr($node, 'div.rt a.series', 'href');
                $title = $this->extractText($node, 'div.rt a.series');
                $thumbnail = $this->extractAttr($node, 'img', 'src');

                if ($title && $link) {
                    // Remove " - Episódios" do título
                    $title = preg_replace('/ - Episódios.*$/', '', $title);

                    $hentais[] = [
                        'title' => $this->sanitize($title),
                        'url' => $this->absoluteUrl($link),
                        'thumbnail' => $thumbnail ? $this->absoluteUrl($thumbnail) : null,
                        'type' => 'series'
                    ];
                }
            });

            Cache::set($cacheKey, $hentais);
            return $this->success($hentais);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar hentais populares', $e);
        }
    }

    /**
     * Lista lançamentos recentes
     */
    public function getLatest(int $page = 1): array
    {
        $cacheKey = Cache::generateKey('hentaistube_latest', ['page' => $page]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $url = "{$this->baseUrl}/page/{$page}/";
            $html = $this->get($url);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar página');
            }

            $crawler = $this->crawl($html);
            $episodes = [];

            $crawler->filter('div.epiContainer:first-child div.epiItem > a')->each(function ( $node) use (&$episodes) {
                $link = $node->attr('href');
                $title = $node->attr('title') ?: $this->extractText($node, 'div');
                $thumbnail = $this->extractAttr($node, 'img', 'src');

                if ($title && $link) {
                    $episodes[] = [
                        'title' => $this->sanitize($title),
                        'url' => $this->absoluteUrl($link),
                        'thumbnail' => $thumbnail ? $this->absoluteUrl($thumbnail) : null,
                        'type' => 'episode'
                    ];
                }
            });

            Cache::set($cacheKey, $episodes);
            return $this->success($episodes);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar lançamentos', $e);
        }
    }

    /**
     * Busca hentais
     */
    public function search(string $query): array
    {
        $cacheKey = Cache::generateKey('hentaistube_search', ['query' => $query]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $q = str_replace(' ', '-', trim($query));
            $url = "{$this->baseUrl}/busca/{$q}/";
            $html = $this->get($url);
            
            if (!$html) {
                return $this->handleError('Falha ao realizar busca');
            }

            $crawler = $this->crawl($html);
            $results = [];

            $crawler->filter('a[href*="/anime/"], a[href*="/hentai/"]')->each(function ( $node) use (&$results) {
                $link = $node->attr('href');
                $title = $node->attr('title') ?: $this->extractText($node, 'div');
                $thumbnail = $this->extractAttr($node, 'img', 'src');

                if ($title && $link && !$this->isDuplicate($results, $link)) {
                    $results[] = [
                        'title' => $this->sanitize($title),
                        'url' => $this->absoluteUrl($link),
                        'thumbnail' => $thumbnail ? $this->absoluteUrl($thumbnail) : null,
                        'type' => 'series'
                    ];
                }
            });

            Cache::set($cacheKey, $results);
            return $this->success($results);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar hentais', $e);
        }
    }

    /**
     * Detalhes do hentai (série)
     */
    public function getDetails(string $seriesUrl): array
    {
        $cacheKey = Cache::generateKey('hentaistube_details', ['url' => $seriesUrl]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $html = $this->get($seriesUrl);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar detalhes');
            }

            $crawler = $this->crawl($html);
            
            $title = $this->extractText($crawler, 'div#anime div.info-right h1');
            $thumbnail = $this->extractAttr($crawler, 'div#anime img', 'src');
            
            // Episódios
            $episodes = [];
            $crawler->filter('ul.pagAniListaContainer > li > a')->each(function ( $node) use (&$episodes) {
                $name = $this->sanitize($node->text());
                $url = $node->attr('href');
                
                if ($name && $url) {
                    $episodes[] = [
                        'name' => $name,
                        'url' => $this->absoluteUrl($url)
                    ];
                }
            });
            
            // Inverte para ordem crescente
            $episodes = array_reverse($episodes);

            $details = [
                'title' => $this->sanitize($title ?? 'Sem título'),
                'url' => $seriesUrl,
                'thumbnail' => $thumbnail ? $this->absoluteUrl($thumbnail) : null,
                'episodes' => $episodes,
                'total_episodes' => count($episodes),
                'type' => 'series'
            ];

            Cache::set($cacheKey, $details);
            return $this->success($details);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar detalhes', $e);
        }
    }

    /**
     * Obtém URL do vídeo
     */
    public function getVideoUrl(string $episodeUrl): array
    {
        $cacheKey = Cache::generateKey('hentaistube_video', ['url' => $episodeUrl]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $html = $this->get($episodeUrl);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar episódio');
            }

            $crawler = $this->crawl($html);
            
            // Extrai iframe do player
            $iframeSrc = $this->extractAttr($crawler, 'iframe.meu-player', 'src');
            
            if (!$iframeSrc) {
                return $this->handleError('Player não encontrado');
            }
            
            // Converte URL relativa
            if (strpos($iframeSrc, '//') === 0) {
                $iframeSrc = 'https:' . $iframeSrc;
            } elseif (!preg_match('/^https?:\/\//', $iframeSrc)) {
                $iframeSrc = $this->absoluteUrl($iframeSrc);
            }
            
            // Carrega página do player
            $playerHtml = $this->get($iframeSrc);
            
            if (!$playerHtml) {
                return $this->handleError('Falha ao carregar player');
            }
            
            $playerCrawler = $this->crawl($playerHtml);
            $videos = [];
            
            // Tenta 720p primeiro
            $source720 = $this->extractAttr($playerCrawler, 'source[label="720p"]', 'src');
            if ($source720) {
                $videos[] = [
                    'url' => $source720,
                    'quality' => '720p',
                    'referer' => $iframeSrc
                ];
            }
            
            // Tenta 360p como fallback
            $source360 = $this->extractAttr($playerCrawler, 'source[label="360p"]', 'src');
            if ($source360) {
                $videos[] = [
                    'url' => $source360,
                    'quality' => '360p',
                    'referer' => $iframeSrc
                ];
            }
            
            if (empty($videos)) {
                return $this->handleError('Vídeo não encontrado');
            }

            Cache::set($cacheKey, $videos);
            return $this->success($videos);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar vídeo', $e);
        }
    }

    /**
     * Verifica se URL já existe no array
     */
    private function isDuplicate(array $items, string $url): bool
    {
        foreach ($items as $item) {
            if ($item['url'] === $url) {
                return true;
            }
        }
        return false;
    }
}
