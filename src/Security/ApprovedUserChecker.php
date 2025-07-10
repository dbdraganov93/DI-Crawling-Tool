<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ApprovedUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isApproved()) {
            throw new CustomUserMessageAccountStatusException('Your account is awaiting admin approval.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
