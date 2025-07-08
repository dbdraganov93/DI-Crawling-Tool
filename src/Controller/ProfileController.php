<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/profile')]
class ProfileController extends AbstractController
{
    #[Route('/change-password', name: 'app_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ChangePasswordType::class, $user, ['require_current' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $current = $form->get('currentPassword')->getData();
            if (!$passwordHasher->isPasswordValid($user, $current)) {
                $form->get('currentPassword')->addError(new FormError('Invalid current password.'));
            } else {
                $newPassword = $form->get('newPassword')->getData();
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $entityManager->flush();

                $this->addFlash('success', 'Password changed successfully.');

                return $this->redirectToRoute('app_home');
            }
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/remove-2fa', name: 'app_profile_remove_2fa', methods: ['POST'])]
    public function removeTwoFactor(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isCsrfTokenValid('remove2fa' . $user->getId(), $request->request->get('_token'))) {
            $user->setTwoFactorSecret(null);
            $user->setTwoFactorEnabled(false);
            $entityManager->flush();
            $this->addFlash('success', 'Two-factor authentication removed.');
        }

        return $this->redirectToRoute('app_home');
    }
}
