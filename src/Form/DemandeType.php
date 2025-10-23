<?php

namespace App\Form;

use App\Entity\Categorie;
use App\Entity\Demande;
use App\Entity\Prestation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DemandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('categorie', EntityType::class, [
                'class' => Categorie::class,
                'choice_label' => 'nom',
                'placeholder' => 'Choisissez une catégorie',
                'mapped' => false,
                'label' => 'Catégorie',
            ])
            ->add('prestations', EntityType::class, [
                'class' => Prestation::class,
                'choices' => [],
                'choice_label' => 'titre',
                'multiple' => true,
                'expanded' => true,
                'label' => 'Prestations disponibles',
            ])
            ->add('dateDebut', DateType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'label' => 'Date de début',
                'attr' => [
                    'min' => (new \DateTime('today'))->format('Y-m-d'), 
                ],
            ])
            ->add('dateFin', DateType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'label' => 'Date de fin',
                'attr' => [
                    'min' => (new \DateTime('today'))->format('Y-m-d'),
                ],
            ])

            ->add('naturedemandeur', ChoiceType::class, [
                'choices' => [
                    'Particulier' => 'particulier',
                    'Hôtel' => 'hotel',
                    'Centre de formation' => 'centre_formation',
                    'Clinique/Hôpital' => 'Clinique/Hôpital',
                ],
                'placeholder' => 'Choisir la nature du demandeur',
                'label' => 'Nature du demandeur',
            ])
            ->add('adresseprestation', null, [
                'label' => 'Adresse de la prestation',
                'attr' => ['placeholder' => 'Indiquez l’adresse exacte du lieu'],
            ])
            ->add('infossupplementaires', TextareaType::class, [
                'label' => 'Informations supplémentaires',
                'required' => false,
                'attr' => ['placeholder' => 'Ajouter des informations supplémentaires si nécessaire'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Demande::class,
        ]);
    }
}

