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
}
