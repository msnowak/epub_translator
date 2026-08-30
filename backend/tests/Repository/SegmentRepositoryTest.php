<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Entity\WorkerError;
use App\Repository\SegmentRepository;
use App\Tests\Support\ProjectFactory;
use App\Tests\Support\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SegmentRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private SegmentRepository $segments;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->segments = self::getContainer()->get(SegmentRepository::class);
    }

    public function testClaimTakesSpineOrderBeforePosition(): void
    {
        $project = $this->project();
        $second = $this->chapter($project, 1);
        $first = $this->chapter($project, 0);

        $this->segment($second, position: 0, sourceText: 'Drugi rozdział');
        $this->segment($first, position: 1, sourceText: 'Pierwszy rozdział, drugi akapit');
        $this->segment($first, position: 0, sourceText: 'Pierwszy rozdział, pierwszy akapit');
        $this->entityManager->flush();

        $claimed = $this->segments->claimNextPending($project);

        self::assertNotNull($claimed);
        self::assertSame('Pierwszy rozdział, pierwszy akapit', $claimed->getSourceText());
        self::assertSame(SegmentStatus::Processing, $claimed->getStatus());
    }

    public function testClaimSkipsSegmentsThatAreNotPending(): void
    {
        $project = $this->project();
        $chapter = $this->chapter($project, 0);

        $done = $this->segment($chapter, position: 0, sourceText: 'Zrobione');
        $done->setStatus(SegmentStatus::Translated);
        $this->segment($chapter, position: 1, sourceText: 'Do zrobienia');
        $this->entityManager->flush();

        $claimed = $this->segments->claimNextPending($project);

        self::assertNotNull($claimed);
        self::assertSame('Do zrobienia', $claimed->getSourceText());
    }

    public function testClaimReturnsNullWhenNothingIsPending(): void
    {
        $project = $this->project();
        $chapter = $this->chapter($project, 0);

        $done = $this->segment($chapter, position: 0, sourceText: 'Zrobione');
        $done->setStatus(SegmentStatus::Translated);
        $this->entityManager->flush();

        self::assertNull($this->segments->claimNextPending($project));
    }

    public function testClaimIgnoresOtherProjects(): void
    {
        $project = $this->project();
        $other = ProjectFactory::create($this->entityManager, $project->getOwner(), 'Inna książka');

        $this->segment($this->chapter($other, 0), position: 0, sourceText: 'Cudzy akapit');
        $this->entityManager->flush();

        self::assertNull($this->segments->claimNextPending($project));
    }

    public function testFindsPreviousTranslatedSegmentInTheSameChapter(): void
    {
        $project = $this->project();
        $chapter = $this->chapter($project, 0);

        $first = $this->segment($chapter, position: 0, sourceText: 'Pierwszy');
        $first->setStatus(SegmentStatus::Translated);
        $first->setTranslatedText('Pierwszy przetłumaczony');
        $current = $this->segment($chapter, position: 1, sourceText: 'Drugi');
        $this->entityManager->flush();

        $previous = $this->segments->findPreviousTranslated($current);

        self::assertNotNull($previous);
        self::assertSame('Pierwszy przetłumaczony', $previous->getTranslatedText());
    }

    public function testPreviousSegmentIsNullAtTheStartOfAChapter(): void
    {
        $project = $this->project();
        $chapter = $this->chapter($project, 0);

        $first = $this->segment($chapter, position: 0, sourceText: 'Pierwszy');
        $this->entityManager->flush();

        self::assertNull($this->segments->findPreviousTranslated($first));
    }

    public function testResetsOrphanedProcessingSegments(): void
    {
        $project = $this->project();
        $chapter = $this->chapter($project, 0);

        $orphan = $this->segment($chapter, position: 0, sourceText: 'Porzucony');
        $orphan->setStatus(SegmentStatus::Processing);
        $this->entityManager->flush();

        self::assertSame(1, $this->segments->resetProcessingToPending($project));
        self::assertFalse($this->segments->hasProcessing($project));
    }

    public function testResetsFailedSegmentsAndClearsTheirBudget(): void
    {
        $project = $this->project();
        $chapter = $this->chapter($project, 0);

        $broken = $this->segment($chapter, position: 0, sourceText: 'Zepsuty');
        $broken->setStatus(SegmentStatus::Failed);
        $broken->setErrorCode(WorkerError::ModelInvalidTranslation);
        $broken->setErrorParams(['attempts' => 3]);
        $broken->incrementAttempts();
        $broken->incrementAttempts();
        $broken->incrementAttempts();
        $this->entityManager->flush();

        self::assertSame(1, $this->segments->resetFailedToPending($project));
        self::assertFalse($this->segments->hasFailed($project));

        $this->entityManager->refresh($broken);

        self::assertSame(SegmentStatus::Pending, $broken->getStatus());
        self::assertSame(0, $broken->getAttempts());
        self::assertNull($broken->getErrorCode());
        self::assertNull($broken->getErrorParams());
    }

    public function testReportsFailedSegments(): void
    {
        $project = $this->project();
        $chapter = $this->chapter($project, 0);

        $broken = $this->segment($chapter, position: 0, sourceText: 'Zepsuty');
        $broken->setStatus(SegmentStatus::Failed);
        $this->entityManager->flush();

        self::assertTrue($this->segments->hasFailed($project));
    }

    private function project(): Project
    {
        $user = UserFactory::create(
            $this->entityManager,
            self::getContainer()->get(UserPasswordHasherInterface::class),
        );

        return ProjectFactory::create($this->entityManager, $user);
    }

    private function chapter(Project $project, int $spineOrder): Chapter
    {
        $chapter = new Chapter($project, $spineOrder, \sprintf('OEBPS/ch%d.xhtml', $spineOrder));
        $this->entityManager->persist($chapter);

        return $chapter;
    }

    private function segment(Chapter $chapter, int $position, string $sourceText): Segment
    {
        $segment = new Segment($chapter, $position, $position, 0, $sourceText, []);
        $this->entityManager->persist($segment);

        return $segment;
    }
}
