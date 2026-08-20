<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Project;
use App\Storage\ProjectStorage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeleteProjectProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Project, void> $removeProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private ProjectStorage $storage,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof Project) {
            throw new \LogicException('The delete-project processor only handles Project instances.');
        }

        // Pliki kasujemy przed wierszem: gdyby kasowanie z bazy sie nie udalo,
        // projekt zostaje widoczny i mozna sprobowac ponownie. Odwrotna
        // kolejnosc zostawialaby osierocone pliki bez sladu w bazie.
        $this->storage->delete($data);

        $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}
