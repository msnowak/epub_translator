<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Ollama\OllamaUnavailableException;
use App\Tests\Support\FakeTranslationEngine;
use App\Translation\TranslationEngineInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class TranslateTryCommandTest extends KernelTestCase
{
    public function testShowsPromptAnswerAndVerdict(): void
    {
        $engine = new FakeTranslationEngine();
        $engine->answerWith('To jest [1]ważne[/1].');

        $tester = $this->tester($engine);
        $exitCode = $tester->execute([
            'text' => 'This is [1]important[/1].',
            '--target' => 'pl',
        ]);

        $output = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('This is [1]important[/1].', $output);
        self::assertStringContainsString('To jest [1]ważne[/1].', $output);
        self::assertStringContainsString('Polish', $output);
    }

    public function testReportsRejectedAnswer(): void
    {
        $engine = new FakeTranslationEngine();
        $engine->answerWith('To jest ważne.');

        $tester = $this->tester($engine);
        $exitCode = $tester->execute([
            'text' => 'This is [1]important[/1].',
            '--target' => 'pl',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('dropped token 1', $tester->getDisplay());
    }

    public function testReportsUnreachableServer(): void
    {
        $engine = new FakeTranslationEngine();
        $engine->failWith(new OllamaUnavailableException('Connection refused'));

        $tester = $this->tester($engine);
        $exitCode = $tester->execute(['text' => 'Hello.', '--target' => 'pl']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Connection refused', $tester->getDisplay());
    }

    private function tester(FakeTranslationEngine $engine): CommandTester
    {
        $kernel = self::bootKernel();
        self::getContainer()->set(TranslationEngineInterface::class, $engine);

        $application = new Application($kernel);

        return new CommandTester($application->find('app:translate:try'));
    }
}
