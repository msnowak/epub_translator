<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Entity\User;
use App\Ollama\OllamaUnavailableException;
use App\Tests\Support\FakeTranslationEngine;
use App\Tests\Support\RecordingLogger;
use App\Translation\PromptBuilder;
use App\Translation\SegmentTranslator;
use App\Translation\TranslationEngineException;
use App\Translation\TranslationValidator;
use PHPUnit\Framework\TestCase;

final class SegmentTranslatorTest extends TestCase
{
    public function testGoodAnswerMarksSegmentTranslated(): void
    {
        $engine = new FakeTranslationEngine();
        $engine->answerWith('To jest [1]ważne[/1].');

        $segment = $this->segment('This is [1]important[/1].');

        $this->translator($engine)->translate($segment, null);

        self::assertSame(SegmentStatus::Translated, $segment->getStatus());
        self::assertSame('To jest [1]ważne[/1].', $segment->getTranslatedText());
        self::assertSame(1, $segment->getAttempts());
        self::assertNull($segment->getErrorMessage());
    }

    public function testRetriesUntilTheAnswerValidates(): void
    {
        $engine = new FakeTranslationEngine();
        // Pierwsza odpowiedz gubi zeton, druga jest poprawna.
        $engine->answerWith('To jest ważne.', 'To jest [1]ważne[/1].');

        $segment = $this->segment('This is [1]important[/1].');

        $this->translator($engine)->translate($segment, null);

        self::assertSame(SegmentStatus::Translated, $segment->getStatus());
        self::assertSame(2, $segment->getAttempts());
        self::assertSame(2, $engine->callCount());
    }

    public function testExhaustedBudgetMarksSegmentFailed(): void
    {
        $engine = new FakeTranslationEngine();
        $engine->answerWith('To jest ważne.');

        $segment = $this->segment('This is [1]important[/1].');

        $this->translator($engine, maxAttempts: 3)->translate($segment, null);

        self::assertSame(SegmentStatus::Failed, $segment->getStatus());
        self::assertSame(3, $segment->getAttempts());
        self::assertSame(3, $engine->callCount());
        self::assertNotNull($segment->getErrorMessage());
        self::assertNull($segment->getTranslatedText());
    }

    public function testSegmentResumesItsRemainingBudget(): void
    {
        $engine = new FakeTranslationEngine();
        $engine->answerWith('To jest ważne.');

        $segment = $this->segment('This is [1]important[/1].');
        $segment->incrementAttempts();
        $segment->incrementAttempts();

        $this->translator($engine, maxAttempts: 3)->translate($segment, null);

        // Zostala jedna proba z trzech, nie trzy nowe.
        self::assertSame(1, $engine->callCount());
        self::assertSame(SegmentStatus::Failed, $segment->getStatus());
    }

    public function testEngineFailureLeavesSegmentUntouched(): void
    {
        $engine = new FakeTranslationEngine();
        $engine->failWith(new OllamaUnavailableException('Connection refused'));

        $segment = $this->segment('This is [1]important[/1].');

        $this->expectException(TranslationEngineException::class);

        try {
            $this->translator($engine)->translate($segment, null);
        } finally {
            self::assertSame(SegmentStatus::Pending, $segment->getStatus());
            self::assertSame(0, $segment->getAttempts());
            self::assertNull($segment->getTranslatedText());
        }
    }

    public function testPreviousSegmentReachesThePrompt(): void
    {
        $engine = new FakeTranslationEngine();
        $engine->answerWith('Był zmęczony.');

        $project = $this->project();
        $previous = new Segment(new Chapter($project, 0, 'OEBPS/ch1.xhtml'), 0, 0, 0, 'The captain spoke.', []);
        $previous->setTranslatedText('Kapitan przemówił.');

        $segment = new Segment(new Chapter($project, 0, 'OEBPS/ch1.xhtml'), 1, 1, 0, 'He was tired.', []);

        $this->translator($engine)->translate($segment, $previous);

        $request = $engine->lastRequest();
        self::assertNotNull($request);
        self::assertStringContainsString('Kapitan przemówił.', $request->userPrompt);
    }

    public function testLogsWhyTheModelAnswerWasRejected(): void
    {
        $engine = new FakeTranslationEngine();
        // Pierwsza odpowiedz gubi zeton, druga jest poprawna.
        $engine->answerWith('To jest ważne.', 'To jest [1]ważne[/1].');

        $logger = new RecordingLogger();
        $segment = $this->segment('This is [1]important[/1].');

        $this->translator($engine, logger: $logger)->translate($segment, null);

        $records = $logger->records();

        self::assertCount(1, $records);
        self::assertSame('notice', $records[0]['level']);
        // Uzytkownik dostaje komunikat ogolny, bo "dropped token 1" nic mu nie
        // powie - ale bez powodu w logu nikt nie zdiagnozuje segmentu, ktory
        // wrocil jako failed.
        self::assertSame('The translation dropped token 1.', $records[0]['context']['reason'] ?? null);
        self::assertSame((string) $segment->getId(), $records[0]['context']['segment'] ?? null);
        self::assertSame(1, $records[0]['context']['attempt'] ?? null);
    }

    public function testLogsNothingWhenTheFirstAnswerValidates(): void
    {
        $engine = new FakeTranslationEngine();
        $engine->answerWith('To jest [1]ważne[/1].');

        $logger = new RecordingLogger();

        $this->translator($engine, logger: $logger)->translate($this->segment('This is [1]important[/1].'), null);

        // Log jest od odrzucen, nie od postepu - ten ostatni widac po statusach
        // segmentow i w logu Messengera.
        self::assertSame([], $logger->records());
    }

    private function translator(
        FakeTranslationEngine $engine,
        int $maxAttempts = 3,
        ?RecordingLogger $logger = null,
    ): SegmentTranslator {
        return new SegmentTranslator(
            new PromptBuilder(),
            $engine,
            new TranslationValidator(),
            $maxAttempts,
            $logger ?? new RecordingLogger(),
        );
    }

    private function project(): Project
    {
        $user = new User();
        $user->setEmail('owner@example.com');

        return new Project($user, 'Książka', 'pl', 'llama3.1:8b', 'book.epub');
    }

    private function segment(string $sourceText): Segment
    {
        $project = $this->project();

        return new Segment(new Chapter($project, 0, 'OEBPS/ch1.xhtml'), 0, 0, 0, $sourceText, []);
    }
}
