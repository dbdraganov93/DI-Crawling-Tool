<?php

namespace App\Repository;

use App\Entity\ShopfullyPreset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopfullyPreset>
 */
class ShopfullyPresetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopfullyPreset::class);
    }
}
