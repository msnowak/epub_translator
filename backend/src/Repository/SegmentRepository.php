<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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

    /**
     * Zajmuje nastepny segment do pracy. Jedno zdanie SQL, wiec dwa workery
     * nigdy nie dostana tego samego wiersza: SKIP LOCKED przepuszcza podglad
     * obok wierszy juz zablokowanych przez inna transakcje. DQL nie zna tej
     * skladni, stad zejscie do DBAL.
     */
    public function claimNextPending(Project $project): ?Segment
    {
        $id = $this->getEntityManager()->getConnection()->fetchOne(
            <<<'SQL'
                UPDATE segment
                SET status = :processing
                WHERE id = (
                    SELECT s.id
                    FROM segment s
                    INNER JOIN chapter c ON c.id = s.chapter_id
                    WHERE s.project_id = :project AND s.status = :pending
                    ORDER BY c.spine_order ASC, s.position ASC
                    LIMIT 1
                    FOR UPDATE OF s SKIP LOCKED
                )
                RETURNING id
                SQL,
            [
                'processing' => SegmentStatus::Processing->value,
                'pending' => SegmentStatus::Pending->value,
                'project' => (string) $project->getId(),
            ],
        );

        if (!\is_string($id)) {
            return null;
        }

        $segment = $this->find(Uuid::fromString($id));

        if (null === $segment) {
            return null;
        }

        // Zdanie powyzej zmienilo wiersz w bazie, a resetProcessingToPending()
        // i resetFailedToPending() to rowniez masowe UPDATE omijajace ORM.
        // Bez odswiezenia dostalibysmy tu encje z tozsamosci pamietajaca stan
        // sprzed tamtych zapytan - miedzy innymi zuzyty budzet prob, przez co
        // ponowiony segment padlby przed pierwszym zapytaniem modelu.
        $this->getEntityManager()->refresh($segment);

        return $segment;
    }

    public function findPreviousTranslated(Segment $segment): ?Segment
    {
        $result = $this->createQueryBuilder('s')
            ->where('s.chapter = :chapter')
            ->andWhere('s.position < :position')
            ->andWhere('s.status IN (:usable)')
            ->setParameter('chapter', $segment->getChapter())
            ->setParameter('position', $segment->getPosition())
            ->setParameter('usable', [SegmentStatus::Translated->value, SegmentStatus::Edited->value])
            ->orderBy('s.position', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Segment ? $result : null;
    }

    public function hasProcessing(Project $project): bool
    {
        return $this->countWithStatus($project, SegmentStatus::Processing) > 0;
    }

    public function hasFailed(Project $project): bool
    {
        return $this->countWithStatus($project, SegmentStatus::Failed) > 0;
    }

    /**
     * @return int liczba segmentow zwolnionych do ponownej pracy
     */
    public function resetProcessingToPending(Project $project): int
    {
        return (int) $this->createQueryBuilder('s')
            ->update()
            ->set('s.status', ':pending')
            ->where('s.project = :project')
            ->andWhere('s.status = :processing')
            ->setParameter('pending', SegmentStatus::Pending->value)
            ->setParameter('processing', SegmentStatus::Processing->value)
            ->setParameter('project', $project)
            ->getQuery()
            ->execute();
    }

    /**
     * @return int liczba segmentow skierowanych do ponownego tlumaczenia
     */
    public function resetFailedToPending(Project $project): int
    {
        return (int) $this->createQueryBuilder('s')
            ->update()
            ->set('s.status', ':pending')
            // Literalne wyrazenia DQL, nie parametry: parametr o wartosci null
            // trafia do sterownika bez typu i Doctrine potrafi go odrzucic.
            ->set('s.attempts', '0')
            ->set('s.errorCode', 'NULL')
            ->set('s.errorParams', 'NULL')
            ->where('s.project = :project')
            ->andWhere('s.status = :failed')
            ->setParameter('pending', SegmentStatus::Pending->value)
            ->setParameter('failed', SegmentStatus::Failed->value)
            ->setParameter('project', $project)
            ->getQuery()
            ->execute();
    }

    /**
     * Clears the attempt budget of a single segment so it can be translated
     * again from scratch.
     */
    public function resetAttempts(Segment $segment): void
    {
        $this->createQueryBuilder('s')
            ->update()
            // Literalne wyrazenia DQL, nie parametry: parametr o wartosci null
            // trafia do sterownika bez typu i Doctrine potrafi go odrzucic.
            ->set('s.attempts', '0')
            ->set('s.errorCode', 'NULL')
            ->set('s.errorParams', 'NULL')
            ->where('s.id = :id')
            ->setParameter('id', $segment->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }

    private function countWithStatus(Project $project, SegmentStatus $status): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.project = :project')
            ->andWhere('s.status = :status')
            ->setParameter('project', $project)
            ->setParameter('status', $status->value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
