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
        private bool $secureCookie,
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
            ->withPath('/api/token/refresh')
            ->withHttpOnly(true)
            // Ustawiane konfiguracja, bo dev stoi na zwyklym HTTP, a produkcja
            // za TLS-em. Obie sciezki czytaja ten sam parametr - ciasteczko
            // kasujace o innych atrybutach nie kasuje niczego.
            ->withSecure($this->secureCookie)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    /**
     * Consumes the old token and issues a new one (rotation).
     *
     * @return array{0: User, 1: Cookie}
     */
    public function rotate(string $plainToken): array
    {
        $hash = $this->hash($plainToken);
        $token = $this->repository->findOneByHash($hash);

        if (null === $token) {
            throw new InvalidRefreshTokenException('Unknown refresh token.');
        }

        $user = $token->getUser();
        $expired = $token->isExpired(new \DateTimeImmutable());

        // The delete is the claim: the database serialises concurrent DELETEs,
        // so only one request sees a deleted row. Without it two browser tabs
        // refreshing at once would each walk away with a valid session chain.
        if (0 === $this->repository->deleteByHash($hash)) {
            throw new InvalidRefreshTokenException('Refresh token was already consumed.');
        }

        // Expiry is checked after the delete on purpose - an expired token must
        // be consumed rather than left sitting in the table.
        if ($expired) {
            throw new InvalidRefreshTokenException('Refresh token has expired.');
        }

        return [$user, $this->issue($user)];
    }

    /**
     * Ends every session of the token's owner and returns a cookie that clears
     * the browser's. Both cookies are built in this class so their attributes
     * cannot drift apart - a clearing cookie with a different path clears
     * nothing.
     */
    public function revoke(?string $plainToken): Cookie
    {
        if (null !== $plainToken && '' !== $plainToken) {
            $token = $this->repository->findOneByHash($this->hash($plainToken));

            // Wylogowanie konczy wszystkie sesje uzytkownika, nie tylko te
            // z przegladarki, ktora kliknela przycisk. Tozsamosc bierze sie
            // z samego ciasteczka, wiec dziala takze wtedy, gdy JWT juz
            // wygasl. Bez sprawdzania, czy cokolwiek skasowano: wylogowanie
            // ma byc idempotentne, a token juz zuzyty to nie blad uzytkownika.
            if (null !== $token) {
                $this->repository->deleteAllForUser($token->getUser());
            }
        }

        return Cookie::create(self::COOKIE_NAME, '')
            // Jeden, nie zero: Symfony czyta zero jako "ciasteczko sesyjne",
            // wiec z data wygasniecia rowna epoce przegladarka dostalaby
            // ciasteczko do zachowania zamiast do skasowania.
            ->withExpires(1)
            ->withPath('/api/token/refresh')
            ->withHttpOnly(true)
            // Ten sam parametr co w issue() - patrz komentarz tam.
            ->withSecure($this->secureCookie)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    private function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
