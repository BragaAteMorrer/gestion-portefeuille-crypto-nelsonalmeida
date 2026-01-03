<?php

namespace App\Controller;

use App\Repository\PriceQuoteRepository;
use App\Repository\PriceHistoryRepository;
use App\Service\PriceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/prices', name: 'api_prices_')]
#[IsGranted('ROLE_USER')]
class PriceController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        Request $request,
        PriceService $priceService,
        PriceQuoteRepository $quoteRepository,
        PriceHistoryRepository $historyRepository,
        HttpClientInterface $httpClient
    ): JsonResponse
    {
        $symbolsParam = (string) $request->query->get('symbols', '');
        $symbols = array_values(array_filter(array_map('strtoupper', preg_split('/[,\s\+]+/', $symbolsParam) ?: [])));
        if (empty($symbols)) {
            $symbols = ['BTC', 'ETH', 'BNB', 'SOL', 'ADA', 'XRP', 'DOGE', 'AVAX', 'MATIC', 'LTC', 'LINK', 'TRX', 'ATOM', 'SHIB', 'UNI', 'ETC', 'BCH', 'XLM', 'APT', 'NEAR', 'ARB'];
        }
        $currency = strtoupper((string) $request->query->get('currency', 'EUR'));
        // Autoriser tout code ISO 4217 de 3 lettres
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'EUR';
        }

        $priceService->getPrices($symbols); // This will update the cache
        $quotes = $quoteRepository->findBySymbolsIndexed($symbols);

        // FX conversion EUR -> target via exchangerate.host
        $fx = 1.0;
        if ($currency !== 'EUR') {
            try {
                $fxResp = $httpClient->request('GET', 'https://api.exchangerate.host/latest', [
                    'query' => [
                        'base' => 'EUR',
                        'symbols' => $currency,
                    ],
                    'timeout' => 6,
                ])->toArray(false);
                if (isset($fxResp['rates'][$currency]) && (float) $fxResp['rates'][$currency] > 0) {
                    $fx = (float) $fxResp['rates'][$currency];
                }
            } catch (\Throwable $e) {
                $fx = 1.0;
            }
        }
        $convert = function (float $priceEur) use ($currency, $fx): float {
            if ($currency === 'EUR') {
                return $priceEur;
            }
            return $priceEur * $fx;
        };

        $items = [];
        $now = new \DateTimeImmutable();
        $since24h = $now->modify('-24 hours');
        foreach ($symbols as $symbol) {
            $quote = $quotes[$symbol] ?? null;
            if ($quote) {
                $changeAbsEur = null;
                $changePct = null;
                $rows = $historyRepository->findSince($symbol, $since24h, 200);
                if (!empty($rows)) {
                    $oldest = $rows[array_key_last($rows)];
                    $pastEur = (float) $oldest->getPrice();
                    $currentEur = (float) $quote->getPrice();
                    $changeAbsEur = $currentEur - $pastEur;
                    if ($pastEur > 0) {
                        $changePct = ($changeAbsEur / $pastEur) * 100;
                    }
                }
                $items[] = [
                    'symbol' => $quote->getSymbol(),
                    'price' => $convert((float) $quote->getPrice()),
                    'currency' => $currency,
                    'updatedAt' => $quote->getUpdatedAt()->format(\DateTimeImmutable::ATOM),
                    'change24h' => $changeAbsEur !== null ? $convert($changeAbsEur) : null,
                    'change24hPct' => $changePct,
                ];
            } else {
                $items[] = [
                    'symbol' => $symbol,
                    'price' => 0.0,
                    'currency' => 'EUR',
                    'updatedAt' => null,
                ];
            }
        }

        return $this->json(['items' => $items]);
    }

    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(
        Request $request,
        PriceHistoryRepository $historyRepository,
        PriceService $priceService,
        HttpClientInterface $httpClient
    ): JsonResponse {
        $symbol = strtoupper((string) $request->query->get('symbol', 'BTC'));
        $limit = (int) $request->query->get('limit', 100);
        $limit = max(1, min($limit, 10000));
        $range = (string) $request->query->get('range', '');
        $startParam = (string) $request->query->get('start', '');
        $currency = strtoupper((string) $request->query->get('currency', 'EUR'));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'EUR';
        }

        // Force a refresh/backfill before reading history to populate recent heures manquantes
        $priceService->getPrices([$symbol]);

        $now = new \DateTimeImmutable();
        $since = null;
        $map = [
            '1h' => '-1 hour',
            '6h' => '-6 hours',
            '1d' => '-1 day',
            '1w' => '-7 days',
            '2w' => '-14 days',
            '1m' => '-1 month',
            '6m' => '-6 months',
            '1y' => '-1 year',
            'all' => null,
        ];
        if (isset($map[$range]) && $map[$range] !== null) {
            $since = $now->modify($map[$range]);
        }

        // If explicit start date provided (YYYY-MM-DD), fill history hourly from that date to now
        if ($startParam) {
            try {
                $startDate = new \DateTimeImmutable($startParam);
                $priceService->fillHistoryRange([$symbol], $startDate, $now);
                $since = $startDate;
            } catch (\Throwable $e) {
                // ignore invalid date
            }
        }

        if ($since) {
            $rows = $historyRepository->findSince($symbol, $since, $limit);
        } else {
            $rows = $historyRepository->findRecent($symbol, $limit);
        }

        // FX conversion EUR -> target via exchangerate.host
        $fx = 1.0;
        if ($currency !== 'EUR') {
            try {
                $fxResp = $httpClient->request('GET', 'https://api.exchangerate.host/latest', [
                    'query' => [
                        'base' => 'EUR',
                        'symbols' => $currency,
                    ],
                    'timeout' => 6,
                ])->toArray(false);
                if (isset($fxResp['rates'][$currency]) && (float) $fxResp['rates'][$currency] > 0) {
                    $fx = (float) $fxResp['rates'][$currency];
                }
            } catch (\Throwable $e) {
                $fx = 1.0;
            }
        }
        $convert = function (float $priceEur) use ($currency, $fx): float {
            if ($currency === 'EUR') {
                return $priceEur;
            }
            return $priceEur * $fx;
        };

        $items = array_map(function ($row) use ($convert, $currency): array {
            return [
                'symbol' => $row->getSymbol(),
                'price' => $convert((float) $row->getPrice()),
                'currency' => $currency,
                'collectedAt' => $row->getCollectedAt()->format(\DateTimeImmutable::ATOM),
            ];
        }, $rows);

        return $this->json(['items' => array_reverse($items)]);
    }

    #[Route('/backfill', name: 'backfill', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function backfill(Request $request, PriceService $priceService): JsonResponse
    {
        $symbolsParam = (string) $request->request->get('symbols', '');
        $symbols = array_values(array_filter(array_map('strtoupper', preg_split('/[,\s\+]+/', $symbolsParam) ?: [])));
        if (empty($symbols)) {
            $symbols = ['BTC','ETH','BNB','SOL','ADA','XRP','DOGE','AVAX','MATIC','LTC','LINK','TRX','ATOM','SHIB','UNI','ETC','BCH','XLM','APT','NEAR','ARB'];
        }

        $startParam = (string) $request->request->get('start', '');
        try {
            $start = $startParam ? new \DateTimeImmutable($startParam) : (new \DateTimeImmutable())->modify('-1 year');
        } catch (\Throwable $e) {
            $start = (new \DateTimeImmutable())->modify('-1 year');
        }
        $end = new \DateTimeImmutable();

        $priceService->fillHistoryRange($symbols, $start, $end);

        return $this->json([
            'message' => 'Backfill déclenché',
            'symbols' => $symbols,
            'start' => $start->format(\DateTimeImmutable::ATOM),
            'end' => $end->format(\DateTimeImmutable::ATOM),
        ]);
    }
}
