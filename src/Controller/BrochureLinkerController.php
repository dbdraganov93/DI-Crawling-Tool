<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BrochureLinkerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BrochureLinkerController extends AbstractController
{
    public function __construct(private BrochureLinkerService $linker)
    {
    }

    #[Route('/brochure/linker', name: 'brochure_linker', methods: ['GET'])]
    public function form(): Response
    {
        return $this->render('brochure/linker.html.twig');
    }

    #[Route('/brochure/linker', name: 'brochure_linker_process', methods: ['POST'])]
    public function process(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('pdf');
        if (!$file) {
            return new JsonResponse(['error' => 'No PDF provided'], Response::HTTP_BAD_REQUEST);
        }
        $website = (string) $request->request->get('website');
        $prefix = (string) $request->request->get('prefix');
        $suffix = (string) $request->request->get('suffix');

        $dir = $this->getParameter('kernel.project_dir') . '/var/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $filename = uniqid('brochure_', true) . '.pdf';
        $file->move($dir, $filename);
        $path = $dir . '/' . $filename;

        $result = $this->linker->process($path, $website, $prefix, $suffix);
        $publicDir = $this->getParameter('kernel.project_dir') . '/public';

        return new JsonResponse([
            'annotated' => str_replace($publicDir, '', $result['annotated']),
            'json' => str_replace($publicDir, '', $result['json']),
            'data' => $result['data'],
        ]);
    }
}
