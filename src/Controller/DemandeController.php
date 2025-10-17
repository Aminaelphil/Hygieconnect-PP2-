<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\Prestation;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

class DemandeController extends AbstractController
{
    /**
     * Étape 1 — Création d'une nouvelle demande et calcul du devis
     */
    #[Route('/demande/new', name: 'app_demande_new')]
    public function new(Request $request, EntityManagerInterface $em, CategorieRepository $categorieRepository): Response
    {
        $demande = new Demande();
        $categories = $categorieRepository->findAll();

        // Pré-remplissage depuis query parameters
        $query = $request->query;
        if ($query->get('dateDebut')) {
            $demande->setDatedebut(new \DateTimeImmutable($query->get('dateDebut')));
        }
        if ($query->get('dateFin')) {
            $demande->setDatefin(new \DateTimeImmutable($query->get('dateFin')));
        }

        if ($request->isMethod('POST')) {
            $this->handleDemandeForm($request, $demande, $em);
            return $this->renderEtape2($demande);
        }

        return $this->render('demande/etape1.html.twig', [
            'categories' => $categories,
            'demande' => $demande,
        ]);
    }

    /**
     * Étape 1 — Modification d'une demande existante et calcul du devis
     */
    #[Route('/demande/{id}/edit', name: 'app_demande_edit')]
    public function edit(Demande $demande, Request $request, EntityManagerInterface $em, CategorieRepository $categorieRepository): Response
    {
        $categories = $categorieRepository->findAll();

        if ($request->isMethod('POST')) {
            $this->handleDemandeForm($request, $demande, $em);
            return $this->renderEtape2($demande);
        }

        return $this->render('demande/etape1.html.twig', [
            'categories' => $categories,
            'demande' => $demande,
        ]);
    }

    /**
     * Récupère les prestations d'une catégorie
     */
    #[Route('/get-prestations/{id}', name: 'get_prestations_by_categorie', methods: ['GET'])]
    public function getPrestationsByCategorie(int $id, CategorieRepository $categorieRepository): Response
    {
        $categorie = $categorieRepository->find($id);

        if (!$categorie) {
            return new Response('Catégorie non trouvée', 404);
        }

        return $this->render('demande/_prestations.html.twig', [
            'prestations' => $categorie->getPrestations(),
        ]);
    }

    /**
     * Gère la soumission du formulaire (création ou édition)
     */
    private function handleDemandeForm(Request $request, Demande $demande, EntityManagerInterface $em): void
    {
        $postData = $request->request->all();
        $demandeData = $postData['demande'] ?? [];

        $dateDebut      = $demandeData['dateDebut'] ?? null;
        $dateFin        = $demandeData['dateFin'] ?? null;
        $prestationsIds = $postData['prestations'] ?? [];

        if ($dateDebut) $demande->setDatedebut(new \DateTimeImmutable($dateDebut));
        if ($dateFin) $demande->setDatefin(new \DateTimeImmutable($dateFin));

        $demande->setNaturedemandeur($demandeData['naturedemandeur'] ?? null);
        $demande->setAdresseprestation($demandeData['adresseprestation'] ?? null);
        $demande->setInfossupplementaires($demandeData['infossupplementaires'] ?? null);

        // Reset des prestations existantes et ajout des nouvelles
        $demande->getPrestations()->clear();
        if (!empty($prestationsIds)) {
            $prestations = $em->getRepository(Prestation::class)
                ->findBy(['id' => $prestationsIds]);

            foreach ($prestations as $prestation) {
                $demande->addPrestation($prestation);
            }
        }

        // Calcul du devis
        $total = 0;
        if ($demande->getDatedebut() && $demande->getDatefin()) {
            $jours = $demande->getDatedebut()->diff($demande->getDatefin())->days + 1;
            foreach ($demande->getPrestations() as $p) {
                $total += ($p->getPrix() ?? 0) * $jours;
            }
        }

        $demande->setDevisestime($total);
        if (!$demande->getDatedemande()) {
            $demande->setDatedemande(new \DateTimeImmutable());
        }

        $em->persist($demande);
        $em->flush();
    }

    /**
     * Affiche l'étape 2 avec le devis calculé
     */
    private function renderEtape2(Demande $demande): Response
    {
        return $this->render('demande/etape2.html.twig', [
            'demande' => $demande,
            'total' => $demande->getDevisestime(),
        ]);
    }
#[Route('/demande/{id}/download', name: 'app_demande_download')]
public function download(Demande $demande): Response
{
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true); 

    $dompdf = new Dompdf($options);

    $html = $this->renderView('demande/etape2_pdf.html.twig', [
        'demande' => $demande,
        'total' => $demande->getDevisestime(),
    ]);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return new Response(
        $dompdf->stream('devis_' . $demande->getId() . '.pdf', ['Attachment' => true]),
        200,
        [
            'Content-Type' => 'application/pdf'
        ]
    );
}
}
