<?php

namespace App\Controller\Api;

use App\Entity\PortfolioEntry;
use App\Repository\PortfolioEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PortfolioController extends AbstractController
{
    #[Route('/api/portfolio', name: 'api_portfolio_list', methods: ['GET'])]
    public function list(PortfolioEntryRepository $repo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        $entries = $repo->findBy(['user' => $user]);
        $data = array_map(function(PortfolioEntry $e) {
            return [
                'id' => $e->getId(),
                'symbol' => $e->getSymbol(),
                'quantity' => $e->getQuantity(),
                'price' => $e->getPrice(),
            ];
        }, $entries);

        return new JsonResponse($data);
    }

    #[Route('/api/portfolio', name: 'api_portfolio_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['symbol']) || !isset($data['quantity'])) {
            return new JsonResponse(['error' => 'symbol and quantity required'], 400);
        }

        $entry = new PortfolioEntry();
        $entry->setUser($user);
        $entry->setSymbol($data['symbol']);
        $entry->setQuantity((float)$data['quantity']);
        $entry->setPrice(isset($data['price']) ? (float)$data['price'] : null);

        $em->persist($entry);
        $em->flush();

        return new JsonResponse(['id' => $entry->getId()], 201);
    }

    #[Route('/api/portfolio/{id}', name: 'api_portfolio_update', methods: ['PUT','PATCH'])]
    public function update(int $id, Request $request, PortfolioEntryRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        $entry = $repo->find($id);
        if (!$entry || $entry->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['symbol'])) $entry->setSymbol($data['symbol']);
        if (isset($data['quantity'])) $entry->setQuantity((float)$data['quantity']);
        if (array_key_exists('price', $data)) $entry->setPrice($data['price'] !== null ? (float)$data['price'] : null);

        $em->flush();

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/api/portfolio/{id}', name: 'api_portfolio_delete', methods: ['DELETE'])]
    public function delete(int $id, PortfolioEntryRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        $entry = $repo->find($id);
        if (!$entry || $entry->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'not found'], 404);
        }

        $em->remove($entry);
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }
}
