<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Chapter;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Entity\WorkerError;
use App\Repository\SegmentRepository;
use App\Tests\Support\ProjectFactory;
use App\Tests\Support\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SegmentRepositoryResetAttemptsTest extends KernelTestCase
{
    public function testClearsTheAttemptBudgetAndTheErrorMessage(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(SegmentRepository::class);

        $segment = $this->exhaustedSegment($entityManager);

        $repository->resetAttempts($segment);
        $entityManager->refresh($segment);

        self::assertSame(0, $segment->getAttempts());
        self::assertNull($segment->getErrorCode());
        self::assertNull($segment->getErrorParams());
    }

    public function testLeavesOtherSegmentsAlone(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(SegmentRepository::class);

        $first = $this->exhaustedSegment($entityManager);
        $second = $this->exhaustedSegment($entityManager);

        $repository->resetAttempts($first);
        $entityManager->refresh($second);

        // Masowy UPDATE bez warunku na identyfikator wyczyscilby cala tabele,
        // a testy patrzace tylko na jeden wiersz by tego nie zauwazyly.
        self::assertSame(3, $second->getAttempts());
        self::assertSame(WorkerError::ModelInvalidTranslation, $second->getErrorCode());
        self::assertSame(['attempts' => 3], $second->getErrorParams());
    }

    private function exhaustedSegment(EntityManagerInterface $entityManager): Segment
    {
        $user = UserFactory::create(
            $entityManager,
            self::getContainer()->get(UserPasswordHasherInterface::class),
            \sprintf('reader-%s@example.com', bin2hex(random_bytes(4))),
        );
        $project = ProjectFactory::create($entityManager, $user);

        $chapter = new Chapter($project, 0, 'OEBPS/ch1.xhtml');
        $entityManager->persist($chapter);

        $segment = new Segment($chapter, 0, 0, 0, 'A paragraph.', []);
        $segment->setStatus(SegmentStatus::Failed);
        $segment->setErrorCode(WorkerError::ModelInvalidTranslation);
        $segment->setErrorParams(['attempts' => 3]);

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $segment->incrementAttempts();
        }

        $entityManager->persist($segment);
        $entityManager->flush();

        return $segment;
    }
}
