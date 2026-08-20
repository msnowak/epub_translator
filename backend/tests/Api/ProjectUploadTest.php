<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Repository\ProjectRepository;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\EpubBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProjectUploadTest extends ApiTestCase
{
    public function testUploadCreatesProject(): void
    {
        $token = $this->authenticate($this->createUser());
        $path = EpubBuilder::create()->withChapter('ch1.xhtml', '<p>Hello</p>')->build();

        $this->upload($token, $path, [
            'title' => 'Moja książka',
            'targetLanguage' => 'pl',
            'ollamaModel' => 'llama3.1:8b',
        ]);

        self::assertResponseStatusCodeSame(201);

        $payload = $this->payload();
        self::assertSame('Moja książka', $payload['title']);
        // Na produkcji odpowiedz niesie status "parsing", bo rozdzialy powstaja
        // w workerze. W testach transport async to sync://, wiec parsowanie
        // konczy sie zanim odpowiedz zostanie zserializowana. Statusy pilnuje
        // ProjectParsingTest; tutaj chodzi o to, ze pole w ogole wyjezdza.
        self::assertSame('ready', $payload['status']);
        self::assertArrayNotHasKey('storagePath', $payload);

        $project = self::getContainer()->get(ProjectRepository::class)->find($payload['id']);
        self::assertNotNull($project);
        self::assertNotNull($project->getStoragePath());
        self::assertFileExists($project->getStoragePath());
    }

    public function testUploadRejectsFileThatIsNotAnEpub(): void
    {
        $token = $this->authenticate($this->createUser());
        $path = EpubBuilder::create()->corrupted()->build();

        $this->upload($token, $path, [
            'title' => 'Moja książka',
            'targetLanguage' => 'pl',
            'ollamaModel' => 'llama3.1:8b',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUploadRequiresTargetLanguage(): void
    {
        $token = $this->authenticate($this->createUser());
        $path = EpubBuilder::create()->withChapter('ch1.xhtml', '<p>Hello</p>')->build();

        $this->upload($token, $path, [
            'title' => 'Moja książka',
            'ollamaModel' => 'llama3.1:8b',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUploadRequiresAuthentication(): void
    {
        $path = EpubBuilder::create()->withChapter('ch1.xhtml', '<p>Hello</p>')->build();

        $this->upload(null, $path, [
            'title' => 'Moja książka',
            'targetLanguage' => 'pl',
            'ollamaModel' => 'llama3.1:8b',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @param array<string, string> $fields
     */
    private function upload(?string $token, string $path, array $fields): void
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];

        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request(
            'POST',
            '/api/projects',
            parameters: $fields,
            files: ['file' => new UploadedFile($path, 'book.epub', 'application/epub+zip', test: true)],
            server: $server,
        );
    }
}
