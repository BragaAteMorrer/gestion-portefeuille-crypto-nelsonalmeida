<?php

namespace App\Service;

use App\Entity\PriceQuote;
use App\Repository\PriceQuoteRepository;
use App\Entity\PriceHistory;
use App\Repository\PriceHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PriceService
{
    // CoinCap endpoints
    private const COINCAP_ASSETS = 'https://api.coincap.io/v2/assets';
    private const COINCAP_RATE_V2 = 'https://api.coincap.io/v2/rates/%s';
    private const COINCAP_RATE_V3 = 'https://rest.coincap.io/v3/rates/%s';
    private const COINCAP_HISTORY_V3 = 'https://rest.coincap.io/v3/assets/%s/history';
    // CoinGecko fallback
    private const COINGECKO_API = 'https://api.coingecko.com/api/v3/simple/price';
    private const COINCAP_HISTORY = 'https://api.coincap.io/v2/assets/%s/history';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PriceQuoteRepository $priceQuoteRepository,
        private readonly PriceHistoryRepository $priceHistoryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheItemPoolInterface $cachePool
    ) {
    }

    /**
     * @param string[] $symbols
     * @return array<string, float>
     */
    public function getPrices(array $symbols): array
    {
        $symbols = array_values(array_unique(array_map('strtoupper', array_filter($symbols))));
        if (empty($symbols)) {
            return [];
        }

        $cacheKey = 'prices_' . md5(implode(',', $symbols));
        $cached = $this->cachePool->getItem($cacheKey);
        if ($cached->isHit()) {
            return $cached->get();
        }

        $cacheQuotes = $this->priceQuoteRepository->findBySymbolsIndexed($symbols);
        $prices = [];

        // Try remote API
        $remotePrices = $this->fetchFromApi($symbols);
        foreach ($symbols as $symbol) {
            if (isset($remotePrices[$symbol])) {
                $prices[$symbol] = $remotePrices[$symbol];
                $this->upsertCache($symbol, $remotePrices[$symbol]);
            } elseif (isset($cacheQuotes[$symbol])) {
                $prices[$symbol] = (float) $cacheQuotes[$symbol]->getPrice();
            } else {
                $prices[$symbol] = 0.0;
            }
        }

        // Backfill history hour by hour since last known point
        $this->backfillHistory($symbols);

        // Store in cache for 60s
        $cached->set($prices);
        $cached->expiresAfter(60);
        $this->cachePool->save($cached);

        return $prices;
    }

    /**
     * @param string[] $symbols
     * @return array<string, float>
     */
    private function fetchFromApi(array $symbols): array
    {
        $prices = [];
        // Map symbol => CoinCap id
        $map = [
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
            'BNB' => 'binance-coin',
            'SOL' => 'solana',
            'XRP' => 'xrp',
            'ADA' => 'cardano',
            'DOGE' => 'dogecoin',
            'DOT' => 'polkadot',
            'MATIC' => 'polygon',
            'AVAX' => 'avalanche',
            'LTC' => 'litecoin',
            'LINK' => 'chainlink',
            'TRX' => 'tron',
            'ATOM' => 'cosmos',
            'SHIB' => 'shiba-inu',
            'UNI' => 'uniswap',
            'ETC' => 'ethereum-classic',
            'BCH' => 'bitcoin-cash',
            'XLM' => 'stellar',
            'APT' => 'aptos',
            'NEAR' => 'near-protocol',
            'ARB' => 'arbitrum',
        ];

        $ids = [];
        foreach ($symbols as $symbol) {
            $ids[$symbol] = $map[$symbol] ?? strtolower($symbol);
        }

        // CoinCap autorise jusqu'à 200 ids
        $headers = [];
        $apiKey = getenv('COINCAP_API_KEY') ?: ($_ENV['COINCAP_API_KEY'] ?? null);
        if ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $assetsData = null;
        try {
            $response = $this->httpClient->request('GET', self::COINCAP_ASSETS, [
                'query' => [
                    'ids' => implode(',', array_values($ids)),
                ],
                'timeout' => 6,
                'headers' => $headers,
            ]);
            $assetsData = $response->toArray(false);
        } catch (\Throwable $e) {
            $assetsData = null;
        }

        $eurRate = $this->fetchRateUsd('EUR', $headers);

        if ($assetsData && isset($assetsData['data']) && is_array($assetsData['data'])) {
            foreach ($assetsData['data'] as $asset) {
                $id = $asset['id'] ?? '';
                $priceUsd = isset($asset['priceUsd']) ? (float) $asset['priceUsd'] : null;
                if (!$id || $priceUsd === null) {
                    continue;
                }
                $symbol = array_search($id, $ids, true);
                if ($symbol === false) {
                    $symbol = strtoupper($asset['symbol'] ?? '');
                }
                if (!$symbol) {
                    continue;
                }
                $priceEur = $eurRate > 0 ? $priceUsd / $eurRate : $priceUsd;
                $prices[strtoupper($symbol)] = $priceEur;
            }
        }

        // Fallback to CoinGecko if CoinCap failed or returned nothing
        $missing = array_diff($symbols, array_keys($prices));
        if (!empty($missing)) {
            // Map symbol => CoinGecko id
            $geckoMap = [
                'BTC' => 'bitcoin',
                'ETH' => 'ethereum',
                'BNB' => 'binancecoin',
                'SOL' => 'solana',
                'XRP' => 'ripple',
                'ADA' => 'cardano',
                'DOGE' => 'dogecoin',
                'DOT' => 'polkadot',
                'MATIC' => 'matic-network',
                'AVAX' => 'avalanche-2',
                'LTC' => 'litecoin',
                'LINK' => 'chainlink',
                'TRX' => 'tron',
                'ATOM' => 'cosmos',
                'SHIB' => 'shiba-inu',
                'UNI' => 'uniswap',
                'ETC' => 'ethereum-classic',
                'BCH' => 'bitcoin-cash',
                'XLM' => 'stellar',
                'APT' => 'aptos',
                'NEAR' => 'near',
                'ARB' => 'arbitrum',
            ];
            $geckoIds = [];
            foreach ($missing as $symbol) {
                $geckoIds[$symbol] = $geckoMap[$symbol] ?? strtolower($symbol);
            }
            try {
                $response = $this->httpClient->request('GET', self::COINGECKO_API, [
                    'query' => [
                        'ids' => implode(',', array_values($geckoIds)),
                        'vs_currencies' => 'eur',
                    ],
                    'timeout' => 6,
                ]);
                $gData = $response->toArray(false);
                foreach ($geckoIds as $symbol => $id) {
                    if (isset($gData[$id]['eur'])) {
                        $prices[$symbol] = (float) $gData[$id]['eur'];
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $prices;
    }

    private function upsertCache(string $symbol, float $price): void
    {
        $symbol = strtoupper($symbol);
        $existing = $this->priceQuoteRepository->findOneBy(['symbol' => $symbol]);
        if (!$existing) {
            $existing = new PriceQuote();
            $existing->setSymbol($symbol);
        }
        $existing->setPrice((string) $price);
        $existing->setCurrency('EUR');
        $this->entityManager->persist($existing);
        // record history
        $history = new PriceHistory();
        $history->setSymbol($symbol);
        $history->setPrice((string) $price);
        $history->setCurrency('EUR');
        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }

    /**
     * Backfill hourly history from last collected point to now.
     * @param string[] $symbols
     */
    private function backfillHistory(array $symbols): void
    {
        if (empty($symbols)) {
            return;
        }

        $headers = [];
        $apiKey = getenv('COINCAP_API_KEY') ?: ($_ENV['COINCAP_API_KEY'] ?? null);
        if ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        // reuse map id
        $map = [
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
            'BNB' => 'binance-coin',
            'SOL' => 'solana',
            'XRP' => 'xrp',
            'ADA' => 'cardano',
            'DOGE' => 'dogecoin',
            'DOT' => 'polkadot',
            'MATIC' => 'polygon',
            'AVAX' => 'avalanche',
            'LTC' => 'litecoin',
            'LINK' => 'chainlink',
            'TRX' => 'tron',
            'ATOM' => 'cosmos',
            'SHIB' => 'shiba-inu',
            'UNI' => 'uniswap',
            'ETC' => 'ethereum-classic',
            'BCH' => 'bitcoin-cash',
            'XLM' => 'stellar',
            'APT' => 'aptos',
            'NEAR' => 'near-protocol',
            'ARB' => 'arbitrum',
        ];

        $eurRate = $this->fetchRateUsd('EUR', $headers);
        $now = new \DateTimeImmutable();

        foreach ($symbols as $symbol) {
            $symbol = strtoupper($symbol);
            $last = $this->priceHistoryRepository->findLastForSymbol($symbol);
            $start = $last ? $last->getCollectedAt()->modify('+1 hour') : $now->modify('-24 hours');
            if ($start >= $now) {
                continue;
            }

            $id = $map[$symbol] ?? strtolower($symbol);
            $startMs = (int) ($start->getTimestamp() * 1000);
            $endMs = (int) ($now->getTimestamp() * 1000);

            try {
                $response = $this->httpClient->request('GET', sprintf(self::COINCAP_HISTORY, $id), [
                    'query' => [
                        'interval' => 'h1',
                        'start' => $startMs,
                        'end' => $endMs,
                    ],
                    'timeout' => 8,
                    'headers' => $headers,
                ]);
                $data = $response->toArray(false);
                if (!isset($data['data']) || !is_array($data['data'])) {
                    continue;
                }
                foreach ($data['data'] as $point) {
                    if (!isset($point['priceUsd'], $point['time'])) {
                        continue;
                    }
                    $collectedAt = new \DateTimeImmutable('@' . ((int) floor(((int) $point['time']) / 1000)));
                    // avoid duplicates
                    if ($last && $collectedAt <= $last->getCollectedAt()) {
                        continue;
                    }
                    if ($this->priceHistoryRepository->existsAt($symbol, $collectedAt)) {
                        continue;
                    }
                    $priceUsd = (float) $point['priceUsd'];
                    $priceEur = $eurRate > 0 ? $priceUsd / $eurRate : $priceUsd;

                    $history = new PriceHistory();
                    $history->setSymbol($symbol);
                    $history->setPrice((string) $priceEur);
                    $history->setCurrency('EUR');
                    $history->setCollectedAt($collectedAt);
                    $this->entityManager->persist($history);
                }
                $this->entityManager->flush();
            } catch (\Throwable $e) {
                // ignore backfill errors
            }
        }
    }

    public function fillHistoryRange(array $symbols, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        if ($start >= $end) {
            return;
        }

        $headers = [];
        $apiKey = getenv('COINCAP_API_KEY') ?: ($_ENV['COINCAP_API_KEY'] ?? null);
        if ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $map = [
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
            'BNB' => 'binance-coin',
            'SOL' => 'solana',
            'XRP' => 'xrp',
            'ADA' => 'cardano',
            'DOGE' => 'dogecoin',
            'DOT' => 'polkadot',
            'MATIC' => 'polygon',
            'AVAX' => 'avalanche',
            'LTC' => 'litecoin',
            'LINK' => 'chainlink',
            'TRX' => 'tron',
            'ATOM' => 'cosmos',
            'SHIB' => 'shiba-inu',
            'UNI' => 'uniswap',
            'ETC' => 'ethereum-classic',
            'BCH' => 'bitcoin-cash',
            'XLM' => 'stellar',
            'APT' => 'aptos',
            'NEAR' => 'near-protocol',
            'ARB' => 'arbitrum',
        ];

        $eurRate = $this->fetchRateUsd('EUR', $headers);

        foreach ($symbols as $symbol) {
            $symbol = strtoupper($symbol);
            $id = $map[$symbol] ?? strtolower($symbol);

            // CoinCap peut limiter la plage, on segmente par 30 jours
            $chunkStart = $start;
            $forceDaily = $start < (new \DateTimeImmutable())->modify('-60 days');
            while ($chunkStart < $end) {
                $chunkEnd = $chunkStart->modify('+30 days');
                if ($chunkEnd > $end) {
                    $chunkEnd = $end;
                }
                $interval = $forceDaily ? 'd1' : 'h1';
                $startMs = (int) ($chunkStart->getTimestamp() * 1000);
                $endMs = (int) ($chunkEnd->getTimestamp() * 1000);

                $data = $this->fetchHistoryChunk($id, $interval, $startMs, $endMs, $headers);
                // Si on n'a rien en h1 et pas en mode daily forcé, retenter en d1
                if (empty($data) && !$forceDaily) {
                    $data = $this->fetchHistoryChunk($id, 'd1', $startMs, $endMs, $headers);
                }

                foreach ($data as $point) {
                    if (!isset($point['priceUsd'], $point['time'])) {
                        continue;
                    }
                    $collectedAt = new \DateTimeImmutable('@' . ((int) floor(((int) $point['time']) / 1000)));
                    if ($this->priceHistoryRepository->existsAt($symbol, $collectedAt)) {
                        continue;
                    }
                    $priceUsd = (float) $point['priceUsd'];
                    $priceEur = $eurRate > 0 ? $priceUsd / $eurRate : $priceUsd;

                    $history = new PriceHistory();
                    $history->setSymbol($symbol);
                    $history->setPrice((string) $priceEur);
                    $history->setCurrency('EUR');
                    $history->setCollectedAt($collectedAt);
                    $this->entityManager->persist($history);
                }
                $this->entityManager->flush();
                $chunkStart = $chunkEnd;
            }
        }
    }

    public function fetchRateUsd(string $currency, array $headers = []): float
    {
        // rateUsd = USD for 1 unit of currency
        $currency = strtoupper($currency);
        $slugMap = [
            'EUR' => 'euro',
            'USD' => 'united-states-dollar',
            'JPY' => 'japanese-yen',
            'GBP' => 'british-pound-sterling',
            'AUD' => 'australian-dollar',
            'CAD' => 'canadian-dollar',
            'CHF' => 'swiss-franc',
            'CNY' => 'chinese-yuan-renminbi',
            'SEK' => 'swedish-krona',
            'NZD' => 'new-zealand-dollar',
        ];
        $slug = $slugMap[$currency] ?? strtolower($currency);

        $rate = 1.0;

        // Try v3
        try {
            $rateResp = $this->httpClient->request('GET', sprintf(self::COINCAP_RATE_V3, $slug), ['timeout' => 6, 'headers' => $headers]);
            $rateData = $rateResp->toArray(false);
            if (isset($rateData['data']['rateUsd'])) {
                $rate = (float) $rateData['data']['rateUsd'];
                return $rate > 0 ? $rate : 1.0;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Fallback v2
        try {
            $rateResp = $this->httpClient->request('GET', sprintf(self::COINCAP_RATE_V2, $slug), ['timeout' => 6, 'headers' => $headers]);
            $rateData = $rateResp->toArray(false);
            if (isset($rateData['data']['rateUsd'])) {
                $rate = (float) $rateData['data']['rateUsd'];
                return $rate > 0 ? $rate : 1.0;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return $rate;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function fetchHistoryChunk(string $id, string $interval, int $startMs, int $endMs, array $headers): array
    {
        try {
            $response = $this->httpClient->request('GET', sprintf(self::COINCAP_HISTORY_V3, $id), [
                'query' => [
                    'interval' => $interval,
                    'start' => $startMs,
                    'end' => $endMs,
                ],
                'timeout' => 12,
                'headers' => $headers,
            ]);
            $data = $response->toArray(false);
            if (isset($data['data']) && is_array($data['data'])) {
                return $data['data'];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return [];
    }
}






