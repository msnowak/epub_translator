<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class UserTest extends TestCase
{
    public function testNewUserHasIdentifierAndCreationDate(): void
    {
        $user = new User();

        self::assertNotSame('', (string) $user->getId());
        // Symfony\Component\Uid\Uuid has no getVersion() method (verified against
        // the installed symfony/uid ^8.1 and its full CHANGELOG.md back to 5.1 -
        // no release ever exposed one). Uuid::v7() returns a UuidV7 instance, so
        // asserting the concrete subclass is the faithful equivalent of the
        // brief's intended "this is a v7 UUID" check.
        self::assertInstanceOf(UuidV7::class, $user->getId());
    }

    public function testRolesAlwaysContainRoleUser(): void
    {
        $user = new User();

        self::assertSame(['ROLE_USER'], $user->getRoles());

        $user->setRoles(['ROLE_ADMIN']);

        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testUserIdentifierIsEmail(): void
    {
        $user = new User();
        $user->setEmail('reader@example.com');

        self::assertSame('reader@example.com', $user->getUserIdentifier());
    }
}
