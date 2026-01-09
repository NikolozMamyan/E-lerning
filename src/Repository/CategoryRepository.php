<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * 🔹 Toutes les catégories (ordre alphabétique)
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 🔹 Catégories qui ont AU MOINS un cours
     */
    public function findUsedCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.courses', 'course')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 🔹 Récupérer une catégorie par slug
     */
    public function findOneBySlug(string $slug): ?Category
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * 🔹 Catégories avec compteur de cours (prêt pour UI)
     */
    public function findWithCourseCount(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c as category, COUNT(course.id) as courseCount')
            ->leftJoin('c.courses', 'course')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
