<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\ProjectStatus;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\SegmentRepository;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeTranslationEngine;
use App\Tests\Support\ProjectFactory;
use App\Translation\TranslationEngineInterface;
use Doctrine\ORM\EntityManagerInterface;

final class ProjectControlTest extends ApiTestCase
{
    private FakeTranslationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FakeTranslationEngine();
        self::getContainer()->set(TranslationEngineInterface::class, $this->engine);
    }

    public function testStartTranslatesTheWholeProject(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithSegments($owner, 2);

        $this->request('POST', '/api/projects/'.$project->getId().'/start', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();

        $reloaded = self::getContainer()->get(ProjectRepository::class)->find($project->getId());
        self::assertNotNull($reloaded);
        // Transport async to sync:// w testach, wiec caly lancuch przerobil sie
        // w trakcie zadania i projekt jest juz domkniety.
        self::assertSame(ProjectStatus::Completed, $reloaded->getStatus());
    }

    public function testStartRejectsAProjectThatIsStillParsing(): void
    {
        $owner = $this->createUser();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner, 'Książka', ProjectStatus::Parsing);

        $this->request('POST', '/api/projects/'.$project->getId().'/start', token: $this->authenticate($owner));

        self::assertResponseStatusCodeSame(409);
        self::assertArrayHasKey('detail', $this->payload());
    }

    public function testStartReleasesSegmentsOrphanedByAKilledWorker(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithSegments($owner, 1);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $segments = self::getContainer()->get(SegmentRepository::class)->findBy(['project' => $project]);
        $segments[0]->setStatus(SegmentStatus::Processing);
        $entityManager->flush();

        $this->request('POST', '/api/projects/'.$project->getId().'/start', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();

        $counts = self::getContainer()->get(SegmentRepository::class)->countByStatus($project);
        self::assertSame(1, $counts['translated'] ?? 0);
    }

    public function testPauseStopsAProjectThatIsTranslating(): void
    {
        $owner = $this->createUser();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner, 'Książka', ProjectStatus::Translating);

        $this->request('POST', '/api/projects/'.$project->getId().'/pause', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();
        self::assertSame('paused', $this->payload()['status']);
    }

    public function testPauseRejectsAProjectThatIsNotRunning(): void
    {
        $owner = $this->createUser();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $this->request('POST', '/api/projects/'.$project->getId().'/pause', token: $this->authenticate($owner));

        self::assertResponseStatusCodeSame(409);
    }

    public function testResumePicksTheWorkBackUp(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithSegments($owner, 1, ProjectStatus::Paused);

        $this->request('POST', '/api/projects/'.$project->getId().'/resume', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();

        $counts = self::getContainer()->get(SegmentRepository::class)->countByStatus($project);
        self::assertSame(1, $counts['translated'] ?? 0);
    }

    public function testCancelStopsAPausedProject(): void
    {
        $owner = $this->createUser();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner, 'Książka', ProjectStatus::Paused);

        $this->request('POST', '/api/projects/'.$project->getId().'/cancel', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();
        self::assertSame('cancelled', $this->payload()['status']);
    }

    public function testRetryFailedClearsTheBudgetAndTranslatesAgain(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithSegments($owner, 1, ProjectStatus::CompletedWithErrors);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $segments = self::getContainer()->get(SegmentRepository::class)->findBy(['project' => $project]);
        $segments[0]->setStatus(SegmentStatus::Failed);
        $segments[0]->incrementAttempts();
        $segments[0]->incrementAttempts();
        $segments[0]->incrementAttempts();
        $entityManager->flush();

        $this->request('POST', '/api/projects/'.$project->getId().'/retry-failed', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();

        $counts = self::getContainer()->get(SegmentRepository::class)->countByStatus($project);
        self::assertSame(1, $counts['translated'] ?? 0);
    }

    public function testStrangerCannotControlSomeoneElsesProject(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $this->request('POST', '/api/projects/'.$project->getId().'/start', token: $this->authenticate($stranger));

        self::assertResponseStatusCodeSame(404);
    }

    public function testControllingAProjectThatDoesNotExistIsAlsoNotFound(): void
    {
        // Ten sam kod co przy cudzym projekcie: z zewnatrz nie da sie odroznic
        // "nie masz dostepu" od "nie ma takiego projektu".
        $owner = $this->createUser();

        $this->request(
            'POST',
            '/api/projects/01920000-0000-7000-8000-000000000000/start',
            token: $this->authenticate($owner),
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testControlRequiresAuthentication(): void
    {
        $owner = $this->createUser();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $this->request('POST', '/api/projects/'.$project->getId().'/start');

        self::assertResponseStatusCodeSame(401);
    }

    private function projectWithSegments(
        User $owner,
        int $count,
        ProjectStatus $status = ProjectStatus::Ready,
    ): Project {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner, 'Książka', $status);

        $chapter = new Chapter($project, 0, 'OEBPS/ch1.xhtml');
        $entityManager->persist($chapter);

        for ($position = 0; $position < $count; ++$position) {
            $entityManager->persist(
                new Segment($chapter, $position, $position, 0, \sprintf('Paragraph %d.', $position), []),
            );
        }

        $entityManager->flush();

        return $project;
    }
}
