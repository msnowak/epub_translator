<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Segment;
use App\Preview\SegmentPlaceholderExposer;
use App\Repository\ChapterRepository;
use App\Security\ProjectVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Segments hang off a chapter, which hangs off a project - the project still
 * decides who may see them. A collection query filtered by OwnerExtension
 * simply comes back empty for a foreign chapter, and an empty list is
 * indistinguishable from "yours, but nothing in it yet" - so existence of the
 * parent chapter has to be checked explicitly, the same way
 * ChapterCollectionProvider checks the parent project.
 *
 * @implements ProviderInterface<Segment>
 */
final readonly class SegmentCollectionProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<Segment> $collectionProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
        private ChapterRepository $chapters,
        private Security $security,
        private SegmentPlaceholderExposer $exposer,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $chapterId = $uriVariables['chapterId'] ?? null;

        $chapter = match (true) {
            $chapterId instanceof Uuid => $this->chapters->find($chapterId),
            \is_string($chapterId) && Uuid::isValid($chapterId) => $this->chapters->find(Uuid::fromString($chapterId)),
            default => null,
        };

        if (null === $chapter || !$this->security->isGranted(ProjectVoter::VIEW, $chapter->getProject())) {
            throw new NotFoundHttpException('Nie znaleziono rozdziału.');
        }

        $segments = $this->collectionProvider->provide($operation, $uriVariables, $context);

        if (!is_iterable($segments)) {
            // Ten provider jest podpiety wylacznie pod GetCollection, wiec
            // opakowany provider zawsze oddaje kolekcje.
            throw new \LogicException('The Doctrine collection provider returned a single item.');
        }

        // Paginator API Platform cache'uje swoj iterator, wiec przejscie po
        // kolekcji tutaj nie robi drugiego zapytania.
        $this->exposer->exposeAll($segments);

        return $segments;
    }
}
