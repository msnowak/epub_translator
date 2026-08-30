<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    protected function createUser(string $email = 'user@example.com', string $password = UserFactory::DEFAULT_PASSWORD): User
    {
        return UserFactory::create(
            self::getContainer()->get(EntityManagerInterface::class),
            self::getContainer()->get(UserPasswordHasherInterface::class),
            $email,
            $password,
        );
    }

    protected function authenticate(User $user): string
    {
        return self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
    }

    /**
     * @param array<string, mixed>|null $body
     */
    protected function request(
        string $method,
        string $uri,
        ?array $body = null,
        ?string $token = null,
        ?string $contentType = null,
    ): void {
        $server = [
            'CONTENT_TYPE' => $contentType ?? 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            // Symfony\Component\HttpFoundation\Request::create() bakes in
            // 'en-us,en;q=0.5' as a default Accept-Language whenever the
            // caller does not set one - a fixture for tests, not a real
            // client's behaviour. Now that "en" is an enabled locale, that
            // default would silently negotiate English for every test here
            // that sends no header, which is the opposite of what "no
            // header" is meant to exercise. Overriding it back to null
            // makes "no header" in a test actually mean no header.
            'HTTP_ACCEPT_LANGUAGE' => null,
        ];

        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
