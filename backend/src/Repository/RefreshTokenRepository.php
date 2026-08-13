<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
final class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findOneByHash(string $tokenHash): ?RefreshToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * Atomically consumes the token. Returns the number of deleted rows, so of
     * two concurrent requests carrying the same cookie exactly one sees 1 and
     * the other sees 0 - without this both would mint valid sessions.
     */
    public function deleteByHash(string $tokenHash): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->where('t.tokenHash = :hash')
            ->setParameter('hash', $tokenHash)
            ->getQuery()
            ->execute();
    }
}
