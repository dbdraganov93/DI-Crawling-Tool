<?php

namespace App\Repository;

use App\Entity\FlipifyImport;
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

    public function findNextPendingId(): ?int
    {
        $result = $this->createQueryBuilder('import')
            ->select('import.id')
            ->where('import.status = :status')
            ->setParameter('status', FlipifyImport::STATUS_PENDING)
            ->orderBy('import.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        if ($result === []) {
            return null;
        }

        $id = $result[0]['id'] ?? null;

        return $id !== null ? (int) $id : null;
    }
}
