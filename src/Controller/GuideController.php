<?php

namespace App\Controller;

use App\Repository\CategorieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GuideController extends AbstractController
{
    #[Route('/guide', name: 'app_guide')]
    public function index(CategorieRepository $categorieRepository): Response
    {
        // On récupère la catégorie "Guide"
        $categorie = $categorieRepository->findOneBy(['nom' => 'Guide']);

        // Si la catégorie n'existe pas
        if (!$categorie) {
            throw $this->createNotFoundException('Catégorie "Guide" introuvable.');
        }

        // On récupère ses prestations
        $guides = $categorie->getPrestations();

        return $this->render('guide/index.html.twig', [
            'guides' => $guides,
        ]);
    }
}
