<?php

namespace App\Controller;

use App\Entity\Demande;
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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

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

    // -------------------------
    // Étape 1 — Informations du demandeur
    // -------------------------
#[Route('/demande/etape-1', name: 'app_demande_new')]
public function etape1(Request $request, SessionInterface $session): Response
{
    // Si on démarre une nouvelle demande, on supprime les données de session
    if ($request->query->get('new') === '1') {
        $session->remove(self::WIZARD_KEY);
    }

    // Récupération des données de la session
    $data = $session->get(self::WIZARD_KEY, []);
    $stepData = $data['etape1'] ?? [];

    // Vérifie si l'utilisateur est connecté
    $user = $this->getUser();
    if ($user) {
        // Préremplissage depuis l'utilisateur connecté
        $stepData['prenom'] = $user->getPrenom() ?? $stepData['prenom'] ?? '';
        $stepData['nom'] = $user->getNom() ?? $stepData['nom'] ?? '';
        $stepData['email'] = $user->getEmail() ?? $stepData['email'] ?? '';
        $stepData['telephone'] = $user->getTelephone() ?? $stepData['telephone'] ?? '';
    }

    // Traitement du formulaire POST
    if ($request->isMethod('POST')) {
        $posted = $request->request->all('demande');

        // Si l'utilisateur n'est pas connecté, autoriser la saisie des informations personnelles
        if (!$user) {
            $stepData['prenom'] = $posted['prenom'] ?? null;
            $stepData['nom'] = $posted['nom'] ?? null;
            $stepData['email'] = $posted['email'] ?? null;
            $stepData['telephone'] = $posted['telephone'] ?? null;
        }

        // Toujours récupérer ces champs
        $stepData['naturedemandeur'] = $posted['naturedemandeur'] ?? null;
        $stepData['adresseprestation'] = $posted['adresseprestation'] ?? null;

        // Sauvegarde dans la session
        $data['etape1'] = $stepData;
        $session->set(self::WIZARD_KEY, $data);

        // Redirection vers l'étape 2
        return $this->redirectToRoute('app_demande_etape2');
    }

    // Rendu du template
    return $this->render('demande/etape1.html.twig', [
        'stepData' => $stepData,
        'userConnected' => $user !== null,
    ]);
}

    // -------------------------
    // Étape 2 — Choix de la catégorie
    // -------------------------
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

    // -------------------------
    // Étape 3 — Choix des prestations
    // -------------------------
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

    // -------------------------
    // Étape 4 — Période & infos supplémentaires
    // -------------------------
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

// -------------------------
// Étape 5 — Devis estimatif et enregistrement
// -------------------------
#[Route('/demande/etape-5', name: 'app_demande_etape5')]
public function etape5(
    SessionInterface $session,
    EntityManagerInterface $em,
    MailerInterface $mailer
): Response {
    // Récupération des données de l'assistant (wizard)
    $data = $session->get(self::WIZARD_KEY, []);

    // Récupération des prestations sélectionnées
    $selectedPrestations = $data['prestations'] ?? [];
    $prestationsEntities = !empty($selectedPrestations)
        ? $this->prestationRepository->findBy(['id' => $selectedPrestations])
        : [];

    // Calcul du total du devis
    $total = 0;
    if (!empty($data['dateDebut']) && !empty($data['dateFin']) && $prestationsEntities) {
        try {
            $dDeb = new \DateTimeImmutable($data['dateDebut']);
            $dFin = new \DateTimeImmutable($data['dateFin']);
            $jours = $dDeb->diff($dFin)->days + 1;

            foreach ($prestationsEntities as $p) {
                $total += ($p->getPrix() ?? 0) * $jours;
            }
        } catch (\Exception $e) {
            // Ignorer les erreurs de date
        }
    }

    // Création de la demande (sans rattachement utilisateur)
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

    // Stocker l'ID de la demande dans la session (pour rattachement après login)
    $data['demande_id'] = $demande->getId();
    $session->set(self::WIZARD_KEY, $data);

    // Génération du numéro de devis
    $devisNumero = 'DEV-' . str_pad($demande->getId(), 5, '0', STR_PAD_LEFT);

    // Génération du PDF du devis
    $html = $this->renderView('demande/devis_pdf.html.twig', [
        'prestations' => $prestationsEntities,
        'total' => $total,
        'demande' => $demande,
        'devisNumero' => $devisNumero,
        'isPdf' => true,
        'nom' => $data['etape1']['nom'] ?? null,
        'email' => $data['etape1']['email'] ?? null,
        'telephone' => $data['etape1']['telephone'] ?? null,
    ]);

    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdfContent = $dompdf->output();

    // Envoi du mail avec le devis en pièce jointe
    $email = (new Email())
        ->from(new Address('no-reply@tonsite.com', 'HygieConnect'))
        ->to($data['etape1']['email'])
        ->subject("Votre devis HygieConnect - $devisNumero")
        ->html($this->renderView('emails/devis.html.twig', [
            'nom' => $data['etape1']['nom'] ?? null,
            'devisNumero' => $devisNumero,
            'total' => $total,
        ]))
        ->attach($pdfContent, "devis-$devisNumero.pdf", 'application/pdf');

    $mailer->send($email);

    // Affichage du récapitulatif avec possibilité de se connecter
    return $this->render('demande/etape5.html.twig', [
        'prestations' => $prestationsEntities,
        'total' => $total,
        'demande' => $demande,
        'devisNumero' => $devisNumero,
        'stepData' => $data,
        'mailEnvoye' => true,
    ]);
}

// -------------------------
// Confirmation de la demande après login
// -------------------------
#[Route('/demande/confirmer', name: 'app_demande_confirmer')]
public function confirmer(SessionInterface $session, EntityManagerInterface $em): Response
{
    $data = $session->get(self::WIZARD_KEY, []);

    // Vérifie si une demande a été sauvegardée
    if (empty($data['demande_id'])) {
        $this->addFlash('warning', 'Aucune demande à confirmer.');
        return $this->redirectToRoute('app_demande_new');
    }

    // Recherche la demande correspondante
    $demande = $em->getRepository(Demande::class)->find($data['demande_id']);
    if (!$demande) {
        $this->addFlash('warning', 'Demande introuvable.');
        return $this->redirectToRoute('app_demande_new');
    }

    // Vérifie si l’utilisateur est connecté
    $user = $this->getUser();
    if (!$user) {
        $this->addFlash('warning', 'Veuillez vous connecter pour confirmer votre demande.');
        return $this->redirectToRoute('app_login');
    }

    //  Rattache la demande à l’utilisateur
    $demande->setUser($user);
    $demande->setStatut(Demande::STATUT_EN_COURS);

    $em->persist($demande);
    $em->flush();

    // Supprime l’ID de la session pour éviter les doublons
    unset($data['demande_id']);
    $session->set(self::WIZARD_KEY, $data);

    // Affiche la page de confirmation
    return $this->render('demande/confirmation.html.twig', [
        'demande' => $demande,
    ]);
}

    // -------------------------
    // Récupération prestations par catégorie (AJAX)
    // -------------------------
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

    // -------------------------
    // Génération PDF d'une demande
    // -------------------------
    #[Route('/demande/devis/pdf/{id}', name: 'app_demande_pdf')]
    public function generatePdf(Demande $demande): Response
    {
        $prestationsEntities = $demande->getPrestations();
        $total = $demande->getDevisestime();
        $devisNumero = 'DEV-' . str_pad($demande->getId(), 5, '0', STR_PAD_LEFT);

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

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new StreamedResponse(function() use ($dompdf) {
            echo $dompdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="devis.pdf"',
        ]);
    }
}
