<?php

namespace App\Repository;

use App\Entity\PriceHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PriceHistory>
 */
class PriceHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PriceHistory::class);
    }

    /**
    * @return PriceHistory[]
    */
    public function findRecent(string $symbol, int $limit = 100): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.symbol = :symbol')
            ->setParameter('symbol', strtoupper($symbol))
            ->orderBy('p.collectedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLastForSymbol(string $symbol): ?PriceHistory
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.symbol = :symbol')
            ->setParameter('symbol', strtoupper($symbol))
            ->orderBy('p.collectedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return PriceHistory[]
     */
    public function findSince(string $symbol, \DateTimeImmutable $since, int $limit = 500): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.symbol = :symbol')
            ->andWhere('p.collectedAt >= :since')
            ->setParameter('symbol', strtoupper($symbol))
            ->setParameter('since', $since)
            ->orderBy('p.collectedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function existsAt(string $symbol, \DateTimeImmutable $collectedAt): bool
    {
        return (bool) $this->createQueryBuilder('p')
            ->select('1')
            ->andWhere('p.symbol = :symbol')
            ->andWhere('p.collectedAt = :collectedAt')
            ->setParameter('symbol', strtoupper($symbol))
            ->setParameter('collectedAt', $collectedAt)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
