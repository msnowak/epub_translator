<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;

final readonly class RefreshTokenManager
{
    public const string COOKIE_NAME = 'refresh_token';
    private const int LIFETIME_DAYS = 30;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RefreshTokenRepository $repository,
    ) {
    }

    public function issue(User $user): Cookie
    {
        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable(\sprintf('+%d days', self::LIFETIME_DAYS));

        $this->entityManager->persist(new RefreshToken($user, $this->hash($plainToken), $expiresAt));
        $this->entityManager->flush();

        return Cookie::create(self::COOKIE_NAME, $plainToken)
            ->withExpires($expiresAt)
            ->withPath('/api')
            ->withHttpOnly(true)
            ->withSecure(false)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    /**
     * Zuzywa stary token i wystawia nowy (rotacja).
     *
     * @return array{0: User, 1: Cookie}
     */
    public function rotate(string $plainToken): array
    {
        $token = $this->repository->findOneByHash($this->hash($plainToken));

        if (null === $token) {
            throw new InvalidRefreshTokenException('Unknown refresh token.');
        }

        $user = $token->getUser();

        $this->entityManager->remove($token);
        $this->entityManager->flush();

        if ($token->isExpired(new \DateTimeImmutable())) {
            throw new InvalidRefreshTokenException('Refresh token has expired.');
        }

        return [$user, $this->issue($user)];
    }

    private function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
