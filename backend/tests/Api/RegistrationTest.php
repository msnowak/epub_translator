<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegistrationTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testUserCanRegister(): void
    {
        $this->post('/api/register', ['email' => 'reader@example.com', 'plainPassword' => 'haslo12345']);

        self::assertResponseStatusCodeSame(201);

        $payload = $this->payload();
        self::assertSame('reader@example.com', $payload['email']);
        self::assertArrayHasKey('id', $payload);
        self::assertArrayNotHasKey('plainPassword', $payload);
        self::assertArrayNotHasKey('password', $payload);
    }

    public function testPasswordIsHashed(): void
    {
        $this->post('/api/register', ['email' => 'reader@example.com', 'plainPassword' => 'haslo12345']);

        $repository = self::getContainer()->get(UserRepository::class);
        $user = $repository->findOneByEmail('reader@example.com');

        self::assertNotNull($user);
        self::assertNotSame('haslo12345', $user->getPassword());

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'haslo12345'));
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testEmailMustBeUnique(): void
    {
        $this->post('/api/register', ['email' => 'reader@example.com', 'plainPassword' => 'haslo12345']);
        $this->post('/api/register', ['email' => 'reader@example.com', 'plainPassword' => 'inne12345']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testEmailMustBeValid(): void
    {
        $this->post('/api/register', ['email' => 'nie-email', 'plainPassword' => 'haslo12345']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testPasswordMustBeAtLeastEightCharacters(): void
    {
        $this->post('/api/register', ['email' => 'reader@example.com', 'plainPassword' => 'krotkie']);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $uri, array $body): void
    {
        $this->client->request('POST', $uri, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $content = (string) $this->client->getResponse()->getContent();

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
