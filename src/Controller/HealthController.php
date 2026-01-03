<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function health(EntityManagerInterface $entityManager): JsonResponse
    {
        $dbOk = true;
        try {
            $entityManager->getConnection()->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        return $this->json([
            'status' => $dbOk ? 'ok' : 'degraded',
            'db' => $dbOk ? 'up' : 'down',
            'time' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
        ], $dbOk ? 200 : 503);
    }
}
