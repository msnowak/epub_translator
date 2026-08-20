<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Segment>
 */
final class SegmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Segment::class);
    }

    /**
     * @return array<string, int> status value => count
     */
    public function countByStatus(Project $project): array
    {
        /** @var list<array{status: SegmentStatus, total: int}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('s.status AS status, COUNT(s.id) AS total')
            ->where('s.project = :project')
            ->setParameter('project', $project)
            ->groupBy('s.status')
            ->getQuery()
            ->getResult();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['status']->value] = (int) $row['total'];
        }

        return $counts;
    }
}
