<?php

namespace App\Repository;

use App\Entity\PriceQuote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PriceQuote>
 */
class PriceQuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PriceQuote::class);
    }

    /**
     * @param string[] $symbols
     * @return array<string, PriceQuote>
     */
    public function findBySymbolsIndexed(array $symbols): array
    {
        if (empty($symbols)) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->where('p.symbol IN (:symbols)')
            ->setParameter('symbols', $symbols);

        $results = $qb->getQuery()->getResult();
        $indexed = [];
        foreach ($results as $quote) {
            if ($quote instanceof PriceQuote) {
                $indexed[$quote->getSymbol()] = $quote;
            }
        }

        return $indexed;
    }
}
