<?php
namespace AnimeScraper\Scrapers;

use AnimeScraper\Utils\Cache;

/**
 * Scraper para AnimeFire
 * Corrige problema de crash com muitos episódios usando paginação
 */
class AnimeFireScraper extends BaseScraper
{
    private const EPISODES_PER_BATCH = 50;

    public function __construct()
    {
        parent::__construct('https://animefire.plus');
        $this->headers['Referer'] = $this->baseUrl;
    }

    /**
     * Lista animes populares
     */
    public function getPopular(int $page = 1): array
    {
        $cacheKey = Cache::generateKey('animefire_popular', ['page' => $page]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $url = "{$this->baseUrl}/top-animes/{$page}";
            $html = $this->get($url);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar página');
            }

            $crawler = $this->crawl($html);
            $animes = [];

            $crawler->filter('article.cardUltimosEps > a')->each(function ( $node) use (&$animes) {
                $title = $this->extractText($node, 'h3.animeTitle');
                $href = $node->attr('href');
                $thumbnail = $this->extractAttr($node, 'img', 'data-src');

                if ($title && $href) {
                    // Corrige URL para página de todos os episódios
                    if (preg_match('/\/\d+$/', $href)) {
                        $href = preg_replace('/\/\d+$/', '-todos-os-episodios', $href);
                    }

                    $animes[] = [
                        'title' => $this->sanitize($title),
                        'url' => $this->absoluteUrl($href),
                        'thumbnail' => $thumbnail ? $this->absoluteUrl($thumbnail) : null,
                        'type' => 'anime'
                    ];
                }
            });

            Cache::set($cacheKey, $animes);
            return $this->success($animes);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar animes populares', $e);
        }
    }

    /**
     * Lista lançamentos recentes
     */
    public function getLatest(int $page = 1): array
    {
        $cacheKey = Cache::generateKey('animefire_latest', ['page' => $page]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $url = "{$this->baseUrl}/home/{$page}";
            $html = $this->get($url);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar página');
            }

            $crawler = $this->crawl($html);
            $animes = [];

            $crawler->filter('article.cardUltimosEps > a')->each(function ( $node) use (&$animes) {
                $title = $this->extractText($node, 'h3.animeTitle');
                $href = $node->attr('href');
                $thumbnail = $this->extractAttr($node, 'img', 'data-src');

                if ($title && $href) {
                    if (preg_match('/\/\d+$/', $href)) {
                        $href = preg_replace('/\/\d+$/', '-todos-os-episodios', $href);
                    }

                    $animes[] = [
                        'title' => $this->sanitize($title),
                        'url' => $this->absoluteUrl($href),
                        'thumbnail' => $thumbnail ? $this->absoluteUrl($thumbnail) : null,
                        'type' => 'anime'
                    ];
                }
            });

            Cache::set($cacheKey, $animes);
            return $this->success($animes);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar lançamentos', $e);
        }
    }

    /**
     * Lista animes dublados
     */
    public function getDubbed(int $page = 1): array
    {
        $result = $this->getPopular($page);
        
        if (!$result['success']) {
            return $result;
        }

        $dubbed = array_filter($result['data'], function($anime) {
            return stripos($anime['title'], 'dublado') !== false;
        });

        return $this->success(array_values($dubbed));
    }

    /**
     * Busca animes
     */
    public function search(string $query, int $page = 1): array
    {
        $cacheKey = Cache::generateKey('animefire_search', ['query' => $query, 'page' => $page]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $q = strtolower(str_replace(' ', '-', trim($query)));
            $url = "{$this->baseUrl}/pesquisar/{$q}/{$page}";
            $html = $this->get($url);
            
            if (!$html) {
                return $this->handleError('Falha ao realizar busca');
            }

            $crawler = $this->crawl($html);
            $animes = [];

            $crawler->filter('article.cardUltimosEps > a')->each(function ( $node) use (&$animes) {
                $title = $this->extractText($node, 'h3.animeTitle');
                $href = $node->attr('href');
                $thumbnail = $this->extractAttr($node, 'img', 'data-src');

                if ($title && $href) {
                    if (preg_match('/\/\d+$/', $href)) {
                        $href = preg_replace('/\/\d+$/', '-todos-os-episodios', $href);
                    }

                    $animes[] = [
                        'title' => $this->sanitize($title),
                        'url' => $this->absoluteUrl($href),
                        'thumbnail' => $thumbnail ? $this->absoluteUrl($thumbnail) : null,
                        'type' => 'anime'
                    ];
                }
            });

            Cache::set($cacheKey, $animes);
            return $this->success($animes);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar animes', $e);
        }
    }

    /**
     * Detalhes do anime
     */
    public function getDetails(string $animeUrl): array
    {
        $cacheKey = Cache::generateKey('animefire_details', ['url' => $animeUrl]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $html = $this->get($animeUrl);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar detalhes');
            }

            $crawler = $this->crawl($html);
            
            $title = $this->extractText($crawler, 'div.div_anime_names h1');
            $thumbnail = $this->extractAttr($crawler, 'div.sub_anime_img img', 'data-src');
            $description = $this->extractText($crawler, 'div.divSinopse span');
            
            // Gêneros
            $genres = [];
            $crawler->filter('a.spanGeneros')->each(function ( $node) use (&$genres) {
                $genres[] = $this->sanitize($node->text());
            });

            $details = [
                'title' => $this->sanitize($title ?? 'Sem título'),
                'url' => $animeUrl,
                'thumbnail' => $thumbnail ? $this->absoluteUrl($thumbnail) : null,
                'description' => $description ? $this->sanitize($description) : null,
                'genres' => $genres,
                'type' => 'anime'
            ];

            Cache::set($cacheKey, $details);
            return $this->success($details);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar detalhes', $e);
        }
    }

    /**
     * Lista episódios com paginação (FIX para crash)
     */
    public function getEpisodes(string $animeUrl, int $batch = 1): array
    {
        $cacheKey = Cache::generateKey('animefire_episodes', ['url' => $animeUrl, 'batch' => $batch]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $html = $this->get($animeUrl);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar episódios');
            }

            $crawler = $this->crawl($html);
            $allEpisodes = [];

            $crawler->filter('div.div_video_list > a')->each(function ( $node) use (&$allEpisodes) {
                $href = $node->attr('href');
                $name = $this->sanitize($node->text());
                
                // Extrai número do episódio
                preg_match('/\/(\d+(?:\.\d+)?)$/', $href, $matches);
                $episodeNumber = $matches[1] ?? 0;

                $allEpisodes[] = [
                    'name' => $name,
                    'url' => $this->absoluteUrl($href),
                    'episode_number' => (float) $episodeNumber
                ];
            });

            // Inverte para ordem crescente
            $allEpisodes = array_reverse($allEpisodes);
            
            // Paginação em batches
            $totalEpisodes = count($allEpisodes);
            $totalBatches = ceil($totalEpisodes / self::EPISODES_PER_BATCH);
            $offset = ($batch - 1) * self::EPISODES_PER_BATCH;
            $episodes = array_slice($allEpisodes, $offset, self::EPISODES_PER_BATCH);

            $result = [
                'episodes' => $episodes,
                'pagination' => [
                    'current_batch' => $batch,
                    'total_batches' => $totalBatches,
                    'episodes_per_batch' => self::EPISODES_PER_BATCH,
                    'total_episodes' => $totalEpisodes,
                    'has_next' => $batch < $totalBatches
                ]
            ];

            Cache::set($cacheKey, $result);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar episódios', $e);
        }
    }

    /**
     * Obtém URL do vídeo
     */
    public function getVideoUrl(string $episodeUrl): array
    {
        $cacheKey = Cache::generateKey('animefire_video', ['url' => $episodeUrl]);
        $cached = Cache::get($cacheKey);
        if ($cached) return $this->success($cached);

        try {
            $html = $this->get($episodeUrl);
            
            if (!$html) {
                return $this->handleError('Falha ao carregar episódio');
            }

            $crawler = $this->crawl($html);
            
            // Tenta extrair do video tag
            $videoSrc = $this->extractAttr($crawler, 'video#my-video', 'data-video-src');
            
            if ($videoSrc) {
                $videoData = $this->get($videoSrc);
                if ($videoData) {
                    $json = json_decode($videoData, true);
                    if (isset($json['data']) && is_array($json['data'])) {
                        $videos = [];
                        foreach ($json['data'] as $item) {
                            if (isset($item['src']) && isset($item['label'])) {
                                $videos[] = [
                                    'url' => $item['src'],
                                    'quality' => $item['label']
                                ];
                            }
                        }
                        
                        if (!empty($videos)) {
                            Cache::set($cacheKey, $videos);
                            return $this->success($videos);
                        }
                    }
                }
            }
            
            // Fallback: tenta iframe
            $iframeSrc = $this->extractAttr($crawler, 'div#div_video iframe', 'src');
            if ($iframeSrc) {
                $iframeHtml = $this->get($this->absoluteUrl($iframeSrc));
                if ($iframeHtml && preg_match('/play_url"\s*:\s*"([^"]+)/', $iframeHtml, $matches)) {
                    $videos = [
                        [
                            'url' => $matches[1],
                            'quality' => 'Default'
                        ]
                    ];
                    Cache::set($cacheKey, $videos);
                    return $this->success($videos);
                }
            }

            return $this->handleError('Vídeo não encontrado');
        } catch (\Exception $e) {
            return $this->handleError('Erro ao buscar vídeo', $e);
        }
    }
}
