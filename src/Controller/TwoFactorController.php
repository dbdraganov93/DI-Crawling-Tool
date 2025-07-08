<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\TwoFactorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/2fa')]
class TwoFactorController extends AbstractController
{
    #[Route('/setup/{id}', name: 'app_user_2fa', methods: ['GET', 'POST'])]
    public function setup(User $user, Request $request, EntityManagerInterface $em, TwoFactorService $service): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$user->getTwoFactorSecret()) {
            $user->setTwoFactorSecret($service->generateSecret());
            $em->flush();
        }

        $qrCode = $service->generateQrCode(
            $service->getOtpAuthUrl($user->getEmail(), $user->getTwoFactorSecret())
        );

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            if ($service->verifyCode($user->getTwoFactorSecret(), $code)) {
                $user->setTwoFactorEnabled(true);
                $em->flush();
                $this->addFlash('success', 'Two-factor authentication enabled.');

                return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
            }

            $this->addFlash('error', 'Invalid authentication code.');
        }

        return $this->render('user/setup_2fa.html.twig', [
            'user' => $user,
            'qrCode' => $qrCode,
            'secret' => $user->getTwoFactorSecret(),
        ]);
    }

    #[Route('/verify', name: 'app_2fa_verify', methods: ['GET', 'POST'])]
    public function verify(Request $request, EntityManagerInterface $em, TwoFactorService $service): Response
    {
        $session = $request->getSession();
        $userId = $session->get('2fa_user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->find($userId);
        if (!$user) {
            $session->remove('2fa_user_id');
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            if ($service->verifyCode($user->getTwoFactorSecret(), $code)) {
                $session->set('2fa_verified', true);
                $session->remove('2fa_user_id');

                return $this->redirectToRoute('app_home');
            }
            $this->addFlash('error', 'Invalid authentication code.');
        }

        return $this->render('security/verify_2fa.html.twig');
    }

    #[Route('/remove/{id}', name: 'app_user_2fa_remove', methods: ['POST'])]
    public function remove(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $current = $this->getUser();
        if (!$current) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isGranted('ROLE_ADMIN') && $current !== $user) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('remove2fa' . $user->getId(), $request->request->get('_token'))) {
            $user->setTwoFactorSecret(null);
            $user->setTwoFactorEnabled(false);
            $em->flush();
            $this->addFlash('success', 'Two-factor authentication removed.');
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
        }

        return $this->redirectToRoute('app_home');
    }
}
