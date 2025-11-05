<?php

namespace App\Controller;

use App\Repository\CategorieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuditController extends AbstractController
{
    #[Route('/audit', name: 'app_audit')]
    public function index(CategorieRepository $categorieRepository): Response
    {
        // On récupère la catégorie "Audit"
        $categorie = $categorieRepository->findOneBy(['nom' => 'Audit']);

        if (!$categorie) {
            throw $this->createNotFoundException('Catégorie "Audit" introuvable.');
        }

        // Prestations liées à cette catégorie
        $audits = $categorie->getPrestations();

        return $this->render('audit/index.html.twig', [
            'audits' => $audits,
        ]);
    }
}
