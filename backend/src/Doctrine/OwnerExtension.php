<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Filtruje zapytania na poziomie SQL, nie po stronie serializacji. Dzieki temu
 * cudzy projekt nie istnieje z punktu widzenia API - odpowiedz to 404, a nie 403,
 * wiec samo istnienie zasobu nie wycieka.
 */
final readonly class OwnerExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    /**
     * @param class-string $resourceClass
     * @param array<string, mixed> $context
     */
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->restrict($queryBuilder, $resourceClass);
    }

    /**
     * @param class-string $resourceClass
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $uriVariables,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->restrict($queryBuilder, $resourceClass);
    }

    /**
     * @param class-string $resourceClass
     */
    private function restrict(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (Project::class !== $resourceClass) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            // Firewall nie powinien tu dopuscic, ale gdyby kiedys dopuscil,
            // pusty wynik jest bezpieczniejszy niz pelna lista.
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere(\sprintf('%s.owner = :owner', $alias))
            ->setParameter('owner', $user);
    }
}
