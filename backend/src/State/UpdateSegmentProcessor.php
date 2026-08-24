<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Preview\SegmentPlaceholderExposer;
use App\Translation\TranslationRejectedException;
use App\Translation\TranslationValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * A human correction goes through the same token validator as a model answer.
 * A dropped token is a lost formatting tag and an invented one ends up in the
 * finished book as a literal [3] - without this check either would surface
 * only after the reader downloads the file.
 *
 * @implements ProcessorInterface<mixed, Segment>
 */
final readonly class UpdateSegmentProcessor implements ProcessorInterface
{
    public function __construct(
        private TranslationValidator $validator,
        private EntityManagerInterface $entityManager,
        private SegmentPlaceholderExposer $exposer,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Segment
    {
        if (!$data instanceof Segment) {
            throw new NotFoundHttpException('Nie znaleziono segmentu.');
        }

        $translation = $data->getTranslatedText();

        if (null === $translation) {
            throw new UnprocessableEntityHttpException('Podaj treść tłumaczenia.');
        }

        try {
            $this->validator->validate($data->getSourceText(), $translation);
        } catch (TranslationRejectedException) {
            // Techniczny powod zostaje w wyjatku; uzytkownikowi potrzebna jest
            // wskazowka, co poprawic, nie nazwa zlamanej reguly.
            throw new UnprocessableEntityHttpException(
                'Tłumaczenie musi zawierać te same znaczniki formatowania co oryginał, w tej samej liczbie.',
            );
        }

        // Reczna poprawka jest ostateczna: automatyczne ponawianie nigdy jej
        // nie nadpisze, zrobi to wylacznie jawny retranslate.
        $data->setStatus(SegmentStatus::Edited);
        $data->setErrorMessage(null);
        $this->entityManager->flush();

        $this->exposer->expose($data);

        return $data;
    }
}
