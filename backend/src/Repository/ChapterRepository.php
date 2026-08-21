<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chapter>
 */
final class ChapterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chapter::class);
    }

    /**
     * Jedno zapytanie dla calego projektu zamiast jednego na rozdzial - przy
     * 40 rozdzialach oszczedza 39 zapytan przy kazdym odczycie listy.
     *
     * @return array<string, array<string, int>> chapter id => (status value => count)
     */
    public function countByStatusForProject(Project $project): array
    {
        /** @var list<array{chapter: mixed, status: SegmentStatus, total: int}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(s.chapter) AS chapter, s.status AS status, COUNT(s.id) AS total')
            ->from(Segment::class, 's')
            ->where('s.project = :project')
            ->setParameter('project', $project)
            ->groupBy('s.chapter, s.status')
            ->getQuery()
            ->getResult();

        $counts = [];

        foreach ($rows as $row) {
            $chapterId = (string) $row['chapter'];
            $counts[$chapterId][$row['status']->value] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @return list<Chapter>
     */
    public function findForProjectInSpineOrder(Project $project): array
    {
        /** @var list<Chapter> $chapters */
        $chapters = $this->createQueryBuilder('c')
            ->where('c.project = :project')
            ->setParameter('project', $project)
            ->orderBy('c.spineOrder', 'ASC')
            ->getQuery()
            ->getResult();

        return $chapters;
    }
}
