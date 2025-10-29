<?php

namespace App\Controller\Admin;

use App\Entity\Demande;
use App\Entity\User;
use App\Entity\Prestation;
use App\Entity\Categorie;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;

class DashboardController extends AbstractDashboardController
{
    #[Route('/formateur', name: 'admin')]
    public function index(): Response
    {
        $routeBuilder = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect(
            $routeBuilder->setController(DemandeCrudController::class)->generateUrl()
        );
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToUrl('Tableau de bord', 'fa fa-home', $this->generateUrl('app_home'));

        yield MenuItem::linkToCrud('Demandes', 'fa fa-envelope', Demande::class);
        yield MenuItem::linkToCrud('Utilisateurs', 'fa fa-user', User::class);
        yield MenuItem::linkToCrud('Prestations', 'fa fa-list', Prestation::class);
        yield MenuItem::linkToCrud('Catégories', 'fa fa-tags', Categorie::class);
    }
}
