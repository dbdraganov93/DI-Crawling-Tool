<?php
// src/Controller/FormController.php
namespace App\Controller;

use App\Form\ShopfullyForm;
use App\Service\IprotoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\SimpleFormType;
use App\CrawlerScripts\ShopfullyCrawler;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ShopfullyLogRepository;

class ShopfullyController extends AbstractController
{
    private IprotoService $iprotoService;
    public function __construct(ShopfullyCrawler $crawler, IprotoService $iprotoService)
    {
        $this->crawler = $crawler;
        $this->iprotoService = $iprotoService;
    }

    #[Route('/shopfully-wizard', name: 'app_shopfully_wizard')]
    public function index(Request $request, ShopfullyLogRepository $logRepo): Response
    {
        $form = $this->createForm(ShopfullyForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();
            $data['timezone'] = $form->get('timezone')->getData();
            $this->crawler->crawl($data);
            $this->addFlash('success', 'Form submitted successfully!');
        }

        $logs = $logRepo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('shopfully/wizard.html.twig', [
            'form' => $form->createView(),
            'logs' => $logs,
        ]);
    }
}
