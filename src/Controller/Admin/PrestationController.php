<?php

namespace App\Controller\Admin;

use App\Entity\Prestation;
use App\Form\PrestationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/prestation')]
class PrestationController extends AbstractController
{
    #[Route('/new', name: 'admin_prestation_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
    $prestation = new Prestation();
    $form = $this->createForm(PrestationType::class, $prestation);
    
    // Retirer le champ token
    $form->remove('token');

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($prestation);
            $em->flush();

            $this->addFlash('success', 'Prestation ajoutée avec succès !');

            return $this->redirectToRoute('admin_prestation_list');
        }

        return $this->render('admin/prestation/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/', name: 'admin_prestation_list')]
    public function list(EntityManagerInterface $em): Response
    {
        $prestations = $em->getRepository(Prestation::class)->findAll();

        return $this->render('admin/prestation/list.html.twig', [
            'prestations' => $prestations,
        ]);
    }
}
