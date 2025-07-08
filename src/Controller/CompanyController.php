<?php

namespace App\Controller;

use App\Service\IprotoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/company')]
final class CompanyController extends AbstractController
{
    #[Route(name: 'app_company_index', methods: ['GET'])]
    public function index(IprotoService $iprotoService): Response
    {
        $owners = $iprotoService->getAllOwners();
        return $this->render('company/index.html.twig', [
            'owners' => $owners,
        ]);
    }

    #[Route('/api/companies', name: 'api_companies', methods: ['GET'])]
    public function getCompaniesByOwner(Request $request, IprotoService $iprotoService): JsonResponse
    {
        $ownerId = $request->query->get('owner');
        if (!$ownerId) {
            return new JsonResponse(['error' => 'Owner ID is required'], 400);
        }

        $showDeleted = $request->query->getBoolean('showDeleted', false);

        try {
            $companies = $iprotoService->getAllCompanies($ownerId, $showDeleted);
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

    #[Route('/api/integrations', name: 'api_company_integrations', methods: ['GET'])]
    public function integrations(Request $request, IprotoService $iprotoService): JsonResponse
    {
        $params = $request->query->all();
        if (!isset($params['owner']) || $params['owner'] === '') {
            return new JsonResponse(['error' => 'Owner ID is required'], 400);
        }

        try {
            $data = $iprotoService->getIntegrations($params);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        $total = $data['hydra:totalItems'] ?? 0;
        $rows = [];
        foreach ($data['hydra:member'] ?? [] as $company) {
            $rows[] = [
                'id' => $company['id'],
                'title' => $company['title'] ?? $company['name'] ?? '',
            ];
        }

        return new JsonResponse([
            'draw' => (int) ($params['draw'] ?? 0),
            'data' => $rows,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
        ]);
    }


    #[Route('/api/timezone', name: 'api_get_timezone')]
    public function getTimezone(Request $request, IprotoService $iprotoService, \App\Service\CountryTimezoneResolver $tzResolver): JsonResponse
    {
        $ownerId = $request->query->get('owner');
        if (!$ownerId) {
            return new JsonResponse(['timezone' => null], 400);
        }

        $owners = $iprotoService->getAllOwners();
        $owner = array_filter($owners, fn ($o) => $o['id'] == $ownerId);
        $owner = reset($owner);

        $timezone = $tzResolver->resolveFromApiPath($owner['country'] ?? null);

        return new JsonResponse(['timezone' => $timezone]);
    }
}
