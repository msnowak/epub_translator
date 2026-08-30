<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\ProjectStatus;
use App\Entity\Segment;
use App\Entity\WorkerError;
use App\Message\TranslateNextSegmentMessage;
use App\Ollama\OllamaUnavailableException;
use App\Repository\SegmentRepository;
use App\Tests\Support\FakeTranslationEngine;
use App\Tests\Support\ProjectFactory;
use App\Tests\Support\UserFactory;
use App\Translation\TranslationEngineInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TranslateNextSegmentHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private FakeTranslationEngine $engine;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->engine = new FakeTranslationEngine();
        self::getContainer()->set(TranslationEngineInterface::class, $this->engine);
    }

    public function testChainTranslatesEverySegmentAndCompletesTheProject(): void
    {
        $project = $this->translatingProject();
        $chapter = $this->chapter($project, 0);
        $this->segment($chapter, 0, 'One.');
        $this->segment($chapter, 1, 'Two.');
        $this->entityManager->flush();

        $this->runChain($project);

        $this->entityManager->refresh($project);

        self::assertSame(ProjectStatus::Completed, $project->getStatus());
        self::assertSame(2, $this->counts($project)['translated'] ?? 0);
        self::assertNull($project->getErrorCode());
    }

    public function testProjectWithAFailedSegmentCompletesWithErrors(): void
    {
        // Zeton w zrodle, ktorego model nigdy nie odda - segment wyczerpie budzet.
        $project = $this->translatingProject();
        $chapter = $this->chapter($project, 0);
        $this->segment($chapter, 0, 'This is [1]important[/1].');
        $this->entityManager->flush();

        $this->engine->answerWith('To jest ważne.');

        $this->runChain($project);

        $this->entityManager->refresh($project);

        self::assertSame(ProjectStatus::CompletedWithErrors, $project->getStatus());
        self::assertSame(1, $this->counts($project)['failed'] ?? 0);
    }

    public function testUnreachableEnginePausesTheProjectAndKeepsTheSegment(): void
    {
        $project = $this->translatingProject();
        $chapter = $this->chapter($project, 0);
        $this->segment($chapter, 0, 'One.');
        $this->entityManager->flush();

        $this->engine->failWith(new OllamaUnavailableException('Connection refused'));

        $this->runChain($project);

        $this->entityManager->refresh($project);

        self::assertSame(ProjectStatus::Paused, $project->getStatus());
        self::assertSame(WorkerError::OllamaUnreachableProject, $project->getErrorCode());
        self::assertSame(1, $this->counts($project)['pending'] ?? 0);
        self::assertSame(0, $this->counts($project)['processing'] ?? 0);
    }

    public function testChainStopsWhenTheProjectIsNoLongerTranslating(): void
    {
        $project = $this->translatingProject();
        $chapter = $this->chapter($project, 0);
        $this->segment($chapter, 0, 'One.');
        $project->setStatus(ProjectStatus::Paused);
        $this->entityManager->flush();

        $this->runChain($project);

        self::assertSame(0, $this->engine->callCount());
        self::assertSame(1, $this->counts($project)['pending'] ?? 0);
    }

    public function testChainOnACancelledProjectLeavesItCancelled(): void
    {
        $project = $this->translatingProject();
        $chapter = $this->chapter($project, 0);
        $this->segment($chapter, 0, 'One.');
        $project->setStatus(ProjectStatus::Cancelled);
        $this->entityManager->flush();

        $this->runChain($project);

        $this->entityManager->refresh($project);

        self::assertSame(ProjectStatus::Cancelled, $project->getStatus());
    }

    public function testMissingProjectIsIgnored(): void
    {
        self::getContainer()->get(MessageBusInterface::class)->dispatch(
            new TranslateNextSegmentMessage('01920000-0000-7000-8000-000000000000'),
        );

        self::assertSame(0, $this->engine->callCount());
    }

    public function testSegmentsAreTranslatedInSpineOrder(): void
    {
        $project = $this->translatingProject();
        $second = $this->chapter($project, 1);
        $first = $this->chapter($project, 0);
        $this->segment($second, 0, 'Second chapter.');
        $this->segment($first, 0, 'First chapter.');
        $this->entityManager->flush();

        $this->engine->answerWith('Pierwszy.', 'Drugi.');

        $this->runChain($project);

        $segments = self::getContainer()->get(SegmentRepository::class)
            ->findBy(['project' => $project]);

        $bySource = [];
        foreach ($segments as $segment) {
            $bySource[$segment->getSourceText()] = $segment->getTranslatedText();
        }

        self::assertSame('Pierwszy.', $bySource['First chapter.']);
        self::assertSame('Drugi.', $bySource['Second chapter.']);
    }

    /**
     * @return array<string, int>
     */
    private function counts(Project $project): array
    {
        return self::getContainer()->get(SegmentRepository::class)->countByStatus($project);
    }

    private function runChain(Project $project): void
    {
        // Transport async to sync:// w testach, wiec dispatch przerabia caly
        // lancuch w tym samym procesie, zanim wroci.
        self::getContainer()->get(MessageBusInterface::class)->dispatch(
            new TranslateNextSegmentMessage((string) $project->getId()),
        );
    }

    private function translatingProject(): Project
    {
        $user = UserFactory::create(
            $this->entityManager,
            self::getContainer()->get(UserPasswordHasherInterface::class),
        );

        $project = ProjectFactory::create($this->entityManager, $user);
        $project->setStatus(ProjectStatus::Translating);

        return $project;
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
