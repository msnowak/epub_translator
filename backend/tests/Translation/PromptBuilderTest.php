<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\Segment;
use App\Entity\User;
use App\Translation\PromptBuilder;
use PHPUnit\Framework\TestCase;

final class PromptBuilderTest extends TestCase
{
    public function testCarriesModelFromProject(): void
    {
        $project = $this->project();
        $request = (new PromptBuilder())->build($project, $this->segment($project, 'Hello.'), null);

        self::assertSame('llama3.1:8b', $request->model);
    }

    public function testUserPromptContainsTheSourceParagraph(): void
    {
        $project = $this->project();
        $request = (new PromptBuilder())->build($project, $this->segment($project, 'This is [1]important[/1].'), null);

        self::assertStringContainsString('This is [1]important[/1].', $request->userPrompt);
    }

    public function testSystemPromptNamesTheTargetLanguage(): void
    {
        $project = $this->project();
        $request = (new PromptBuilder())->build($project, $this->segment($project, 'Hello.'), null);

        self::assertStringContainsString('Polish', $request->systemPrompt);
    }

    public function testSystemPromptNamesTheSourceLanguageWhenGiven(): void
    {
        $project = $this->project();
        $project->setSourceLanguage('en');

        $request = (new PromptBuilder())->build($project, $this->segment($project, 'Hello.'), null);

        self::assertStringContainsString('English', $request->systemPrompt);
    }

    public function testSystemPromptOmitsSourceLanguageWhenAbsent(): void
    {
        $project = $this->project();
        $request = (new PromptBuilder())->build($project, $this->segment($project, 'Hello.'), null);

        self::assertStringNotContainsString('written in', $request->systemPrompt);
    }

    public function testSystemPromptCarriesTheCustomPrompt(): void
    {
        $project = $this->project();
        $project->setCustomPrompt('Zachowaj styl formalny.');

        $request = (new PromptBuilder())->build($project, $this->segment($project, 'Hello.'), null);

        self::assertStringContainsString('Zachowaj styl formalny.', $request->systemPrompt);
    }

    public function testPreviousSegmentBecomesReferenceContext(): void
    {
        $project = $this->project();
        $previous = $this->segment($project, 'The captain spoke.');
        $previous->setTranslatedText('Kapitan przemówił.');

        $request = (new PromptBuilder())->build($project, $this->segment($project, 'He was tired.'), $previous);

        self::assertStringContainsString('The captain spoke.', $request->userPrompt);
        self::assertStringContainsString('Kapitan przemówił.', $request->userPrompt);
        self::assertStringContainsString('He was tired.', $request->userPrompt);
    }

    public function testPreviousSegmentWithoutTranslationIsIgnored(): void
    {
        $project = $this->project();
        $previous = $this->segment($project, 'The captain spoke.');

        $request = (new PromptBuilder())->build($project, $this->segment($project, 'He was tired.'), $previous);

        self::assertStringNotContainsString('The captain spoke.', $request->userPrompt);
    }

    public function testUnknownLanguageCodeIsPassedThrough(): void
    {
        $user = new User();
        $user->setEmail('owner@example.com');
        $project = new Project($user, 'Książka', 'xx', 'llama3.1:8b', 'book.epub');

        $request = (new PromptBuilder())->build($project, $this->segment($project, 'Hello.'), null);

        self::assertStringContainsString('xx', $request->systemPrompt);
    }

    private function project(): Project
    {
        $user = new User();
        $user->setEmail('owner@example.com');

        return new Project($user, 'Książka', 'pl', 'llama3.1:8b', 'book.epub');
    }

    private function segment(Project $project, string $sourceText): Segment
    {
        return new Segment(new Chapter($project, 0, 'OEBPS/ch1.xhtml'), 0, 0, 0, $sourceText, []);
    }
}
