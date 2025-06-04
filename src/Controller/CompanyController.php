<?php

namespace App\Controller;

use App\Entity\Company;
use App\Form\CompanyType;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\IprotoService;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/company')]
final class CompanyController extends AbstractController
{
    #[Route(name: 'app_company_index', methods: ['GET'])]
    public function index(CompanyRepository $companyRepository): Response
    {
        return $this->render('company/index.html.twig', [
            'companies' => $companyRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_company_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $company = new Company();
        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($company);
            $entityManager->flush();

            return $this->redirectToRoute('app_company_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('company/new.html.twig', [
            'company' => $company,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_company_show', methods: ['GET'])]
    public function show(Company $company): Response
    {
        return $this->render('company/show.html.twig', [
            'company' => $company,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_company_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Company $company, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_company_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('company/edit.html.twig', [
            'company' => $company,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_company_delete', methods: ['POST'])]
    public function delete(Request $request, Company $company, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $company->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($company);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_company_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/api/companies', name: 'api_companies', methods: ['GET'])]
    public function getCompaniesByOwner(Request $request, IprotoService $iprotoService): JsonResponse
    {
        $ownerId = $request->query->get('owner');

        if (!$ownerId) {
            return new JsonResponse(['error' => 'Owner ID is required'], 400);
        }

        try {
            $companies = $iprotoService->getAllCompanies($ownerId);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        $result = [];

        foreach ($companies as $company) {
            $label = $company['title'] . ' (ID: ' . $company['id'] . ')';
            $result[] = ['id' => $company['id'], 'label' => $label];
        }

        return new JsonResponse($result);
    }

    #[Route('/api/timezone', name: 'api_get_timezone')]
    public function getTimezone(Request $request, IprotoService $iprotoService, CountryTimezoneResolver $tzResolver): JsonResponse
    {
        $ownerId = $request->query->get('owner');
        if (!$ownerId) {
            return new JsonResponse(['timezone' => null], 400);
        }

        $owners = $iprotoService->getAllOwners();
        $owner = array_filter($owners, fn($o) => $o['id'] == $ownerId);
        $owner = reset($owner);

        $timezone = $tzResolver->resolveFromApiPath($owner['country'] ?? null);

        return new JsonResponse(['timezone' => $timezone]);
    }
}
