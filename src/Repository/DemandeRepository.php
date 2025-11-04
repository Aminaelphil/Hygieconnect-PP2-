<?php

namespace App\Repository;

use App\Entity\Demande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Demande>
 */
class DemandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande::class);
    }
public function searchDemandes(?string $search, ?string $natureDemandeur, ?string $statut, ?string $categorie): array
{
    $qb = $this->createQueryBuilder('d')
        ->leftJoin('d.user', 'u')
        ->leftJoin('d.prestations', 'p')
        ->leftJoin('p.categorie', 'c')
        ->addSelect('u', 'p', 'c');

    if (!empty($search)) {
        $qb->andWhere('LOWER(u.nom) LIKE :search OR LOWER(u.prenom) LIKE :search')
           ->setParameter('search', '%' . strtolower($search) . '%');
    }

    if (!empty($natureDemandeur)) {
        $qb->andWhere('d.naturedemandeur = :nature')
           ->setParameter('nature', $natureDemandeur);
    }

    if (!empty($statut)) {
        $qb->andWhere('d.statut = :statut')
           ->setParameter('statut', $statut);
    }

    if (!empty($categorie)) {
        $qb->andWhere('c.nom = :categorie')
           ->setParameter('categorie', $categorie);
    }

    return $qb->orderBy('d.id', 'DESC')
              ->getQuery()
              ->getResult();
}

    //    /**
    //     * @return Demande[] Returns an array of Demande objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Demande
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
