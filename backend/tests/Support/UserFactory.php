<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFactory
{
    public const string DEFAULT_PASSWORD = 'haslo12345';

    public static function create(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $hasher,
        string $email = 'user@example.com',
        string $password = self::DEFAULT_PASSWORD,
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER']);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
