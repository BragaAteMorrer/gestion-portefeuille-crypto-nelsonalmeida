<?php

namespace App\Controller;

use App\Entity\PortfolioEntry;
use App\Entity\Transaction;
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

#[Route('/api/transactions', name: 'api_transactions_')]
#[IsGranted('ROLE_USER')]
class TransactionController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(TransactionRepository $repository): JsonResponse
    {
        $transactions = $repository->findForUser($this->getUser());

        $items = array_map(static function (Transaction $transaction): array {
            return [
                'id' => $transaction->getId(),
                'symbol' => $transaction->getSymbol(),
                'side' => $transaction->getSide(),
                'quantity' => (float) $transaction->getQuantity(),
                'price' => (float) $transaction->getPrice(),
                'createdAt' => $transaction->getCreatedAt()->format(DateTimeImmutable::ATOM),
            ];
        }, $transactions);

        return $this->json(['items' => $items]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        PortfolioEntryRepository $entryRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $symbol = trim($data['symbol'] ?? '');
        $side = strtolower(trim($data['side'] ?? 'buy'));
        $quantity = (float) ($data['quantity'] ?? 0);
        $price = (float) ($data['price'] ?? 0);

        if ($symbol === '' || !in_array($side, ['buy', 'sell'], true) || $quantity <= 0 || $price < 0) {
            return $this->json(['message' => 'Champs invalides'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();

        $transaction = new Transaction();
        $transaction->setUser($user);
        $transaction->setSymbol($symbol);
        $transaction->setSide($side);
        $transaction->setQuantity((string) $quantity);
        $transaction->setPrice((string) $price);

        $entityManager->persist($transaction);

        $entry = $entryRepository->findOneBy(['user' => $user, 'symbol' => strtoupper($symbol)]);

        if ($side === 'buy') {
            if (!$entry) {
                $entry = new PortfolioEntry();
                $entry->setUser($user);
                $entry->setSymbol($symbol);
                $entry->setLabel(strtoupper($symbol));
                $entityManager->persist($entry);
            }
            $currentQty = (float) $entry->getQuantity();
            $currentAvg = (float) $entry->getAveragePrice();
            $newQty = $currentQty + $quantity;
            $newAvg = $newQty > 0 ? (($currentAvg * $currentQty) + ($price * $quantity)) / $newQty : $price;
            $entry->setQuantity((string) $newQty);
            $entry->setAveragePrice((string) $newAvg);
        } else {
            if (!$entry) {
                return $this->json(['message' => 'Aucune position à vendre pour ce symbole'], Response::HTTP_BAD_REQUEST);
            }
            $currentQty = (float) $entry->getQuantity();
            if ($quantity > $currentQty) {
                return $this->json(['message' => 'Quantité à vendre supérieure à la position détenue'], Response::HTTP_BAD_REQUEST);
            }
            $newQty = $currentQty - $quantity;
            $entry->setQuantity((string) $newQty);
        }

        $entry->touch();
        $entityManager->flush();

        return $this->json(['message' => 'Transaction enregistrée']);
    }
}
