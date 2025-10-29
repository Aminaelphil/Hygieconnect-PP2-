<?php

namespace App\Controller\Admin;

use App\Entity\Demande;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DemandeCrudController extends AbstractCrudController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getEntityFqcn(): string
    {
        return Demande::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('user', 'Client'),
            AssociationField::new('prestations', 'Prestations'),
            TextField::new('naturedemandeur', 'Nature demandeur'),
            TextField::new('adresseprestation', 'Adresse prestation'),
            DateField::new('datedemande', 'Date demande')->onlyOnIndex(),
            DateField::new('datedebut', 'Début prestation'),
            DateField::new('datefin', 'Fin prestation'),
            NumberField::new('devisestime', 'Devis estimé'),
            TextEditorField::new('infossupplementaires', 'Informations supplémentaires'),
            ChoiceField::new('statut', 'Statut')
                ->setChoices([
                    'En attente' => Demande::STATUT_EN_ATTENTE,
                    'En cours' => Demande::STATUT_EN_COURS,
                    'Acceptée' => Demande::STATUT_ACCEPTEE,
                    'Refusée' => Demande::STATUT_REFUSEE,
                    'Annulée' => Demande::STATUT_ANNULEE,
                ])
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $accepter = Action::new('accepter', 'Accepter', 'fa fa-check')
            ->linkToCrudAction('accepterDemande');

        $refuser = Action::new('refuser', 'Refuser', 'fa fa-times')
            ->linkToCrudAction('refuserDemande');

        $annuler = Action::new('annuler', 'Annuler', 'fa fa-ban')
            ->linkToCrudAction('annulerDemande');

        return $actions
            ->add('index', $accepter)
            ->add('index', $refuser)
            ->add('index', $annuler);
    }

    // --- Actions personnalisées pour EasyAdmin ---
    public function accepterDemande(AdminContext $context): RedirectResponse
    {
        $demande = $context->getEntity()->getInstance();
        $demande->setStatut(Demande::STATUT_ACCEPTEE);

        $this->em->persist($demande);
        $this->em->flush();

        $this->addFlash('success', 'Demande acceptée !');
        $referrer = $context->getReferrer() ?? $this->generateUrl('admin');
        return $this->redirect($referrer);
    }

    public function refuserDemande(AdminContext $context): RedirectResponse
    {
        $demande = $context->getEntity()->getInstance();
        $demande->setStatut(Demande::STATUT_REFUSEE);

        $this->em->persist($demande);
        $this->em->flush();

        $this->addFlash('danger', 'Demande refusée.');
        $referrer = $context->getReferrer() ?? $this->generateUrl('admin');
        return $this->redirect($referrer);
    }

    public function annulerDemande(AdminContext $context): RedirectResponse
    {
        $demande = $context->getEntity()->getInstance();
        $demande->setStatut(Demande::STATUT_ANNULEE);

        $this->em->persist($demande);
        $this->em->flush();

        $this->addFlash('warning', 'Demande annulée.');
        $referrer = $context->getReferrer() ?? $this->generateUrl('admin');
        return $this->redirect($referrer);
    }
}
