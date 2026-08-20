<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;

final class ProjectIsolationTest extends ApiTestCase
{
    public function testCollectionShowsOnlyOwnProjects(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        ProjectFactory::create($entityManager, $owner, 'Moja książka');
        ProjectFactory::create($entityManager, $stranger, 'Cudza książka');

        $this->request('GET', '/api/projects', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();

        // payload() opisuje ogolna mape JSON-a; kolekcja jest lista obiektow.
        /** @var list<array<string, mixed>> $payload */
        $payload = $this->payload();
        self::assertCount(1, $payload);
        self::assertSame('Moja książka', $payload[0]['title']);
    }

    public function testStrangerCannotReadSomeoneElsesProject(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner, 'Moja książka');

        $this->request('GET', '/api/projects/'.$project->getId(), token: $this->authenticate($stranger));

        self::assertResponseStatusCodeSame(404);
    }

    public function testOwnerCanReadOwnProject(): void
    {
        $owner = $this->createUser('owner@example.com');

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner, 'Moja książka');

        $this->request('GET', '/api/projects/'.$project->getId(), token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();
        self::assertSame('Moja książka', $this->payload()['title']);
    }

    public function testAnonymousRequestIsRejected(): void
    {
        $this->request('GET', '/api/projects');

        self::assertResponseStatusCodeSame(401);
    }
}
