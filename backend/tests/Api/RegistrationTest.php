<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Repository\UserRepository;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegistrationTest extends ApiTestCase
{
    public function testUserCanRegister(): void
    {
        $this->request('POST', '/api/register', ['email' => 'reader@example.com', 'plainPassword' => 'haslo12345']);

        self::assertResponseStatusCodeSame(201);

        $payload = $this->payload();
        self::assertSame('reader@example.com', $payload['email']);
        self::assertArrayHasKey('id', $payload);
        self::assertArrayNotHasKey('plainPassword', $payload);
        self::assertArrayNotHasKey('password', $payload);
    }

    public function testPasswordIsHashed(): void
    {
        $this->request('POST', '/api/register', ['email' => 'reader@example.com', 'plainPassword' => 'haslo12345']);

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
        $this->request('POST', '/api/register', ['email' => 'reader@example.com', 'plainPassword' => 'haslo12345']);
        $this->request('POST', '/api/register', ['email' => 'reader@example.com', 'plainPassword' => 'inne12345']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testEmailMustBeValid(): void
    {
        $this->request('POST', '/api/register', ['email' => 'nie-email', 'plainPassword' => 'haslo12345']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testPasswordMustBeAtLeastEightCharacters(): void
    {
        $this->request('POST', '/api/register', ['email' => 'reader@example.com', 'plainPassword' => 'krotkie']);

        self::assertResponseStatusCodeSame(422);
    }
}
