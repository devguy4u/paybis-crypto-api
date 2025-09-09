<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExchangeRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExchangeRate>
 */
class ExchangeRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExchangeRate::class);
    }

    /**
     * Save an exchange rate entity
     */
    public function save(ExchangeRate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove an exchange rate entity
     */
    public function remove(ExchangeRate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find rates for the last 24 hours for a specific pair
     *
     * @return ExchangeRate[]
     */
    public function findLast24Hours(string $pair): array
    {
        $since = new \DateTimeImmutable('-24 hours');

        return $this->createQueryBuilder('er')
            ->andWhere('er.pair = :pair')
            ->andWhere('er.timestamp >= :since')
            ->setParameter('pair', $pair)
            ->setParameter('since', $since)
            ->orderBy('er.timestamp', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find rates for a specific day and pair
     *
     * @return ExchangeRate[]
     */
    public function findByDay(string $pair, \DateTimeInterface $date): array
    {
        $startOfDay = (clone $date)->setTime(0, 0, 0);
        $endOfDay = (clone $date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('er')
            ->andWhere('er.pair = :pair')
            ->andWhere('er.timestamp >= :start')
            ->andWhere('er.timestamp <= :end')
            ->setParameter('pair', $pair)
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->orderBy('er.timestamp', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the latest rate for a specific pair
     */
    public function findLatestByPair(string $pair): ?ExchangeRate
    {
        return $this->createQueryBuilder('er')
            ->andWhere('er.pair = :pair')
            ->setParameter('pair', $pair)
            ->orderBy('er.timestamp', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Clean up old rates (older than specified days)
     */
    public function cleanupOldRates(int $daysToKeep = 30): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$daysToKeep} days");

        return $this->createQueryBuilder('er')
            ->delete()
            ->andWhere('er.timestamp < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }

    /**
     * Get statistics for a pair within a date range
     */
    public function getStatistics(string $pair, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $result = $this->createQueryBuilder('er')
            ->select('
                COUNT(er.id) as count,
                MIN(er.rate) as min_rate,
                MAX(er.rate) as max_rate,
                AVG(er.rate) as avg_rate
            ')
            ->andWhere('er.pair = :pair')
            ->andWhere('er.timestamp >= :from')
            ->andWhere('er.timestamp <= :to')
            ->setParameter('pair', $pair)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleResult();

        return [
            'count' => (int) $result['count'],
            'min_rate' => $result['min_rate'] ? (float) $result['min_rate'] : null,
            'max_rate' => $result['max_rate'] ? (float) $result['max_rate'] : null,
            'avg_rate' => $result['avg_rate'] ? (float) $result['avg_rate'] : null,
        ];
    }
}
