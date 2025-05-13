<?php
// src/Controller/FormController.php
namespace App\Controller;

use App\Form\ShopfullyForm;
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
    public function __construct(ShopfullyCrawler $crawler)
    {
        $this->crawler = $crawler;
    }

    #[Route('/shopfully-wizard', name: 'app_shopfully_wizard')]
    public function index(Request $request, ShopfullyLogRepository $logRepo): Response
    {
        $form = $this->createForm(ShopfullyForm::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->crawler->handleFormData($data);
            // Handle form data (e.g. save to DB, etc.)

            $this->addFlash('success', 'Form submitted successfully!');
        }

        $logs = $logRepo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('shopfully/wizard.html.twig', [
            'form' => $form->createView(),
            'logs' => $logs,
        ]);
    }


//    public function form(Request $request, ShopfullyCrawler $crawler): Response
//    {
//        $form = $this->createForm(ShopfullyForm::class);
//        $form->handleRequest($request);
//
//        if ($form->isSubmitted() && $form->isValid()) {
//
//            $data = $form->getData();
//
//            $crawler->handleFormData($data);
//
//            $this->addFlash('success', 'Form submitted successfully.');
//            return $this->redirectToRoute('form');
//        }
//
//        return $this->render('wizard.html.twig', [
//            'form' => $form->createView(),
//        ]);
//    }

}
