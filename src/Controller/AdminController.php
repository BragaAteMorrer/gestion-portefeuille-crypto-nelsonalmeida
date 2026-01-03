<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\PortfolioEntryRepository;
use App\Repository\TransactionRepository;
use App\Service\PriceService;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin', name: 'api_admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/users', name: 'users', methods: ['GET'])]
    public function listUsers(
        UserRepository $userRepository,
        PortfolioEntryRepository $portfolioRepository,
        TransactionRepository $transactionRepository
    ): JsonResponse {
        $users = $userRepository->findAll();

        $payload = array_map(static function (User $user) use ($portfolioRepository, $transactionRepository): array {
            $portfolioCount = count($portfolioRepository->findForUser($user));
            $transactionCount = count($transactionRepository->findForUser($user, 9999));
            $roles = $user->getRoles();
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'displayName' => $user->getDisplayName(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'birthDate' => $user->getBirthDate()?->format(\DateTimeImmutable::ATOM),
                'roles' => $roles,
                'suspended' => in_array('ROLE_SUSPENDED', $roles, true),
                'createdAt' => $user->getCreatedAt()->format(\DateTimeImmutable::ATOM),
                'portfolioCount' => $portfolioCount,
                'transactionCount' => $transactionCount,
            ];
        }, $users);

        return $this->json(['users' => $payload]);
    }

    #[Route('/users/{id}/role', name: 'toggle_role', methods: ['PATCH'])]
    public function toggleRole(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $target = $userRepository->find($id);
        if (!$target) {
            return $this->json(['message' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $isAdmin = (bool) ($data['admin'] ?? false);
        $roles = $target->getRoles();
        if ($isAdmin && !in_array('ROLE_ADMIN', $roles, true)) {
            $roles[] = 'ROLE_ADMIN';
        }
        if (!$isAdmin) {
            $roles = array_values(array_filter($roles, static fn (string $r) => $r !== 'ROLE_ADMIN'));
        }
        $target->setRoles($roles);
        $entityManager->flush();

        return $this->json(['message' => 'Roles mis a jour', 'roles' => $target->getRoles()]);
    }

    #[Route('/users/{id}/suspend', name: 'toggle_suspend', methods: ['PATCH'])]
    public function toggleSuspend(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $target = $userRepository->find($id);
        if (!$target) {
            return $this->json(['message' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $suspended = (bool) ($data['suspended'] ?? false);
        $roles = $target->getRoles();

        if ($suspended && !in_array('ROLE_SUSPENDED', $roles, true)) {
            $roles[] = 'ROLE_SUSPENDED';
        } elseif (!$suspended) {
            $roles = array_values(array_filter($roles, static fn (string $r) => $r !== 'ROLE_SUSPENDED'));
        }

        $target->setRoles($roles);
        $entityManager->flush();

        return $this->json(['message' => 'Statut mis a jour', 'suspended' => in_array('ROLE_SUSPENDED', $roles, true)]);
    }

    #[Route('/users/{id}/profile', name: 'update_profile', methods: ['PATCH'])]
    public function updateProfileAdmin(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $target = $userRepository->find($id);
        if (!$target) {
            return $this->json(['message' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $displayName = isset($data['displayName']) ? trim((string) $data['displayName']) : null;
        $firstName = isset($data['firstName']) ? trim((string) $data['firstName']) : null;
        $lastName = isset($data['lastName']) ? trim((string) $data['lastName']) : null;
        $birthDateParam = isset($data['birthDate']) ? trim((string) $data['birthDate']) : null;
        $birthDate = null;
        if ($birthDateParam !== null && $birthDateParam !== '') {
            try {
                $birthDate = new \DateTimeImmutable($birthDateParam);
            } catch (\Throwable $e) {
                return $this->json(['message' => 'Date de naissance invalide'], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($displayName !== null && $displayName !== '') {
            $target->setDisplayName($displayName);
        }
        if ($firstName !== null) {
            $target->setFirstName($firstName !== '' ? $firstName : null);
        }
        if ($lastName !== null) {
            $target->setLastName($lastName !== '' ? $lastName : null);
        }
        if ($birthDateParam !== null) {
            $target->setBirthDate($birthDate);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Profil utilisateur mis a jour',
            'user' => [
                'id' => $target->getId(),
                'email' => $target->getEmail(),
                'displayName' => $target->getDisplayName(),
                'firstName' => $target->getFirstName(),
                'lastName' => $target->getLastName(),
                'birthDate' => $target->getBirthDate()?->format(\DateTimeImmutable::ATOM),
                'roles' => $target->getRoles(),
            ],
        ]);
    }

    #[Route('/users/{id}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(
        int $id,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $target = $userRepository->find($id);
        if (!$target) {
            return $this->json(['message' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($target);
        $entityManager->flush();

        return $this->json([], Response::HTTP_NO_CONTENT);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(
        UserRepository $userRepository,
        PortfolioEntryRepository $portfolioRepository,
        TransactionRepository $transactionRepository
    ): JsonResponse {
        $users = $userRepository->count([]);
        $portfolios = $portfolioRepository->count([]);
        $transactions = $transactionRepository->count([]);

        return $this->json([
            'users' => $users,
            'portfolioEntries' => $portfolios,
            'transactions' => $transactions,
        ]);
    }

    #[Route('/users/{id}/portfolio', name: 'user_portfolio', methods: ['GET'])]
    public function userPortfolio(
        int $id,
        UserRepository $userRepository,
        PortfolioEntryRepository $portfolioRepository,
        TransactionRepository $transactionRepository,
        PriceService $priceService
    ): JsonResponse {
        $target = $userRepository->find($id);
        if (!$target instanceof User) {
            return $this->json(['message' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $entries = $portfolioRepository->findForUser($target);
        $symbols = array_map(static fn (\App\Entity\PortfolioEntry $entry) => $entry->getSymbol(), $entries);
        $livePrices = $priceService->getPrices($symbols);

        $items = array_map(static function (\App\Entity\PortfolioEntry $entry) use ($livePrices): array {
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
                'updatedAt' => $entry->getUpdatedAt()->format(\DateTimeImmutable::ATOM),
            ];
        }, $entries);

        $totalValue = array_sum(array_column($items, 'estimatedValue'));
        $totalInvested = array_sum(array_map(static function (array $item) {
            return $item['quantity'] * $item['averagePrice'];
        }, $items));

        $transactions = $transactionRepository->findForUser($target, 500);
        $txPayload = array_map(static function (\App\Entity\Transaction $transaction): array {
            return [
                'id' => $transaction->getId(),
                'symbol' => $transaction->getSymbol(),
                'side' => $transaction->getSide(),
                'quantity' => (float) $transaction->getQuantity(),
                'price' => (float) $transaction->getPrice(),
                'createdAt' => $transaction->getCreatedAt()->format(\DateTimeImmutable::ATOM),
            ];
        }, $transactions);

        return $this->json([
            'user' => [
                'id' => $target->getId(),
                'email' => $target->getEmail(),
                'displayName' => $target->getDisplayName(),
                'firstName' => $target->getFirstName(),
                'lastName' => $target->getLastName(),
                'birthDate' => $target->getBirthDate()?->format(\DateTimeImmutable::ATOM),
            ],
            'portfolio' => [
                'items' => $items,
                'totals' => [
                    'invested' => $totalInvested,
                    'estimatedValue' => $totalValue,
                ],
            ],
            'transactions' => $txPayload,
        ]);
    }
}
