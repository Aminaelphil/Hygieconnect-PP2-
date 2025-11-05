<?php

namespace App\Controller;

use App\Repository\CategorieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FormationController extends AbstractController
{
    #[Route('/formations', name: 'app_formations')]
    public function index(CategorieRepository $categorieRepository): Response
    {
        // On récupère la catégorie "Formation"
        $categorie = $categorieRepository->findOneBy(['nom' => 'Formation']);

        if (!$categorie) {
            throw $this->createNotFoundException('Catégorie "Formation" introuvable.');
        }

        // Prestations liées à cette catégorie
        $formations = $categorie->getPrestations();

        return $this->render('formation/index.html.twig', [
            'formations' => $formations,
        ]);
    }
}
