<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\OllamaPingCommand;
use App\Ollama\OllamaClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OllamaPingCommandTest extends TestCase
{
    public function testReportsModelsOnSuccess(): void
    {
        $http = new MockHttpClient(new MockResponse('{"models":[{"name":"llama3.1:8b"}]}', [
            'response_headers' => ['content-type' => 'application/json'],
        ]));

        $tester = new CommandTester(new OllamaPingCommand(new OllamaClient($http), 'http://host.docker.internal:11434'));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('http://host.docker.internal:11434', $tester->getDisplay());
        self::assertStringContainsString('llama3.1:8b', $tester->getDisplay());
    }

    public function testReportsFailureWhenServerUnreachable(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new TransportException('Connection refused');
        });

        $tester = new CommandTester(new OllamaPingCommand(new OllamaClient($http), 'http://host.docker.internal:11434'));
        $exitCode = $tester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Connection refused', $tester->getDisplay());
        self::assertStringContainsString('OLLAMA_HOST', $tester->getDisplay());
    }
}
