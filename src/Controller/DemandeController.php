<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\User;
use App\Entity\Prestation;
use App\Entity\Categorie;
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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

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
    $user = $this->getUser();

    // Récupération de l'ID de la demande en session
    $data = $session->get(self::WIZARD_KEY, []);
    $demandeId = $data['demande_id'] ?? null;

    // Si utilisateur non connecté, stocker l'URL de redirection et forcer login
    if (!$user) {
        $session->set('redirect_after_login', $this->generateUrl('app_demande_confirmer'));
        $this->addFlash('warning', 'Veuillez vous connecter pour confirmer votre demande.');
        return $this->redirectToRoute('app_login');
    }

    $demande = null;
    if ($demandeId) {
        $demande = $em->getRepository(Demande::class)->find($demandeId);
    }

    // Si aucune demande trouvée en session, chercher la dernière demande non rattachée
    if (!$demande) {
        $demande = $em->getRepository(Demande::class)->findOneBy(['user' => null], ['id' => 'DESC']);
    }

    // Si toujours aucune demande, afficher la page confirmation avec null
    if (!$demande) {
        // On peut afficher un message sur la page confirmation plutôt que de rediriger
        
        return $this->render('demande/confirmation.html.twig', [
            'demande' => null
        ]);
    }

    // Rattacher la demande si nécessaire
    if ($demande->getUser() === null) {
        $demande->setUser($user);
        $demande->setStatut(Demande::STATUT_EN_COURS);
        $em->persist($demande);
        $em->flush();
    }

    // Supprimer l’ID de la session
    unset($data['demande_id']);
    $session->set(self::WIZARD_KEY, $data);

    return $this->render('demande/confirmation.html.twig', [
        'demande' => $demande
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
    #[Route('/mes-demandes', name: 'app_mes_demandes')]
        public function mesDemandes(EntityManagerInterface $em): Response
        {
            $user = $this->getUser();
            if (!$user) {
                $this->addFlash('warning', 'Vous devez être connecté pour consulter vos demandes.');
                return $this->redirectToRoute('app_login');
            }

            $demandes = $em->getRepository(Demande::class)->findBy(
                ['user' => $user],
                ['datedemande' => 'DESC']
            );

            return $this->render('demande/mes_demandes.html.twig', [
                'demandes' => $demandes,
            ]);
        }

#[Route('/demande/annuler/{id}', name: 'app_demande_annuler', methods: ['POST', 'GET'])]
public function annuler(
    int $id,
    EntityManagerInterface $em,
    MailerInterface $mailer
): Response {
    $user = $this->getUser();

    if (!$user) {
        $this->addFlash('warning', 'Vous devez être connecté pour effectuer cette action.');
        return $this->redirectToRoute('app_login');
    }

    $demande = $em->getRepository(Demande::class)->find($id);

    if (!$demande) {
        $this->addFlash('danger', 'Demande introuvable.');
        return $this->redirectToRoute('app_mes_demandes');
    }

    if ($demande->getUser() !== $user) {
        $this->addFlash('danger', 'Vous ne pouvez pas annuler cette demande.');
        return $this->redirectToRoute('app_mes_demandes');
    }

    //  Mise à jour du statut
    $demande->setStatut(Demande::STATUT_ANNULEE);
    $em->flush();

    //  Envoi d’un email de notification
    $email = (new TemplatedEmail())
        ->from(new Address('no-reply@hygieconnect.com', 'HygieConnect'))
        ->to($user->getEmail())
        ->subject('Votre demande a été annulée')
        ->htmlTemplate('emails/demande_annulee.html.twig')
        ->context([
            'user' => $user,
            'demande' => $demande,
        ]);

    $mailer->send($email);

    //  Message flash
    $this->addFlash('success', 'Votre demande a bien été annulée et un email de confirmation vous a été envoyé.');

    return $this->redirectToRoute('app_mes_demandes');
}

// Côté ADMIN
#[Route('/admin/demandes', name: 'app_admin_demandes')]
public function adminDemandes(EntityManagerInterface $em): Response
{
    $this->denyAccessUnlessGranted('ROLE_ADMIN');

    // --- DEMANDES ---
    $demandes = $em->getRepository(Demande::class)->findBy([], ['datedemande' => 'DESC']);

    $total = count($demandes);
    $acceptees = count(array_filter($demandes, fn($d) => $d->getStatut() === 'acceptée'));
    $refusees = count(array_filter($demandes, fn($d) => $d->getStatut() === 'refusée'));
    $enCours = count(array_filter($demandes, fn($d) => $d->getStatut() === 'en cours'));
    $annulees = count(array_filter($demandes, fn($d) => $d->getStatut() === 'annulée'));

    $tauxAccept = $total > 0 ? round(($acceptees / $total) * 100, 1) : 0;
    $tauxAnnule = $total > 0 ? round(($annulees / $total) * 100, 1) : 0;

    // --- UTILISATEURS ---
    $users = $em->getRepository(User::class)->findAll();
    $usersWithDemandes = count(array_filter($users, fn($u) => count($u->getDemandes()) > 0));
    $totalUsers = count($users);

    // --- PRESTATIONS PAR CATEGORIE ---
    $prestations = $em->getRepository(Prestation::class)->findAll();

    $prestationsByCategorie = [];
    foreach ($prestations as $prestation) {
        $categorie = $prestation->getCategorie()?->getNom() ?? 'Non catégorisé';
        if (!isset($prestationsByCategorie[$categorie])) {
            $prestationsByCategorie[$categorie] = 0;
        }
        $prestationsByCategorie[$categorie]++;
    }

    return $this->render('admin/admin_demandes.html.twig', [
        'demandes' => $demandes,
        'stats' => [
            'total' => $total,
            'acceptees' => $acceptees,
            'refusees' => $refusees,
            'enCours' => $enCours,
            'annulees' => $annulees,
            'tauxAccept' => $tauxAccept,
            'tauxAnnule' => $tauxAnnule,
            'lastUpdate' => new \DateTime(),
            'totalUsers' => $totalUsers,
            'usersWithDemandes' => $usersWithDemandes,
            'prestationsByCategorie' => $prestationsByCategorie
        ],
    ]);
}

        // --- Accepter ---
        #[Route('/admin/demande/{id}/accepter', name: 'admin_demande_accepter')]
        public function accepterDemande(Demande $demande, EntityManagerInterface $em, MailerInterface $mailer): RedirectResponse
        {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $demande->setStatut(Demande::STATUT_ACCEPTEE);
            $em->flush();

            if ($demande->getUser() && $demande->getUser()->getEmail()) {
                $email = (new TemplatedEmail())
                    ->from(new Address('no-reply@hygieconnect.com', 'HygieConnect'))
                    ->to($demande->getUser()->getEmail())
                    ->subject('Votre demande a été acceptée')
                    ->htmlTemplate('emails/demande_statut.html.twig')
                    ->context([
                        'nom' => $demande->getUser()->getNom(),
                        'id' => $demande->getId(),
                        'statut' => 'Acceptée',
                    ]);

                $mailer->send($email);
            }

            $this->addFlash('success', 'Demande acceptée et notification envoyée.');
            return $this->redirectToRoute('app_admin_demandes');
        }

        // --- Refuser ---
        #[Route('/admin/demande/{id}/refuser', name: 'admin_demande_refuser')]
        public function refuserDemande(Demande $demande, EntityManagerInterface $em, MailerInterface $mailer): RedirectResponse
        {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $demande->setStatut(Demande::STATUT_REFUSEE);
            $em->flush();

            if ($demande->getUser() && $demande->getUser()->getEmail()) {
                $email = (new TemplatedEmail())
                    ->from(new Address('no-reply@hygieconnect.com', 'HygieConnect'))
                    ->to($demande->getUser()->getEmail())
                    ->subject('Votre demande a été refusée')
                    ->htmlTemplate('emails/demande_statut.html.twig')
                    ->context([
                        'nom' => $demande->getUser()->getNom(),
                        'id' => $demande->getId(),
                        'statut' => 'Refusée',
                    ]);

                $mailer->send($email);
            }

            $this->addFlash('danger', 'Demande refusée et notification envoyée.');
            return $this->redirectToRoute('app_admin_demandes');
        }

        // --- Annuler ---
        #[Route('/admin/demande/{id}/annuler', name: 'admin_demande_annuler')]
        public function annulerDemande(Demande $demande, EntityManagerInterface $em, MailerInterface $mailer): RedirectResponse
        {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $demande->setStatut(Demande::STATUT_ANNULEE);
            $em->flush();

            if ($demande->getUser() && $demande->getUser()->getEmail()) {
                $email = (new TemplatedEmail())
                    ->from(new Address('no-reply@hygieconnect.com', 'HygieConnect'))
                    ->to($demande->getUser()->getEmail())
                    ->subject('Votre demande a été annulée')
                    ->htmlTemplate('emails/demande_statut.html.twig')
                    ->context([
                        'nom' => $demande->getUser()->getNom(),
                        'id' => $demande->getId(),
                        'statut' => 'Annulée',
                    ]);

                $mailer->send($email);
            }

            $this->addFlash('warning', 'Demande annulée et notification envoyée.');
            return $this->redirectToRoute('app_admin_demandes');
        }
}
