<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Security\RefreshTokenManager;
use App\Tests\Support\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RefreshTokenManagerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private RefreshTokenRepository $repository;

    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(RefreshTokenRepository::class);
        $this->hasher = $container->get(UserPasswordHasherInterface::class);
    }

    /**
     * @return list<array{bool}>
     */
    public static function secureFlags(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('secureFlags')]
    public function testTheIssuedCookieCarriesTheConfiguredSecureFlag(bool $secure): void
    {
        $manager = $this->manager($secure);
        $user = $this->user('cookie@example.com');

        self::assertSame($secure, $manager->issue($user)->isSecure());
    }

    #[DataProvider('secureFlags')]
    public function testTheClearingCookieCarriesTheSameSecureFlag(bool $secure): void
    {
        // Ciasteczko kasujace o innych atrybutach niz wydajace nie kasuje
        // niczego, wiec obie sciezki musza czytac ten sam parametr.
        self::assertSame($secure, $this->manager($secure)->revoke(null)->isSecure());
    }

    public function testTheClearingCookieKeepsEveryOtherAttributeInStep(): void
    {
        $manager = $this->manager(true);
        $user = $this->user('paired@example.com');

        $issued = $manager->issue($user);
        $cleared = $manager->revoke(null);

        self::assertSame($issued->getPath(), $cleared->getPath());
        self::assertSame($issued->isHttpOnly(), $cleared->isHttpOnly());
        self::assertSame($issued->getSameSite(), $cleared->getSameSite());
    }

    public function testTheContainerWiresTheSecureFlagFromTheTestEnvironment(): void
    {
        // Poprzednie testy budowaly manager recznie z jawnym boolem, wiec
        // zla nazwa zmiennej w services.yaml nie zostalaby wykryta - tu
        // sciagamy egzemplarz z kontenera, jak robi to reszta aplikacji.
        $manager = self::getContainer()->get(RefreshTokenManager::class);
        $user = $this->user('wired@example.com');

        self::assertFalse($manager->issue($user)->isSecure());
    }

    private function manager(bool $secure): RefreshTokenManager
    {
        return new RefreshTokenManager($this->entityManager, $this->repository, $secure);
    }

    private function user(string $email): User
    {
        return UserFactory::create($this->entityManager, $this->hasher, $email);
    }
}
