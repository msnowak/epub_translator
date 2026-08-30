<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\SegmentStatus;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Filters both segment collections by status. Written by hand rather than
 * configured as a SearchFilter because the column is a backed enum: a typo in
 * the query string must not quietly widen the result to the whole book.
 */
final readonly class SegmentStatusFilter implements FilterInterface
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param class-string         $resourceClass
     * @param array<string, mixed> $context
     */
    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];
        $value = $filters['status'] ?? null;

        if (null === $value || '' === $value) {
            return;
        }

        $status = \is_string($value) ? SegmentStatus::tryFrom($value) : null;

        if (null === $status) {
            throw new BadRequestHttpException($this->translator->trans('segment.unknown_status'));
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parameter = $queryNameGenerator->generateParameterName('status');

        $queryBuilder
            ->andWhere(\sprintf('%s.status = :%s', $alias, $parameter))
            ->setParameter($parameter, $status);
    }

    /**
     * @param class-string $resourceClass
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDescription(string $resourceClass): array
    {
        return [
            'status' => [
                'property' => 'status',
                'type' => 'string',
                'required' => false,
                'description' => 'Keeps only the segments in this status.',
                'schema' => ['type' => 'string', 'enum' => array_column(SegmentStatus::cases(), 'value')],
            ],
        ];
    }
}
