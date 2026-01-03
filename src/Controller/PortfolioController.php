<?php

namespace App\Controller;

use App\Entity\PortfolioEntry;
use App\Service\PriceService;
use App\Repository\PortfolioEntryRepository;
use App\Repository\TransactionRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/portfolio', name: 'api_portfolio_')]
#[IsGranted('ROLE_USER')]
class PortfolioController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(PortfolioEntryRepository $repository, TransactionRepository $transactionRepository, PriceService $priceService): JsonResponse
    {
        $user = $this->getUser();
        $entries = $repository->findForUser($user);

        $symbols = array_map(static fn (PortfolioEntry $entry) => $entry->getSymbol(), $entries);
        $livePrices = $priceService->getPrices($symbols);

        $items = array_map(static function (PortfolioEntry $entry) use ($livePrices): array {
            $quantity = (float) $entry->getQuantity();
            $avgPrice = (float) $entry->getAveragePrice();
            $currentPrice = $livePrices[$entry->getSymbol()] ?? $avgPrice;
            return [
                'id' => $entry->getId(),
                'symbol' => $entry->getSymbol(),
                'label' => $entry->getLabel(),
                'quantity' => $quantity,
                'averagePrice' => $avgPrice,
                'currentPrice' => $currentPrice,
                'estimatedValue' => $quantity * $currentPrice,
                'updatedAt' => $entry->getUpdatedAt()->format(DateTimeImmutable::ATOM),
            ];
        }, $entries);

        $totalValue = array_sum(array_column($items, 'estimatedValue'));
        $totalInvested = array_sum(array_map(static function (array $item) {
            return $item['quantity'] * $item['averagePrice'];
        }, $items));

        $transactions = $transactionRepository->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $stateBySymbol = [];
        $realized = 0.0;
        foreach ($transactions as $tx) {
            $sym = $tx->getSymbol();
            $qty = (float) $tx->getQuantity();
            $price = (float) $tx->getPrice();
            $side = strtolower($tx->getSide());

            $state = $stateBySymbol[$sym] ?? ['qty' => 0.0, 'avg' => 0.0];

            if ($side === 'buy') {
                $newQty = $state['qty'] + $qty;
                $newAvg = $newQty > 0 ? (($state['avg'] * $state['qty']) + ($price * $qty)) / $newQty : $price;
                $state['qty'] = $newQty;
                $state['avg'] = $newAvg;
            } elseif ($side === 'sell') {
                $sellQty = min($qty, $state['qty']);
                if ($sellQty > 0) {
                    $realized += ($price - $state['avg']) * $sellQty;
                    $state['qty'] -= $sellQty;
                }
            }

            $stateBySymbol[$sym] = $state;
        }

        return $this->json([
            'items' => $items,
            'totals' => [
                'invested' => $totalInvested,
                'estimatedValue' => $totalValue,
                'realized' => $realized,
            ],
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $symbol = trim($data['symbol'] ?? '');
        $label = trim($data['label'] ?? $symbol);
        $quantity = (float) ($data['quantity'] ?? 0);
        $averagePrice = (float) ($data['averagePrice'] ?? 0);

        if ($symbol === '' || $quantity <= 0 || $averagePrice < 0) {
            return $this->json(['message' => 'Champs invalides'], Response::HTTP_BAD_REQUEST);
        }

        $entry = new PortfolioEntry();
        $entry->setUser($this->getUser());
        $entry->setSymbol($symbol);
        $entry->setLabel($label !== '' ? $label : strtoupper($symbol));
        $entry->setQuantity((string) $quantity);
        $entry->setAveragePrice((string) $averagePrice);

        $entityManager->persist($entry);
        $entityManager->flush();

        return $this->json(['message' => 'Ajouté au portefeuille', 'id' => $entry->getId()], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        PortfolioEntryRepository $repository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $entry = $repository->findOneForUser($id, $this->getUser());
        if (!$entry) {
            return $this->json(['message' => 'Ligne introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (isset($data['symbol'])) {
            $entry->setSymbol((string) $data['symbol']);
        }
        if (isset($data['label'])) {
            $entry->setLabel((string) $data['label']);
        }
        if (isset($data['quantity'])) {
            $entry->setQuantity((string) max(0, (float) $data['quantity']));
        }
        if (isset($data['averagePrice'])) {
            $entry->setAveragePrice((string) max(0, (float) $data['averagePrice']));
        }

        $entry->touch();
        $entityManager->flush();

        return $this->json(['message' => 'Mise à jour réussie']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        PortfolioEntryRepository $repository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $entry = $repository->findOneForUser($id, $this->getUser());
        if (!$entry) {
            return $this->json(['message' => 'Ligne introuvable'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($entry);
        $entityManager->flush();

        return $this->json([], Response::HTTP_NO_CONTENT);
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function reset(
        EntityManagerInterface $entityManager,
        PortfolioEntryRepository $repository,
        TransactionRepository $transactionRepository
    ): JsonResponse
    {
        $user = $this->getUser();
        $entries = $repository->findForUser($user);
        $transactions = $transactionRepository->findForUser($user, 100000);
        foreach ($entries as $entry) {
            $entityManager->remove($entry);
        }
        foreach ($transactions as $tx) {
            $entityManager->remove($tx);
        }
        $entityManager->flush();

        return $this->json(['message' => 'Portefeuille réinitialisé']);
    }
}
