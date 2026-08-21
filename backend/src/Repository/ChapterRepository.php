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
     * @return array<string, int> status value => count
     */
    public function countByStatusForChapter(Chapter $chapter): array
    {
        /** @var list<array{status: SegmentStatus, total: int}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('s.status AS status, COUNT(s.id) AS total')
            ->from(Segment::class, 's')
            ->where('s.chapter = :chapter')
            ->setParameter('chapter', $chapter)
            ->groupBy('s.status')
            ->getQuery()
            ->getResult();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['status']->value] = (int) $row['total'];
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
