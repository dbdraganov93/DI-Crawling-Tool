<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager)
    {
        $user = new User();
        $user->setEmail('admin@admin.com');
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $user->setPassword($this->hasher->hashPassword($user, 'admin'));

        $manager->persist($user);
        $manager->flush();
    }
}
