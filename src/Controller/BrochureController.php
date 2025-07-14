<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BrochureLinkerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class BrochureController extends AbstractController
{
    public function __construct(private BrochureLinkerService $linker)
    {
    }

    #[Route('/brochure/upload', name: 'brochure_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('pdf');
        if (!$file) {
            return new JsonResponse(['error' => 'No PDF provided'], Response::HTTP_BAD_REQUEST);
        }

        $dir = $this->getParameter('kernel.project_dir') . '/var/brochures/original';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . '/' . $file->getClientOriginalName();
        $file->move($dir, $file->getClientOriginalName());

        $result = $this->linker->process($path);

        return new JsonResponse([
            'annotated_pdf' => $result['annotated'],
            'data_file' => $result['json'],
            'data' => $result['data'],
        ]);
    }
}
