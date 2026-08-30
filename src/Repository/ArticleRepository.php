<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * @return Article[]
     */
    public function findFeed(string $filter, ?User $user, int $limit, int $offset): array
    {
        $queryBuilder = $this->createQueryBuilder('article')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($filter === 'mine' && $user) {
            $queryBuilder
                ->andWhere('article.author = :author')
                ->setParameter('author', $user);
        }

        if ($filter === 'popular') {
            $queryBuilder
                ->addSelect('(SIZE(article.likedBy) + SIZE(article.comments)) AS HIDDEN engagementScore')
                ->orderBy('engagementScore', 'DESC')
                ->addOrderBy('article.createdAt', 'DESC');
        } else {
            $queryBuilder->orderBy('article.createdAt', 'DESC');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return Article[]
     */
    public function findTrending(int $limit = 3): array
    {
        return $this->createQueryBuilder('article')
            ->addSelect('(SIZE(article.likedBy) + SIZE(article.comments)) AS HIDDEN engagementScore')
            ->orderBy('engagementScore', 'DESC')
            ->addOrderBy('article.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Article[]
     */
    public function findForProfile(User $author, string $sort = 'latest', int $limit = 20): array
    {
        $queryBuilder = $this->createQueryBuilder('article')
            ->andWhere('article.author = :author')
            ->setParameter('author', $author)
            ->setMaxResults($limit);

        if ($sort === 'trending') {
            $queryBuilder
                ->addSelect('(SIZE(article.likedBy) + SIZE(article.comments)) AS HIDDEN engagementScore')
                ->orderBy('engagementScore', 'DESC')
                ->addOrderBy('article.createdAt', 'DESC');
        } elseif ($sort === 'discussed') {
            $queryBuilder
                ->addSelect('SIZE(article.comments) AS HIDDEN commentsCount')
                ->orderBy('commentsCount', 'DESC')
                ->addOrderBy('article.createdAt', 'DESC');
        } else {
            $queryBuilder->orderBy('article.createdAt', 'DESC');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return array{posts: int, likesReceived: int, commentsReceived: int}
     */
    public function getAuthorStats(User $author): array
    {
        $stats = $this->createQueryBuilder('article')
            ->select('COUNT(DISTINCT article.id) AS posts')
            ->addSelect('COUNT(DISTINCT liker.id) AS likesReceived')
            ->addSelect('COUNT(DISTINCT comment.id) AS commentsReceived')
            ->leftJoin('article.likedBy', 'liker')
            ->leftJoin('article.comments', 'comment')
            ->andWhere('article.author = :author')
            ->setParameter('author', $author)
            ->getQuery()
            ->getSingleResult();

        return [
            'posts' => (int) $stats['posts'],
            'likesReceived' => (int) $stats['likesReceived'],
            'commentsReceived' => (int) $stats['commentsReceived'],
        ];
    }

//    /**
//     * @return Article[] Returns an array of Article objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Article
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
