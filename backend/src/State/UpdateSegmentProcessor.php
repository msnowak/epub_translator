<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Preview\SegmentPlaceholderExposer;
use App\Translation\TranslationRejectedException;
use App\Translation\TranslationRejectionReason;
use App\Translation\TranslationValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Segment
    {
        if (!$data instanceof Segment) {
            throw new NotFoundHttpException($this->translator->trans('segment.not_found'));
        }

        $translation = $data->getTranslatedText();

        if (null === $translation) {
            throw new UnprocessableEntityHttpException($this->translator->trans('segment.rejected.empty'));
        }

        try {
            $this->validator->validate($data->getSourceText(), $translation);
        } catch (TranslationRejectedException $exception) {
            throw new UnprocessableEntityHttpException($this->detailFor($exception));
        }

        // Reczna poprawka jest ostateczna: automatyczne ponawianie nigdy jej
        // nie nadpisze, zrobi to wylacznie jawny retranslate.
        $data->setStatus(SegmentStatus::Edited);
        $data->setErrorMessage(null);
        $this->entityManager->flush();

        $this->exposer->expose($data);

        return $data;
    }

    /**
     * Komunikat ma odpowiadac faktycznej przyczynie: "brakuje zetonow" przy
     * pustym tlumaczeniu kieruje poszukiwania w zla strone.
     */
    private function detailFor(TranslationRejectedException $exception): string
    {
        $key = match ($exception->reason) {
            TranslationRejectionReason::Empty => 'segment.rejected.empty',
            TranslationRejectionReason::TokenIntegrity => 'segment.rejected.token_integrity',
            // validate() nigdy nie zglasza echa - sprawdza je wylacznie
            // assertNotEchoed(), ktorej ten procesor celowo nie wola. Gdyby
            // kiedys ta galaz stala sie osiagalna, cichy komunikat o zetonach
            // bylby klamstwem identycznym z tym, co naprawia to zadanie -
            // lepiej glosno wybuchnac i zdradzic zlamany niezmiennik, niz
            // cicho klamac uzytkownikowi.
            TranslationRejectionReason::Echo => throw new \LogicException(
                'TranslationRejectionReason::Echo must never reach UpdateSegmentProcessor: the echo rule is an engine-path check (see TranslationValidator::assertNotEchoed()), not a data-integrity failure a human edit can trigger.',
            ),
        };

        return $this->translator->trans($key);
    }
}
