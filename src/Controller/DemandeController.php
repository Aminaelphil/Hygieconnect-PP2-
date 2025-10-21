<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\Prestation;
use App\Repository\CategorieRepository;
use App\Repository\PrestationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DemandeController extends AbstractController
{
    private const WIZARD_KEY = 'demande_wizard';

    private CategorieRepository $categorieRepository;
    private PrestationRepository $prestationRepository;

    public function __construct(
        CategorieRepository $categorieRepository,
        PrestationRepository $prestationRepository
    ) {
        $this->categorieRepository  = $categorieRepository;
        $this->prestationRepository = $prestationRepository;
    }

    /**
     * Étape 1 — Informations du demandeur
     */
    #[Route('/demande/etape-1', name: 'app_demande_new')]
    public function etape1(Request $request, SessionInterface $session): Response
    {
        // Réinitialiser le wizard si c'est une nouvelle demande
        if ($request->query->get('new') === '1') {
            $session->remove(self::WIZARD_KEY);
        }

        $data = $session->get(self::WIZARD_KEY, []);

        if ($request->isMethod('POST')) {
            $posted = $request->request->all('demande');
            $data['etape1'] = [
                'nom' => $posted['nom'] ?? null,
                'email' => $posted['email'] ?? null,
                'telephone' => $posted['telephone'] ?? null,
                'naturedemandeur' => $posted['naturedemandeur'] ?? null,
                'adresseprestation' => $posted['adresseprestation'] ?? null,
            ];
            $session->set(self::WIZARD_KEY, $data);

            return $this->redirectToRoute('app_demande_etape2');
        }

        return $this->render('demande/etape1.html.twig', [
            'stepData' => $data['etape1'] ?? [],
        ]);
    }


    /**
     * Étape 2 — Choix de la catégorie
     */
    #[Route('/demande/etape-2', name: 'app_demande_etape2')]
    public function etape2(Request $request, SessionInterface $session): Response
    {
        $categories = $this->categorieRepository->findAll();
        $data = $session->get(self::WIZARD_KEY, []);

        if ($request->isMethod('POST')) {
            $posted = $request->request->all('demande');
            $data['categorie_id'] = $posted['categorie'] ?? null;
            $session->set(self::WIZARD_KEY, $data);

            return $this->redirectToRoute('app_demande_etape3');
        }

        return $this->render('demande/etape2.html.twig', [
            'categories' => $categories,
            'selectedCategorieId' => $data['categorie_id'] ?? null,
        ]);
    }

    /**
     * Étape 3 — Choix des prestations
     */
    #[Route('/demande/etape-3', name: 'app_demande_etape3')]
    public function etape3(Request $request, SessionInterface $session): Response
    {
        $data = $session->get(self::WIZARD_KEY, []);
        $categorieId = $data['categorie_id'] ?? null;
        $prestations = [];

        if ($categorieId) {
            $categorie = $this->categorieRepository->find($categorieId);
            if ($categorie) {
                $prestations = $categorie->getPrestations();
            }
        }

        if ($request->isMethod('POST')) {
            $postedPrestations = $request->request->all('prestations');
            $data['prestations'] = is_array($postedPrestations) ? array_values($postedPrestations) : [];
            $session->set(self::WIZARD_KEY, $data);

            return $this->redirectToRoute('app_demande_etape4');
        }

        return $this->render('demande/etape3.html.twig', [
            'prestations' => $prestations,
            'selectedPrestations' => $data['prestations'] ?? [],
        ]);
    }

    /**
     * Étape 4 — Période & informations supplémentaires
     */
    #[Route('/demande/etape-4', name: 'app_demande_etape4')]
    public function etape4(Request $request, SessionInterface $session): Response
    {
        $data = $session->get(self::WIZARD_KEY, []);

        if ($request->isMethod('POST')) {
            $posted = $request->request->all('demande');
            $data['dateDebut'] = $posted['dateDebut'] ?? null;
            $data['dateFin'] = $posted['dateFin'] ?? null;
            $data['infossupplementaires'] = $posted['infossupplementaires'] ?? null;
            $session->set(self::WIZARD_KEY, $data);

            return $this->redirectToRoute('app_demande_etape5');
        }

        return $this->render('demande/etape4.html.twig', [
            'stepData' => $data,
        ]);
    }

    /**
     * Étape 5 — Devis estimatif et enregistrement en base
     */
    #[Route('/demande/etape-5', name: 'app_demande_etape5')]
    public function etape5(SessionInterface $session, EntityManagerInterface $em): Response
    {
        $data = $session->get(self::WIZARD_KEY, []);

        $selectedPrestations = $data['prestations'] ?? [];
        $prestationsEntities = [];

        if (!empty($selectedPrestations)) {
            $prestationsEntities = $this->prestationRepository->findBy(['id' => $selectedPrestations]);
        }

        // Calcul du total
        $total = 0;
        if (!empty($data['dateDebut']) && !empty($data['dateFin']) && !empty($prestationsEntities)) {
            try {
                $dDeb = new \DateTimeImmutable($data['dateDebut']);
                $dFin = new \DateTimeImmutable($data['dateFin']);
                $jours = $dDeb->diff($dFin)->days + 1;
                foreach ($prestationsEntities as $p) {
                    $total += ($p->getPrix() ?? 0) * $jours;
                }
            } catch (\Exception $e) {}
        }

        // --- Enregistrement de la demande pour avoir un ID ---
        $demande = new Demande();
        $demande->setDatedemande(new \DateTimeImmutable());
        $demande->setDatedebut(!empty($data['dateDebut']) ? new \DateTimeImmutable($data['dateDebut']) : null);
        $demande->setDatefin(!empty($data['dateFin']) ? new \DateTimeImmutable($data['dateFin']) : null);
        $demande->setInfossupplementaires($data['infossupplementaires'] ?? null);
        $demande->setNaturedemandeur($data['etape1']['naturedemandeur'] ?? null);
        $demande->setAdresseprestation($data['etape1']['adresseprestation'] ?? null);
        $demande->setDevisestime($total);
        $demande->setStatut(Demande::STATUT_EN_ATTENTE);

        foreach ($prestationsEntities as $p) {
            $demande->addPrestation($p);
        }

        $em->persist($demande);
        $em->flush();

        // Numéro de devis basé sur l'ID
        $devisNumero = 'DEV-' . str_pad($demande->getId(), 5, '0', STR_PAD_LEFT);

        return $this->render('demande/etape5.html.twig', [
            'prestations' => $prestationsEntities,
            'total' => $total,
            'demande' => $demande,
            'devisNumero' => $devisNumero,
            'stepData' => $data,
        ]);
    }

    /**
     * Récupération dynamique des prestations par catégorie (AJAX)
     */
    #[Route('/get-prestations/{id}', name: 'get_prestations_by_categorie', methods: ['GET'])]
    public function getPrestationsByCategorie(int $id): Response
    {
        $categorie = $this->categorieRepository->find($id);

        if (!$categorie) {
            return new Response('Catégorie non trouvée', 404);
        }

        return $this->render('demande/_prestations.html.twig', [
            'prestations' => $categorie->getPrestations(),
        ]);
    }

    /**
     * Génération PDF
     */
    #[Route('/demande/devis/pdf/{id}', name: 'app_demande_pdf')]
    public function generatePdf(Request $request, Demande $demande): Response
    {
        // Récupération des données du wizard depuis la session
        $wizardData = $request->getSession()->get('demande_wizard', []);
        $etape1 = $wizardData['etape1'] ?? [];

        // Récupération des prestations et du total
        $prestationsEntities = $demande->getPrestations();
        $total = $demande->getDevisestime();

        // Numéro de devis
        $devisNumero = 'DEV-' . str_pad($demande->getId(), 5, '0', STR_PAD_LEFT);

        // Génération du HTML du PDF
        $html = $this->renderView('demande/devis_pdf.html.twig', [
            'prestations' => $prestationsEntities,
            'total' => $total,
            'demande' => $demande,
            'devisNumero' => $devisNumero,
            'isPdf' => true,
            'nom' => $etape1['nom'] ?? null,
            'email' => $etape1['email'] ?? null,
            'telephone' => $etape1['telephone'] ?? null,
        ]);

        // Configuration Dompdf
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Retourne le PDF en téléchargement (attachment)
        return new StreamedResponse(function() use ($dompdf) {
            echo $dompdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="devis.pdf"',
        ]);
    }

}