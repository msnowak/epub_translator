<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Chapter;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Entity\User;
use App\Entity\WorkerError;
use App\Ollama\OllamaUnavailableException;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeTranslationEngine;
use App\Tests\Support\ProjectFactory;
use App\Translation\TranslationEngineInterface;
use Doctrine\ORM\EntityManagerInterface;

final class SegmentRetranslateTest extends ApiTestCase
{
    private FakeTranslationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FakeTranslationEngine();
        self::getContainer()->set(TranslationEngineInterface::class, $this->engine);
    }

    public function testRetranslatesAFailedSegment(): void
    {
        $owner = $this->createUser();
        $segment = $this->segment($owner, 'This is important.', SegmentStatus::Failed);
        $this->engine->answerWith('To jest ważne.');

        $this->request('POST', '/api/segments/'.$segment->getId().'/retranslate', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->refresh($segment);

        self::assertSame(SegmentStatus::Translated, $segment->getStatus());
        self::assertSame('To jest ważne.', $segment->getTranslatedText());
    }

    public function testRetranslateExposesPreviewPlaceholdersForTheEditor(): void
    {
        // Ten sam powod co dla UpdateSegmentProcessor (patrz SegmentEditTest):
        // RetranslateSegmentProcessor tez wola SegmentPlaceholderExposer::
        // expose() wprost, wiec nic nie gwarantuje, ze ta wartosc przetrwa
        // akurat te sciezke bez wlasnego testu.
        $owner = $this->createUser();
        $segment = $this->segment($owner, 'This is [1]important[/1].', SegmentStatus::Failed, ['1' => '<em>']);
        $this->engine->answerWith('To jest [1]ważne[/1].');

        $this->request('POST', '/api/segments/'.$segment->getId().'/retranslate', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();
        self::assertSame(['1' => '<em>'], $this->payload()['previewPlaceholders']);
    }

    public function testRetranslateOverwritesAManualEdit(): void
    {
        // Reczna poprawka jest chroniona przed automatem, ale nie przed jawnym
        // zadaniem uzytkownika.
        $owner = $this->createUser();
        $segment = $this->segment($owner, 'This is important.', SegmentStatus::Edited);
        $this->engine->answerWith('Wersja od modelu.');

        $this->request('POST', '/api/segments/'.$segment->getId().'/retranslate', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->refresh($segment);

        self::assertSame('Wersja od modelu.', $segment->getTranslatedText());
    }

    public function testRetranslateClearsASpentAttemptBudget(): void
    {
        $owner = $this->createUser();
        $segment = $this->segment($owner, 'This is important.', SegmentStatus::Failed);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $segment->incrementAttempts();
        $segment->incrementAttempts();
        $segment->incrementAttempts();
        $entityManager->flush();

        $this->engine->answerWith('To jest ważne.');

        $this->request('POST', '/api/segments/'.$segment->getId().'/retranslate', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();

        $entityManager->refresh($segment);

        // Bez zerowania budzetu segment padlby przed pierwszym zapytaniem modelu.
        self::assertSame(SegmentStatus::Translated, $segment->getStatus());
    }

    public function testRefusesASegmentThatIsBeingTranslated(): void
    {
        $owner = $this->createUser();
        $segment = $this->segment($owner, 'This is important.', SegmentStatus::Processing);

        $this->request('POST', '/api/segments/'.$segment->getId().'/retranslate', token: $this->authenticate($owner));

        self::assertResponseStatusCodeSame(409);
        self::assertArrayHasKey('detail', $this->payload());
    }

    public function testUnreachableEngineLeavesTheSegmentFailed(): void
    {
        $owner = $this->createUser();
        $segment = $this->segment($owner, 'This is important.', SegmentStatus::Failed);
        $this->engine->failWith(new OllamaUnavailableException('Connection refused'));

        $this->request('POST', '/api/segments/'.$segment->getId().'/retranslate', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->refresh($segment);

        // Pojedyncze ponowienie nie pauzuje projektu - nie ma tu lancucha do
        // zatrzymania. Segment wraca do stanu, z ktorego mozna sprobowac znowu.
        self::assertSame(SegmentStatus::Failed, $segment->getStatus());
        self::assertSame(WorkerError::OllamaUnreachableSegment, $segment->getErrorCode());
    }

    public function testStrangerCannotRetranslateSomeoneElsesSegment(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');
        $segment = $this->segment($owner, 'This is important.', SegmentStatus::Failed);

        $this->request('POST', '/api/segments/'.$segment->getId().'/retranslate', token: $this->authenticate($stranger));

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param array<array-key, string> $placeholders
     */
    private function segment(User $owner, string $sourceText, SegmentStatus $status, array $placeholders = []): Segment
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $chapter = new Chapter($project, 0, 'OEBPS/ch1.xhtml');
        $entityManager->persist($chapter);

        $segment = new Segment($chapter, 0, 0, 0, $sourceText, $placeholders);
        $segment->setStatus($status);
        $entityManager->persist($segment);
        $entityManager->flush();

        return $segment;
    }
}
