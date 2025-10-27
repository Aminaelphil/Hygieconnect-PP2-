<?php

namespace App\Entity;

use App\Repository\DemandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandeRepository::class)]
class Demande
{
        // --- Statuts possibles pour la demande ---
    const STATUT_EN_ATTENTE = 'en attente';
    const STATUT_EN_COURS = 'en cours';
    const STATUT_ACCEPTEE   = 'acceptée';
    const STATUT_REFUSEE    = 'refusée';
    const STATUT_ANNULEE = 'annulée';
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'demandes')]
    private ?User $user = null;

    /**
     * @var Collection<int, Prestation>
     */
    #[ORM\ManyToMany(targetEntity: Prestation::class, inversedBy: 'demandes')]
    private Collection $prestations;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $infossupplementaires = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $datedebut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $datefin = null;

    #[ORM\Column(nullable: true)]
    private ?float $devisestime = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $datedemande = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $naturedemandeur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresseprestation = null;

    public function __construct()
    {
        $this->prestations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Prestation>
     */
    public function getPrestations(): Collection
    {
        return $this->prestations;
    }

    public function addPrestation(Prestation $prestation): static
    {
        if (!$this->prestations->contains($prestation)) {
            $this->prestations->add($prestation);
        }

        return $this;
    }

    public function removePrestation(Prestation $prestation): static
    {
        $this->prestations->removeElement($prestation);

        return $this;
    }

    public function getInfossupplementaires(): ?string
    {
        return $this->infossupplementaires;
    }

    public function setInfossupplementaires(?string $infossupplementaires): static
    {
        $this->infossupplementaires = $infossupplementaires;

        return $this;
    }

    public function getDatedebut(): ?\DateTimeImmutable
    {
        return $this->datedebut;
    }

    public function setDatedebut(?\DateTimeImmutable $datedebut): static
    {
        $this->datedebut = $datedebut;

        return $this;
    }

    public function getDatefin(): ?\DateTimeImmutable
    {
        return $this->datefin;
    }

    public function setDatefin(?\DateTimeImmutable $datefin): static
    {
        $this->datefin = $datefin;

        return $this;
    }

    public function getDevisestime(): ?float
    {
        return $this->devisestime;
    }

    public function setDevisestime(?float $devisestime): static
    {
        $this->devisestime = $devisestime;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDatedemande(): ?\DateTimeImmutable
    {
        return $this->datedemande;
    }

    public function setDatedemande(?\DateTimeImmutable $datedemande): static
    {
        $this->datedemande = $datedemande;

        return $this;
    }

    public function getNaturedemandeur(): ?string
    {
        return $this->naturedemandeur;
    }

    public function setNaturedemandeur(?string $naturedemandeur): static
    {
        $this->naturedemandeur = $naturedemandeur;

        return $this;
    }

    public function getAdresseprestation(): ?string
    {
        return $this->adresseprestation;
    }

    public function setAdresseprestation(?string $adresseprestation): static
    {
        $this->adresseprestation = $adresseprestation;

        return $this;
    }
}
