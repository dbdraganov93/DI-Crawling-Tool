<?php

namespace App\Repository;

use App\Entity\FlipifyImport;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FlipifyImport>
 */
class FlipifyImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlipifyImport::class);
    }

    /**
     * @return list<FlipifyImport>
     */
    public function findLatest(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC']);
    }

    public function findNextPendingId(?DateTimeImmutable $stalledBefore = null): ?int
    {
        $queryBuilder = $this->createQueryBuilder('import')
            ->select('import.id')
            ->orderBy('import.createdAt', 'ASC')
            ->setMaxResults(1);

        if ($stalledBefore !== null) {
            $queryBuilder
                ->where(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->eq('import.status', ':pending'),
                        $queryBuilder->expr()->andX(
                            $queryBuilder->expr()->eq('import.status', ':processing'),
                            $queryBuilder->expr()->isNull('import.processedAt'),
                            $queryBuilder->expr()->isNotNull('import.processingStartedAt'),
                            $queryBuilder->expr()->lte('import.processingStartedAt', ':stalledBefore'),
                        ),
                    ),
                )
                ->setParameter('pending', FlipifyImport::STATUS_PENDING)
                ->setParameter('processing', FlipifyImport::STATUS_PROCESSING)
                ->setParameter('stalledBefore', $stalledBefore);
        } else {
            $queryBuilder
                ->where('import.status = :pending')
                ->setParameter('pending', FlipifyImport::STATUS_PENDING);
        }

        $result = $queryBuilder
            ->getQuery()
            ->getArrayResult();

        if ($result === []) {
            return null;
        }

        $id = $result[0]['id'] ?? null;

        return $id !== null ? (int) $id : null;
    }
}
