<?php

namespace App\Controller\Admin;

use App\Entity\Demande;
use App\Entity\User;
use App\Entity\Prestation;
use App\Repository\DemandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class AdminController extends AbstractController
{
    #[Route('/admin/demandes', name: 'app_admin_demandes')]
    public function adminDemandes(Request $request, EntityManagerInterface $em, DemandeRepository $demandeRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // === FILTRES DE RECHERCHE ===
        $search = $request->query->get('search');
        $nature = $request->query->get('naturedemandeur');
        $statut = $request->query->get('statut');
        $categorie = $request->query->get('categorie');

        // === RÉSULTATS FILTRÉS ===
        $demandes = $demandeRepository->searchDemandes($search, $nature, $statut, $categorie);

        // === STATS GÉNÉRALES ===
        $total = count($demandes);
        $acceptees = count(array_filter($demandes, fn($d) => $d->getStatut() === 'acceptée'));
        $refusees = count(array_filter($demandes, fn($d) => $d->getStatut() === 'refusée'));
        $enCours = count(array_filter($demandes, fn($d) => $d->getStatut() === 'en cours'));
        $annulees = count(array_filter($demandes, fn($d) => $d->getStatut() === 'annulée'));

        $tauxAccept = $total > 0 ? round(($acceptees / $total) * 100, 1) : 0;
        $tauxAnnule = $total > 0 ? round(($annulees / $total) * 100, 1) : 0;

        $users = $em->getRepository(User::class)->findAll();
        $usersWithDemandes = count(array_filter($users, fn($u) => count($u->getDemandes()) > 0));
        $totalUsers = count($users);

        $prestations = $em->getRepository(Prestation::class)->findAll();
        $prestationsByCategorie = [];
        foreach ($prestations as $prestation) {
            $cat = $prestation->getCategorie()?->getNom() ?? 'Non catégorisé';
            if (!isset($prestationsByCategorie[$cat])) {
                $prestationsByCategorie[$cat] = 0;
            }
            $prestationsByCategorie[$cat]++;
        }

        return $this->render('admin/admin_demandes.html.twig', [
            'demandes' => $demandes,
            'search' => $search,
            'nature' => $nature,
            'statut' => $statut,
            'categorie' => $categorie,
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
                'prestationsByCategorie' => $prestationsByCategorie,
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
